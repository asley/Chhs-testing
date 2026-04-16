<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Services;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;

/**
 * GoogleDriveService
 *
 * Mirrors report PDFs to Google Drive using a Google Workspace service account.
 * Files are uploaded to a configured folder and shared with the configured
 * Google Workspace domain (staff links are still controlled by Gibbon roles).
 *
 * @version v1
 * @since   v30.0.02-chhs
 */
class GoogleDriveService
{
    private const APP_PROPERTY_REPORT_ARCHIVE_ENTRY = 'gibbonReportArchiveEntryID';

    /** @var array|null */
    private $settings = null;

    /** @var Connection */
    private $db;

    /** @var GoogleDrive|null */
    private $driveService = null;

    /** @var bool|null */
    private $hasGoogleDriveFileIDColumn = null;

    /** @var string|null */
    private $lastError = null;

    /** @var array In-memory folder ID cache keyed by "{parentId}/{name}" */
    private $folderCache = [];

    public function __construct(SettingGateway $settingGateway, Connection $db)
    {
        $this->db = $db;

        $raw = $settingGateway->getSettingByScope('Reports', 'googleDrive');
        if (!empty($raw)) {
            $this->settings = json_decode($raw, true);
        }
    }

    /**
     * Returns true if Drive sync is configured and enabled.
     */
    public function isEnabled(): bool
    {
        return !empty($this->settings['enabled'])
            && $this->settings['enabled'] === 'Y'
            && !empty($this->settings['serviceAccountJSON'])
            && !empty($this->settings['folderId'])
            && $this->hasGoogleDriveFileIDColumn();
    }

    /**
     * Upload a local file to Google Drive.
     *
     * @param string      $localPath      Absolute path to the file on disk.
     * @param string      $filename       Filename to use in Drive.
     * @param string      $mimeType       MIME type (default: application/pdf).
     * @param string|null $parentFolderId Override the root folder ID (e.g. a year/form subfolder).
     * @param array       $options        Optional upload behavior: existingFileId, externalKey.
     * @return string|null                Drive file ID on success, null on failure.
     */
    public function uploadFile(string $localPath, string $filename, string $mimeType = 'application/pdf', ?string $parentFolderId = null, array $options = []): ?string
    {
        $this->lastError = null;

        if (!$this->isEnabled()) {
            $this->lastError = 'Google Drive sync is disabled or not fully configured.';
            return null;
        }

        if (!is_file($localPath)) {
            $this->lastError = 'Local file not found: ' . $localPath;
            return null;
        }

        $content = file_get_contents($localPath);
        if ($content === false) {
            $this->lastError = 'Unable to read local file before upload: ' . $localPath;
            return null;
        }

        try {
            $service = $this->getDriveService();
            $targetFolderId = $parentFolderId ?? $this->settings['folderId'];
            $existingFileId = trim((string)($options['existingFileId'] ?? ''));
            $externalKey = trim((string)($options['externalKey'] ?? ''));

            if (!empty($existingFileId)) {
                $updatedFileId = $this->updateExistingFile($service, $existingFileId, $targetFolderId, $filename, $content, $mimeType, $externalKey);
                if (!empty($updatedFileId)) {
                    return $updatedFileId;
                }
            }

            if (!empty($externalKey)) {
                $matchedByKey = $this->findFileByExternalKey($service, $externalKey);
                if (!empty($matchedByKey)) {
                    $updatedFileId = $this->updateExistingFile($service, $matchedByKey, $targetFolderId, $filename, $content, $mimeType, $externalKey);
                    if (!empty($updatedFileId)) {
                        return $updatedFileId;
                    }
                }
            }

            $matchedByName = $this->findFileByName($service, $filename, $targetFolderId);
            if (!empty($matchedByName)) {
                $updatedFileId = $this->updateExistingFile($service, $matchedByName, $targetFolderId, $filename, $content, $mimeType, $externalKey);
                if (!empty($updatedFileId)) {
                    return $updatedFileId;
                }
            }

            $fileMetadataData = [
                'name'    => $filename,
                'parents' => [$targetFolderId],
            ];
            if (!empty($externalKey)) {
                $fileMetadataData['appProperties'] = [
                    self::APP_PROPERTY_REPORT_ARCHIVE_ENTRY => $externalKey,
                ];
            }
            $fileMetadata = new DriveFile($fileMetadataData);

            $file = $service->files->create($fileMetadata, [
                'data'             => $content,
                'mimeType'         => $mimeType,
                'uploadType'       => 'multipart',
                'fields'           => 'id',
                'supportsAllDrives' => true,
            ]);

            $fileId = $file->getId();

            if (!empty($fileId)) {
                $this->setDomainReadPermission($service, $fileId);
            }

            return $fileId ?: null;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('GoogleDriveService::uploadFile error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a Drive file by ID. Returns true when deleted or already missing.
     */
    public function deleteFile(string $fileId): bool
    {
        $this->lastError = null;

        if (!$this->isEnabled()) {
            $this->lastError = 'Google Drive sync is disabled or not fully configured.';
            return false;
        }

        if (empty($fileId)) {
            return true;
        }

        try {
            $service = $this->getDriveService();
            $service->files->delete($fileId, [
                'supportsAllDrives' => true,
            ]);
            return true;
        } catch (\Exception $e) {
            if ($this->isNotFoundException($e)) {
                return true;
            }

            $this->lastError = $e->getMessage();
            error_log('GoogleDriveService::deleteFile error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check whether a Drive file exists and is not trashed.
     */
    public function fileExists(string $fileId): bool
    {
        $this->lastError = null;

        if (!$this->isEnabled()) {
            $this->lastError = 'Google Drive sync is disabled or not fully configured.';
            return false;
        }

        if (empty($fileId)) {
            return false;
        }

        try {
            $service = $this->getDriveService();
            $file = $service->files->get($fileId, [
                'fields'            => 'id,trashed',
                'supportsAllDrives' => true,
            ]);

            return !empty($file->getId()) && !$file->getTrashed();
        } catch (\Exception $e) {
            if ($this->isNotFoundException($e)) {
                return false;
            }

            $this->lastError = $e->getMessage();
            error_log('GoogleDriveService::fileExists error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return the most recent upload error captured by this service.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Returns a web view URL for the given Drive file ID.
     */
    public function getWebViewLink(string $fileId): string
    {
        return 'https://drive.google.com/file/d/' . urlencode($fileId) . '/view';
    }

    /**
     * Find an existing Drive folder by name inside a parent, or create it.
     * Results are cached in-memory so repeated calls within one request are free.
     *
     * @param string $name      Folder display name.
     * @param string $parentId  Parent folder ID.
     * @return string|null      Folder ID on success, null on failure.
     */
    public function findOrCreateFolder(string $name, string $parentId): ?string
    {
        $cacheKey = $parentId . '/' . $name;
        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        try {
            $service = $this->getDriveService();

            // Escape single quotes in folder name for the Drive query
            $safeName = $this->escapeDriveQueryLiteral($name);
            $results = $service->files->listFiles([
                'q'                         => "name = '{$safeName}' and '{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'fields'                    => 'files(id)',
                'supportsAllDrives'         => true,
                'includeItemsFromAllDrives' => true,
            ]);

            $files = $results->getFiles();
            if (!empty($files)) {
                $folderId = $files[0]->getId();
                $this->folderCache[$cacheKey] = $folderId;
                return $folderId;
            }

            // Folder not found — create it
            $folderMetadata = new DriveFile([
                'name'     => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$parentId],
            ]);
            $folder = $service->files->create($folderMetadata, [
                'fields'            => 'id',
                'supportsAllDrives' => true,
            ]);

            $folderId = $folder->getId();
            $this->folderCache[$cacheKey] = $folderId;
            return $folderId;

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('GoogleDriveService::findOrCreateFolder error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the three-level folder hierarchy (school year → year group → form group)
     * under the configured root folder, creating subfolders as needed.
     *
     * Returns the leaf (form group) folder ID, or the root folder ID if any level fails.
     *
     * @param string $schoolYear  e.g. "2025-2026"
     * @param string $yearGroup   e.g. "Year 7"
     * @param string $formGroup   e.g. "7A"
     * @return string             Leaf folder ID to upload into.
     */
    public function resolveFolderPath(string $schoolYear, string $yearGroup, string $formGroup): string
    {
        $rootId = $this->settings['folderId'];

        $yearId = $this->findOrCreateFolder($schoolYear, $rootId);
        if (empty($yearId)) return $rootId;

        $yearGroupId = $this->findOrCreateFolder($yearGroup, $yearId);
        if (empty($yearGroupId)) return $yearId;

        $formGroupId = $this->findOrCreateFolder($formGroup, $yearGroupId);
        return $formGroupId ?: $yearGroupId;
    }

    /**
     * Build a human-readable Drive filename from student and report context.
     * Format: {Surname}_{PreferredName}_{ReportIdentifier}.pdf
     *
     * @param string $surname
     * @param string $preferredName
     * @param string $reportIdentifier
     * @return string
     */
    public static function buildFilename(string $surname, string $preferredName, string $reportIdentifier): string
    {
        $clean = function (string $s): string {
            $s = preg_replace('/[\/\\\\:*?"<>|]/', '', $s); // strip Drive-unsafe chars
            $s = preg_replace('/\s+/', '_', $s);             // spaces → underscores
            $s = preg_replace('/_+/', '_', $s);              // collapse runs
            return trim($s, '_');
        };

        return $clean($surname) . '_' . $clean($preferredName) . '_' . $clean($reportIdentifier) . '.pdf';
    }

    /**
     * Build and return an authenticated Google Drive service instance.
     */
    private function getDriveService(): GoogleDrive
    {
        if ($this->driveService !== null) {
            return $this->driveService;
        }

        $credentialsArray = json_decode($this->settings['serviceAccountJSON'], true);
        if (empty($credentialsArray)) {
            throw new \RuntimeException('Google Drive: invalid service account JSON in settings.');
        }

        $client = new GoogleClient();
        $client->setApplicationName('Gibbon Reports');
        $client->setAuthConfig($credentialsArray);
        $client->setScopes([GoogleDrive::DRIVE]);

        // Impersonate a real Google account for personal Drive uploads
        if (!empty($this->settings['impersonateEmail'])) {
            $client->setSubject($this->settings['impersonateEmail']);
        }

        $this->driveService = new GoogleDrive($client);
        return $this->driveService;
    }

    /**
     * Grant read permission only to users in the configured Workspace domain.
     */
    private function setDomainReadPermission(GoogleDrive $service, string $fileId): void
    {
        $domain = $this->getWorkspaceDomain();
        if (empty($domain)) {
            error_log('GoogleDriveService::setDomainReadPermission skipped: no workspace domain configured.');
            return;
        }

        try {
            $permission = new \Google\Service\Drive\Permission([
                'type' => 'domain',
                'role' => 'reader',
                'domain' => $domain,
                'allowFileDiscovery' => false,
            ]);
            $service->permissions->create($fileId, $permission, [
                'fields'            => 'id',
                'supportsAllDrives' => true,
            ]);
        } catch (\Exception $e) {
            error_log('GoogleDriveService::setDomainReadPermission error: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the Workspace domain from explicit settings or impersonation email.
     */
    private function getWorkspaceDomain(): ?string
    {
        $workspaceDomain = strtolower(trim((string)($this->settings['workspaceDomain'] ?? '')));
        if (!empty($workspaceDomain) && preg_match('/^[a-z0-9.-]+$/', $workspaceDomain)) {
            return $workspaceDomain;
        }

        $impersonateEmail = trim((string)($this->settings['impersonateEmail'] ?? ''));
        if (!empty($impersonateEmail) && strpos($impersonateEmail, '@') !== false) {
            [, $domain] = explode('@', $impersonateEmail, 2);
            $domain = strtolower(trim($domain));
            if (!empty($domain) && preg_match('/^[a-z0-9.-]+$/', $domain)) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Attempt to update an existing file in-place, including optional parent move.
     *
     * Returns null only when the file is no longer found and caller should create a new file.
     */
    private function updateExistingFile(
        GoogleDrive $service,
        string $fileId,
        string $parentFolderId,
        string $filename,
        string $content,
        string $mimeType,
        string $externalKey = ''
    ): ?string {
        $metadataData = ['name' => $filename];
        if (!empty($externalKey)) {
            $metadataData['appProperties'] = [
                self::APP_PROPERTY_REPORT_ARCHIVE_ENTRY => $externalKey,
            ];
        }

        try {
            $removeParents = '';
            $addParents = '';

            $current = $service->files->get($fileId, [
                'fields'            => 'id,parents',
                'supportsAllDrives' => true,
            ]);

            $currentParents = $current->getParents() ?: [];
            if (!empty($parentFolderId) && !in_array($parentFolderId, $currentParents, true)) {
                $addParents = $parentFolderId;
                if (!empty($currentParents)) {
                    $removeParents = implode(',', $currentParents);
                }
            }

            $params = [
                'data'             => $content,
                'mimeType'         => $mimeType,
                'uploadType'       => 'multipart',
                'fields'           => 'id',
                'supportsAllDrives' => true,
            ];

            if (!empty($addParents)) {
                $params['addParents'] = $addParents;
            }
            if (!empty($removeParents)) {
                $params['removeParents'] = $removeParents;
            }

            $updated = $service->files->update($fileId, new DriveFile($metadataData), $params);
            $updatedId = $updated->getId();

            return !empty($updatedId) ? $updatedId : $fileId;
        } catch (\Exception $e) {
            if ($this->isNotFoundException($e)) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Find a Drive file by report archive entry key in appProperties.
     */
    private function findFileByExternalKey(GoogleDrive $service, string $externalKey): ?string
    {
        $safeValue = $this->escapeDriveQueryLiteral($externalKey);

        $results = $service->files->listFiles([
            'q'                         => "appProperties has { key='" . self::APP_PROPERTY_REPORT_ARCHIVE_ENTRY . "' and value='{$safeValue}' } and trashed = false",
            'fields'                    => 'files(id)',
            'pageSize'                  => 1,
            'supportsAllDrives'         => true,
            'includeItemsFromAllDrives' => true,
        ]);

        $files = $results->getFiles();
        if (!empty($files)) {
            return $files[0]->getId();
        }

        return null;
    }

    /**
     * Find a Drive file by name within a single parent folder.
     */
    private function findFileByName(GoogleDrive $service, string $filename, string $parentFolderId): ?string
    {
        $safeName = $this->escapeDriveQueryLiteral($filename);
        $results = $service->files->listFiles([
            'q'                         => "name = '{$safeName}' and '{$parentFolderId}' in parents and trashed = false",
            'fields'                    => 'files(id)',
            'pageSize'                  => 1,
            'supportsAllDrives'         => true,
            'includeItemsFromAllDrives' => true,
        ]);

        $files = $results->getFiles();
        if (!empty($files)) {
            return $files[0]->getId();
        }

        return null;
    }

    /**
     * Escape values interpolated into Drive query strings.
     */
    private function escapeDriveQueryLiteral(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /**
     * Identify common "not found" responses from Google Drive client exceptions.
     */
    private function isNotFoundException(\Exception $e): bool
    {
        if ($e instanceof GoogleServiceException && (int)$e->getCode() === 404) {
            return true;
        }

        $message = strtolower($e->getMessage());
        return strpos($message, 'not found') !== false || strpos($message, 'notfound') !== false;
    }

    /**
     * Verify the report archive table has the Drive file ID column.
     */
    private function hasGoogleDriveFileIDColumn(): bool
    {
        if ($this->hasGoogleDriveFileIDColumn !== null) {
            return $this->hasGoogleDriveFileIDColumn;
        }

        try {
            $column = $this->db->selectOne("SHOW COLUMNS FROM gibbonReportArchiveEntry LIKE 'googleDriveFileID'");
            $this->hasGoogleDriveFileIDColumn = !empty($column);
        } catch (\Exception $e) {
            $this->hasGoogleDriveFileIDColumn = false;
            error_log('GoogleDriveService::hasGoogleDriveFileIDColumn error: ' . $e->getMessage());
        }

        return $this->hasGoogleDriveFileIDColumn;
    }
}

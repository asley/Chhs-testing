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
     * @param string $localPath  Absolute path to the file on disk.
     * @param string $filename   Filename to use in Drive.
     * @param string $mimeType   MIME type (default: application/pdf).
     * @return string|null       Drive file ID on success, null on failure.
     */
    public function uploadFile(string $localPath, string $filename, string $mimeType = 'application/pdf'): ?string
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

        try {
            $service = $this->getDriveService();

            $fileMetadata = new DriveFile([
                'name'    => $filename,
                'parents' => [$this->settings['folderId']],
            ]);

            $content = file_get_contents($localPath);
            if ($content === false) {
                $this->lastError = 'Unable to read local file before upload: ' . $localPath;
                return null;
            }

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

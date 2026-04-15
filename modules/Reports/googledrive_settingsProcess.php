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

use Gibbon\Data\Validator;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['serviceAccountJSON' => 'RAW']);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Reports/googledrive_settings.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

if (isActionAccessible($guid, $connection2, '/modules/Reports/archive_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$enabled            = $_POST['enabled'] ?? 'N';
$folderId           = trim($_POST['folderId'] ?? '');
$impersonateEmail   = trim($_POST['impersonateEmail'] ?? '');
$workspaceDomain    = strtolower(trim($_POST['workspaceDomain'] ?? ''));
$serviceAccountJSON = trim($_POST['serviceAccountJSON'] ?? '');

$settingGateway = $container->get(SettingGateway::class);

// Load existing settings so we can preserve the saved JSON key if the placeholder was submitted
$existing = [];
$raw = $settingGateway->getSettingByScope('Reports', 'googleDrive');
if (!empty($raw)) {
    $existing = json_decode($raw, true) ?? [];
}

// If the user submitted the masked placeholder text (didn't replace), keep the stored key
$submittedMaskedPlaceholder = strpos($serviceAccountJSON, '*** saved') === 0
    && strpos($serviceAccountJSON, 'paste new key to replace') !== false;
if ($submittedMaskedPlaceholder) {
    $serviceAccountJSON = $existing['serviceAccountJSON'] ?? '';
}

// Validate JSON key if provided
if (!empty($serviceAccountJSON)) {
    $decoded = json_decode($serviceAccountJSON, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($decoded['type']) || $decoded['type'] !== 'service_account') {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
}

if (!empty($workspaceDomain) && !preg_match('/^[a-z0-9.-]+$/', $workspaceDomain)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

if (!empty($impersonateEmail) && preg_match('/@.*\\.iam\\.gserviceaccount\\.com$/i', $impersonateEmail)) {
    $URL .= '&return=error4';
    header("Location: {$URL}");
    exit;
}

$newSettings = [
    'enabled'            => $enabled,
    'folderId'           => $folderId,
    'impersonateEmail'   => $impersonateEmail,
    'workspaceDomain'    => $workspaceDomain,
    'serviceAccountJSON' => $serviceAccountJSON,
];

// updateSettingByScope only does an UPDATE — if the row doesn't exist yet (first save
// on a fresh install) it returns false. Fall back to an INSERT in that case.
$updated = $settingGateway->updateSettingByScope('Reports', 'googleDrive', json_encode($newSettings));

if (!$updated) {
    // Row may not exist yet — insert it using affectingStatement (same pattern as core Gibbon code)
    try {
        $count = $pdo->affectingStatement(
            "INSERT INTO gibbonSetting (scope, name, value) VALUES (:scope, :name, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            ['scope' => 'Reports', 'name' => 'googleDrive', 'value' => json_encode($newSettings)]
        );
        $updated = ($count !== false);
    } catch (\Exception $e) {
        error_log('googledrive_settingsProcess: insert failed — ' . $e->getMessage());
        $updated = false;
    }
}

$URL .= $updated ? '&return=success0' : '&return=error2';
header("Location: {$URL}");

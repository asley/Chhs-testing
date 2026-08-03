<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Data\Validator;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . '/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/settings.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$partialFail = false;

$settingsToUpdate = [
    'tcexamBaseUrl' => '',
    'bridgeKeyId' => '',
    'bridgeSharedSecret' => '',
    'bridgeServicePersonID' => '',
    'signatureMaxSkewSeconds' => 'required',
    'enrollmentSyncEnabled' => 'required',
    'gradeSyncEnabled' => 'required',
    'dryRunEnabled' => 'required',
];

$settingDefinitions = [
    'tcexamBaseUrl' => [
        'nameDisplay' => 'TCExam Base URL',
        'description' => 'Base URL for the TCExam API endpoint, eg https://tcexam.example.com',
        'default' => '',
    ],
    'bridgeKeyId' => [
        'nameDisplay' => 'Bridge Key ID',
        'description' => 'Public key identifier for signed integration requests.',
        'default' => '',
    ],
    'bridgeSharedSecret' => [
        'nameDisplay' => 'Bridge Shared Secret',
        'description' => 'Shared secret used for HMAC request verification.',
        'default' => '',
    ],
    'bridgeServicePersonID' => [
        'nameDisplay' => 'Bridge Service Person ID',
        'description' => 'Person ID used as last editor for automated grade sync writes. Leave blank to fall back to System Administrator setting.',
        'default' => '',
    ],
    'signatureMaxSkewSeconds' => [
        'nameDisplay' => 'Max Signature Skew (Seconds)',
        'description' => 'Maximum allowed difference between request timestamp and server time.',
        'default' => '300',
    ],
    'enrollmentSyncEnabled' => [
        'nameDisplay' => 'Enrollment Sync Enabled',
        'description' => 'Enable class and enrollment sync from Gibbon to TCExam.',
        'default' => 'N',
    ],
    'gradeSyncEnabled' => [
        'nameDisplay' => 'Grade Sync Enabled',
        'description' => 'Enable grade push and write-back integration.',
        'default' => 'N',
    ],
    'dryRunEnabled' => [
        'nameDisplay' => 'Dry Run Mode',
        'description' => 'Validate sync operations without writing final assessment data.',
        'default' => 'Y',
    ],
];

foreach ($settingsToUpdate as $name => $property) {
    $value = $_POST[$name] ?? '';

    if ($property === 'required' && empty($value)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    if ($name === 'signatureMaxSkewSeconds') {
        $value = max(30, min(3600, (int) $value));
    }

    if ($name === 'bridgeServicePersonID') {
        $value = trim((string) $value);
        if ($value !== '' && !ctype_digit($value)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }
    }

    if ($name === 'bridgeSharedSecret') {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
    }

    if ($settingGateway->getSettingByScope('juss-examBridge', $name, true) === false) {
        $definition = $settingDefinitions[$name] ?? ['nameDisplay' => $name, 'description' => '', 'default' => ''];

        try {
            $insert = $connection2->prepare("
                INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
                VALUES (:scope, :name, :nameDisplay, :description, :value)
            ");
            $insert->execute([
                'scope' => 'juss-examBridge',
                'name' => $name,
                'nameDisplay' => $definition['nameDisplay'],
                'description' => $definition['description'],
                'value' => $definition['default'],
            ]);
        } catch (PDOException $e) {
            $partialFail = true;
        }
    }

    $updated = $settingGateway->updateSettingByScope('juss-examBridge', $name, $value);
    $partialFail = $partialFail || !$updated;
}

$URL .= $partialFail
    ? '&return=warning1'
    : '&return=success0';

header("Location: {$URL}");

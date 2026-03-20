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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Data\ImportType;
use Gibbon\Module\DataAdmin\DatabaseTools;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Data\PasswordPolicy;

// Gibbon Bootstrap
include __DIR__ . '/../../gibbon.php';

// Module Bootstrap
require __DIR__ . '/module.php';

$type = isset($_POST['type']) ? $_POST['type'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . "/records_orphaned.php&type=$type";

if (isActionAccessible($guid, $connection2, '/modules/Data Admin/records_orphaned.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$checkUserPermissions = $settingGateway->getSettingByScope('Data Admin', 'enableUserLevelPermissions');
$passwordPolicy = $container->get(PasswordPolicy::class);
$importType = ImportType::loadImportType($type, $settingGateway, $passwordPolicy, $pdo);

if (!$importType || !$importType->isValid()) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$isImportAccessible = ($checkUserPermissions == 'Y' && $importType->isImportAccessible($guid, $connection2) != false);
if (!$isImportAccessible) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$tableName = $importType->getPrimaryTable();
$primaryKey = $importType->getPrimaryKey();

if ($action === 'delete') {
    // Delete a single orphaned record
    $recordID = isset($_POST['recordID']) ? $_POST['recordID'] : '';

    if (empty($recordID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        $sql = "DELETE FROM `" . str_replace('`', '``', $tableName) . "` WHERE `" . str_replace('`', '``', $primaryKey) . "` = :recordID";
        $pdo->executeQuery(['recordID' => $recordID], $sql);

        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    } catch (\PDOException $e) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

} elseif ($action === 'deleteAll') {
    // Delete all orphaned records for this type
    $databaseTools = new DatabaseTools($session, $pdo);
    $orphanedRecords = $databaseTools->getOrphanedRecords($importType);

    if (empty($orphanedRecords)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $ids = array_column($orphanedRecords, $primaryKey);

    if (empty($ids)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        $placeholders = array_map(function ($i) { return ":id$i"; }, array_keys($ids));
        $sql = "DELETE FROM `" . str_replace('`', '``', $tableName) . "` WHERE `" . str_replace('`', '``', $primaryKey) . "` IN (" . implode(',', $placeholders) . ")";

        $data = [];
        foreach ($ids as $i => $id) {
            $data[":id$i"] = $id;
        }

        $pdo->executeQuery($data, $sql);

        $URLReturn = $session->get('absoluteURL') . '/index.php?q=/modules/' . getModuleName($_POST['address']) . '/records_manage.php&return=success0';
        header("Location: {$URLReturn}");
        exit;
    } catch (\PDOException $e) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

} else {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

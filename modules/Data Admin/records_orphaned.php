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
use Gibbon\Session\TokenHandler;

// Module Bootstrap
require __DIR__ . '/module.php';

if (isActionAccessible($guid, $connection2, "/modules/Data Admin/records_orphaned.php") == false) {
    //Acess denied
    echo "<div class='error'>" ;
    echo __("You do not have access to this action.") ;
    echo "</div>" ;
} else {
    $page->breadcrumbs
        ->add(__('Manage Records', 'Data Admin'), 'records_manage.php')
        ->add(__('Orphaned Records', 'Data Admin'));
    
    // Info
    echo "<div class='warning'>" ;
    echo __('Orphaned records are those where the link between this record and any related records on other tables has been broken. This can happen if other records are deleted or replaced without removing the linked records.', 'Data Admin');
    echo "</div>" ;

    if (isset($_GET['return'])) {
        $returns = ['success0' => __('Orphaned record(s) deleted successfully.')];
        if (isset($returns[$_GET['return']])) {
            echo "<div class='success'>{$returns[$_GET['return']]}</div>";
        }
    }

    $databaseTools = new DatabaseTools($session, $pdo);

    // Get the importType information
    $type = (isset($_GET['type']))? $_GET['type'] : '';

    $settingGateway = $container->get(SettingGateway::class);
    $passwordPolicy = $container->get(PasswordPolicy::class);
    $importType = ImportType::loadImportType($type, $settingGateway, $passwordPolicy, $pdo);

    $orphanedRecords = $databaseTools->getOrphanedRecords($importType);

    $primaryKey = $importType->getPrimaryKey();
    $relationships = array();

    // Get the relational fields
    foreach ($importType->getTableFields() as $fieldName) {
        if ($importType->isFieldRequired($fieldName) == false) {
            continue;
        } // Skip non-required fields for orphan checks

        if ($importType->isFieldRelational($fieldName) && !$importType->isFieldReadOnly($fieldName)) {
            $relationships[$fieldName] = $importType->getField($fieldName, 'relationship');
        }
    }

    $checkUserPermissions = $settingGateway->getSettingByScope('Data Admin', 'enableUserLevelPermissions');
    $isImportAccessible = ($checkUserPermissions == 'Y' && $importType->isImportAccessible($guid, $connection2) != false);

    if (count($orphanedRecords)<1) {
        echo "<div class='success'>" ;
        echo __("There are no orphaned records to display.") ;
        echo "</div>" ;
    } else {

        // Bulk delete all button
        $tokenHandler = $container->get(TokenHandler::class);
        if ($isImportAccessible) {
            $actionURL = $session->get('absoluteURL') . '/modules/Data Admin/records_orphanedProcess.php';
            echo "<div style='margin-bottom: 10px; text-align: right;'>";
            echo "<form method='post' action='{$actionURL}' onsubmit=\"return confirm('" . __('Are you sure you want to delete ALL orphaned records for this type? This action cannot be undone.') . "');\">";
            echo "<input type='hidden' name='type' value='{$type}'>";
            echo "<input type='hidden' name='action' value='deleteAll'>";
            echo "<input type='hidden' name='address' value='" . $session->get('address') . "'>";
            echo "<input type='hidden' name='csrftoken' value='" . $tokenHandler->getCSRF() . "'>";
            echo "<input type='hidden' name='nonce' value='" . $tokenHandler->getNonce() . "'>";
            echo "<input type='submit' class='buttonLink' style='background-color: #cc0000; color: #fff; padding: 5px 15px; border: none; border-radius: 3px; cursor: pointer;' value='" . __('Delete All Orphaned Records') . " (" . count($orphanedRecords) . ")'>";
            echo "</form>";
            echo "</div>";
        }

        echo "<table class='fullWidth colorOddEven' cellspacing='0'>" ;

        echo "<tr class='head'>" ;
        echo "<th style='width: 15%;padding: 5px 5px 5px 20px !important;'>" ;
        echo $primaryKey;
        echo "</th>" ;

        foreach ($relationships as $relationship) {
            echo "<th style='width: 10%;padding: 5px !important;'>" ;
            echo $relationship['key'];
            echo "</th>" ;
        }

        echo "<th style='width: 12%;padding: 5px !important;'>" ;
        echo __("Actions") ;
        echo "</th>" ;
        echo "</tr>" ;

        foreach ($orphanedRecords as $row) {

            $importTypeName = $importType->getDetail('type');

            echo "<tr>" ;
            echo "<td>".$row[$primaryKey]. "</td>" ;

            foreach ($relationships as $relationship) {
                if (!empty($row[ $relationship['key'] ])) {
                    echo "<td>" .$row[ $relationship['key'] ]."</td>";
                } else {
                    echo "<td class='error'>" .__('Missing', 'Data Admin').': ';
                    echo $row[ $relationship['key'].'_Rel' ];
                    echo "</td>";
                }
            }

            echo "<td>";
            if ($isImportAccessible) {
                $actionURL = $session->get('absoluteURL') . '/modules/Data Admin/records_orphanedProcess.php';
                echo "<form method='post' action='{$actionURL}' style='display:inline;' onsubmit=\"return confirm('" . __('Are you sure you want to delete this orphaned record?') . "');\">";
                echo "<input type='hidden' name='type' value='{$type}'>";
                echo "<input type='hidden' name='action' value='delete'>";
                echo "<input type='hidden' name='recordID' value='" . $row[$primaryKey] . "'>";
                echo "<input type='hidden' name='address' value='" . $session->get('address') . "'>";
                echo "<input type='hidden' name='csrftoken' value='" . $tokenHandler->getCSRF() . "'>";
                echo "<input type='hidden' name='nonce' value='" . $tokenHandler->getNonce() . "'>";
                echo "<input type='submit' class='buttonLink' style='color: #cc0000; background: none; border: none; cursor: pointer; text-decoration: underline; padding: 2px;' value='" . __('Delete') . "'>";
                echo "</form>";
            } else {
                echo "<img style='margin-left: 5px' title='" . __('You do not have access to this action.'). "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/key.png'/>" ;
            }
            echo "</td>";

            echo "</tr>" ;
        }

        echo "</table><br/>" ;
    }
}

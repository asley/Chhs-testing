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

$name = 'juss-examBridge';
$description = 'Integration bridge module for Gibbon and TCExam.';
$entryURL = 'index.php';
$type = 'Additional';
$category = 'Assessment';
$version = '0.1.0';
$author = 'CHHS';
$url = 'https://gibbonedu.org';

$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='tcexamBaseUrl', nameDisplay='TCExam Base URL', description='Base URL for the TCExam API endpoint, eg https://tcexam.example.com', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='bridgeKeyId', nameDisplay='Bridge Key ID', description='Public key identifier for signed integration requests.', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='bridgeSharedSecret', nameDisplay='Bridge Shared Secret', description='Shared secret used for HMAC request verification.', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='enrollmentSyncEnabled', nameDisplay='Enrollment Sync Enabled', description='Enable class and enrollment sync from Gibbon to TCExam.', value='N';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='gradeSyncEnabled', nameDisplay='Grade Sync Enabled', description='Enable grade push and write-back integration.', value='N';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='dryRunEnabled', nameDisplay='Dry Run Mode', description='Validate sync operations without writing final assessment data.', value='Y';";

$actionRows[] = [
    'name' => 'juss-examBridge',
    'precedence' => '0',
    'category' => 'General',
    'description' => 'View integration status and setup guidance.',
    'URLList' => 'index.php',
    'entryURL' => 'index.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name' => 'Bridge Settings',
    'precedence' => '1',
    'category' => 'Configuration',
    'description' => 'Configure connection settings for TCExam integration.',
    'URLList' => 'settings.php',
    'entryURL' => 'settings.php',
    'entrySidebar' => 'Y',
    'menuShow' => 'Y',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'N',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

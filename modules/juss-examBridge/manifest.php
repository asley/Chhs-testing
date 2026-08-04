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
$version = '0.5.2';
$author = 'CHHS';
$url = 'https://gibbonedu.org';

$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgePersonMap` (
    `gibbonJussExamBridgePersonMapID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `externalUserId` VARCHAR(100) NOT NULL,
    `externalEmail` VARCHAR(255) NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonJussExamBridgePersonMapID`),
    UNIQUE KEY `uniq_person` (`gibbonPersonID`),
    UNIQUE KEY `uniq_external_user` (`externalUserId`),
    KEY `idx_external_email` (`externalEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeClassMap` (
    `gibbonJussExamBridgeClassMapID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gibbonCourseClassID` INT UNSIGNED NOT NULL,
    `externalCohortId` VARCHAR(100) NOT NULL,
    `externalClassCode` VARCHAR(100) NULL,
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonJussExamBridgeClassMapID`),
    UNIQUE KEY `uniq_class` (`gibbonCourseClassID`),
    UNIQUE KEY `uniq_external_cohort` (`externalCohortId`),
    KEY `idx_external_class_code` (`externalClassCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeAssessmentMap` (
    `gibbonJussExamBridgeAssessmentMapID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `externalExamId` VARCHAR(100) NOT NULL,
    `gibbonInternalAssessmentColumnID` INT UNSIGNED NULL,
    `gibbonMarkbookColumnID` INT UNSIGNED NULL,
    `syncMode` ENUM('internal_assessment','markbook','both') NOT NULL DEFAULT 'internal_assessment',
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonJussExamBridgeAssessmentMapID`),
    UNIQUE KEY `uniq_external_exam` (`externalExamId`),
    KEY `idx_internal_assessment_column` (`gibbonInternalAssessmentColumnID`),
    KEY `idx_markbook_column` (`gibbonMarkbookColumnID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeSyncLog` (
    `gibbonJussExamBridgeSyncLogID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `direction` ENUM('inbound','outbound') NOT NULL,
    `operationType` VARCHAR(50) NOT NULL,
    `idempotencyKey` VARCHAR(128) NOT NULL,
    `payloadHash` VARCHAR(128) NOT NULL,
    `status` ENUM('accepted','rejected','error') NOT NULL,
    `errorCode` VARCHAR(50) NULL,
    `errorDetail` TEXT NULL,
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonJussExamBridgeSyncLogID`),
    UNIQUE KEY `uniq_idempotency_key` (`idempotencyKey`),
    KEY `idx_direction_operation` (`direction`, `operationType`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeNonce` (
    `gibbonJussExamBridgeNonceID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nonce` VARCHAR(128) NOT NULL,
    `keyId` VARCHAR(100) NOT NULL,
    `requestTimestamp` DATETIME NOT NULL,
    `expiresAt` DATETIME NOT NULL,
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonJussExamBridgeNonceID`),
    UNIQUE KEY `uniq_nonce` (`nonce`),
    KEY `idx_expires_at` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='tcexamBaseUrl', nameDisplay='TCExam Base URL', description='Base URL for the TCExam API endpoint, eg https://tcexam.example.com', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='bridgeKeyId', nameDisplay='Bridge Key ID', description='Public key identifier for signed integration requests.', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='bridgeSharedSecret', nameDisplay='Bridge Shared Secret', description='Shared secret used for HMAC request verification.', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='bridgeServicePersonID', nameDisplay='Bridge Service Person ID', description='Person ID used as last editor for automated grade sync writes. Leave blank to fall back to System Administrator setting.', value='';";
$gibbonSetting[] = "INSERT INTO gibbonSetting SET scope='juss-examBridge', name='signatureMaxSkewSeconds', nameDisplay='Max Signature Skew (Seconds)', description='Maximum allowed difference between request timestamp and server time.', value='300';";
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
    'name' => 'Bridge Mappings',
    'precedence' => '2',
    'category' => 'Configuration',
    'description' => 'Manage TCExam-to-Gibbon identity and assessment mappings.',
    'URLList' => 'mappings.php,mapping_person.php,mapping_personProcess.php,mapping_class.php,mapping_classProcess.php,mapping_assessment.php,mapping_assessmentProcess.php',
    'entryURL' => 'mappings.php',
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

$actionRows[] = [
    'name' => 'Pull Grades from TCExam',
    'precedence' => '3',
    'category' => 'Assessment',
    'description' => 'Manually pull TCExam results into mapped Internal Assessment columns.',
    'URLList' => 'internalAssessment_write_pullProcess.php',
    'entryURL' => 'internalAssessment_write_pullProcess.php',
    'entrySidebar' => 'N',
    'menuShow' => 'N',
    'defaultPermissionAdmin' => 'Y',
    'defaultPermissionTeacher' => 'Y',
    'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N',
    'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N',
    'categoryPermissionOther' => 'N',
];

<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

$sql[$count][0] = '0.1.0';
$sql[$count][1] = '-- Initial module scaffold release';

$count++;
$sql[$count][0] = '0.2.0';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgePersonMap` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end

CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeClassMap` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end

CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeAssessmentMap` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end

CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeSyncLog` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end

CREATE TABLE IF NOT EXISTS `gibbonJussExamBridgeNonce` (
    `gibbonJussExamBridgeNonceID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nonce` VARCHAR(128) NOT NULL,
    `keyId` VARCHAR(100) NOT NULL,
    `requestTimestamp` DATETIME NOT NULL,
    `expiresAt` DATETIME NOT NULL,
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonJussExamBridgeNonceID`),
    UNIQUE KEY `uniq_nonce` (`nonce`),
    KEY `idx_expires_at` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end

INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'juss-examBridge', 'signatureMaxSkewSeconds', 'Max Signature Skew (Seconds)', 'Maximum allowed difference between request timestamp and server time.', '300'
WHERE NOT EXISTS (
    SELECT 1
    FROM gibbonSetting
    WHERE scope='juss-examBridge' AND name='signatureMaxSkewSeconds'
);end
";

$count++;
$sql[$count][0] = '0.3.0';
$sql[$count][1] = '-- Week 4 enrollment sync endpoint release (no schema changes).';

$count++;
$sql[$count][0] = '0.4.0';
$sql[$count][1] = '-- Grade upsert API endpoint release with idempotency + sync logging (no schema changes).';

$count++;
$sql[$count][0] = '0.5.0';
$sql[$count][1] = '-- Added mapping admin UI and service person ID setting (no schema changes).';

$count++;
$sql[$count][0] = '0.5.1';
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'juss-examBridge', 'bridgeServicePersonID', 'Bridge Service Person ID', 'Person ID used as last editor for automated grade sync writes. Leave blank to fall back to System Administrator setting.', ''
WHERE NOT EXISTS (
    SELECT 1
    FROM gibbonSetting
    WHERE scope='juss-examBridge' AND name='bridgeServicePersonID'
);end

INSERT INTO gibbonAction
(gibbonModuleID, name, precedence, category, description, helpURL, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther)
SELECT
    m.gibbonModuleID,
    'Bridge Mappings',
    2,
    'Configuration',
    'Manage TCExam-to-Gibbon identity and assessment mappings.',
    NULL,
    'mappings.php,mapping_person.php,mapping_personProcess.php,mapping_class.php,mapping_classProcess.php,mapping_assessment.php,mapping_assessmentProcess.php',
    'mappings.php',
    'Y',
    'Y',
    'Y',
    'N',
    'N',
    'N',
    'N',
    'Y',
    'N',
    'N',
    'N'
FROM gibbonModule m
WHERE m.name='juss-examBridge'
  AND NOT EXISTS (
      SELECT 1
      FROM gibbonAction a
      WHERE a.gibbonModuleID = m.gibbonModuleID
        AND a.name = 'Bridge Mappings'
  );end

INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
SELECT '001', a.gibbonActionID
FROM gibbonAction a
INNER JOIN gibbonModule m ON m.gibbonModuleID = a.gibbonModuleID
WHERE m.name='juss-examBridge'
  AND a.name='Bridge Mappings'
  AND NOT EXISTS (
      SELECT 1
      FROM gibbonPermission p
      WHERE p.gibbonRoleID='001'
        AND p.gibbonActionID=a.gibbonActionID
  );end
";

$count++;
$sql[$count][0] = '0.5.2';
$sql[$count][1] = "
INSERT INTO gibbonAction
(gibbonModuleID, name, precedence, category, description, helpURL, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther)
SELECT
    m.gibbonModuleID,
    'Pull Grades from TCExam',
    3,
    'Assessment',
    'Manually pull TCExam results into mapped Internal Assessment columns.',
    NULL,
    'internalAssessment_write_pullProcess.php',
    'internalAssessment_write_pullProcess.php',
    'N',
    'N',
    'Y',
    'Y',
    'N',
    'N',
    'N',
    'Y',
    'N',
    'N',
    'N'
FROM gibbonModule m
WHERE m.name='juss-examBridge'
  AND NOT EXISTS (
      SELECT 1
      FROM gibbonAction a
      WHERE a.gibbonModuleID = m.gibbonModuleID
        AND a.name = 'Pull Grades from TCExam'
  );end

INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
SELECT '001', a.gibbonActionID
FROM gibbonAction a
INNER JOIN gibbonModule m ON m.gibbonModuleID = a.gibbonModuleID
WHERE m.name='juss-examBridge'
  AND a.name='Pull Grades from TCExam'
  AND NOT EXISTS (
      SELECT 1
      FROM gibbonPermission p
      WHERE p.gibbonRoleID='001'
        AND p.gibbonActionID=a.gibbonActionID
  );end
";

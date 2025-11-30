<?php
$sql=array();
$count=0;
$sql[$count][0]="1.00" ; // version number
$sql[$count][1]="" ; // sql statements
$count++;

$sql[$count][0]="1.01" ; // version number
$sql[$count][1]="" ; // sql statements
$count++;

$sql[$count][0]="1.02" ; // version number
$sql[$count][1]="
ALTER TABLE hpCategory ADD categoryType ENUM('House','Student') NOT NULL DEFAULT 'House' AFTER categoryOrder;end
ALTER TABLE hpCategory ADD categoryPresets TEXT NOT NULL AFTER categoryType;end
" ;
$count++;

$sql[$count][0]="1.03" ; // version number
$sql[$count][1]="
UPDATE gibbonAction SET category='Manage' WHERE (name='Manage points' OR name='Categories') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points');end
UPDATE gibbonAction SET category='Award' WHERE (name='Award student points' OR name='Award house points') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points');end
UPDATE gibbonAction SET category='View' WHERE (name='View points overall' OR name='View points individual' OR name='View points class' OR name='View my points') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points');end
" ;
$count++;

$sql[$count][0]="1.3.01" ; // version number
$sql[$count][1]="
ALTER TABLE `hpPointHouse` CHANGE `points` `points` INT(4) UNSIGNED NOT NULL;end
ALTER TABLE `hpPointStudent` CHANGE `points` `points` INT(4) UNSIGNED NOT NULL;end
UPDATE gibbonAction SET precedence=0 WHERE (name='Award student points' OR name='Award house points') AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points');end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points'), 'Award student points_unlimited', 1, 'Award', 'Award points to students, without a limit.', 'award.php', 'award.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='House Points' AND gibbonAction.name='Award student points_unlimited'));end
INSERT INTO `gibbonAction` (`gibbonModuleID`, `name`, `precedence`, `category`, `description`, `URLList`, `entryURL`, `defaultPermissionAdmin`, `defaultPermissionTeacher`, `defaultPermissionStudent`, `defaultPermissionParent`, `defaultPermissionSupport`, `categoryPermissionStaff`, `categoryPermissionStudent`, `categoryPermissionParent`, `categoryPermissionOther`) VALUES ((SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points'), 'Award house points_unlimited', 1, 'Award', 'Award points to house, without a limit.', 'house.php', 'house.php', 'Y', 'N', 'N', 'N', 'N', 'Y', 'N', 'N', 'N');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='House Points' AND gibbonAction.name='Award house points_unlimited'));end
UPDATE `gibbonModule` SET `entryURL`='overall.php' WHERE name='House Points';end
INSERT INTO `hpCategory` (`categoryID`, `categoryName`, `categoryOrder`, `categoryType`, `categoryPresets`) VALUES ('0', '-- Unlimited House Points --', '0', 'House', '');end
INSERT INTO `hpCategory` (`categoryID`, `categoryName`, `categoryOrder`, `categoryType`, `categoryPresets`) VALUES ('0', '-- Unlimited Student Points --', '0', 'Student', '');end
" ;
$count++;

$sql[$count][0]="1.3.02" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.3.03" ; // version number
$sql[$count][1]="
UPDATE gibbonAction JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID) SET gibbonAction.categoryPermissionStudent='Y' WHERE gibbonModule.name = 'House Points';end" ;
$count++;

$sql[$count][0]="1.4.00" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.4.01" ; // version number
$sql[$count][1]="
INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, `gibbonModuleID`) VALUES (NULL, 'House Points', 'Student Dashboard', 'a:3:{s:16:\"sourceModuleName\";s:12:\"House Points\";s:18:\"sourceModuleAction\";s:19:\"View points overall\";s:19:\"sourceModuleInclude\";s:19:\"hook_housepoint.php\";}', (SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points'));end
INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, `gibbonModuleID`) VALUES (NULL, 'House Points', 'Parental Dashboard', 'a:3:{s:16:\"sourceModuleName\";s:12:\"House Points\";s:18:\"sourceModuleAction\";s:19:\"View points overall\";s:19:\"sourceModuleInclude\";s:19:\"hook_housepoint.php\";}', (SELECT gibbonModuleID FROM gibbonModule WHERE name='House Points'));end
" ;
$count++;

$sql[$count][0]="1.5.00" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.5.01" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.5.02" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.5.03" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.5.04" ; // version number
$sql[$count][1]="" ;
$count++;

$sql[$count][0]="1.5.05" ; // version number
$sql[$count][1]="" ;
$count++;

//v1.6.00
++$count;
$sql[$count][0] = '1.6.00';
$sql[$count][1] = "
UPDATE gibbonModule SET author='Gibbon Foundation', url='https://gibbonedu.org', description='Module to allow allocating and display of house points.' WHERE name='House Points';end
";

//v1.6.01
++$count;
$sql[$count][0] = '1.6.01';
$sql[$count][1] = "";
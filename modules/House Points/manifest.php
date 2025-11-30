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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//Basica variables
$name = "House Points" ;
$description = "Module to allow allocating and display of house points." ;
$entryURL = "overall.php" ;
$type = "Additional" ;
$category = "Learn" ;
$version = "1.6.01" ;
$author = "Gibbon Foundation";
$url = "https://gibbonedu.org";

//Module tables
$moduleTables[0] = "
    CREATE TABLE hpCategory (
    categoryID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoryName VARCHAR(45) NOT NULL,
    categoryOrder TINYINT(4) UNSIGNED NOT NULL,
    categoryType ENUM('House','Student') NOT NULL DEFAULT 'House',
    categoryPresets TEXT NOT NULL,
    PRIMARY KEY (categoryID)
    );";

$moduleTables[1] = "
    CREATE TABLE hpPointStudent (
    hpID INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    studentID int(10) unsigned NOT NULL,
    categoryID int(10) unsigned NOT NULL,
    points INT(4) unsigned NOT NULL,
    reason varchar(255) NOT NULL,
    yearID int(10) unsigned NOT NULL,
    awardedDate datetime NOT NULL,
    awardedBy int(10) unsigned NOT NULL,
    PRIMARY KEY (hpID)
    );";

$moduleTables[2] = "
    CREATE TABLE hpPointHouse (
    hpID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    houseID INT(10) UNSIGNED NOT NULL,
    categoryID INT(10) UNSIGNED NOT NULL,
    points INT(4) UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    yearID INT(10) UNSIGNED NOT NULL,
    awardedDate DATETIME NOT NULL,
    awardedBy INT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (hpID)
    );";

//Action rows
// for admin and SLT only
$actionRows[0]["name"]="Categories" ;
$actionRows[0]["precedence"]="1";
$actionRows[0]["category"]="Manage" ;
$actionRows[0]["description"]="Manage category list" ;
$actionRows[0]["URLList"]="category.php" ;
$actionRows[0]["entryURL"]="category.php" ;
$actionRows[0]["defaultPermissionAdmin"]="Y" ;
$actionRows[0]["defaultPermissionTeacher"]="N" ;
$actionRows[0]["defaultPermissionStudent"]="N" ;
$actionRows[0]["defaultPermissionParent"]="N" ;
$actionRows[0]["defaultPermissionPublic"]="N" ;
$actionRows[0]["defaultPermissionSupport"]="Y" ;
$actionRows[0]["categoryPermissionStaff"]="Y" ;
$actionRows[0]["categoryPermissionStudent"]="N" ;
$actionRows[0]["categoryPermissionParent"]="N" ;
$actionRows[0]["categoryPermissionOther"]="N" ;

$actionRows[1]["name"]="Award student points" ;
$actionRows[1]["precedence"]="1";
$actionRows[1]["category"]="Award" ;
$actionRows[1]["description"]="Award points to students" ;
$actionRows[1]["URLList"]="award.php" ;
$actionRows[1]["entryURL"]="award.php" ;
$actionRows[1]["defaultPermissionAdmin"]="Y" ;
$actionRows[1]["defaultPermissionTeacher"]="Y" ;
$actionRows[1]["defaultPermissionStudent"]="N" ;
$actionRows[1]["defaultPermissionParent"]="N" ;
$actionRows[1]["defaultPermissionPublic"]="N" ;
$actionRows[1]["defaultPermissionSupport"]="Y" ;
$actionRows[1]["categoryPermissionStaff"]="Y" ;
$actionRows[1]["categoryPermissionStudent"]="N" ;
$actionRows[1]["categoryPermissionParent"]="N" ;
$actionRows[1]["categoryPermissionOther"]="N" ;

$actionRows[2]["name"]="Award house points" ;
$actionRows[2]["precedence"]="1";
$actionRows[2]["category"]="Award" ;
$actionRows[2]["description"]="Award points to house" ;
$actionRows[2]["URLList"]="house.php" ;
$actionRows[2]["entryURL"]="house.php" ;
$actionRows[2]["defaultPermissionAdmin"]="Y" ;
$actionRows[2]["defaultPermissionTeacher"]="Y" ;
$actionRows[2]["defaultPermissionStudent"]="N" ;
$actionRows[2]["defaultPermissionParent"]="N" ;
$actionRows[2]["defaultPermissionPublic"]="N" ;
$actionRows[2]["defaultPermissionSupport"]="Y" ;
$actionRows[2]["categoryPermissionStaff"]="Y" ;
$actionRows[2]["categoryPermissionStudent"]="N" ;
$actionRows[2]["categoryPermissionParent"]="N" ;
$actionRows[2]["categoryPermissionOther"]="N" ;

$actionRows[3]["name"]="View points overall" ;
$actionRows[3]["precedence"]="4";
$actionRows[3]["category"]="View" ;
$actionRows[3]["description"]="View points for each house" ;
$actionRows[3]["URLList"]="overall.php" ;
$actionRows[3]["entryURL"]="overall.php" ;
$actionRows[3]["defaultPermissionAdmin"]="Y" ;
$actionRows[3]["defaultPermissionTeacher"]="Y" ;
$actionRows[3]["defaultPermissionStudent"]="Y" ;
$actionRows[3]["defaultPermissionParent"]="Y" ;
$actionRows[3]["defaultPermissionPublic"]="N" ;
$actionRows[3]["defaultPermissionSupport"]="Y" ;
$actionRows[3]["categoryPermissionStaff"]="Y" ;
$actionRows[3]["categoryPermissionStudent"]="Y" ;
$actionRows[3]["categoryPermissionParent"]="Y" ;
$actionRows[3]["categoryPermissionOther"]="Y" ;

$actionRows[4]["name"]="View points individual" ;
$actionRows[4]["precedence"]="5";
$actionRows[4]["category"]="View" ;
$actionRows[4]["description"]="Select individual student to view house points" ;
$actionRows[4]["URLList"]="individual.php" ;
$actionRows[4]["entryURL"]="individual.php" ;
$actionRows[4]["defaultPermissionAdmin"]="Y" ;
$actionRows[4]["defaultPermissionTeacher"]="Y" ;
$actionRows[4]["defaultPermissionStudent"]="N" ;
$actionRows[4]["defaultPermissionParent"]="N" ;
$actionRows[4]["defaultPermissionPublic"]="N" ;
$actionRows[4]["defaultPermissionSupport"]="Y" ;
$actionRows[4]["categoryPermissionStaff"]="Y" ;
$actionRows[4]["categoryPermissionStudent"]="N" ;
$actionRows[4]["categoryPermissionParent"]="N" ;
$actionRows[4]["categoryPermissionOther"]="N" ;

$actionRows[5]["name"]="View points class" ;
$actionRows[5]["precedence"]="6";
$actionRows[5]["category"]="View" ;
$actionRows[5]["description"]="View points for whole class" ;
$actionRows[5]["URLList"]="classpoints.php" ;
$actionRows[5]["entryURL"]="classpoints.php" ;
$actionRows[5]["defaultPermissionAdmin"]="Y" ;
$actionRows[5]["defaultPermissionTeacher"]="Y" ;
$actionRows[5]["defaultPermissionStudent"]="N" ;
$actionRows[5]["defaultPermissionParent"]="N" ;
$actionRows[5]["defaultPermissionPublic"]="N" ;
$actionRows[5]["defaultPermissionSupport"]="Y" ;
$actionRows[5]["categoryPermissionStaff"]="Y" ;
$actionRows[5]["categoryPermissionStudent"]="N" ;
$actionRows[5]["categoryPermissionParent"]="N" ;
$actionRows[5]["categoryPermissionOther"]="N" ;

// student's view
$actionRows[6]["name"]="View my points" ;
$actionRows[6]["precedence"]="7";
$actionRows[6]["category"]="View" ;
$actionRows[6]["description"]="Student can see how many points they have" ;
$actionRows[6]["URLList"]="mypoints.php" ;
$actionRows[6]["entryURL"]="mypoints.php" ;
$actionRows[6]["defaultPermissionAdmin"]="N" ;
$actionRows[6]["defaultPermissionTeacher"]="N" ;
$actionRows[6]["defaultPermissionStudent"]="Y" ;
$actionRows[6]["defaultPermissionParent"]="N" ;
$actionRows[6]["defaultPermissionPublic"]="N" ;
$actionRows[6]["defaultPermissionSupport"]="N" ;
$actionRows[6]["categoryPermissionStaff"]="N" ;
$actionRows[6]["categoryPermissionStudent"]="Y" ;
$actionRows[6]["categoryPermissionParent"]="N" ;
$actionRows[6]["categoryPermissionOther"]="N" ;

// manage points
$actionRows[7]["name"]="Manage points" ;
$actionRows[7]["precedence"]="8";
$actionRows[7]["category"]="Manage" ;
$actionRows[7]["description"]="modify points awarded" ;
$actionRows[7]["URLList"]="manage.php" ;
$actionRows[7]["entryURL"]="manage.php" ;
$actionRows[7]["defaultPermissionAdmin"]="Y" ;
$actionRows[7]["defaultPermissionTeacher"]="N" ;
$actionRows[7]["defaultPermissionStudent"]="N" ;
$actionRows[7]["defaultPermissionParent"]="N" ;
$actionRows[7]["defaultPermissionPublic"]="N" ;
$actionRows[7]["defaultPermissionSupport"]="Y" ;
$actionRows[7]["categoryPermissionStaff"]="Y" ;
$actionRows[7]["categoryPermissionStudent"]="N" ;
$actionRows[7]["categoryPermissionParent"]="N" ;
$actionRows[7]["categoryPermissionOther"]="N" ;


$actionRows[8]["name"]="Award student points_unlimited" ;
$actionRows[8]["precedence"]="2";
$actionRows[8]["category"]="Award" ;
$actionRows[8]["description"]="Award points to students, without a limit" ;
$actionRows[8]["URLList"]="award.php" ;
$actionRows[8]["entryURL"]="award.php" ;
$actionRows[8]["defaultPermissionAdmin"]="Y" ;
$actionRows[8]["defaultPermissionTeacher"]="N" ;
$actionRows[8]["defaultPermissionStudent"]="N" ;
$actionRows[8]["defaultPermissionParent"]="N" ;
$actionRows[8]["defaultPermissionPublic"]="N" ;
$actionRows[8]["defaultPermissionSupport"]="N" ;
$actionRows[8]["categoryPermissionStaff"]="Y" ;
$actionRows[8]["categoryPermissionStudent"]="N" ;
$actionRows[8]["categoryPermissionParent"]="N" ;
$actionRows[8]["categoryPermissionOther"]="N" ;

$actionRows[9]["name"]="Award house points_unlimited" ;
$actionRows[9]["precedence"]="2";
$actionRows[9]["category"]="Award" ;
$actionRows[9]["description"]="Award points to house, without a limit" ;
$actionRows[9]["URLList"]="house.php" ;
$actionRows[9]["entryURL"]="house.php" ;
$actionRows[9]["defaultPermissionAdmin"]="Y" ;
$actionRows[9]["defaultPermissionTeacher"]="N" ;
$actionRows[9]["defaultPermissionStudent"]="N" ;
$actionRows[9]["defaultPermissionParent"]="N" ;
$actionRows[9]["defaultPermissionPublic"]="N" ;
$actionRows[9]["defaultPermissionSupport"]="N" ;
$actionRows[9]["categoryPermissionStaff"]="Y" ;
$actionRows[9]["categoryPermissionStudent"]="N" ;
$actionRows[9]["categoryPermissionParent"]="N" ;
$actionRows[9]["categoryPermissionOther"]="N" ;

// Add some default categories
$gibbonSetting[0]="INSERT INTO `hpCategory` (`categoryID`, `categoryName`, `categoryOrder`, `categoryType`, `categoryPresets`) VALUES ('0', '-- Unlimited House Points --', '0', 'House', '')";
$gibbonSetting[1]="INSERT INTO `hpCategory` (`categoryID`, `categoryName`, `categoryOrder`, `categoryType`, `categoryPresets`) VALUES ('0', '-- Unlimited Student Points --', '0', 'Student', '')";


$array = array();
$array['sourceModuleName'] = $name;
$array['sourceModuleAction'] = 'View points overall';
$array['sourceModuleInclude'] = 'hook_housepoint.php';
$hooks[0] = "
INSERT INTO gibbonHook
(gibbonHookID, name, type, options, gibbonModuleID)
VALUES
(
    NULL,
    'House Points',
    'Staff Dashboard',
    '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name')
),
(
    NULL,
    'House Points',
    'Student Dashboard',
    '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name')
),
(
    NULL,
    'House Points',
    'Parental Dashboard',
    '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name')
);
";

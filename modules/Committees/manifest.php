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

// Basic variables
$name = "Committees";
$description = "Manage committee members and sign-up.";
$entryURL = "committees.php";
$type = "Additional";
$category = "Other";
$version = "1.3.00";
$author = "Sandra Kuipers";
$url = "https://github.com/SKuipers";

// Module tables
$moduleTables[] = "CREATE TABLE `committeesCommittee` (
    `committeesCommitteeID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonSchoolYearID` INT(3) UNSIGNED ZEROFILL NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` TEXT NULL,
    `logo` VARCHAR(255) NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `signup` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    PRIMARY KEY (`committeesCommitteeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `committeesRole` (
    `committeesRoleID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `committeesCommitteeID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `name` VARCHAR(60) NOT NULL,
    `type` ENUM('Chair','Admin','Member') NOT NULL DEFAULT 'Member',
    `seats` INT(4) NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `signup` ENUM('Y','N') NOT NULL DEFAULT 'N',
    PRIMARY KEY (`committeesRoleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `committeesMember` (
    `committeesMemberID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `committeesCommitteeID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `committeesRoleID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    PRIMARY KEY (`committeesMemberID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";


// gibbonSettings entries
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Committees', 'signupActive', 'Sign-up Active?', 'System-wide access control', 'N');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope` ,`name` ,`nameDisplay` ,`description` ,`value`) VALUES ('Committees', 'signupMaximum', 'Maximum Committees per Person', '', '1');";

// Action rows
$actionRows[] = [
    'name'                      => 'View Committees',
    'precedence'                => '0',
    'category'                  => 'Committees',
    'description'               => '',
    'URLList'                   => 'committees.php,committee.php',
    'entryURL'                  => 'committees.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'My Committees',
    'precedence'                => '0',
    'category'                  => 'Committees',
    'description'               => '',
    'URLList'                   => 'committees_my.php,committee_leave.php',
    'entryURL'                  => 'committees_my.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Sign-up for Committees',
    'precedence'                => '0',
    'category'                  => 'Committees',
    'description'               => '',
    'URLList'                   => 'committee_signup.php',
    'entryURL'                  => 'committee_signup.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Manage Committees_all',
    'precedence'                => '1',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'committees_manage.php,committees_manage_add.php,committees_manage_edit.php,committees_manage_edit_role_edit.php,committees_manage_delete.php,committees_manage_members.php,committees_manage_members_add.php,committees_manage_members_edit.php,committees_manage_members_delete.php',
    'entryURL'                  => 'committees_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Manage Committees_myCommitteeAdmin',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'committees_manage_edit.php,committees_manage_edit_role_edit.php,committees_manage_members.php,committees_manage_members_add.php,committees_manage_members_edit.php,committees_manage_members_delete.php',
    'entryURL'                  => 'committees_manage_edit.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'N',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Manage Settings',
    'precedence'                => '0',
    'category'                  => 'Administration',
    'description'               => '',
    'URLList'                   => 'settings.php',
    'entryURL'                  => 'settings.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Committee Membership',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => '',
    'URLList'                   => 'report_members.php',
    'entryURL'                  => 'report_members.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Staff Not Signed-up',
    'precedence'                => '0',
    'category'                  => 'Reports',
    'description'               => '',
    'URLList'                   => 'report_notSignedUp.php',
    'entryURL'                  => 'report_notSignedUp.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

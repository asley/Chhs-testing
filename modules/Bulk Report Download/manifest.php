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
along with this program.  If not, see <http:// www.gnu.org/licenses/>.
*/

// This file describes the module, including database tables

// Basic variables
$name        = 'Bulk Report Download';            // The name of the module as it appears to users. Needs to be unique to installation. Also the name of the folder that holds the unit.
$description = 'Allows users to download reports in bulk by form-groups and yeargroups.';            // Short text description
$entryURL    = '/bulk_download_ui.php';   // The landing page for the unit, used in the main menu
$type        = 'Additional';  // Do not change.
$category    = 'Publish';            // The main menu area to place the module in
$version     = '1.0.0';            // Version number
$author      = 'Asley Smith';            // Your name
$url         = 'https://example.com';            // Your URL

// Module tables & gibbonSettings entries
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `bulk_download_logs` (
    `logID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `userID` int(10) unsigned NOT NULL,
    `downloadDate` datetime NOT NULL,
    `criteria` text NOT NULL,
    PRIMARY KEY (`logID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Add gibbonSettings entries
$gibbonSetting = [];

// Action rows 
// One array per action
$actionRows[] = [
    'name'                      => 'Bulk Download Reports', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0', // If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Publish', // Optional: subgroups for the right hand side module menu
    'description'               => 'UI for selecting form-groups and yeargroups to download reports.', // Text description
    'URLList'                   => ['/bulk_download_ui.php', '/bulk_download_process.php'], // List of pages included in this action
    'entryURL'                  => '/bulk_download_ui.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'N', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
    'name'                      => 'Bulk Download Process', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0', // If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Tools', // Optional: subgroups for the right hand side module menu
    'description'               => 'Handles the report generation and download process.', // Text description
    'URLList'                   => ['/bulk_download_process.php'], // List of pages included in this action
    'entryURL'                  => '/bulk_download_process.php', // The landing action for the page.
    'entrySidebar'              => 'N', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'N', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'N', // Should this action be available to user roles in the Other category?
];

// Hooks
$hooks[] = ""; // Serialised array to create hook and set options. See Hooks documentation online.
?>

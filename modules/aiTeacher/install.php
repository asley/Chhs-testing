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

// Gibbon includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/install.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    try {
        // Create chat conversations table
        $sql = "CREATE TABLE IF NOT EXISTS `aiTeacherChats` (
            `aiTeacherChatID` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `gibbonPersonID` int(10) unsigned NOT NULL,
            `title` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`aiTeacherChatID`),
            KEY `gibbonPersonID` (`gibbonPersonID`),
            CONSTRAINT `aiTeacherChats_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $pdo->executeQuery(array(), $sql);

        // Create chat messages table
        $sql = "CREATE TABLE IF NOT EXISTS `aiTeacherChatMessages` (
            `aiTeacherChatMessageID` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `aiTeacherChatID` int(10) unsigned NOT NULL,
            `role` enum('user','assistant') NOT NULL,
            `content` text NOT NULL,
            `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`aiTeacherChatMessageID`),
            KEY `aiTeacherChatID` (`aiTeacherChatID`),
            CONSTRAINT `aiTeacherChatMessages_ibfk_1` FOREIGN KEY (`aiTeacherChatID`) REFERENCES `aiTeacherChats` (`aiTeacherChatID`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $pdo->executeQuery(array(), $sql);

        // Add module installation record
        $sql = "INSERT INTO gibbonModule (name, version, active) VALUES ('aiTeacher', '1.0.0', 'Y')";
        $pdo->executeQuery(array(), $sql);

        $page->addSuccess(__('Module installed successfully. Database tables have been created.'));
    } catch (Exception $e) {
        $page->addError(__('Failed to install module: ') . $e->getMessage());
    }
}
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
$name        = 'aiTeacher';            // The name of the module as it appears to users
$description = 'AI-powered teaching assistant for CSEC curriculum support, assessment analysis, and teacher productivity tools.';
$entryURL    = "index.php";
$type        = "Additional";
$category    = 'Teaching & Learning';
$version     = '2.1.00';
$author      = 'Asley Smith';
$url         = 'https://tasanz.com';            


// Module tables
$moduleTables = array();

$moduleTables[] = "
CREATE TABLE `gibbonChatBotFeedback` (
    `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `user_message` text NOT NULL,
    `ai_response` text NOT NULL,
    `feedback_type` enum('like','dislike') NOT NULL,
    `feedback_text` text,
    `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE `gibbonChatBotTraining` (
    `gibbonChatBotTrainingID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `question` text NOT NULL,
    `answer` text NOT NULL,
    `approved` tinyint(1) NOT NULL DEFAULT '0',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonChatBotTrainingID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE `gibbonChatBotCourseMaterials` (
    `gibbonChatBotCourseMaterialsID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `type` varchar(50) NOT NULL,
    `description` text NULL,
    `filePath` varchar(255) NULL,
    `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `dateAdded` date NOT NULL,
    `gibbonPersonIDCreator` int(10) unsigned zerofill NOT NULL,
    PRIMARY KEY (`gibbonChatBotCourseMaterialsID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE `gibbonChatBotStudentProgress` (
    `gibbonChatBotStudentProgressID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `progress` decimal(5,2) NOT NULL DEFAULT '0.00',
    `lastActivity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonChatBotStudentProgressID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE `gibbonChatBotStudentAnalytics` (
    `gibbonChatBotStudentAnalyticsID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `analyticsData` text NOT NULL,
    `dateGenerated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonChatBotStudentAnalyticsID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE `gibbonChatBotInterventions` (
    `gibbonChatBotInterventionsID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `gibbonCourseID` int(8) unsigned zerofill NOT NULL,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `interventionType` varchar(50) NOT NULL,
    `description` text NOT NULL,
    `status` enum('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
    `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dateModified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`gibbonChatBotInterventionsID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE IF NOT EXISTS `aiTeacherLogs` (
    `aiTeacherLogID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned NOT NULL,
    `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `action` varchar(50) NOT NULL,
    `subject` varchar(100) NOT NULL,
    `details` text,
    `response` text,
    `feedback` text,
    PRIMARY KEY (`aiTeacherLogID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    CONSTRAINT `aiTeacherLogs_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "
CREATE TABLE IF NOT EXISTS `aiTeacherUploads` (
    `aiTeacherUploadID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned NOT NULL,
    `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `filename` varchar(255) NOT NULL,
    `filepath` varchar(255) NOT NULL,
    `filetype` varchar(50) NOT NULL,
    `filesize` int(10) unsigned NOT NULL,
    `subject` varchar(100) NOT NULL,
    `description` text,
    `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (`aiTeacherUploadID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    CONSTRAINT `aiTeacherUploads_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// gibbonSettings: array of associative arrays (not SQL)
$gibbonSettings = array(
    array(
        'name' => 'aiTeacher_deepseek_api_key',
        'value' => '',
        'description' => 'DeepSeek API Key for AI integration'
    ),
    array(
        'name' => 'aiTeacher_upload_path',
        'value' => 'uploads/aiTeacher',
        'description' => 'Path for storing uploaded resources'
    ),
    array(
        'name' => 'aiTeacher_score_threshold',
        'value' => '60',
        'description' => 'Threshold for student performance alerts (percentage)'
    )
);

// Action rows: array of associative arrays (not SQL)
$actionRows = array(
    array(
        'name'                      => 'aiTeacher',
        'precedence'                => '0',
        'category'                  => 'General',
        'description'               => 'Main dashboard for AI Teacher Assistance features',
        'URLList'                   => 'index.php',
        'entryURL'                  => 'index.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'N',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    ),
    array(
        'name'                      => 'Curriculum Support',
        'precedence'                => '1',
        'category'                  => 'Features',
        'description'               => 'Generate lesson plans and schemes of work for CSEC subjects',
        'URLList'                   => 'curriculum_support.php',
        'entryURL'                  => 'curriculum_support.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'N',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    ),
    array(
        'name'                      => 'Assessment Analysis',
        'precedence'                => '2',
        'category'                  => 'Features',
        'description'               => 'Analyze student performance and generate intervention strategies',
        'URLList'                   => 'assessment_analysis.php',
        'entryURL'                  => 'assessment_analysis.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'N',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    ),
    array(
        'name'                      => 'Resource Generator',
        'precedence'                => '3',
        'category'                  => 'Features',
        'description'               => 'Generate quizzes, worksheets, and assessment materials',
        'URLList'                   => 'resource_generator.php',
        'entryURL'                  => 'resource_generator.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'N',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    ),
    array(
        'name'                      => 'Settings',
        'precedence'                => '4',
        'category'                  => 'Configuration',
        'description'               => 'Configure AI Teacher Assistance settings',
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
        'categoryPermissionOther'   => 'N'
    ),
    array(
        'name'                      => 'AI Chatbot',
        'precedence'                => '5',
        'category'                  => 'Features',
        'description'               => 'Chat with the AI Teacher Assistant and request content',
        'URLList'                   => 'chatbot.php',
        'entryURL'                  => 'chatbot.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'Y',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'Y',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    ),
    // Module actions
    array(
        'name'                      => __('Assessment Analysis Renamed'), // Temporarily changed
        'precedence'                => '6',
        'category'                  => 'Features',
        'description'               => __('Analyze student assessments and get AI recommendations for failing students.'),
        'URLList'                   => 'assessment_analysis.php',
        'entryURL'                  => 'assessment_analysis.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'N',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N',
        'displayPermissions' => array(
            'Staff',
            'Admin'
        )
    ),
    array(
        'name'                      => __('My AI Feedback'), // New action for students
        'precedence'                => '7', // Added
        'category'                  => 'Features', // Or a new category like 'Student Tools'
        'description'               => __('View AI-generated feedback for your assessments.'),
        'URLList'                   => 'my_ai_feedback.php', // Added
        'entryURL'                  => 'my_ai_feedback.php',
        'entrySidebar'              => 'Y', // Added
        'menuShow'                  => 'Y', // Added
        'defaultPermissionAdmin'    => 'N', // Added (Admins might not need direct access here, or 'Y')
        'defaultPermissionTeacher'  => 'N', // Added
        'defaultPermissionStudent'  => 'Y', // Added
        'defaultPermissionParent'   => 'N', // Added
        'defaultPermissionSupport'  => 'N', // Added
        'categoryPermissionStaff'   => 'N', // Added
        'categoryPermissionStudent' => 'Y', // Added
        'categoryPermissionParent'  => 'N', // Added
        'categoryPermissionOther'   => 'N', // Added
        'displayPermissions' => array(
            'Student'
        )
    ),
    array(
        'name'                      => 'Get AI Recommendation', // This is likely an internal/AJAX script
        'precedence'                => '8', // Added
        'category'                  => 'AJAX', // Or similar internal category
        'description'               => 'Handles AJAX requests for AI recommendations.',
        'URLList'                   => 'get_ai_recommendation.php', // Added
        'entryURL'                  => 'get_ai_recommendation.php',
        'entrySidebar'              => 'N', // Added (AJAX endpoints usually not in sidebar)
        'menuShow'                  => 'N', // Added (AJAX endpoints usually not in menu)
        'defaultPermissionAdmin'    => 'Y', // Added
        'defaultPermissionTeacher'  => 'Y', // Added
        'defaultPermissionStudent'  => 'Y', // Added (If students can trigger this)
        'defaultPermissionParent'   => 'N', // Added
        'defaultPermissionSupport'  => 'Y', // Added
        'categoryPermissionStaff'   => 'Y', // Added
        'categoryPermissionStudent' => 'Y', // Added
        'categoryPermissionParent'  => 'N', // Added
        'categoryPermissionOther'   => 'N', // Added
        'displayPermissions' => array(
            'Staff',
            'Admin',
            'Student'
        ),
        'secure' => true // Mark as secure if it doesn't render a full page
    ),
    array(
        'name'                      => 'AI Tutor Chat',
        'precedence'                => '9',
        'category'                  => 'Features',
        'description'               => 'Chat with your personal AI tutor for homework help and study guidance',
        'URLList'                   => 'student_ai_tutor.php,student_ai_tutor_ajax.php,student_chat_history.php,student_chat_view.php',
        'entryURL'                  => 'student_ai_tutor.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'Y',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'Y',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'Y',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    ),

    array(
        'name'                      => 'Student AI Tutor Usage',
        'precedence'                => '0',
        'category'                  => 'Monitoring',
        'description'               => 'Monitor student AI tutor conversations and view prompts',
        'URLList'                   => 'teacher_student_usage.php,teacher_conversation_view.php',
        'entryURL'                  => 'teacher_student_usage.php',
        'entrySidebar'              => 'Y',
        'menuShow'                  => 'Y',
        'defaultPermissionAdmin'    => 'Y',
        'defaultPermissionTeacher'  => 'Y',
        'defaultPermissionStudent'  => 'N',
        'defaultPermissionParent'   => 'N',
        'defaultPermissionSupport'  => 'N',
        'categoryPermissionStaff'   => 'Y',
        'categoryPermissionStudent' => 'N',
        'categoryPermissionParent'  => 'N',
        'categoryPermissionOther'   => 'N'
    )
);

// Hooks: array of associative arrays
$hooks = array(
    array(
        'name' => 'AI Teacher Assistance',
        'type' => 'hook',
        'options' => array(
            'source' => 'AI Teacher Assistance',
            'hook' => 'moduleFunctions.php',
            'priority' => '1'
        )
    )
);

// No accidental array assignments to $moduleTables below this point!

-- Create settings table
CREATE TABLE IF NOT EXISTS `aiTeacherSettings` (
    `aiTeacherSettingsID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `scope` varchar(50) NOT NULL,
    `name` varchar(100) NOT NULL,
    `value` text,
    `description` text,
    PRIMARY KEY (`aiTeacherSettingsID`),
    UNIQUE KEY `scope_name` (`scope`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create logs table
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
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create uploads table
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
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create chat conversations table
CREATE TABLE IF NOT EXISTS `aiTeacherChats` (
    `aiTeacherChatID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned NOT NULL,
    `title` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`aiTeacherChatID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    CONSTRAINT `aiTeacherChats_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create chat messages table
CREATE TABLE IF NOT EXISTS `aiTeacherChatMessages` (
    `aiTeacherChatMessageID` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `aiTeacherChatID` int(10) unsigned NOT NULL,
    `role` enum('user','assistant') NOT NULL,
    `content` text NOT NULL,
    `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`aiTeacherChatMessageID`),
    KEY `aiTeacherChatID` (`aiTeacherChatID`),
    CONSTRAINT `aiTeacherChatMessages_ibfk_1` FOREIGN KEY (`aiTeacherChatID`) REFERENCES `aiTeacherChats` (`aiTeacherChatID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Insert default settings
INSERT INTO `aiTeacherSettings` (`scope`, `name`, `value`, `description`) VALUES
('aiTeacher', 'deepseek_api_key', '', 'DeepSeek API Key for AI integration'),
('aiTeacher', 'upload_path', 'uploads/aiTeacher', 'Path for storing uploaded resources'),
('aiTeacher', 'score_threshold', '60', 'Threshold for student performance alerts (percentage)');

-- Add foreign key constraints
ALTER TABLE `aiTeacherLogs`
    ADD CONSTRAINT `aiTeacherLogs_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE;

ALTER TABLE `aiTeacherUploads`
    ADD CONSTRAINT `aiTeacherUploads_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE; 
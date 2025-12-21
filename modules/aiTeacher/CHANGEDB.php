<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v0.0.00
$sql[$count][0] = "0.0.00";
$sql[$count][1] = "-- First version, nothing to update";


// v2.0.00 - Phase 1: Student AI Tutor Chat
$count++;
$sql[$count][0] = "2.0.00";
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `aiTeacherStudentConversations` (
  `conversationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonCourseID` int(8) unsigned zerofill DEFAULT NULL,
  `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
  `sessionID` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `sender` enum('student','ai','teacher') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `flagged` tinyint(1) DEFAULT 0,
  `flagReason` varchar(255) DEFAULT NULL,
  `context` text,
  `rating` enum('helpful','not_helpful') DEFAULT NULL,
  `teacherReviewed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`conversationID`),
  KEY `idx_student` (`gibbonPersonID`),
  KEY `idx_session` (`sessionID`),
  KEY `idx_flagged` (`flagged`),
  CONSTRAINT `aiTeacherStudentConversations_ibfk_1`
    FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end

CREATE TABLE IF NOT EXISTS `aiTeacherChatSessions` (
  `sessionID` varchar(50) NOT NULL,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `startTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastActivity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `topic` varchar(255) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `messageCount` int(5) DEFAULT 0,
  `resolved` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`sessionID`),
  KEY `idx_student` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;end
";

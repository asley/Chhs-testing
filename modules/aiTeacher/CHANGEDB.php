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

INSERT INTO gibbonAction (
    gibbonModuleID,
    name,
    precedence,
    category,
    description,
    URLList,
    entryURL,
    entrySidebar,
    menuShow,
    defaultPermissionAdmin,
    defaultPermissionTeacher,
    defaultPermissionStudent,
    defaultPermissionParent,
    defaultPermissionSupport,
    categoryPermissionStaff,
    categoryPermissionStudent,
    categoryPermissionParent,
    categoryPermissionOther
)
SELECT
    gibbonModuleID,
    'AI Tutor Chat',
    '9',
    'Features',
    'Chat with your personal AI tutor for homework help and study guidance',
    'student_ai_tutor.php,student_ai_tutor_ajax.php,student_chat_history.php',
    'student_ai_tutor.php',
    'Y',
    'Y',
    'Y',
    'Y',
    'Y',
    'N',
    'Y',
    'Y',
    'Y',
    'N',
    'N'
FROM gibbonModule
WHERE name = 'aiTeacher'
AND NOT EXISTS (
    SELECT 1 FROM gibbonAction WHERE name = 'AI Tutor Chat'
);end

INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
SELECT
    r.gibbonRoleID,
    a.gibbonActionID
FROM gibbonRole r
CROSS JOIN gibbonAction a
WHERE a.name = 'AI Tutor Chat'
AND (
    (r.category = 'Staff' AND a.defaultPermissionAdmin = 'Y')
    OR (r.category = 'Student' AND a.defaultPermissionStudent = 'Y')
)
AND NOT EXISTS (
    SELECT 1 FROM gibbonPermission p
    WHERE p.gibbonRoleID = r.gibbonRoleID
    AND p.gibbonActionID = a.gibbonActionID
);end
";

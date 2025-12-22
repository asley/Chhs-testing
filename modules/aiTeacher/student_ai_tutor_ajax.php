<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

AJAX handler for AI Tutor Chat
*/

// Gibbon includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/student_ai_tutor.php') == false) {
    echo json_encode([
        'success' => false,
        'error' => 'Access denied'
    ]);
    exit;
}

// Get current user
$gibbonPersonID = $gibbon->session->get('gibbonPersonID');
$gibbonSchoolYearID = $gibbon->session->get('gibbonSchoolYearID');

// Get POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'sendMessage':
            $message = trim($_POST['message'] ?? '');
            $sessionID = $_POST['sessionID'] ?? '';
            $gibbonCourseID = $_POST['gibbonCourseID'] ?? null;

            if (empty($message)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Message is empty'
                ]);
                exit;
            }

            if (empty($sessionID)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid session'
                ]);
                exit;
            }

            // Save student message
            saveStudentMessage($connection2, $gibbonPersonID, $gibbonSchoolYearID, $sessionID, $message, $gibbonCourseID);

            // Get AI response
            $result = getAITutorResponse($connection2, $gibbonPersonID, $gibbonSchoolYearID, $message, $sessionID, $gibbonCourseID);

            // Log action
            logAITeacherAction($connection2, $gibbonPersonID, 'AI Tutor Chat', 'General', $message, $result['response'] ?? '');

            echo json_encode($result);
            break;

        case 'rateResponse':
            $rating = $_POST['rating'] ?? '';
            $sessionID = $_POST['sessionID'] ?? '';

            if (!in_array($rating, ['helpful', 'not_helpful'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid rating'
                ]);
                exit;
            }

            // Update last AI message with rating
            $sql = "UPDATE aiTeacherStudentConversations
                    SET rating = :rating
                    WHERE sessionID = :sessionID
                    AND sender = 'ai'
                    ORDER BY timestamp DESC LIMIT 1";

            $connection2->executeQuery([
                'rating' => $rating,
                'sessionID' => $sessionID
            ], $sql);

            echo json_encode([
                'success' => true,
                'message' => 'Thank you for your feedback!'
            ]);
            break;

        case 'flagMessage':
            $reason = trim($_POST['reason'] ?? 'No reason provided');
            $sessionID = $_POST['sessionID'] ?? '';

            // Flag last AI message for teacher review
            $sql = "UPDATE aiTeacherStudentConversations
                    SET flagged = 1, flagReason = :reason, teacherReviewed = 0
                    WHERE sessionID = :sessionID
                    AND sender = 'ai'
                    ORDER BY timestamp DESC LIMIT 1";

            $connection2->executeQuery([
                'reason' => $reason,
                'sessionID' => $sessionID
            ], $sql);

            // TODO: Send email notification to teacher

            echo json_encode([
                'success' => true,
                'message' => 'Message has been flagged for teacher review.'
            ]);
            break;

        case 'newChat':
            // Create new chat session
            $newSessionID = generateSessionID();
            $sql = "INSERT INTO aiTeacherChatSessions
                    (sessionID, gibbonPersonID, startTime, lastActivity, messageCount)
                    VALUES (:sessionID, :personID, NOW(), NOW(), 0)";

            $connection2->executeQuery([
                'sessionID' => $newSessionID,
                'personID' => $gibbonPersonID
            ], $sql);

            echo json_encode([
                'success' => true,
                'sessionID' => $newSessionID,
                'message' => 'New chat session created'
            ]);
            break;

        case 'getHistory':
            $sessionID = $_POST['sessionID'] ?? '';

            if (empty($sessionID)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid session'
                ]);
                exit;
            }

            $messages = getConversationContext($connection2, $sessionID, 50);

            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("AI Tutor AJAX Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred. Please try again.'
    ]);
}

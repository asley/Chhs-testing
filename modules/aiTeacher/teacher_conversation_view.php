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

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Services\Format;

// Get database from container
$pdo = $container->get('db');

$page->breadcrumbs
    ->add(__('Student AI Tutor Usage'), 'teacher_student_usage.php')
    ->add(__('View Conversation'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/teacher_student_usage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $sessionID = $_GET['sessionID'] ?? '';

    if (empty($sessionID)) {
        $page->addError(__('Invalid session ID.'));
    } else {
        // Get session details including student photo
        $sql = "SELECT s.*, p.surname, p.preferredName, p.gibbonPersonID, p.image_240
                FROM aiTeacherChatSessions s
                JOIN gibbonPerson p ON s.gibbonPersonID = p.gibbonPersonID
                WHERE s.sessionID = :sessionID";
        $result = $pdo->select($sql, ['sessionID' => $sessionID]);

        if ($result->rowCount() === 0) {
            $page->addError(__('Session not found.'));
        } else {
            $session = $result->fetch();
            $studentName = Format::name('', $session['preferredName'], $session['surname'], 'Student', true);
            $studentPhoto = $session['image_240'] ?? '';
            $absoluteURL = $gibbon->session->get('absoluteURL');

            echo '<div class="linkTop">';
            echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/teacher_student_usage.php">';
            echo __('Back to Usage Monitor');
            echo '</a>';
            echo '</div>';

            echo '<h2>' . __('Student Conversation') . '</h2>';

            // Display topic prominently if available
            if (!empty($session['topic'])) {
                echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                echo '<div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">' . __('Conversation Topic') . '</div>';
                echo '<div style="font-size: 18px; font-weight: 600;">' . htmlspecialchars($session['topic']) . '</div>';
                echo '</div>';
            }

            // Session info
            echo '<table class="smallIntBorder fullWidth" cellspacing="0">';
            echo '<tr>';
            echo '<td style="width: 20%; font-weight: bold;">' . __('Student') . '</td>';
            echo '<td>' . $studentName . '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td style="font-weight: bold;">' . __('Started') . '</td>';
            echo '<td>' . Format::dateTime($session['startTime']) . '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td style="font-weight: bold;">' . __('Last Activity') . '</td>';
            echo '<td>' . Format::dateTime($session['lastActivity']) . '</td>';
            echo '</tr>';
            echo '<tr>';
            echo '<td style="font-weight: bold;">' . __('Total Messages') . '</td>';
            echo '<td>' . $session['messageCount'] . '</td>';
            echo '</tr>';
            echo '</table>';

            echo '<h3>' . __('Full Conversation') . '</h3>';

            // Add CSS for chat display
            echo '<link rel="stylesheet" type="text/css" href="' . $gibbon->session->get('absoluteURL') . '/modules/aiTeacher/css/student_tutor.css">';

            // Add MathJax for mathematical expressions
            echo '<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js" async></script>';
            echo '<style>
                .ai-numbered-list, .ai-bullet-list { margin: 10px 0; padding-left: 25px; }
                .ai-numbered-list li, .ai-bullet-list li { margin: 5px 0; }
                .math-block { display: block; margin: 15px 0; text-align: center; }
                .math-inline { display: inline; }
                .message-bubble p { margin: 8px 0; }
                .message-bubble p:first-child { margin-top: 0; }
                .message-bubble p:last-child { margin-bottom: 0; }
            </style>';

            // Get all messages for this session
            $messages = getConversationContext($pdo, $sessionID, 1000);

            if (count($messages) > 0) {
                echo '<div class="ai-tutor-messages" style="max-height: none; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">';

                foreach ($messages as $msg) {
                    $isStudent = ($msg['sender'] === 'student');
                    $messageClass = $isStudent ? 'student-message' : 'ai-message';
                    $isFlagged = isset($msg['flagged']) && $msg['flagged'] == 1;

                    // Generate avatar HTML
                    if ($isStudent) {
                        if (!empty($studentPhoto)) {
                            $photoPath = $absoluteURL . '/' . $studentPhoto;
                            $avatarHTML = '<img src="' . $photoPath . '" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">';
                        } else {
                            $avatarHTML = '<img src="' . $absoluteURL . '/themes/Default/img/anonymous_240.jpg" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">';
                        }
                    } else {
                        $avatarHTML = '🤖';
                    }

                    echo '<div class="' . $messageClass . '" style="margin-bottom: 15px; ' . ($isFlagged ? 'border: 2px solid red; background: #ffe6e6;' : '') . '">';

                    if (!$isStudent) {
                        echo '<div class="message-avatar">' . $avatarHTML . '</div>';
                    }

                    echo '<div class="message-bubble">';

                    // Show flagged warning
                    if ($isFlagged && isset($msg['flagReason'])) {
                        echo '<div style="background: #cc0000; color: white; padding: 5px 10px; margin-bottom: 10px; border-radius: 4px; font-size: 0.9em;">';
                        echo '⚠️ <strong>Flagged:</strong> ' . ucfirst(str_replace('_', ' ', $msg['flagReason']));
                        echo '</div>';
                    }

                    // Use markdown and math rendering for AI messages, simple formatting for student messages
                    if ($isStudent) {
                        echo '<p>' . nl2br(htmlspecialchars($msg['message'])) . '</p>';
                    } else {
                        echo renderMarkdownAndMath($msg['message']);
                    }
                    echo '<span class="message-time" style="font-size: 0.8em; color: #666;">';
                    echo date('g:i A', strtotime($msg['timestamp']));

                    // Show rating if exists
                    if (isset($msg['rating']) && !empty($msg['rating'])) {
                        echo ' • ';
                        echo $msg['rating'] === 'helpful' ? '👍 Student rated helpful' : '👎 Student rated not helpful';
                    }

                    echo '</span>';
                    echo '</div>';

                    if ($isStudent) {
                        echo '<div class="message-avatar">' . $avatarHTML . '</div>';
                    }

                    echo '</div>';
                }

                echo '</div>';
            } else {
                echo '<div class="warning">';
                echo __('No messages found in this conversation.');
                echo '</div>';
            }

            // Teacher Feedback section
            echo '<h3 style="margin-top: 30px;">' . __('Teacher Feedback') . '</h3>';

            // Create table if it doesn't exist (do this first before any queries)
            $createTableSQL = "CREATE TABLE IF NOT EXISTS aiTeacherConversationComments (
                commentID INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sessionID VARCHAR(64) NOT NULL,
                gibbonPersonIDTeacher INT(10) UNSIGNED NOT NULL,
                comment TEXT NOT NULL,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (sessionID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            try {
                $pdo->statement($createTableSQL);
            } catch (Exception $e) {
                error_log("Error creating aiTeacherConversationComments table: " . $e->getMessage());
            }

            // Handle feedback submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacherComment'])) {
                $teacherComment = trim($_POST['teacherComment']);
                $teacherID = $gibbon->session->get('gibbonPersonID');

                if (!empty($teacherComment)) {
                    try {
                        // Insert comment
                        $insertSQL = "INSERT INTO aiTeacherConversationComments
                                     (sessionID, gibbonPersonIDTeacher, comment, timestamp)
                                     VALUES (:sessionID, :teacherID, :comment, NOW())";
                        $pdo->insert($insertSQL, [
                            'sessionID' => $sessionID,
                            'teacherID' => $teacherID,
                            'comment' => $teacherComment
                        ]);

                        echo '<div class="success">' . __('Feedback added successfully.') . '</div>';
                    } catch (Exception $e) {
                        echo '<div class="error">' . __('Error saving feedback.') . '</div>';
                        error_log("Error saving teacher feedback: " . $e->getMessage());
                    }
                }
            }

            // Get existing comments
            $commentsSQL = "SELECT c.*, t.preferredName, t.surname, t.image_240
                           FROM aiTeacherConversationComments c
                           JOIN gibbonPerson t ON c.gibbonPersonIDTeacher = t.gibbonPersonID
                           WHERE c.sessionID = :sessionID
                           ORDER BY c.timestamp DESC";

            try {
                $commentsResult = $pdo->select($commentsSQL, ['sessionID' => $sessionID]);

                // Display existing comments
                if ($commentsResult && $commentsResult->rowCount() > 0) {
                    echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">';
                    echo '<h4 style="margin-top: 0;">' . __('Previous Feedback') . '</h4>';

                    while ($comment = $commentsResult->fetch()) {
                        $teacherName = Format::name('', $comment['preferredName'], $comment['surname'], 'Staff', false);
                        $teacherPhoto = $comment['image_240'] ?? '';

                        echo '<div style="display: flex; gap: 10px; margin-bottom: 15px; padding: 10px; background: white; border-radius: 6px; border-left: 3px solid #667eea;">';

                        // Teacher photo
                        if (!empty($teacherPhoto)) {
                            echo '<img src="' . $absoluteURL . '/' . $teacherPhoto . '" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">';
                        } else {
                            echo '<div style="width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center;">👨‍🏫</div>';
                        }

                        echo '<div style="flex: 1;">';
                        echo '<div style="font-weight: bold; margin-bottom: 5px;">' . $teacherName . '</div>';
                        echo '<div style="color: #666; font-size: 0.9em; margin-bottom: 8px;">' . Format::dateTime($comment['timestamp']) . '</div>';
                        echo '<div style="line-height: 1.5;">' . nl2br(htmlspecialchars($comment['comment'])) . '</div>';
                        echo '</div>';
                        echo '</div>';
                    }

                    echo '</div>';
                }
            } catch (Exception $e) {
                // Table might not exist yet, silently continue
            }

            // Add new comment form
            echo '<form method="POST" action="">';
            echo '<div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">';
            echo '<h4 style="margin-top: 0;">' . __('Add Feedback') . '</h4>';
            echo '<p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">' . __('Provide constructive feedback on this student\'s AI tutor conversation. This can help guide their learning and identify areas for improvement.') . '</p>';

            echo '<textarea name="teacherComment"
                          style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 14px; resize: vertical;"
                          placeholder="' . __('Enter your feedback here...') . '"
                          required></textarea>';

            echo '<div style="margin-top: 15px; text-align: right;">';
            echo '<button type="submit"
                          style="background: #667eea; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500;"
                          onmouseover="this.style.background=\'#5568d3\'"
                          onmouseout="this.style.background=\'#667eea\'">';
            echo __('Add Feedback');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
        }
    }
}

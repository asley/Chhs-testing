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
        // Get session details
        $sql = "SELECT s.*, p.surname, p.preferredName, p.gibbonPersonID
                FROM aiTeacherChatSessions s
                JOIN gibbonPerson p ON s.gibbonPersonID = p.gibbonPersonID
                WHERE s.sessionID = :sessionID";
        $result = $pdo->executeQuery(['sessionID' => $sessionID], $sql);

        if ($result->rowCount() === 0) {
            $page->addError(__('Session not found.'));
        } else {
            $session = $result->fetch();
            $studentName = Format::name('', $session['preferredName'], $session['surname'], 'Student', true);

            echo '<div class="linkTop">';
            echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/teacher_student_usage.php">';
            echo __('Back to Usage Monitor');
            echo '</a>';
            echo '</div>';

            echo '<h2>' . __('Student Conversation') . '</h2>';

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
            if (!empty($session['topic'])) {
                echo '<tr>';
                echo '<td style="font-weight: bold;">' . __('Topic') . '</td>';
                echo '<td>' . htmlspecialchars($session['topic']) . '</td>';
                echo '</tr>';
            }
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
                    $avatar = $isStudent ? '👤' : '🤖';
                    $isFlagged = isset($msg['flagged']) && $msg['flagged'] == 1;

                    echo '<div class="' . $messageClass . '" style="margin-bottom: 15px; ' . ($isFlagged ? 'border: 2px solid red; background: #ffe6e6;' : '') . '">';

                    if (!$isStudent) {
                        echo '<div class="message-avatar">' . $avatar . '</div>';
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
                        echo '<div class="message-avatar">' . $avatar . '</div>';
                    }

                    echo '</div>';
                }

                echo '</div>';
            } else {
                echo '<div class="warning">';
                echo __('No messages found in this conversation.');
                echo '</div>';
            }

            // Teacher notes section
            echo '<h3 style="margin-top: 30px;">' . __('Teacher Notes') . '</h3>';
            echo '<div class="linkTop">';
            echo '<p><em>' . __('This section could be used for adding teacher observations or follow-up actions.') . '</em></p>';
            echo '</div>';
        }
    }
}

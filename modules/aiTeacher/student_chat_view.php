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

$page->breadcrumbs
    ->add(__('AI Tutor Chat'), 'student_ai_tutor.php')
    ->add(__('Chat History'), 'student_chat_history.php')
    ->add(__('View Conversation'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/student_ai_tutor.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get current user
    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
    $sessionID = $_GET['sessionID'] ?? '';

    if (empty($sessionID)) {
        $page->addError(__('Invalid session ID.'));
    } else {
        // Verify session belongs to this user
        $sql = "SELECT * FROM aiTeacherChatSessions
                WHERE sessionID = :sessionID AND gibbonPersonID = :personID";
        $result = $pdo->executeQuery([
            'sessionID' => $sessionID,
            'personID' => $gibbonPersonID
        ], $sql);

        if ($result->rowCount() === 0) {
            $page->addError(__('You do not have access to this conversation.'));
        } else {
            $session = $result->fetch();

            echo '<div class="linkTop">';
            echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/student_chat_history.php">';
            echo __('Back to Chat History');
            echo '</a>';
            echo '</div>';

            echo '<h2>' . __('Conversation Details') . '</h2>';

            // Session info
            echo '<table class="smallIntBorder fullWidth" cellspacing="0">';
            echo '<tr>';
            echo '<td style="width: 20%; font-weight: bold;">' . __('Started') . '</td>';
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

            echo '<h3>' . __('Conversation') . '</h3>';

            // Add CSS for chat display
            echo '<link rel="stylesheet" type="text/css" href="' . $gibbon->session->get('absoluteURL') . '/modules/aiTeacher/css/student_tutor.css">';

            // Get all messages for this session
            $messages = getConversationContext($pdo, $sessionID, 1000);

            if (count($messages) > 0) {
                echo '<div class="ai-tutor-messages" style="max-height: none; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">';

                foreach ($messages as $msg) {
                    $isStudent = ($msg['sender'] === 'student');
                    $messageClass = $isStudent ? 'student-message' : 'ai-message';
                    $avatar = $isStudent ? '👤' : '🤖';

                    echo '<div class="' . $messageClass . '" style="margin-bottom: 15px;">';
                    if (!$isStudent) {
                        echo '<div class="message-avatar">' . $avatar . '</div>';
                    }
                    echo '<div class="message-bubble">';
                    echo '<p>' . nl2br(htmlspecialchars($msg['message'])) . '</p>';
                    echo '<span class="message-time" style="font-size: 0.8em; color: #666;">';
                    echo date('g:i A', strtotime($msg['timestamp']));
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

            echo '<p style="margin-top: 20px;">';
            echo '<a class="button" href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/student_ai_tutor.php&sessionID=' . $sessionID . '">';
            echo __('Continue This Conversation');
            echo '</a>';
            echo '</p>';
        }
    }
}

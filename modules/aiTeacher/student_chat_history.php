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

$page->breadcrumbs
    ->add(__('AI Tutor Chat'), 'student_ai_tutor.php')
    ->add(__('Chat History'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/student_ai_tutor.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get current user
    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
    $gibbonSchoolYearID = $gibbon->session->get('gibbonSchoolYearID');

    echo '<div class="linkTop">';
    echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/student_ai_tutor.php">';
    echo __('Back to AI Tutor');
    echo '</a>';
    echo '</div>';

    echo '<h2>' . __('Your Chat History') . '</h2>';
    echo '<p>' . __('View your previous conversations with the AI tutor.') . '</p>';

    // Get all chat sessions for this student
    try {
        $sql = "SELECT
                    s.sessionID,
                    s.startTime,
                    s.lastActivity,
                    s.topic,
                    s.subject,
                    s.messageCount,
                    s.resolved,
                    (SELECT COUNT(*) FROM aiTeacherStudentConversations
                     WHERE sessionID = s.sessionID AND sender = 'student') as studentMessages,
                    (SELECT COUNT(*) FROM aiTeacherStudentConversations
                     WHERE sessionID = s.sessionID AND sender = 'ai') as aiMessages
                FROM aiTeacherChatSessions s
                WHERE s.gibbonPersonID = :personID
                ORDER BY s.lastActivity DESC
                LIMIT 50";

        $result = $pdo->executeQuery(['personID' => $gibbonPersonID], $sql);

        if ($result->rowCount() > 0) {
            echo '<table class="fullWidth colorOddEven" cellspacing="0">';
            echo '<thead>';
            echo '<tr class="head">';
            echo '<th>' . __('Date') . '</th>';
            echo '<th>' . __('Last Activity') . '</th>';
            echo '<th>' . __('Messages') . '</th>';
            echo '<th>' . __('Topic') . '</th>';
            echo '<th>' . __('Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            while ($row = $result->fetch()) {
                $sessionID = $row['sessionID'];
                $startTime = dateConvertBack($guid, date('Y-m-d', strtotime($row['startTime'])));
                $lastActivity = dateConvertBack($guid, date('Y-m-d H:i', strtotime($row['lastActivity'])));
                $messageCount = $row['messageCount'];
                $topic = $row['topic'] ?? __('General Discussion');

                echo '<tr>';
                echo '<td>' . $startTime . '</td>';
                echo '<td>' . $lastActivity . '</td>';
                echo '<td>' . $messageCount . ' ' . __('messages') . '</td>';
                echo '<td>' . htmlspecialchars($topic) . '</td>';
                echo '<td>';
                echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/student_chat_view.php&sessionID=' . $sessionID . '">';
                echo __('View Conversation');
                echo '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<div class="warning">';
            echo __('You have no chat history yet. Start a conversation with the AI tutor!');
            echo '</div>';

            echo '<p>';
            echo '<a class="button" href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/student_ai_tutor.php">';
            echo __('Start Chatting');
            echo '</a>';
            echo '</p>';
        }

    } catch (Exception $e) {
        echo '<div class="error">';
        echo __('An error occurred while loading your chat history.');
        echo '</div>';
        error_log("Error in student_chat_history: " . $e->getMessage());
    }
}

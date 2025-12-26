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
    echo '<p>' . __('View your previous conversations with the AI tutor. Click the pencil icon to rename a conversation.') . '</p>';

    // Add CSS for editing
    echo '<style>
        .topic-display { display: flex; align-items: center; gap: 10px; }
        .topic-text { flex: 1; }
        .topic-edit-btn {
            cursor: pointer;
            color: #667eea;
            font-size: 14px;
            padding: 4px 8px;
            border: 1px solid #667eea;
            border-radius: 4px;
            background: white;
            transition: all 0.2s;
        }
        .topic-edit-btn:hover {
            background: #667eea;
            color: white;
        }
        .topic-input {
            padding: 6px;
            border: 1px solid #667eea;
            border-radius: 4px;
            width: 100%;
            max-width: 400px;
        }
        .topic-save-btn, .topic-cancel-btn {
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin-left: 5px;
        }
        .topic-save-btn {
            background: #10b981;
            color: white;
            border: none;
        }
        .topic-cancel-btn {
            background: #ef4444;
            color: white;
            border: none;
        }
    </style>';

    // Add JavaScript for inline editing
    echo '<script>
    function editTopic(sessionID, currentTopic) {
        const displayDiv = document.getElementById("topic-display-" + sessionID);
        const editDiv = document.getElementById("topic-edit-" + sessionID);

        displayDiv.style.display = "none";
        editDiv.style.display = "flex";

        const input = document.getElementById("topic-input-" + sessionID);
        input.value = currentTopic;
        input.focus();
        input.select();
    }

    function cancelEdit(sessionID) {
        const displayDiv = document.getElementById("topic-display-" + sessionID);
        const editDiv = document.getElementById("topic-edit-" + sessionID);

        displayDiv.style.display = "flex";
        editDiv.style.display = "none";
    }

    async function saveTopic(sessionID) {
        const input = document.getElementById("topic-input-" + sessionID);
        const newTopic = input.value.trim();

        if (!newTopic) {
            alert("Topic cannot be empty");
            return;
        }

        const absoluteURL = "' . $gibbon->session->get('absoluteURL') . '";

        try {
            const response = await fetch(absoluteURL + "/modules/aiTeacher/student_ai_tutor_ajax.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    action: "updateTopic",
                    sessionID: sessionID,
                    topic: newTopic
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update the display
                const topicText = document.getElementById("topic-text-" + sessionID);
                topicText.textContent = newTopic;

                // Switch back to display mode
                cancelEdit(sessionID);
            } else {
                alert("Failed to update topic: " + (data.error || "Unknown error"));
            }
        } catch (error) {
            console.error("Error updating topic:", error);
            alert("Failed to update topic. Please try again.");
        }
    }
    </script>';

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
                $startTime = Format::date($row['startTime']);
                $lastActivity = Format::dateTime($row['lastActivity']);
                $messageCount = $row['messageCount'];
                $topic = $row['topic'] ?? __('General Discussion');

                echo '<tr>';
                echo '<td>' . $startTime . '</td>';
                echo '<td>' . $lastActivity . '</td>';
                echo '<td>' . $messageCount . ' ' . __('messages') . '</td>';
                echo '<td>';

                // Display mode
                echo '<div class="topic-display" id="topic-display-' . $sessionID . '">';
                echo '<span class="topic-text" id="topic-text-' . $sessionID . '">' . htmlspecialchars($topic) . '</span>';
                echo '<button class="topic-edit-btn" onclick="editTopic(\'' . $sessionID . '\', \'' . htmlspecialchars($topic, ENT_QUOTES) . '\')">✏️ Edit</button>';
                echo '</div>';

                // Edit mode (hidden by default)
                echo '<div class="topic-display" id="topic-edit-' . $sessionID . '" style="display: none;">';
                echo '<input type="text" class="topic-input" id="topic-input-' . $sessionID . '" maxlength="100" />';
                echo '<button class="topic-save-btn" onclick="saveTopic(\'' . $sessionID . '\')">✓ Save</button>';
                echo '<button class="topic-cancel-btn" onclick="cancelEdit(\'' . $sessionID . '\')">✕ Cancel</button>';
                echo '</div>';

                echo '</td>';
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

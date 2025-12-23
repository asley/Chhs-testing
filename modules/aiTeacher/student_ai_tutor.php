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

$page->breadcrumbs->add(__('AI Tutor Chat'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/student_ai_tutor.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get current user
    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
    $gibbonSchoolYearID = $gibbon->session->get('gibbonSchoolYearID');

    // Check if resuming an existing session
    $sessionID = $_GET['sessionID'] ?? null;

    // If sessionID provided, verify it belongs to this user
    if ($sessionID) {
        $sql = "SELECT sessionID FROM aiTeacherChatSessions
                WHERE sessionID = :sessionID AND gibbonPersonID = :personID";
        $result = $pdo->executeQuery(['sessionID' => $sessionID, 'personID' => $gibbonPersonID], $sql);

        if ($result->rowCount() === 0) {
            // Invalid session, create new one
            $sessionID = getOrCreateChatSession($pdo, $gibbonPersonID, $gibbonSchoolYearID);
        }
    } else {
        // Get or create chat session
        $sessionID = getOrCreateChatSession($pdo, $gibbonPersonID, $gibbonSchoolYearID);
    }

    // Get AI settings to check if configured
    $settings = getAITeacherSettings($pdo);

    if (empty($settings['deepseek_api_key'])) {
        echo '<div class="error">';
        echo __('AI Tutor is not configured. Please contact your administrator.');
        echo '</div>';
        return;
    }

    // Add CSS and JavaScript
    echo '<link rel="stylesheet" type="text/css" href="' . $gibbon->session->get('absoluteURL') . '/modules/aiTeacher/css/student_tutor.css">';
    echo '<script src="' . $gibbon->session->get('absoluteURL') . '/modules/aiTeacher/js/student_tutor.js"></script>';

    // Get conversation history for current session
    $context = getConversationContext($pdo, $sessionID, 50);

    echo '<div class="ai-tutor-container">';

    // Header
    echo '<div class="ai-tutor-header">';
    echo '<h2>' . __('AI Tutor') . '</h2>';
    echo '<p class="text-xs opacity-75">' . __('Ask me anything about your coursework. I\'m here to help you learn!') . '</p>';
    echo '<div class="ai-tutor-actions">';
    echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/student_chat_history.php" class="button-link">' . __('View History') . '</a>';
    echo '<button onclick="clearChat()" class="button-link secondary">' . __('New Chat') . '</button>';
    echo '</div>';
    echo '</div>';

    // Chat messages area
    echo '<div class="ai-tutor-messages" id="chatMessages">';

    if (empty($context)) {
        // Welcome message
        echo '<div class="ai-message">';
        echo '<div class="message-avatar">🤖</div>';
        echo '<div class="message-bubble">';
        echo '<p>' . __('Hi! I\'m your AI tutor. I can help you understand concepts, guide you through problems, and support your learning journey.') . '</p>';
        echo '<p class="text-sm mt-2">' . __('What would you like to learn about today?') . '</p>';
        echo '</div>';
        echo '</div>';
    } else {
        // Load previous messages
        foreach ($context as $msg) {
            $isStudent = ($msg['sender'] === 'student');
            $messageClass = $isStudent ? 'student-message' : 'ai-message';
            $avatar = $isStudent ? '👤' : '🤖';

            echo '<div class="' . $messageClass . '">';
            if (!$isStudent) {
                echo '<div class="message-avatar">' . $avatar . '</div>';
            }
            echo '<div class="message-bubble">';
            echo '<p>' . nl2br(htmlspecialchars($msg['message'])) . '</p>';
            echo '<span class="message-time">' . date('g:i A', strtotime($msg['timestamp'])) . '</span>';
            echo '</div>';
            if ($isStudent) {
                echo '<div class="message-avatar">' . $avatar . '</div>';
            }
            echo '</div>';
        }
    }

    echo '</div>';

    // Typing indicator (hidden by default)
    echo '<div class="ai-typing-indicator" id="typingIndicator" style="display: none;">';
    echo '<div class="message-avatar">🤖</div>';
    echo '<div class="typing-dots">';
    echo '<span></span><span></span><span></span>';
    echo '</div>';
    echo '</div>';

    // Input area
    echo '<div class="ai-tutor-input">';
    echo '<form id="chatForm" onsubmit="return sendMessage(event);">';
    echo '<input type="hidden" id="sessionID" value="' . htmlspecialchars($sessionID) . '">';
    echo '<input type="hidden" id="gibbonPersonID" value="' . htmlspecialchars($gibbonPersonID) . '">';
    echo '<input type="hidden" id="gibbonSchoolYearID" value="' . htmlspecialchars($gibbonSchoolYearID) . '">';
    echo '<input type="hidden" id="absoluteURL" value="' . $gibbon->session->get('absoluteURL') . '">';

    echo '<div class="input-wrapper">';
    echo '<textarea id="messageInput"
                    name="message"
                    placeholder="' . __('Ask your question here...') . '"
                    rows="1"
                    maxlength="500"
                    onkeydown="handleKeyPress(event)"
                    oninput="autoResize(this)"></textarea>';
    echo '<button type="submit" id="sendButton" class="send-button">';
    echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
    echo '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>';
    echo '</svg>';
    echo '</button>';
    echo '</div>';

    echo '<div class="input-footer">';
    echo '<span class="char-count"><span id="charCount">0</span>/500</span>';
    echo '<span class="help-text">' . __('Press Enter to send, Shift+Enter for new line') . '</span>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // Rating and feedback area (shown after AI response)
    echo '<div class="message-feedback" id="messageFeedback" style="display: none;">';
    echo '<p class="text-sm">' . __('Was this response helpful?') . '</p>';
    echo '<div class="feedback-buttons">';
    echo '<button onclick="rateResponse(\'helpful\')" class="feedback-btn helpful">👍 ' . __('Helpful') . '</button>';
    echo '<button onclick="rateResponse(\'not_helpful\')" class="feedback-btn not-helpful">👎 ' . __('Not Helpful') . '</button>';
    echo '<button onclick="flagMessage()" class="feedback-btn flag">🚩 ' . __('Flag for Teacher') . '</button>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End container

    // Initialize JavaScript
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo '  scrollToBottom();';
    echo '  document.getElementById("messageInput").focus();';
    echo '});';
    echo '</script>';
}

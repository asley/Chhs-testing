/**
 * Student AI Tutor Chat - JavaScript
 * Handles chat interactions, message sending, and UI updates
 */

// Global variables
let isTyping = false;
let currentSessionID = null;

/**
 * Send message to AI tutor
 */
async function sendMessage(event) {
    event.preventDefault();

    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();

    if (!message || isTyping) {
        return false;
    }

    // Get session data
    const sessionID = document.getElementById('sessionID').value;
    const gibbonPersonID = document.getElementById('gibbonPersonID').value;
    const gibbonSchoolYearID = document.getElementById('gibbonSchoolYearID').value;
    const absoluteURL = document.getElementById('absoluteURL').value;

    // Add student message to chat
    addMessageToChat(message, 'student');

    // Clear input
    messageInput.value = '';
    updateCharCount();
    autoResize(messageInput);

    // Show typing indicator
    showTypingIndicator();

    try {
        // Send to server
        const response = await fetch(absoluteURL + '/modules/aiTeacher/student_ai_tutor_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'sendMessage',
                message: message,
                sessionID: sessionID,
                gibbonPersonID: gibbonPersonID,
                gibbonSchoolYearID: gibbonSchoolYearID
            })
        });

        const data = await response.json();

        // Hide typing indicator
        hideTypingIndicator();

        if (data.success) {
            // Add AI response to chat (use HTML if available)
            addMessageToChat(data.response, 'ai', data.responseHtml);

            // Re-render math equations
            if (window.MathJax) {
                MathJax.typesetPromise().catch((err) => console.error('MathJax error:', err));
            }

            // Show feedback buttons
            showFeedbackButtons();

            // Check if message was flagged
            if (data.flagged) {
                showFlagNotification(data.flagReason);
            }
        } else {
            // Show error
            addErrorMessage(data.error || 'An error occurred. Please try again.');
        }

    } catch (error) {
        console.error('Error sending message:', error);
        hideTypingIndicator();
        addErrorMessage('Failed to send message. Please check your connection.');
    }

    return false;
}

/**
 * Add message to chat UI
 * @param {string} message - The plain text message
 * @param {string} sender - 'student' or 'ai'
 * @param {string} messageHtml - Optional pre-rendered HTML for AI messages
 */
function addMessageToChat(message, sender, messageHtml = null) {
    const messagesContainer = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = sender === 'student' ? 'student-message' : 'ai-message';

    const avatar = sender === 'student' ? '👤' : '🤖';
    const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    let html = '';
    if (sender === 'ai') {
        html += `<div class="message-avatar">${avatar}</div>`;
    }
    html += `<div class="message-bubble">`;

    // Use pre-rendered HTML for AI messages if available, otherwise escape
    if (sender === 'ai' && messageHtml) {
        html += messageHtml;
    } else {
        html += `<p>${escapeHtml(message).replace(/\n/g, '<br>')}</p>`;
    }

    html += `<span class="message-time">${time}</span>`;
    html += `</div>`;
    if (sender === 'student') {
        html += `<div class="message-avatar">${avatar}</div>`;
    }

    messageDiv.innerHTML = html;
    messagesContainer.appendChild(messageDiv);

    scrollToBottom();
}

/**
 * Add error message to chat
 */
function addErrorMessage(message) {
    const messagesContainer = document.getElementById('chatMessages');
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `
        <div class="message-bubble error">
            <p>⚠️ ${escapeHtml(message)}</p>
        </div>
    `;
    messagesContainer.appendChild(errorDiv);
    scrollToBottom();
}

/**
 * Show typing indicator
 */
function showTypingIndicator() {
    isTyping = true;
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.style.display = 'flex';
        scrollToBottom();
    }
    document.getElementById('sendButton').disabled = true;
}

/**
 * Hide typing indicator
 */
function hideTypingIndicator() {
    isTyping = false;
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    document.getElementById('sendButton').disabled = false;
}

/**
 * Show feedback buttons after AI response
 */
function showFeedbackButtons() {
    const feedbackDiv = document.getElementById('messageFeedback');
    if (feedbackDiv) {
        feedbackDiv.style.display = 'block';
        setTimeout(() => {
            feedbackDiv.style.display = 'none';
        }, 10000); // Hide after 10 seconds
    }
}

/**
 * Rate AI response
 */
async function rateResponse(rating) {
    const sessionID = document.getElementById('sessionID').value;
    const absoluteURL = document.getElementById('absoluteURL').value;

    try {
        const response = await fetch(absoluteURL + '/modules/aiTeacher/student_ai_tutor_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'rateResponse',
                rating: rating,
                sessionID: sessionID
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            document.getElementById('messageFeedback').style.display = 'none';
        }
    } catch (error) {
        console.error('Error rating response:', error);
    }
}

/**
 * Flag message for teacher review
 */
async function flagMessage() {
    const reason = prompt('Please tell us why you\'re flagging this response (optional):');

    if (reason === null) {
        return; // User cancelled
    }

    const sessionID = document.getElementById('sessionID').value;
    const absoluteURL = document.getElementById('absoluteURL').value;

    try {
        const response = await fetch(absoluteURL + '/modules/aiTeacher/student_ai_tutor_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'flagMessage',
                reason: reason || 'No reason provided',
                sessionID: sessionID
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message, 'success');
            document.getElementById('messageFeedback').style.display = 'none';
        }
    } catch (error) {
        console.error('Error flagging message:', error);
        showNotification('Failed to flag message. Please try again.', 'error');
    }
}

/**
 * Clear chat and start new session
 */
async function clearChat() {
    if (!confirm('Start a new chat? Your current conversation will be saved in history.')) {
        return;
    }

    const absoluteURL = document.getElementById('absoluteURL').value;

    try {
        const response = await fetch(absoluteURL + '/modules/aiTeacher/student_ai_tutor_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'newChat'
            })
        });

        const data = await response.json();

        if (data.success) {
            // Update session ID
            document.getElementById('sessionID').value = data.sessionID;

            // Clear messages
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.innerHTML = `
                <div class="ai-message">
                    <div class="message-avatar">🤖</div>
                    <div class="message-bubble">
                        <p>Hi! I'm your AI tutor. I can help you understand concepts, guide you through problems, and support your learning journey.</p>
                        <p class="text-sm mt-2">What would you like to learn about today?</p>
                    </div>
                </div>
            `;

            showNotification('New chat started!', 'success');
        }
    } catch (error) {
        console.error('Error creating new chat:', error);
        showNotification('Failed to create new chat. Please refresh the page.', 'error');
    }
}

/**
 * Handle keyboard shortcuts
 */
function handleKeyPress(event) {
    // Enter without Shift = Send message
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage(event);
        return false;
    }
}

/**
 * Auto-resize textarea as user types
 */
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
    updateCharCount();
}

/**
 * Update character count
 */
function updateCharCount() {
    const messageInput = document.getElementById('messageInput');
    const charCount = document.getElementById('charCount');
    if (messageInput && charCount) {
        charCount.textContent = messageInput.value.length;

        // Warn if approaching limit
        if (messageInput.value.length > 450) {
            charCount.parentElement.classList.add('warning');
        } else {
            charCount.parentElement.classList.remove('warning');
        }
    }
}

/**
 * Scroll chat to bottom
 */
function scrollToBottom() {
    const messagesContainer = document.getElementById('chatMessages');
    if (messagesContainer) {
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 100);
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 6px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

/**
 * Show flag notification
 */
function showFlagNotification(reason) {
    let message = '';
    switch (reason) {
        case 'self_harm':
            message = 'If you\'re going through a difficult time, please speak with a teacher or counselor.';
            break;
        case 'cheating_attempt':
            message = 'Remember, I\'m here to help you learn, not to do your work for you.';
            break;
        case 'profanity':
            message = 'Please keep our conversation respectful.';
            break;
        default:
            message = 'This message has been flagged for review.';
    }
    showNotification(message, 'warning');
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add CSS animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

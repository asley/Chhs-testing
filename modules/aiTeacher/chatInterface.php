<?php
// Ensure this file is being included from chatbot.php and has access to necessary variables
if (!isset($pdo) || !isset($gibbonPersonID) || !isset($page) || !isset($gibbon) || !isset($settings)) {
    die('This file cannot be accessed directly or essential variables are missing.');
}

// Fetch user's chats for the sidebar
$userChats = [];
try {
    $stmt = $pdo->executeQuery(
        ['gibbonPersonID' => $gibbonPersonID],
        "SELECT aiTeacherChatID, title FROM aiTeacherChats WHERE gibbonPersonID = :gibbonPersonID ORDER BY updated_at DESC"
    );
    $userChats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $page->addError(__('Error fetching chat list: ') . $e->getMessage());
}

// Fetch messages for the current chat if a chat is selected
$chatMessages = [];
// $currentChatID is expected to be set in chatbot.php
if (!empty($currentChatID)) {
    try {
        $stmt = $pdo->executeQuery(
            ['currentChatID' => $currentChatID],
            "SELECT role, content, timestamp FROM aiTeacherChatMessages WHERE aiTeacherChatID = :currentChatID ORDER BY timestamp ASC"
        );
        $chatMessages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $page->addError(__('Error fetching messages: ') . $e->getMessage());
    }
}

$formActionURL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/chatbot.php';
if ($currentChatID) {
    $formActionURL .= '&chat_id=' . $currentChatID;
}

// Determine if AI is configured for initial message
$aiConfigured = !empty($settings['deepseek_api_key']); // Or your chosen API key setting name

// Function to format chat messages (Markdown table, bold, italic support)
function formatChatMessageContent($content) {
    $html = '';
    $lines = explode("\n", $content);
    $inTable = false;
    $tableHeaderProcessed = false;

    // Placeholders for safe markdown conversion
    $uid = uniqid('ph_'); // Unique prefix for placeholders
    $placeholders = [
        'bold_start' => $uid . 'B_S', 'bold_end' => $uid . 'B_E',
        'italic_start' => $uid . 'I_S', 'italic_end' => $uid . 'I_E',
    ];
    $htmlTags = [
        'bold_start' => '<strong>', 'bold_end' => '</strong>',
        'italic_start' => '<em>', 'italic_end' => '</em>',
    ];

    // Helper function to apply markdown to placeholders, then escape, then convert placeholders to HTML
    $formatTextWithMarkdown = function($text) use ($placeholders, $htmlTags) {
        // 1. Convert markdown (**, *) to unique placeholders
        $processedText = $text;
        $processedText = preg_replace('/\*\*(.*?)\*\*/s', $placeholders['bold_start'] . '$1' . $placeholders['bold_end'], $processedText);
        // Regex for italics: ensure it's not part of a bold marker or another italic marker
        $processedText = preg_replace('/(?<!\*)\*(?!\*)([^\s*](?:.*?[^\s*])?)(?<!\*)\*(?!\*)/s', $placeholders['italic_start'] . '$1' . $placeholders['italic_end'], $processedText);

        // 2. Escape HTML special characters from the text that now contains placeholders
        $escapedText = htmlspecialchars($processedText, ENT_QUOTES, 'UTF-8');

        // 3. Convert escaped placeholders back to actual HTML tags (<strong>, <em>)
        $finalHtml = $escapedText;
        foreach ($placeholders as $key => $placeholderValue) {
            // The placeholderValue itself needs to be escaped as it was in $escapedText
            $finalHtml = str_replace(htmlspecialchars($placeholderValue, ENT_QUOTES, 'UTF-8'), $htmlTags[$key], $finalHtml);
        }
        return $finalHtml;
    };


    foreach ($lines as $line) {
        // Check for Markdown table row: | ... |
        if (preg_match('/^\s*\|(.+)\|\s*$/', $line, $matches)) {
            if (!$inTable) {
                $html .= "<table class=\"chat-markdown-table\">";
                $inTable = true;
                $tableHeaderProcessed = false; // Reset for each new table
            }

            $rowData = $matches[1];
            // Check for table header separator: |---|---| or |:---|:---:|
            if (preg_match('/^(\s*[:-]-+[:-]\s*\|)+(\s*[:-]-+[:-]\s*)?$/', $rowData)) {
                if (!$tableHeaderProcessed && strpos($html, "<thead>") !== false && strpos($html, "</thead>") === false) {
                    // If previous row was <th>, it's already in <thead> implicitly or explicitly.
                    // Now we close <thead> and open <tbody>.
                    // Ensure the last <tr> was correctly wrapped in <thead> if it was the first.
                    // This logic assumes the row *before* this separator was the header.
                    // A simple way: if <thead> is open, close it and open <tbody>.
                     $html = str_replace("<tr>", "<thead><tr>", $html); // Ensure thead if not already
                     $html = str_replace("</tr>", "</tr></thead><tbody>", $html);
                     $tableHeaderProcessed = true; // Mark header as fully processed (including separator)
                }
                continue; // Don't render the separator line itself
            }

            $cells = explode('|', $rowData);
            $tag = ($inTable && !$tableHeaderProcessed) ? 'th' : 'td'; // First content row of the table is header

            if ($tag === 'th' && strpos($html, "<thead>") === false) { // Ensure we are in thead for header rows
                $html .= "<thead>";
            } elseif ($tag === 'td' && $tableHeaderProcessed && strpos($html, "<tbody>") === false && strpos($html, "</thead>") !== false) {
                 // This case: header separator was processed, <thead> is closed, now starting <td> rows.
                 // Ensure <tbody> is opened.
                 $html .= "<tbody>";
            }


            $html .= "<tr>";
            foreach ($cells as $cell) {
                $cellContent = trim($cell);
                $html .= "<{$tag}>" . $formatTextWithMarkdown($cellContent) . "</{$tag}>";
            }
            $html .= "</tr>";

        } else { // Not a table line
            if ($inTable) {
                // End of table
                if (strpos($html, "<thead>") !== false && strpos($html, "</thead>") === false) $html .= "</thead>";
                if (strpos($html, "<tbody>") !== false && strpos($html, "</tbody>") === false) $html .= "</tbody>";
                else if (strpos($html, "<thead>") === false && strpos($html, "<tbody>") === false && $inTable) { // Table without explicit header
                     // If table started but no thead/tbody, assume all rows were data
                     $html = str_replace("<table class=\"chat-markdown-table\">", "<table class=\"chat-markdown-table\"><tbody>", $html);
                     $html .= "</tbody>";
                }
                $html .= "</table>";
                $inTable = false;
                $tableHeaderProcessed = false;
            }
            
            $html .= nl2br($formatTextWithMarkdown($line), false) . "\n";
        }
    }
    // If content ends with an open table
    if ($inTable) {
        if (strpos($html, "<thead>") !== false && strpos($html, "</thead>") === false) $html .= "</thead>";
        if (strpos($html, "<tbody>") !== false && strpos($html, "</tbody>") === false) $html .= "</tbody>";
        else if (strpos($html, "<thead>") === false && strpos($html, "<tbody>") === false && $inTable) {
             $html = str_replace("<table class=\"chat-markdown-table\">", "<table class=\"chat-markdown-table\"><tbody>", $html);
             $html .= "</tbody>";
        }
        $html .= "</table>";
    }
    
    return rtrim($html, "\n"); // Remove trailing newline if any from the last nl2br
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    .chat-page-container {
        display: flex;
        height: calc(100vh - 200px); /* Adjust height considering Gibbon header/footer */
        font-family: Arial, sans-serif;
        background-color: #f0f2f5; /* Light background for the whole page */
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden; /* To contain rounded corners */
    }

    .chat-sidebar {
        width: 280px; /* Slightly wider sidebar */
        border-right: 1px solid #d1d7dc; /* Softer border color */
        background-color: #ffffff; /* White sidebar */
        padding: 0;
        display: flex;
        flex-direction: column;
    }
    .sidebar-header {
        padding: 15px;
        border-bottom: 1px solid #d1d7dc;
        display: flex; /* Use flexbox for layout */
        flex-direction: column; /* Stack items vertically */
    }
    .sidebar-header h3 {
        margin: 0 0 15px 0; /* Increased bottom margin */
        font-size: 1.2em;
        color: #333;
        text-align: left; /* Ensure "MY CHATS" is left-aligned */
    }

    .new-chat-form { /* Container for button and input */
        display: flex;
        flex-direction: column; /* Stack button and input */
        width: 100%;
    }

    .new-chat-form button { /* Style for the "+ New" button */
        padding: 10px 15px; /* Larger padding */
        border-radius: 6px; /* Slightly more rounded */
        background-color: #007bff;
        color: white;
        border: none;
        cursor: pointer;
        font-size: 1em; /* Adjust font size as needed */
        text-align: center; /* Center icon and text */
        width: 100%; /* Make button full width */
        margin-bottom: 10px; /* Space below the button */
    }
    .new-chat-form button:hover {
        background-color: #0056b3;
    }
    .new-chat-form button i {
        margin-right: 8px; /* Space between icon and text */
    }

    .new-chat-form input[type="text"] {
        width: 100%; /* Full width */
        padding: 10px; /* Consistent padding */
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box; /* Include padding and border in the element's total width and height */
    }

    .chat-list {
        list-style: none;
        padding: 0;
        margin: 0;
        overflow-y: auto;
        flex-grow: 1;
    }
    .chat-list-item {
        padding: 12px 15px;
        border-bottom: 1px solid #e0e0e0;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .chat-list-item:hover, .chat-list-item.active {
        background-color: #e9ecef; /* Light hover/active state */
    }
    .chat-list-item a {
        text-decoration: none;
        color: #333;
        flex-grow: 1;
    }
    .chat-list-item .delete-chat-form button {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        font-size: 0.9em;
        padding: 5px;
    }
    .chat-list-item .delete-chat-form button:hover {
        color: #a71d2a;
    }


    .chat-main-interface {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        background-color: #f4f7f6; /* Light grey background for the chat area */
    }

    .chat-header {
        padding: 15px 20px;
        background-color: #ffffff;
        border-bottom: 1px solid #d1d7dc;
        font-size: 1.1em;
        font-weight: bold;
        color: #333;
    }
     .chat-header .current-chat-title {
        font-weight: normal;
        color: #555;
    }


    .chat-messages-area {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px; /* Space between messages */
    }

    .chat-message {
        display: flex;
        gap: 10px;
        max-width: 75%; /* Slightly wider messages */
        align-items: flex-start; /* Align avatar with top of message */
    }

    .ai-message {
        align-self: flex-start; /* AI messages on the left */
    }
    .ai-message .message-content-wrapper {
        background-color: #ffffff; /* White background for AI message bubbles */
        border: 1px solid #e0e0e0; /* Subtle border for AI messages */
    }


    .user-message {
        align-self: flex-end; /* User messages on the right */
    }
    .user-message .message-content-wrapper {
        background-color: #dcf8c6; /* Light green for user messages */
    }


    .message-avatar .icon-ai, .message-avatar .icon-user {
        font-size: 18px; /* Smaller avatar icon */
        color: #fff;
        border-radius: 50%;
        padding: 8px; /* Adjust padding for size */
        display: inline-flex; /* Use flex for centering */
        align-items: center;
        justify-content: center;
        width: 36px; /* Fixed width */
        height: 36px; /* Fixed height */
        line-height: 1;
    }
    .message-avatar .icon-ai {
        background-color: #007bff; /* Blue for AI */
    }
    .message-avatar .icon-user {
        background-color: #28a745; /* Green for User */
    }
    
    .message-content-wrapper {
        display: flex;
        flex-direction: column;
        padding: 10px 15px;
        border-radius: 12px; /* More rounded corners */
        box-shadow: 0 1px 2px rgba(0,0,0,0.05); /* Softer shadow */
    }

    .message-header { /* Not used in the image, but can be for sender name if needed */
        font-weight: bold;
        color: #007bff; 
        margin-bottom: 5px;
        font-size: 0.9em;
    }

    .message-text {
        line-height: 1.6; /* Improved readability */
        color: #333;
        font-size: 0.95em;
        white-space: pre-wrap; /* Preserve line breaks from AI */
    }
    .message-text ul {
        list-style-position: inside;
        padding-left: 5px; /* Indent list items slightly */
        margin-top: 5px;
        margin-bottom: 5px;
    }
    .message-text li {
        padding-bottom: 4px;
    }


    .message-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
        font-size: 0.7em; /* Smaller timestamp */
        color: #6c757d; /* Grey for timestamp */
    }
    
    .message-actions { /* For thumbs up/down, copy, etc. */
        display: flex;
        gap: 8px;
    }
    .message-actions .action-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1em; 
        color: #6c757d;
    }
    .message-actions .action-btn:hover {
        color: #333;
    }

    /* Initial message styling */
    .initial-ai-message-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 30px;
        margin: auto; /* Center it in the messages area if no messages */
        max-width: 600px;
    }
    .initial-ai-message-container .icon-ai-large {
        font-size: 32px; /* Adjusted for Font Awesome icon size */
        color: #fff;
        background-color: #007bff;
        border-radius: 50%;
        /* padding: 15px; */ /* Padding might make the icon too small, width/height control size */
        margin-bottom: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 70px;
        height: 70px;
    }
    .initial-ai-message-container h2 {
        font-size: 1.5em;
        color: #333;
        margin-bottom: 10px;
    }
    .initial-ai-message-container p {
        color: #555;
        font-size: 1em;
        line-height: 1.5;
    }
     .initial-ai-message-container ul {
        list-style: none;
        padding: 0;
        text-align: left;
        display: inline-block; /* To center the block of text */
        margin-top: 10px;
    }
    .initial-ai-message-container ul li {
        padding-bottom: 5px;
    }


    .message-input-area {
        display: flex;
        align-items: center; /* Align items vertically */
        padding: 15px 20px; /* Consistent padding */
        border-top: 1px solid #d1d7dc; /* Softer border */
        background-color: #ffffff; 
    }

    .message-input-area textarea {
        flex-grow: 1;
        padding: 12px 15px; /* More padding */
        border: 1px solid #ced4da; /* Softer border */
        border-radius: 22px; /* More rounded input field */
        resize: none;
        min-height: 44px; 
        font-size: 1em;
        line-height: 1.4;
        margin-right: 10px;
        max-height: 150px; /* Limit growth */
        overflow-y: auto; /* Scroll if too much text */
    }
    .message-input-area textarea:focus {
        outline: none;
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }

    .message-input-area .send-button {
        background-color: #007bff; 
        color: white;
        border: none;
        border-radius: 50%; 
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.2s;
        flex-shrink: 0; /* Prevent button from shrinking */
    }
    .message-input-area .send-button:hover {
        background-color: #0056b3; 
    }
    .message-input-area .send-button svg {
        width: 20px; 
        height: 20px;
    }

    /* For Gibbon specific overrides if necessary */
    .button:hover, input[type="submit"]:hover {
        /* Your Gibbon theme might have !important, adjust if needed */
        background-color: #0056b3 !important; 
    }

</style>

<div class="chat-page-container">
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <h3><?php echo __('MY CHATS'); // Changed to uppercase to match image ?></h3>
            <form method="post" action="<?php echo $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/chatbot.php'; ?>" class="new-chat-form">
                <button type="submit" name="new_chat"><i class="fas fa-plus"></i> <?php echo __('New'); ?></button>
                <input type="text" name="chat_title" placeholder="<?php echo __('New chat title (optional)'); ?>" />
            </form>
        </div>
        <ul class="chat-list">
            <?php if (empty($userChats)): ?>
                <li style="padding: 15px; text-align: center; color: #777;"><?php echo __('No chats yet. Start a new one!'); ?></li>
            <?php else: ?>
                <?php foreach ($userChats as $chat): ?>
                    <li class="chat-list-item <?php echo ($currentChatID == $chat['aiTeacherChatID']) ? 'active' : ''; ?>">
                        <a href="<?php echo $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/chatbot.php&chat_id=' . $chat['aiTeacherChatID']; ?>">
                            <?php echo htmlspecialchars($chat['title']); ?>
                        </a>
                        <form method="post" action="<?php echo $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/chatbot.php'; ?>" class="delete-chat-form" onsubmit="return confirm('<?php echo __('Are you sure you want to delete this chat?'); ?>');">
                            <input type="hidden" name="chat_id" value="<?php echo $chat['aiTeacherChatID']; ?>">
                            <button type="submit" name="delete_chat" title="<?php echo __('Delete Chat'); ?>"><i class="fas fa-times"></i></button> <!-- Cross icon -->
                        </form>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <div class="chat-main-interface">
        <div class="chat-header">
            <?php
            $currentChatTitle = __('AI Teaching Assistant Chat');
            if ($currentChatID && !empty($userChats)) {
                foreach($userChats as $c) {
                    if ($c['aiTeacherChatID'] == $currentChatID) {
                        $currentChatTitle = htmlspecialchars($c['title']);
                        break;
                    }
                }
            }
            echo $currentChatTitle;
            ?>
        </div>

        <div class="chat-messages-area" id="chat-messages-area">
            <?php if (!$aiConfigured): ?>
                 <div class="initial-ai-message-container">
                    <p><?php echo __('AI features are not configured. Please contact your administrator.'); ?></p>
                </div>
            <?php elseif (empty($chatMessages) && $currentChatID): ?>
                <!-- If a chat is selected but has no messages yet, perhaps a small prompt or just empty -->
                 <div class="initial-ai-message-container">
                    <p><?php echo __('No messages in this chat yet. Send a message to start!'); ?></p>
                </div>
            <?php elseif (empty($chatMessages) && !$currentChatID): ?>
                <div class="initial-ai-message-container">
                     <div class="message-avatar" style="margin-bottom: 15px;">
                        <span class="icon-ai-large"><i class="fas fa-robot"></i></span>
                    </div>
                    <h2><?php echo __('AI Teaching Assistant'); ?></h2>
                    <p><?php echo __('Hello! I can help you with:'); ?></p>
                    <ul>
                        <li><?php echo __('Creating detailed lesson plans'); ?></li>
                        <li><?php echo __('Analyzing student grades'); ?></li>
                        <li><?php echo __('Providing teaching guidance'); ?></li>
                        <li><?php echo __('Answering educational questions'); ?></li>
                    </ul>
                    <p><?php echo __('How can I help you today? Select a chat or start a new one.'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($chatMessages as $message): ?>
                    <div class="chat-message <?php echo ($message['role'] === 'assistant') ? 'ai-message' : 'user-message'; ?>">
                        <?php if ($message['role'] === 'assistant'): ?>
                            <div class="message-avatar">
                                <span class="icon-ai"><i class="fas fa-robot"></i></span>
                            </div>
                        <?php endif; ?>
                        <div class="message-content-wrapper">
                            <?php /* If you want to show sender name:
                            <div class="message-header">
                                <?php echo ($message['role'] === 'assistant') ? 'AI Assistant' : 'You'; ?>
                            </div>
                            */ ?>
                            <div class="message-text"><?php echo formatChatMessageContent($message['content']); ?></div>
                            <div class="message-footer">
                                <span class="timestamp"><?php echo date('g:i a', strtotime($message['timestamp'])); ?></span>
                                <?php if ($message['role'] === 'assistant'): ?>
                                <div class="message-actions">
                                    <!-- Add actions like copy, thumbs up/down here if needed -->
                                    <!-- <button class="action-btn">&#128077;</button> 
                                         <button class="action-btn">&#128078;</button> -->
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                         <?php if ($message['role'] === 'user'): ?>
                            <div class="message-avatar">
                                <span class="icon-user"><i class="fas fa-user"></i></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="message-input-area">
            <form method="post" action="<?php echo $formActionURL; ?>" style="display: flex; flex-grow: 1; align-items: center;">
                <textarea name="message" placeholder="<?php echo ($aiConfigured) ? __('Type your message here...') : __('AI not configured.'); ?>" <?php echo !$aiConfigured ? 'disabled' : ''; ?>></textarea>
                <button type="submit" name="send" class="send-button" <?php echo !$aiConfigured ? 'disabled' : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="24px" height="24px"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Auto-scroll to the bottom of the messages
    const messagesArea = document.getElementById('chat-messages-area');
    if (messagesArea) {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    // Auto-resize textarea
    const textarea = document.querySelector('.message-input-area textarea');
    if (textarea) {
        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto'; // Reset height
            textarea.style.height = (textarea.scrollHeight) + 'px'; // Set to scroll height
        });
    }
</script>
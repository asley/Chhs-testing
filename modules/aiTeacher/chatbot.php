<?php
ini_set('max_execution_time', 120);

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Module\aiTeacher\DeepSeekAPI;

$page->breadcrumbs->add(__('AI Teacher Assistance'), 'index.php');
$page->breadcrumbs->add(__('AI Chatbot'));

if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/chatbot.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $settings = getAITeacherSettings($pdo);
    if (empty($settings['deepseek_api_key'])) {
        $page->addError(__('DeepSeek API key is not configured. Please contact your administrator.'));
    } else {
        try {
            if (!$pdo) throw new Exception('Database connection not initialized');

            $pdo->executeQuery([], "CREATE TABLE IF NOT EXISTS `aiTeacherChats` (
                `aiTeacherChatID` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `gibbonPersonID` int(10) unsigned NOT NULL,
                `title` varchar(255) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`aiTeacherChatID`),
                KEY `gibbonPersonID` (`gibbonPersonID`),
                CONSTRAINT `aiTeacherChats_ibfk_1` FOREIGN KEY (`gibbonPersonID`) REFERENCES `gibbonPerson` (`gibbonPersonID`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            $pdo->executeQuery([], "CREATE TABLE IF NOT EXISTS `aiTeacherChatMessages` (
                `aiTeacherChatMessageID` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `aiTeacherChatID` int(10) unsigned NOT NULL,
                `role` enum('user','assistant') NOT NULL,
                `content` text NOT NULL,
                `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`aiTeacherChatMessageID`),
                KEY `aiTeacherChatID` (`aiTeacherChatID`),
                CONSTRAINT `aiTeacherChatMessages_ibfk_1` FOREIGN KEY (`aiTeacherChatID`) REFERENCES `aiTeacherChats` (`aiTeacherChatID`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
            $currentChatID = $_GET['chat_id'] ?? null;

            if (isset($_POST['new_chat'])) {
                $title = trim($_POST['chat_title'] ?? 'New Chat');
                $pdo->executeQuery([
                    'gibbonPersonID' => $gibbonPersonID,
                    'title' => $title
                ], "INSERT INTO aiTeacherChats (gibbonPersonID, title) VALUES (:gibbonPersonID, :title)");
                $currentChatID = $pdo->getConnection()->lastInsertId();
            }

            if (isset($_POST['delete_chat'], $_POST['chat_id'])) {
                $pdo->executeQuery([
                    'chatID' => $_POST['chat_id'],
                    'gibbonPersonID' => $gibbonPersonID
                ], "DELETE FROM aiTeacherChats WHERE aiTeacherChatID = :chatID AND gibbonPersonID = :gibbonPersonID");
                $currentChatID = null;
            }

            if (isset($_POST['send']) && !empty(trim($_POST['message']))) {
                $userMessage = trim($_POST['message']);

                if (!$currentChatID) {
                    $title = substr($userMessage, 0, 50) . (strlen($userMessage) > 50 ? '...' : '');
                    $pdo->executeQuery([
                        'gibbonPersonID' => $gibbonPersonID,
                        'title' => $title
                    ], "INSERT INTO aiTeacherChats (gibbonPersonID, title) VALUES (:gibbonPersonID, :title)");
                    $currentChatID = $pdo->getConnection()->lastInsertId();
                }

                $pdo->executeQuery([
                    'chatID' => $currentChatID,
                    'content' => $userMessage
                ], "INSERT INTO aiTeacherChatMessages (aiTeacherChatID, role, content) VALUES (:chatID, 'user', :content)");

                $aiResponse = null;
                try {
                    $api = new DeepSeekAPI($settings['deepseek_api_key']);
                    // $response = $api->send([  // Old problematic call
                    //     'model' => 'deepseek-chat',
                    //     'messages' => [['role' => 'user', 'content' => $userMessage]]
                    // ]);
                    // if (!$response['success']) {
                    //     throw new Exception($response['error']);
                    // }
                    // $aiResponse = $response['response']['choices'][0]['message']['content'] ?? null;
                    $aiResponse = $api->generateResponse($userMessage); // Corrected: Use public method

                    if ($aiResponse === null) {
                        // generateResponse() logs detailed errors internally.
                        // Throw a general error for the user.
                        throw new Exception('Failed to get a response from the AI service. Please check the system logs.');
                    }

                    // $aiResponse is now the string content directly.
                    if ($currentChatID) { // No need to check if $aiResponse is truthy if an empty response is valid.
                        $pdo->executeQuery([
                            'chatID' => $currentChatID,
                            'content' => $aiResponse
                        ], "INSERT INTO aiTeacherChatMessages (aiTeacherChatID, role, content) VALUES (:chatID, 'assistant', :content)");

                        logAITeacherAction(
                            $pdo,
                            $gibbonPersonID,
                            'Chatbot Interaction',
                            'Chat Message',
                            $userMessage,
                            $aiResponse
                        );
                    }
                } catch (Exception $e) {
                    $page->addError(__('AI system error: ') . $e->getMessage());
                }
            }

            include __DIR__ . '/chatInterface.php';
        } catch (Exception $e) {
            $page->addError(__('Database error: ') . $e->getMessage());
        }
    }
}

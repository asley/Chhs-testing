// ...
// require_once __DIR__ . '/../../gibbon.php'; // Or your Gibbon bootstrap
// require_once __DIR__ . '/moduleFunctions.php';
// require_once __DIR__ . '/src/DeepSeekAPI.php';

// header('Content-Type: application/json'); // If you expect JSON back

// $settings = getAITeacherSettings($pdo); // Assuming $pdo is initialized
// $apiKey = $settings['deepseek_api_key'] ?? null;

// if (!$apiKey) {
//     error_log("Resource Generator Error: API key not configured.");
//     echo json_encode(['success' => false, 'error' => 'AI system error: API key not configured.']);
//     exit;
// }

// // Get data from the form (example for POST)
// $subject = $_POST['subject'] ?? '';
// $topic = $_POST['topic'] ?? '';
// $assessmentType = $_POST['assessmentType'] ?? '';
// $customPrompt = $_POST['customPrompt'] ?? '';

// // Basic validation
// if (empty($subject) || empty($topic) || empty($assessmentType)) {
//     error_log("Resource Generator Error: Missing required fields.");
//     echo json_encode(['success' => false, 'error' => 'Missing required fields. Please fill in Subject, Topic, and Assessment Type.']);
//     exit;
// }

// // Construct the prompt for the AI
// $prompt = "Generate a {$assessmentType} for the subject '{$subject}' on the topic '{$topic}'.";
// if (!empty($customPrompt)) {
//     $prompt .= " Follow these instructions: {$customPrompt}";
// }
// // Add more details to the prompt as needed for better results.

// try {
//     $api = new \Gibbon\Module\aiTeacher\DeepSeekAPI($apiKey);
//     $generatedContent = $api->generateResponse($prompt);

//     // Send the successful response back to the frontend
//     echo json_encode(['success' => true, 'assessment' => nl2br(htmlspecialchars($generatedContent))]);

// } catch (\Exception $e) {
//     // Log the detailed error to PHP error log
//     error_log("Resource Generator - AI API Call Failed: " . $e->getMessage());
//     error_log("Prompt used: " . $prompt); // Log the prompt for debugging

//     // Send a generic error to the client
//     echo json_encode(['success' => false, 'error' => 'AI system error: Failed to generate assessment. Please check system logs.']);
// }
// exit;
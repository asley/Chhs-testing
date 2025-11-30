<?php
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/DeepSeekAPI.php';

$settings = getAITeacherSettings($pdo);
$aiEnabled = !empty($settings['deepseek_api_key']);

$data = json_decode(file_get_contents('php://input'), true);
$requestedStudentId = $data['studentId'] ?? ''; // Ensure studentId is passed from AJAX
$studentName = $data['studentName'] ?? '';
$courseName = $data['courseName'] ?? '';
$score = $data['score'] ?? '';

// --- New Security Check ---
$loggedInUserId = $gibbon->session->get('gibbonPersonID');
$loggedInUserRoleId = $gibbon->session->get('gibbonRoleIDCurrent'); 

// Define or fetch the Student Role ID. This might need adjustment based on your Gibbon setup.
// A more robust way would be to get role by name: $studentRoleID = $gibbon->roles->getRoleIDByName('Student');
$studentRoleID = 5; // Placeholder: Replace with actual Student Role ID from your Gibbon's gibbonRole table

// Check if the logged-in user is a student
if ($loggedInUserRoleId == $studentRoleID) {
    // If student, they can only request their own data
    if (empty($requestedStudentId) || $requestedStudentId != $loggedInUserId) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error: You are not authorized to request recommendations for this student.';
        exit;
    }
} else {
    // For non-students (e.g., teachers, admins), ensure studentId is provided if they are using this endpoint
    // This part maintains functionality for assessment_analysis.php where teachers request for students
    if (empty($requestedStudentId)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error: Student ID is missing in the request.';
        exit;
    }
}
// --- End Security Check ---

if ($aiEnabled && $studentName && $courseName && $score !== '' && !empty($requestedStudentId)) { // Added !empty($requestedStudentId)
    $prompt = "Ezekel's Chemistry grade is below the school standard. Please provide a personalized intervention message for {$studentName} in {$courseName} (current score: {$score}%). The message should address the student directly and encourage improvement. Then, list 3 or 4 specific, actionable recommendations to help them improve their performance in {$courseName}. Format the response as:\n\nPersonalized Message:\n...\n\nRecommendations:\n1.\n2.\n3.\n4.";
    try {
        $api = new \Gibbon\Module\aiTeacher\DeepSeekAPI($settings['deepseek_api_key']);
        $recommendation = $api->generateResponse($prompt);
        echo nl2br(htmlspecialchars($recommendation));
    } catch (\Exception $e) {
        echo 'AI error: ' . htmlspecialchars($e->getMessage());
    }
} else {
    echo 'AI not enabled or missing data.';
}
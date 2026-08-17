<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Data\Validator;

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/Services/PlannerContextBuilder.php';

use Gibbon\Module\aiTeacher\Services\PlannerContextBuilder;

$_POST = $container->get(Validator::class)->sanitize($_POST, [
    'description' => 'HTML',
    'teachersNotes' => 'HTML',
    'homeworkDetails' => 'HTML',
]);

$gibbonPlannerEntryID = $_POST['gibbonPlannerEntryID'] ?? '';
$baseReturn = $session->get('absoluteURL').'/index.php?q=/modules/aiTeacher/planner_generate.php&gibbonPlannerEntryID='.urlencode($gibbonPlannerEntryID);

if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/planner_generate.php')) {
    header("Location: {$baseReturn}&return=error0");
    exit();
}

if (empty($gibbonPlannerEntryID)) {
    header("Location: {$baseReturn}&return=error1");
    exit();
}

try {
    $context = (new PlannerContextBuilder($connection2, $guid))
        ->buildForLesson($gibbonPlannerEntryID, $session->get('gibbonPersonID'));

    $name = trim($_POST['name'] ?? '');
    $summary = trim(strip_tags($_POST['summary'] ?? ''));
    $description = $_POST['description'] ?? '';
    $teachersNotes = $_POST['teachersNotes'] ?? '';
    $homeworkDetails = $_POST['homeworkDetails'] ?? '';
    $homeworkTimeCap = $_POST['homeworkTimeCap'] ?? null;

    if ($name === '') {
        $name = $context['lesson']['name'] ?? __('AI Generated Lesson');
    }
    $name = mb_substr($name, 0, 50);
    $summary = mb_substr($summary, 0, 255);
    $homeworkTimeCap = is_numeric($homeworkTimeCap) ? (int) $homeworkTimeCap : null;

    $homework = trim(strip_tags($homeworkDetails)) !== '' ? 'Y' : ($context['lesson']['homework'] ?? 'N');

    $data = [
        'gibbonPlannerEntryID' => $gibbonPlannerEntryID,
        'name' => $name,
        'summary' => $summary,
        'description' => $description,
        'teachersNotes' => $teachersNotes,
        'homework' => $homework,
        'homeworkDetails' => $homeworkDetails,
        'homeworkTimeCap' => $homeworkTimeCap,
        'gibbonPersonIDLastEdit' => $session->get('gibbonPersonID'),
    ];

    $sql = "UPDATE gibbonPlannerEntry
        SET name = :name,
            summary = :summary,
            description = :description,
            teachersNotes = :teachersNotes,
            homework = :homework,
            homeworkDetails = :homeworkDetails,
            homeworkTimeCap = :homeworkTimeCap,
            gibbonPersonIDLastEdit = :gibbonPersonIDLastEdit
        WHERE gibbonPlannerEntryID = :gibbonPlannerEntryID";
    $result = $connection2->prepare($sql);
    $result->execute($data);

    $returnParams = [
        'q' => '/modules/Planner/planner_edit.php',
        'gibbonPlannerEntryID' => $gibbonPlannerEntryID,
        'viewBy' => 'class',
        'gibbonCourseClassID' => $context['lesson']['gibbonCourseClassID'],
        'return' => 'success0',
    ];
    header('Location: '.$session->get('absoluteURL').'/index.php?'.http_build_query($returnParams));
    exit();
} catch (\Exception $e) {
    error_log('AI Teacher planner apply failed: '.$e->getMessage());
    header("Location: {$baseReturn}&return=error2");
    exit();
}

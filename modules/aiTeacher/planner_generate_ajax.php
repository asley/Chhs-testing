<?php

ob_clean();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
set_time_limit(180);

function ai_teacher_json_response(array $data): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/moduleFunctions.php';
require_once __DIR__ . '/src/DeepSeekAPI.php';
require_once __DIR__ . '/src/OpenAIAPI.php';
require_once __DIR__ . '/src/Services/AITeacherSettingsService.php';
require_once __DIR__ . '/src/Services/AITeacherProvider.php';
require_once __DIR__ . '/src/Services/AITeacherLogger.php';
require_once __DIR__ . '/src/Services/CsecSubjectMap.php';
require_once __DIR__ . '/src/Services/PlannerContextBuilder.php';
require_once __DIR__ . '/src/Services/LessonContentGenerator.php';

use Gibbon\Module\aiTeacher\Services\AITeacherLogger;
use Gibbon\Module\aiTeacher\Services\AITeacherProvider;
use Gibbon\Module\aiTeacher\Services\AITeacherSettingsService;
use Gibbon\Module\aiTeacher\Services\CsecSubjectMap;
use Gibbon\Module\aiTeacher\Services\LessonContentGenerator;
use Gibbon\Module\aiTeacher\Services\PlannerContextBuilder;

try {
    if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/planner_generate.php')) {
        ai_teacher_json_response(['success' => false, 'error' => 'Access denied.']);
    }

    $gibbonPlannerEntryID = $_POST['gibbonPlannerEntryID'] ?? '';
    if (empty($gibbonPlannerEntryID)) {
        ai_teacher_json_response(['success' => false, 'error' => 'No Planner lesson was specified.']);
    }

    $settings = (new AITeacherSettingsService($pdo))->getSettings();
    $context = (new PlannerContextBuilder($connection2, $guid))
        ->buildForLesson($gibbonPlannerEntryID, $session->get('gibbonPersonID'));

    $provider = new AITeacherProvider($settings);
    $draft = (new LessonContentGenerator($provider, new CsecSubjectMap()))
        ->generate($context, $_POST['outputType'] ?? 'lesson_homework', trim($_POST['customInstructions'] ?? ''));

    (new AITeacherLogger($pdo))->logPlannerGeneration([
        'gibbonPersonID' => $session->get('gibbonPersonID'),
        'gibbonPlannerEntryID' => $context['lesson']['id'],
        'gibbonCourseClassID' => $context['lesson']['gibbonCourseClassID'],
        'gibbonUnitID' => $context['lesson']['gibbonUnitID'],
        'subject' => $draft['meta']['subject'] ?? '',
        'outputType' => $_POST['outputType'] ?? 'lesson_homework',
        'promptHash' => $draft['meta']['promptHash'] ?? null,
        'provider' => $draft['meta']['provider'] ?? null,
        'status' => 'Success',
        'error' => null,
    ]);

    ai_teacher_json_response($draft);
} catch (\Exception $e) {
    ai_teacher_json_response(['success' => false, 'error' => $e->getMessage()]);
}


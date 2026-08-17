<?php
/*
Gibbon, Flexible & Open School System
*/

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
use Gibbon\Session\TokenHandler;

$page->breadcrumbs
    ->add(__('AI Teacher Assistance'), 'index.php')
    ->add(__('Planner AI Generator'));

if (!isActionAccessible($guid, $connection2, '/modules/aiTeacher/planner_generate.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonPlannerEntryID = $_GET['gibbonPlannerEntryID'] ?? '';
if (empty($gibbonPlannerEntryID)) {
    $page->addError(__('No Planner lesson was specified.'));
    return;
}

try {
    $settingsService = new AITeacherSettingsService($pdo);
    $settings = $settingsService->getSettings();
    $contextBuilder = new PlannerContextBuilder($connection2, $guid);
    $context = $contextBuilder->buildForLesson($gibbonPlannerEntryID, $session->get('gibbonPersonID'));

    $returnParams = [
        'q' => '/modules/Planner/planner_edit.php',
        'gibbonPlannerEntryID' => $context['lesson']['id'],
        'viewBy' => 'class',
        'gibbonCourseClassID' => $context['lesson']['gibbonCourseClassID'],
    ];
    $returnUrl = $session->get('absoluteURL').'/index.php?'.http_build_query($returnParams);

    echo '<div class="linkTop">';
    echo '<a href="'.htmlPrep($returnUrl).'">'.__('Return to Planner Lesson').'</a>';
    echo '</div>';

    echo '<h2>'.__('Planner AI Generator').'</h2>';
    echo '<p>'.__('Generate draft lesson content and homework from the selected Planner unit. Review and apply the draft directly to this Planner lesson.').'</p>';

    echo '<table class="fullWidth colorOddEven">';
    echo '<tr><td><strong>'.__('Class').'</strong></td><td>'.htmlPrep(($context['course']['nameShort'] ?? '').'.'.($context['class']['nameShort'] ?? '')).'</td></tr>';
    echo '<tr><td><strong>'.__('Lesson').'</strong></td><td>'.htmlPrep($context['lesson']['name'] ?? '').'</td></tr>';
    echo '<tr><td><strong>'.__('Unit').'</strong></td><td>'.htmlPrep($context['unit']['name'] ?? __('No unit selected')).'</td></tr>';
    echo '</table>';

    if (empty($context['unit'])) {
        $page->addWarning(__('This lesson has no selected unit. Select a unit in Planner before generating syllabus-aligned content.'));
    } elseif (empty($settings['deepseek_api_key']) && empty($settings['openai_api_key'])) {
        $page->addError(__('No AI API key is configured. Please contact your administrator.'));
    } else {
        echo '<form method="post" action="'.$session->get('absoluteURL').'/index.php?q=/modules/aiTeacher/planner_generate.php&gibbonPlannerEntryID='.urlencode($gibbonPlannerEntryID).'">';
        echo '<table class="fullWidth">';
        echo '<tr>';
        echo '<td><strong>'.__('Output Type').'</strong><br/>'.__('Choose what aiTeacher should draft.').'</td>';
        echo '<td class="right">';
        echo '<select name="outputType" style="width: 300px">';
        $outputTypes = [
            'lesson_homework' => __('Lesson Content and Homework'),
            'lesson' => __('Lesson Content Only'),
            'homework' => __('Homework Only'),
        ];
        foreach ($outputTypes as $value => $label) {
            $selected = ($_POST['outputType'] ?? 'lesson_homework') === $value ? ' selected' : '';
            echo '<option value="'.htmlPrep($value).'"'.$selected.'>'.htmlPrep($label).'</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td><strong>'.__('Custom Instructions').'</strong><br/>'.__('Optional teacher guidance for the AI draft.').'</td>';
        echo '<td class="right"><textarea name="customInstructions" style="width: 300px; height: 80px;">'.htmlPrep($_POST['customInstructions'] ?? '').'</textarea></td>';
        echo '</tr>';
        echo '</table>';
        echo '<div class="right"><input type="submit" name="generate" value="'.__('Generate Draft').'" class="button"></div>';
        echo '</form>';
    }

    if (isset($_POST['generate']) && !empty($context['unit'])) {
        $outputType = $_POST['outputType'] ?? 'lesson_homework';
        $customInstructions = trim($_POST['customInstructions'] ?? '');
        $provider = new AITeacherProvider($settings);
        $generator = new LessonContentGenerator($provider, new CsecSubjectMap());
        $logger = new AITeacherLogger($pdo);

        try {
            $draft = $generator->generate($context, $outputType, $customInstructions);
            $logger->logPlannerGeneration([
                'gibbonPersonID' => $session->get('gibbonPersonID'),
                'gibbonPlannerEntryID' => $context['lesson']['id'],
                'gibbonCourseClassID' => $context['lesson']['gibbonCourseClassID'],
                'gibbonUnitID' => $context['lesson']['gibbonUnitID'],
                'subject' => $draft['meta']['subject'] ?? '',
                'outputType' => $outputType,
                'promptHash' => $draft['meta']['promptHash'] ?? null,
                'provider' => $draft['meta']['provider'] ?? null,
                'status' => 'Success',
                'error' => null,
            ]);

            echo '<h3>'.__('Generated Draft').'</h3>';
            echo '<p>'.__('Review and edit the draft below, then apply it to the Planner lesson. You can still make final edits in Planner afterwards.').'</p>';
            echo '<form method="post" action="'.$session->get('absoluteURL').'/modules/aiTeacher/planner_applyProcess.php">';
            echo '<input type="hidden" name="csrftoken" value="'.htmlPrep($container->get(TokenHandler::class)->getCSRF()).'">';
            echo '<input type="hidden" name="nonce" value="'.htmlPrep($container->get(TokenHandler::class)->getNonce()).'">';
            echo '<input type="hidden" name="address" value="/modules/aiTeacher/planner_generate.php">';
            echo '<input type="hidden" name="gibbonPlannerEntryID" value="'.htmlPrep($context['lesson']['id']).'">';
            renderAITeacherDraftField(__('Lesson Name'), 'name', $draft['lesson']['name'] ?? '', 2);
            renderAITeacherDraftField(__('Summary'), 'summary', $draft['lesson']['summary'] ?? '', 3);
            renderAITeacherDraftField(__('Lesson Details'), 'description', $draft['lesson']['description'] ?? '', 12);
            renderAITeacherDraftField(__('Teacher Notes'), 'teachersNotes', $draft['lesson']['teachersNotes'] ?? '', 8);
            renderAITeacherDraftField(__('Homework Details'), 'homeworkDetails', $draft['homework']['details'] ?? '', 8);
            renderAITeacherDraftField(__('Homework Time Cap'), 'homeworkTimeCap', (string) ($draft['homework']['timeCap'] ?? ''), 1);
            echo '<div class="right"><input type="submit" name="apply" value="'.__('Apply to Planner').'" class="button"></div>';
            echo '</form>';
            echo '<div class="linkTop"><a href="'.htmlPrep($returnUrl).'">'.__('Return Without Applying').'</a></div>';
        } catch (\Exception $e) {
            $logger->logPlannerGeneration([
                'gibbonPersonID' => $session->get('gibbonPersonID'),
                'gibbonPlannerEntryID' => $context['lesson']['id'],
                'gibbonCourseClassID' => $context['lesson']['gibbonCourseClassID'],
                'gibbonUnitID' => $context['lesson']['gibbonUnitID'],
                'subject' => $context['course']['name'] ?? '',
                'outputType' => $outputType,
                'promptHash' => null,
                'provider' => isset($provider) ? $provider->getProviderName() : null,
                'status' => 'Error',
                'error' => $e->getMessage(),
            ]);
            $page->addError(__('The AI draft could not be generated: {error}', ['error' => $e->getMessage()]));
        }
    }
} catch (\Exception $e) {
    $page->addError($e->getMessage());
}

function renderAITeacherDraftField($label, $name, $value, $rows)
{
    echo '<h4>'.htmlPrep($label).'</h4>';
    echo '<textarea name="'.htmlPrep($name).'" style="width: 100%; min-height: '.max(40, (int) $rows * 24).'px;">'.htmlPrep($value).'</textarea>';
}

<?php
/*
Gibbon, Flexible & Open School System
*/

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mappings.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('juss-examBridge'), 'index.php');
    $page->breadcrumbs->add(__('Bridge Mappings'));

    $absoluteURL = $session->get('absoluteURL');

    $personCount = 0;
    $classCount = 0;
    $assessmentCount = 0;

    try {
        $personCount = (int) $connection2->query('SELECT COUNT(*) FROM gibbonJussExamBridgePersonMap')->fetchColumn();
        $classCount = (int) $connection2->query('SELECT COUNT(*) FROM gibbonJussExamBridgeClassMap')->fetchColumn();
        $assessmentCount = (int) $connection2->query('SELECT COUNT(*) FROM gibbonJussExamBridgeAssessmentMap')->fetchColumn();
    } catch (PDOException $e) {
        $page->addError(__('Unable to load mapping counts.'));
    }

    echo '<h2>' . __('Mapping Administration') . '</h2>';
    echo '<p>' . __('Configure TCExam external IDs to resolve Gibbon class, student and assessment targets for grade sync.') . '</p>';

    echo '<table class="smallIntBorder fullWidth colorOddEven">';
    echo '<tr><td><b>' . __('Person Mappings') . '</b></td><td>' . $personCount . '</td><td><a href="' . $absoluteURL . '/index.php?q=/modules/juss-examBridge/mapping_person.php">' . __('Manage') . '</a></td></tr>';
    echo '<tr><td><b>' . __('Class Mappings') . '</b></td><td>' . $classCount . '</td><td><a href="' . $absoluteURL . '/index.php?q=/modules/juss-examBridge/mapping_class.php">' . __('Manage') . '</a></td></tr>';
    echo '<tr><td><b>' . __('Assessment Mappings') . '</b></td><td>' . $assessmentCount . '</td><td><a href="' . $absoluteURL . '/index.php?q=/modules/juss-examBridge/mapping_assessment.php">' . __('Manage') . '</a></td></tr>';
    echo '</table>';
}

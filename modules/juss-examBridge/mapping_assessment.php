<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mapping_assessment.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('juss-examBridge'), 'index.php');
    $page->breadcrumbs->add(__('Bridge Mappings'), 'mappings.php');
    $page->breadcrumbs->add(__('Assessment Mappings'));

    $iaColumns = [];
    try {
        $sql = "
            SELECT iac.gibbonInternalAssessmentColumnID, iac.name AS assessmentName, c.nameShort AS courseCode, cc.nameShort AS classCode
            FROM gibbonInternalAssessmentColumn iac
            INNER JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = iac.gibbonCourseClassID
            INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            ORDER BY c.nameShort, cc.nameShort, iac.name
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $iaColumns[(int) $row['gibbonInternalAssessmentColumnID']] = sprintf(
                '%s.%s | %s (%s)',
                $row['courseCode'],
                $row['classCode'],
                $row['assessmentName'],
                $row['gibbonInternalAssessmentColumnID']
            );
        }
    } catch (PDOException $e) {
        $page->addError(__('Unable to load internal assessment columns.'));
    }

    $form = Form::create('assessmentMap', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/mapping_assessmentProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'upsert');

    $row = $form->addRow();
    $row->addLabel('externalExamId', __('External Exam ID'));
    $row->addTextField('externalExamId')->maxLength(100)->required();

    $row = $form->addRow();
    $row->addLabel('gibbonInternalAssessmentColumnID', __('Internal Assessment Column'));
    $row->addSelect('gibbonInternalAssessmentColumnID')->fromArray($iaColumns)->required()->placeholder();

    $row = $form->addRow();
    $row->addLabel('syncMode', __('Sync Mode'));
    $row->addSelect('syncMode')->fromArray([
        'internal_assessment' => __('Internal Assessment'),
        'markbook' => __('Markbook'),
        'both' => __('Both'),
    ])->selected('internal_assessment')->required();

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Save Mapping'));

    echo '<h2>' . __('Add or Update Assessment Mapping') . '</h2>';
    echo $form->getOutput();

    echo '<h2>' . __('Current Assessment Mappings') . '</h2>';
    echo '<table class="smallIntBorder fullWidth colorOddEven">';
    echo '<tr><th>' . __('External Exam ID') . '</th><th>' . __('Internal Assessment Column ID') . '</th><th>' . __('Sync Mode') . '</th><th>' . __('Actions') . '</th></tr>';

    try {
        $listSql = "
            SELECT gibbonJussExamBridgeAssessmentMapID, externalExamId, gibbonInternalAssessmentColumnID, syncMode
            FROM gibbonJussExamBridgeAssessmentMap
            ORDER BY externalExamId
        ";
        $listStmt = $connection2->prepare($listSql);
        $listStmt->execute();
        while ($row = $listStmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['externalExamId']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['gibbonInternalAssessmentColumnID']) . '</td>';
            echo '<td>' . htmlspecialchars($row['syncMode']) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/mapping_assessmentProcess.php" style="display:inline">';
            echo '<input type="hidden" name="address" value="' . htmlspecialchars($session->get('address')) . '">';
            echo '<input type="hidden" name="action" value="delete">';
            echo '<input type="hidden" name="gibbonJussExamBridgeAssessmentMapID" value="' . (int) $row['gibbonJussExamBridgeAssessmentMapID'] . '">';
            echo '<button type="submit" onclick="return confirm(\'' . __('Are you sure?') . '\');">' . __('Delete') . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    } catch (PDOException $e) {
        echo '<tr><td colspan="4">' . __('Unable to load mappings.') . '</td></tr>';
    }

    echo '</table>';
}

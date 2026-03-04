<?php
/*
Gibbon, Flexible & Open School System
*/

use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/mapping_class.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('juss-examBridge'), 'index.php');
    $page->breadcrumbs->add(__('Bridge Mappings'), 'mappings.php');
    $page->breadcrumbs->add(__('Class Mappings'));

    $classes = [];
    try {
        $sql = "
            SELECT cc.gibbonCourseClassID, c.nameShort AS courseCode, cc.nameShort AS classCode, c.name AS courseName, cc.name AS className
            FROM gibbonCourseClass cc
            INNER JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            ORDER BY c.nameShort, cc.nameShort
        ";
        $stmt = $connection2->prepare($sql);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[(int) $row['gibbonCourseClassID']] = sprintf(
                '%s.%s | %s - %s',
                $row['courseCode'],
                $row['classCode'],
                $row['courseName'],
                $row['className']
            );
        }
    } catch (PDOException $e) {
        $page->addError(__('Unable to load class list.'));
    }

    $form = Form::create('classMap', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/mapping_classProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'upsert');

    $row = $form->addRow();
    $row->addLabel('externalCohortId', __('External Cohort ID'));
    $row->addTextField('externalCohortId')->maxLength(100)->required();

    $row = $form->addRow();
    $row->addLabel('externalClassCode', __('External Class Code'));
    $row->addTextField('externalClassCode')->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('gibbonCourseClassID', __('Gibbon Course Class'));
    $row->addSelect('gibbonCourseClassID')->fromArray($classes)->required()->placeholder();

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Save Mapping'));

    echo '<h2>' . __('Add or Update Class Mapping') . '</h2>';
    echo $form->getOutput();

    echo '<h2>' . __('Current Class Mappings') . '</h2>';
    echo '<table class="smallIntBorder fullWidth colorOddEven">';
    echo '<tr><th>' . __('External Cohort ID') . '</th><th>' . __('External Class Code') . '</th><th>' . __('Class') . '</th><th>' . __('Actions') . '</th></tr>';

    try {
        $listSql = "
            SELECT cm.gibbonJussExamBridgeClassMapID, cm.externalCohortId, cm.externalClassCode,
                   cc.gibbonCourseClassID, c.nameShort AS courseCode, cc.nameShort AS classCode
            FROM gibbonJussExamBridgeClassMap cm
            LEFT JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = cm.gibbonCourseClassID
            LEFT JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            ORDER BY cm.externalCohortId
        ";
        $listStmt = $connection2->prepare($listSql);
        $listStmt->execute();
        while ($row = $listStmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['externalCohortId']) . '</td>';
            echo '<td>' . htmlspecialchars((string) $row['externalClassCode']) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['courseCode'] . '.' . $row['classCode'] . ' (' . $row['gibbonCourseClassID'] . ')')) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/mapping_classProcess.php" style="display:inline">';
            echo '<input type="hidden" name="address" value="' . htmlspecialchars($session->get('address')) . '">';
            echo '<input type="hidden" name="action" value="delete">';
            echo '<input type="hidden" name="gibbonJussExamBridgeClassMapID" value="' . (int) $row['gibbonJussExamBridgeClassMapID'] . '">';
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

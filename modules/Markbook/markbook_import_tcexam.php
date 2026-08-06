<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;

require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_edit.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$gibbonMarkbookColumnID = $_GET['gibbonMarkbookColumnID'] ?? '';

if ($gibbonCourseClassID == '' || $gibbonMarkbookColumnID == '') {
    $page->addError(__('You have not specified one or more required parameters.'));
    return;
}

$highestAction = getHighestGroupedAction($guid, '/modules/Markbook/markbook_edit.php', $connection2);
$departmentGateway = $container->get(DepartmentGateway::class);

$data = ['gibbonCourseClassID' => $gibbonCourseClassID];
$sql = "SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourse.gibbonDepartmentID
        FROM gibbonCourse
        JOIN gibbonCourseClass ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
        WHERE gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID";
$result = $pdo->executeQuery($data, $sql);
$class = ($result->rowCount() == 1) ? $result->fetch() : null;

if (empty($class)) {
    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    return;
}

$teacherList = getTeacherList($pdo, $gibbonCourseClassID);
$departmentAccess = $departmentGateway->selectMemberOfDepartmentByRole($class['gibbonDepartmentID'], $session->get('gibbonPersonID'), ['Coordinator', 'Teacher (Curriculum)'])->fetch();
$canEditThisClass = (isset($teacherList[$session->get('gibbonPersonID')]) || $highestAction == 'Edit Markbook_everything' || ($highestAction == 'Edit Markbook_multipleClassesInDepartment' && !empty($departmentAccess)));

if (!$canEditThisClass) {
    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    return;
}

$data = [
    'gibbonMarkbookColumnID' => $gibbonMarkbookColumnID,
    'gibbonCourseClassID' => $gibbonCourseClassID,
];
$sql = "SELECT *
        FROM gibbonMarkbookColumn
        WHERE gibbonMarkbookColumnID=:gibbonMarkbookColumnID
        AND gibbonCourseClassID=:gibbonCourseClassID";
$result = $pdo->executeQuery($data, $sql);
$column = ($result->rowCount() == 1) ? $result->fetch() : null;

if (empty($column)) {
    $page->addError(__('The selected column does not exist, or you do not have access to it.'));
    return;
}

$page->breadcrumbs
    ->add(__('View {courseClass} Markbook', [
        'courseClass' => Format::courseClassName($class['course'], $class['class']),
    ]), 'markbook_view.php', [
        'gibbonCourseClassID' => $gibbonCourseClassID,
    ])
    ->add(__('Import TCExam CSV'));

if (isset($_GET['importAccepted'])) {
    $page->addSuccess(sprintf(
        __('TCExam CSV import complete: %1$s accepted, %2$s updated, %3$s inserted, %4$s rejected.'),
        htmlPrep($_GET['importAccepted'] ?? '0'),
        htmlPrep($_GET['importUpdated'] ?? '0'),
        htmlPrep($_GET['importInserted'] ?? '0'),
        htmlPrep($_GET['importRejected'] ?? '0')
    ));
}

echo '<p>';
echo __('Upload a TCExam marksheet CSV for this Markbook column. The import matches students by primary or alternate email address and only writes marks for students enrolled in this class.');
echo '</p>';

echo '<ul>';
echo '<li>'.__('Target class').': <b>'.Format::courseClassName($class['course'], $class['class']).'</b></li>';
echo '<li>'.__('Target column').': <b>'.htmlPrep($column['name']).'</b></li>';
echo '<li>'.__('Attempt rule').': '.__('latest submitted row per student email').'</li>';
echo '</ul>';

$form = Form::create('importTcExamMarks', $session->get('absoluteURL').'/modules/'.$session->get('module').'/markbook_import_tcexamProcess.php?gibbonCourseClassID='.$gibbonCourseClassID.'&gibbonMarkbookColumnID='.$gibbonMarkbookColumnID.'&address='.$session->get('address'));
$form->addHiddenValue('address', $session->get('address'));

$form->addRow()->addHeading('Upload', __('Upload'));

$row = $form->addRow();
    $row->addLabel('file', __('TCExam CSV File'))->description(__('Expected columns include Email, Score, Total Points Possible, Percentage, Status, and Submitted At.'));
    $row->addFileUpload('file')->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Import Marks'));

echo $form->getOutput();

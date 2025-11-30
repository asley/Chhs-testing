<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

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

use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    $page->breadcrumbs
        ->add(__m('Manage Committees'), 'committees_manage.php', ['gibbonSchoolYearID' => $gibbonSchoolYearID])
        ->add(__m('Add Committee'));

    $editLink = '';
    if (isset($_GET['editID'])) {
        $editLink = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committees_manage_edit.php&gibbonSchoolYearID='.$gibbonSchoolYearID.'&committeesCommitteeID='.$_GET['editID'].'&search='.$_GET['search'];
    }
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], $editLink, null);
    }

    $form = Form::create('committeesManage', $session->get('absoluteURL').'/modules/Committees/committees_manage_addProcess.php');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique for this school year.'));
        $row->addTextField('name')->maxLength(120)->required();

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->required();

    $row = $form->addRow();
        $row->addLabel('signup', __m('Can Sign-up?'))->description(__m('Assuming system-wide sign-up is open, should this committee be available to select?'));
        $row->addYesNo('signup')->required();

    $row = $form->addRow();
        $row->addLabel('file', __('Logo'))->description(__('125x125px jpg/png/gif'));
        $row->addFileUpload('file')->accepts('.jpg,.jpeg,.gif,.png');

    $row = $form->addRow();
        $column = $row->addColumn()->setClass('');
        $column->addLabel('description', __('Description'));
        $column->addEditor('description', $guid);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}

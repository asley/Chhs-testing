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
use Gibbon\Services\Format;
use Gibbon\Module\Committees\Domain\CommitteeGateway;
use Gibbon\Module\Committees\Domain\CommitteeRoleGateway;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage_edit_role_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $search = $_GET['search'] ?? '';
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $committeesCommitteeID = $_GET['committeesCommitteeID'] ?? '';
    $committeesRoleID = $_GET['committeesRoleID'] ?? '';

    $page->breadcrumbs
        ->add(__m('Manage Committees'), 'committees_manage.php', ['search' => $search, 'gibbonSchoolYearID' => $gibbonSchoolYearID])
        ->add(__m('Edit Committee'), 'committees_manage_edit.php', ['committeesCommitteeID' => $committeesCommitteeID, 'gibbonSchoolYearID' => $gibbonSchoolYearID])
        ->add(__m('Edit Role'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    if (empty($committeesCommitteeID) || empty($committeesRoleID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $highestManageAction = getHighestGroupedAction($guid, '/modules/Committees/committees_manage_edit.php', $connection2);
    if (empty($highestManageAction) || $highestManageAction == 'Manage Committees_myCommitteeAdmin') {
        if (!$container->get(CommitteeGateway::class)->isPersonCommitteeAdmin($committeesCommitteeID, $session->get('gibbonPersonID'))) {
            $page->addError(__('You do not have access to this action.'));
            return;
        }
    }

    $committee = $container->get(CommitteeGateway::class)->getByID($committeesCommitteeID);
    $values = $container->get(CommitteeRoleGateway::class)->getByID($committeesRoleID);

    if (empty($committee) || empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $form = Form::create('committeesManage', $session->get('absoluteURL').'/modules/Committees/committees_manage_edit_role_editProcess.php?search='.$search);

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $committee['gibbonSchoolYearID']);
    $form->addHiddenValue('committeesCommitteeID', $committeesCommitteeID);
    $form->addHiddenValue('committeesRoleID', $committeesRoleID);

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique for this committee.'));
        $row->addTextField('name')->maxLength(60)->required();

    $row = $form->addRow();
        $row->addLabel('type', __('Type'));
        $row->addSelect('type')->fromArray(['Member' => __m('Member'), 'Admin' => __m('Admin'), 'Chair' => __m('Chair')])->required();

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->required();

    $row = $form->addRow();
        $row->addLabel('signup', __m('Can Sign-up?'))->description(__m('Is this role selectable during committee sign-up?'));
        $row->addYesNo('signup')->required();
        
    $row = $form->addRow();
        $row->addLabel('seats', __('Seats'))->description(__m('The number of available spaces for new members during sign-up. This does not limit members added manually.'));
        $row->addNumber('seats')->onlyInteger(true)->minimum(1);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    $form->loadAllValuesFrom($values);

    echo $form->getOutput();
}

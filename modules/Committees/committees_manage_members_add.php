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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\Committees\Domain\CommitteeRoleGateway;
use Gibbon\Module\Committees\Domain\CommitteeGateway;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage_members_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $search = $_GET['search'] ?? '';
    $committeesCommitteeID = $_GET['committeesCommitteeID'] ?? '';
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    $page->breadcrumbs
        ->add(__m('Manage Committees'), 'committees_manage.php', ['search' => $search, 'gibbonSchoolYearID' => $gibbonSchoolYearID])
        ->add(__m('Manage Members'), 'committees_manage_members.php', ['committeesCommitteeID' => $committeesCommitteeID, 'gibbonSchoolYearID' => $gibbonSchoolYearID])
        ->add(__m('Add Member'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    if (empty($committeesCommitteeID)) {
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

    $form = Form::create('committeesManage', $session->get('absoluteURL').'/modules/Committees/committees_manage_members_addProcess.php?search='.$search);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
    $form->addHiddenValue('committeesCommitteeID', $committeesCommitteeID);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Person'))->description(__('Must be unique.'));
        $row->addSelectStaff('gibbonPersonID')->placeholder()->required();

    $roles = $container->get(CommitteeRoleGateway::class)->selectActiveRolesByCommittee($committeesCommitteeID)->fetchKeyPair();
    $row = $form->addRow();
        $row->addLabel('committeesRoleID', __('Role'));
        $row->addSelect('committeesRoleID')->fromArray($roles);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}

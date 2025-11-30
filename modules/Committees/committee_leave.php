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
use Gibbon\Services\Format;
use Gibbon\Module\Committees\Domain\CommitteeGateway;
use Gibbon\Module\Committees\Domain\CommitteeMemberGateway;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committee_leave.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $committeesCommitteeID = $_GET['committeesCommitteeID'] ?? '';

    $page->breadcrumbs
        ->add(__m('My Committees'), 'committees.php')
        ->add(__m('Leave Committee'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    if (empty($committeesCommitteeID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    $committeeGateway = $container->get(CommitteeGateway::class);
    $committeeMemberGateway = $container->get(CommitteeMemberGateway::class);

    $committee = $committeeGateway->getByID($committeesCommitteeID);
    $member = $committeeMemberGateway->selectBy(['committeesCommitteeID' => $committeesCommitteeID, 'gibbonPersonID' => $session->get('gibbonPersonID')])->fetch();

    if (empty($committee) || empty($member)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $signupActive = $container->get(SettingGateway::class)->getSettingByScope('Committees', 'signupActive');
    if ($signupActive == 'Y') {
        echo Format::alert(__m('This action will remove you from the selected committee.').' '.__m('You can then join a new committee from the View Committees page.'), 'warning');
    } else {
        echo Format::alert(__m('This action will remove you from the selected committee.').' '.__m('Sign-up is not currently active, you will not be able to automatically join a new committee.'), 'warning');
    }

    $form = Form::create('committeesLeave', $session->get('absoluteURL').'/modules/Committees/committee_leaveProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('committeesCommitteeID', $committeesCommitteeID);
    $form->addHiddenValue('committeesMemberID', $member['committeesMemberID']);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDLabel', __('Person'));
        $row->addSelectStaff('gibbonPersonID')->readonly()->selected($session->get('gibbonPersonID'));

    $row = $form->addRow();
        $row->addLabel('committeeLabel', __m('Committee'));
        $row->addTextField('committee')->readonly()->setValue($committee['name']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}

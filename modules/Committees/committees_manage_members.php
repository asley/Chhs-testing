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

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\Committees\Domain\CommitteeGateway;
use Gibbon\Module\Committees\Domain\CommitteeMemberGateway;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage_members.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $search = $_GET['search'] ?? '';
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    $page->breadcrumbs
        ->add(__m('Manage Committees'), 'committees_manage.php', ['search' => $search, 'gibbonSchoolYearID' => $gibbonSchoolYearID])
        ->add(__m('Manage Members'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $committeeGateway = $container->get(CommitteeGateway::class);
    $committeeMemberGateway = $container->get(CommitteeMemberGateway::class);

    $committeesCommitteeID = $_GET['committeesCommitteeID'] ?? '';

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

    $committee = $container->get(CommitteeGateway::class)->getByID($committeesCommitteeID);
    if (empty($committee)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // QUERY
    $criteria = $committeeGateway->newQueryCriteria(true)
        ->sortBy(['committeesRole.type', 'gibbonPerson.surname', 'gibbonPerson.preferredName'])
        ->fromPOST();

    $committees = $committeeMemberGateway->queryMembersByCommittee($criteria, $committeesCommitteeID);

    // DATA TABLE
    $table = DataTable::createPaginated('committees', $criteria);
    $table->setTitle($committee['name']);
    $table->addMetaData('blankSlate', __m('There are currently no members in this committee.'));

    $table->addHeaderAction('add', __('Add'))
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->addParam('committeesCommitteeID', $committeesCommitteeID)
        ->setURL('/modules/Committees/committees_manage_members_add.php')
        ->displayLabel();

    $table->addColumn('fullName', __('Name'))
        ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
        ->format(function ($person) {
            return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', true, true);
        });

    $table->addColumn('role', __('Role'));

    // ACTIONS
    $table->addActionColumn()
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->addParam('committeesCommitteeID', $committeesCommitteeID)
        ->addParam('committeesMemberID')
        ->format(function ($person, $actions) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/Committees/committees_manage_members_edit.php');

            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/Committees/committees_manage_members_delete.php');
        });

    echo $table->render($committees);
}

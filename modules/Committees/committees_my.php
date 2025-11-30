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
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_my.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('My Committees'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $committeeGateway = $container->get(CommitteeGateway::class);

    // QUERY
    $criteria = $committeeGateway->newQueryCriteria(true)
        ->sortBy('name', 'ASC')
        ->fromPOST();

    $gibbonPersonID = $session->get('gibbonPersonID');
    $committees = $committeeGateway->queryCommitteesByMember($criteria, $session->get('gibbonSchoolYearID'), $gibbonPersonID);

    $highestManageAction = getHighestGroupedAction($guid, '/modules/Committees/committees_manage_edit.php', $connection2);
    $canSignup = isActionAccessible($guid, $connection2, '/modules/Committees/committee_signup.php');
    $signupActive = $container->get(SettingGateway::class)->getSettingByScope('Committees', 'signupActive');

    // DATA TABLE
    $table = DataTable::createPaginated('committees', $criteria);
    $table->setTitle(__m('My Committees'));
    $table->addMetaData('blankSlate', __m('You are not currently a member of any committees.'));

    $table->modifyRows(function ($committee, $row) {
        if ($committee['active'] != 'Y') $row->addClass('error');
        return $row;
    });

    $table->addColumn('name', __('Name'))
        ->format(function ($committee) {
            $url = './index.php?q=/modules/Committees/committee.php&committeesCommitteeID='.$committee['committeesCommitteeID'];
            return Format::link($url, $committee['name']);
        });

    // ACTIONS
    $table->addActionColumn()
        ->addParam('committeesCommitteeID')
        ->format(function ($committee, $actions) use ($canSignup, $signupActive, $highestManageAction, $committeeGateway, $gibbonPersonID) {
            $actions->addAction('view', __('View'))
                    ->setURL('/modules/Committees/committee.php');

            if ($highestManageAction == 'Manage Committees_all' || 
               ($highestManageAction == 'Manage Committees_myCommitteeAdmin' && $committeeGateway->isPersonCommitteeAdmin($committee['committeesCommitteeID'], $gibbonPersonID))) {
                $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/Committees/committees_manage_edit.php');

                $actions->addAction('members', __m('Manage Members'))
                        ->setIcon('attendance')
                        ->setURL('/modules/Committees/committees_manage_members.php');
            }
            
            if ($canSignup && $signupActive == 'Y') {
                $actions->addAction('leave', __m('Leave Committee'))
                        ->setIcon('iconCross')
                        ->setURL('/modules/Committees/committee_leave.php');
            }
        });

    echo $table->render($committees);
}

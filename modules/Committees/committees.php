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

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;
use Gibbon\Module\Committees\Domain\CommitteeGateway;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__m('View Committees'));

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $committeeGateway = $container->get(CommitteeGateway::class);

    // QUERY
    $criteria = $committeeGateway->newQueryCriteria(true)
        ->sortBy('name', 'ASC')
        ->filterBy('active', 'Y')
        ->fromPOST();

    $committees = $committeeGateway->queryCommittees($criteria, $session->get('gibbonSchoolYearID'));

    $canSignup = isActionAccessible($guid, $connection2, '/modules/Committees/committee_signup.php');
    $signupActive = $container->get(SettingGateway::class)->getSettingByScope('Committees', 'signupActive');

    // GRID TABLE
    $gridRenderer = new GridView($container->get('twig'));
    $table = $container->get(DataTable::class)->setRenderer($gridRenderer);
    $table->setTitle(__m('Committees'));

    if ($canSignup && $signupActive == 'Y') {
        $table->setDescription(Format::alert(__m('Committee sign-up is available. A number next to a committee indicates the currently available seats.'), 'success').'<br/>');
    }

    $table->addMetaData('gridClass', 'content-center justify-center');
    $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/3 text-center mb-4');

    $table->addColumn('logo')
        ->setClass('text-center')
        ->format(function ($committee) use ($canSignup, $signupActive) {
            $url = "./index.php?q=/modules/Committees/committee.php&committeesCommitteeID=".$committee['committeesCommitteeID'];
            $logoURL = !empty($committee['logo'])
                ? $committee['logo']
                : 'themes/Default/img/attendance_large.png';
            $text = Format::userPhoto($logoURL, 125, 'w-full h-full '.(!empty($committee['logo']) ? 'p-1' : 'p-6'));

            $availableSeats = intval($committee['totalSeats']) - intval($committee['usedSeats']);
            if ($canSignup && $signupActive == 'Y' && $committee['signup'] == 'Y' && $availableSeats > 0) {
                $text .= '<div class="badge right-0 top-0 mt-2 mr-2">'.$availableSeats.'</div>';
            }
            
            return Format::link($url, $text, ['class' => 'inline-block relative w-20 h-20 sm:w-32 sm:h-32']);
        });

    $table->addColumn('name')
        ->setClass('text-sm font-bold my-2')
        ->format(function ($committee) {
            $url = "./index.php?q=/modules/Committees/committee.php&committeesCommitteeID=".$committee['committeesCommitteeID'];
            return Format::link($url, $committee['name'], ['class' => '']);
        });

    echo $table->render($committees);
}

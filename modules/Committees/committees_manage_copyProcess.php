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

use Gibbon\Module\Committees\Domain\CommitteeGateway;
use Gibbon\Module\Committees\Domain\CommitteeRoleGateway;


$_POST['address'] = '/modules/Committees/committees_manage.php';

require_once '../../gibbon.php';

$gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? '';
$gibbonSchoolYearIDNext = $_GET['gibbonSchoolYearIDNext'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committees_manage.php&gibbonSchoolYearID='.$gibbonSchoolYearIDNext;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($gibbonSchoolYearID) || empty($gibbonSchoolYearIDNext)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $partialFail = false;

    $committeeGateway = $container->get(CommitteeGateway::class);
    $committeeRoleGateway = $container->get(CommitteeRoleGateway::class);

    $criteria = $committeeGateway->newQueryCriteria();
    $committees = $committeeGateway->queryCommittees($criteria, $gibbonSchoolYearID)->toArray();

    foreach ($committees as $committee) {
        $data = array_intersect_key($committee, array_flip(['name', 'active', 'signup', 'description', 'logo']));
        $data['gibbonSchoolYearID'] = $gibbonSchoolYearIDNext;

        if (!$committeeGateway->unique($data, ['name', 'gibbonSchoolYearID'])) {
            continue;
        }

        $committeesCommitteeID = $committeeGateway->insert($data);

        $roles = $committeeRoleGateway->queryRoles($criteria, $committee['committeesCommitteeID'])->toArray();

        foreach ($roles as $role) {
            $data = array_intersect_key($role, array_flip(['name', 'type', 'active', 'signup', 'seats']));
            $data['committeesCommitteeID'] = $committeesCommitteeID;

            $committeesRoleID = $committeeRoleGateway->insert($data);
        }
    }

    $URL .= $partialFail
        ? "&return=warning1"
        : "&return=success0";

    header("Location: {$URL}");
}

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

use Gibbon\Module\Committees\Domain\CommitteeMemberGateway;

require_once '../../gibbon.php';

$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
$committeesCommitteeID = $_POST['committeesCommitteeID'] ?? '';
$committeesMemberID = $_POST['committeesMemberID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committees_manage_members.php&gibbonSchoolYearID='.$gibbonSchoolYearID.'&committeesCommitteeID='.$committeesCommitteeID;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage_members_delete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} elseif (empty($committeesCommitteeID) || empty($committeesMemberID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $committeeMemberGateway = $container->get(CommitteeMemberGateway::class);
    $values = $committeeMemberGateway->getByID($committeesMemberID);

    if (empty($values)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $deleted = $committeeMemberGateway->delete($committeesMemberID);

    $URL .= !$deleted
        ? '&return=error2'
        : '&return=success0';

    header("Location: {$URL}");
}

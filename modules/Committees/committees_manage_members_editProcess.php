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
use Gibbon\Module\Committees\Domain\CommitteeMemberGateway;

require_once '../../gibbon.php';

$search = $_GET['search'] ?? '';
$gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
$committeesCommitteeID = $_POST['committeesCommitteeID'] ?? '';
$committeesMemberID = $_POST['committeesMemberID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committees_manage_members_edit.php&gibbonSchoolYearID='.$gibbonSchoolYearID.'&committeesCommitteeID='.$committeesCommitteeID.'&committeesMemberID='.$committeesMemberID.'&search='.$search;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committees_manage_members_edit.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $committeeGateway = $container->get(CommitteeGateway::class);
    $committeeRoleGateway = $container->get(CommitteeRoleGateway::class);
    $committeeMemberGateway = $container->get(CommitteeMemberGateway::class);

    $data = [
        'committeesRoleID' => $_POST['committeesRoleID'] ?? '',
    ];

    // Validate the required values are present
    if (empty($committeesCommitteeID) || empty($committeesMemberID) || empty($data['committeesRoleID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate the database relationships exist
    if (!$committeeGateway->exists($committeesCommitteeID) || !$committeeMemberGateway->exists($committeesMemberID) || !$committeeRoleGateway->exists($data['committeesRoleID'])) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the record
    $updated = $committeeMemberGateway->update($committeesMemberID, $data);

    $URL .= !$updated
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}

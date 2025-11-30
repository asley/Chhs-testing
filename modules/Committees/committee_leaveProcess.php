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

$committeesCommitteeID = $_POST['committeesCommitteeID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committee_leave.php&committeesCommitteeID='.$committeesCommitteeID;
$URLSuccess = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committees_my.php';

if (isActionAccessible($guid, $connection2, '/modules/Committees/committee_leave.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $committeeMemberGateway = $container->get(CommitteeMemberGateway::class);

    $data = [
        'committeesCommitteeID' => $committeesCommitteeID,
        'committeesMemberID'    => $_POST['committeesMemberID'] ?? '',
        'gibbonPersonID'        => $session->get('gibbonPersonID'),
    ];

    // Validate the required values are present
    if (empty($data['committeesCommitteeID']) || empty($data['committeesMemberID']) || empty($data['gibbonPersonID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record exists
    if (!$committeeMemberGateway->exists($data['committeesMemberID'])) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Delete the record
    $deleted = $committeeMemberGateway->delete($data['committeesMemberID']);
    if (!$deleted) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $URLSuccess .= "&return=success0";
    header("Location: {$URLSuccess}");
}

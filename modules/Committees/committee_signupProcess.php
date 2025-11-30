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
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$committeesCommitteeID = $_POST['committeesCommitteeID'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Committees/committee.php&committeesCommitteeID='.$committeesCommitteeID;

if (isActionAccessible($guid, $connection2, '/modules/Committees/committee_signup.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $committeeGateway = $container->get(CommitteeGateway::class);
    $committeeRoleGateway = $container->get(CommitteeRoleGateway::class);
    $committeeMemberGateway = $container->get(CommitteeMemberGateway::class);

    $data = [
        'committeesCommitteeID' => $committeesCommitteeID,
        'committeesRoleID'      => $_POST['committeesRoleID'] ?? '',
        'gibbonPersonID'        => $session->get('gibbonPersonID'),
    ];

    // Validate the required values are present
    if (empty($data['committeesCommitteeID']) || empty($data['committeesRoleID']) || empty($data['gibbonPersonID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    $committee = $committeeGateway->getByID($data['committeesCommitteeID']);
    $role = $committeeRoleGateway->getByID($data['committeesRoleID']);
    
    // Validate the database relationships exist
    if (empty($committee) || empty($role)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    $settingGateway = $container->get(SettingGateway::class);

    // Ensure the committee signup is available
    $signupActive = $settingGateway->getSettingByScope('Committees', 'signupActive');
    if ($signupActive != 'Y' || $committee['signup'] != 'Y') {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    // Ensure there are seats available
    $memberCount = $committeeRoleGateway->getMemberCountByRole($data['committeesRoleID']);
    $availableSeats = intval($role['seats']) - $memberCount;
    if ($role['signup'] != 'Y' || $availableSeats <= 0) {
        $URL .= '&return=error4';
        header("Location: {$URL}");
        exit;
    }

    // Ensure the person has not exceeded their max sign-ups
    $signupMaximum = $settingGateway->getSettingByScope('Committees', 'signupMaximum');
    $roleCount = $committeeRoleGateway->getRoleCountByPerson($session->get('gibbonSchoolYearID'), $data['gibbonPersonID']);
    if ($roleCount >= $signupMaximum) {
        $URL .= '&return=error5';
        header("Location: {$URL}");
        exit;
    }

    // Validate that this record is unique
    if (!$committeeMemberGateway->unique($data, ['gibbonPersonID', 'committeesCommitteeID'])) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Create the record
    $committeesMemberID = $committeeMemberGateway->insert($data);

    $URL .= !$committeesMemberID
        ? "&return=error2"
        : "&return=success0";

    header("Location: {$URL}");
}

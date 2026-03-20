<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

require_once '../../gibbon.php';

$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$gibbonInternalAssessmentColumnID = $_GET['gibbonInternalAssessmentColumnID'] ?? '';
$action = $_GET['action'] ?? ''; // 'lock' or 'unlock'

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'] ?? '')."/internalAssessment_manage.php&gibbonCourseClassID=$gibbonCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/Formal Assessment/internalAssessment_manage.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

if (empty($gibbonInternalAssessmentColumnID) || empty($gibbonCourseClassID) || !in_array($action, ['lock', 'unlock'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$locked = ($action === 'lock') ? 'Y' : 'N';

try {
    $data = ['locked' => $locked, 'gibbonInternalAssessmentColumnID' => $gibbonInternalAssessmentColumnID, 'gibbonCourseClassID' => $gibbonCourseClassID];
    $sql = 'UPDATE gibbonInternalAssessmentColumn SET locked=:locked WHERE gibbonInternalAssessmentColumnID=:gibbonInternalAssessmentColumnID AND gibbonCourseClassID=:gibbonCourseClassID';
    $result = $connection2->prepare($sql);
    $result->execute($data);
} catch (PDOException $e) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

$URL .= '&return=success0';
header("Location: {$URL}");

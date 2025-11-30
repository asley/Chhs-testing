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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @DEPRECATED - V1.5.04
 * Not used anywhere.
 * 
 */
function readMyPoints($dbh, $studentID, $yearID) {
    $data = array(
        'studentID' => $studentID,
        'yearID' => $yearID
    );
    $sql = "SELECT
        hpPointStudent.points,
        CONCAT(LEFT(gibbonPerson.preferredName,1), '.', gibbonPerson.surname) AS teacherName,
        hpPointStudent.awardedDate,
        hpCategory.categoryName
        FROM hpPointStudent
        INNER JOIN hpCategory
        ON hpCategory.categoryID = hpPointStudent.categoryID
        INNER JOIN gibbonPerson
        ON gibbonPerson.gibbonPersonID = hpPointStudent.awardedBy
        WHERE hpPointStudent.studentID = :studentID
        AND hpPointStudent.yearID = :yearID
        ORDER BY hpPointStudent.awardedDate DESC";
    $rs = $dbh->prepare($sql);
    $rs->execute($data);
    return $rs;
}

// Pivots the table returned in readEventsList() to have houses as columns and an event associated to them.
function parseEventsList($rs) {
    $oldTable = $rs->fetchAll();
    $uniqueHouses = array_unique(array_column($oldTable, 'houseName'));
    $sortValues = array_column($oldTable, 'reason'); 
    array_multisort($sortValues, SORT_ASC, $oldTable);
    $newTable = [];

    foreach ($oldTable as $row) {
        if (empty($row['reason'])) continue;

        if(!empty($newTable[$row['reason']][$row['houseName']])) {
            $newTable[$row['reason']][$row['houseName']] += $row['individualPoints'];
        } else {
            $newTable[$row['reason']][$row['houseName']] = $row['individualPoints'];
        }
        $newTable[$row['reason']]['awardedDate'] = $row['reason'];
        $newTable[$row['reason']]['reason'] = $row['reason'];
    }

    $sortValues = array_column($newTable, 'awardedDate'); 
    array_multisort($sortValues, SORT_DESC, $newTable);

    return ['events' => $newTable, 'houses' => $uniqueHouses];
    
}

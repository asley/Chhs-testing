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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


/* use Gibbon\Domain\Student\StudentGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Domain\School\FormGroupGateway;
 */

 use Gibbon\Database\Connection;
 
 /**
  * Fetch all distinct form groups.
  *
  * @param Connection $connection2 The Gibbon database connection.
  * @return array The list of form groups with their IDs and names.
  */
 function getFormGroups(PDO $connection2) {
     $pdo = $connection2; // Access the raw PDO connection
 
     if (!$pdo instanceof PDO) {
         throw new Exception("Error: Failed to get a valid PDO connection.");
     }
 
     $query = "
         SELECT gibbonFormGroup.gibbonFormGroupID, gibbonFormGroup.name AS formGroupName
         FROM gibbonFormGroup
         ORDER BY gibbonFormGroup.name
     ";
 
     try {
         $stmt = $pdo->prepare($query);
         $stmt->execute();
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     } catch (Exception $e) {
         error_log("Error in getFormGroups: " . $e->getMessage());
         return [];
     }
 }
 
 /**
  * Fetch all distinct year groups.
  *
  * @param Connection $connection2 The Gibbon database connection.
  * @return array The list of year groups with their IDs and names.
  */
 function getCustomYearGroups(PDO $connection2) {
     $pdo = $connection2; // Access the raw PDO connection
 
     if ($connection2 instanceof Gibbon\Database\Connection) {
        $pdo = $connection2->getConnection(); // Get the raw PDO connection
    } elseif ($connection2 instanceof PDO) {
        $pdo = $connection2; // Already a PDO object
    } else {
        throw new Exception("Invalid connection object passed to getCustomYearGroups.");
    }
 
     $query = "
         SELECT gibbonYearGroup.gibbonYearGroupID, gibbonYearGroup.name AS yearGroupName
         FROM gibbonYearGroup
         ORDER BY gibbonYearGroup.name
     ";
 
     try {
         $stmt = $pdo->prepare($query);
         $stmt->execute();
         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     } catch (Exception $e) {
         error_log("Error in getCustomYearGroups: " . $e->getMessage());
         return [];
     }
 }
 
 ?>
 
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

$sql = [];
$count = 0;

// v1.0.1 - Fix missing permissions for "View Grade Analytics" action
$sql[$count][0] = '1.0.1';
$sql[$count][1] = "-- Add missing permissions for View Grade Analytics action
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
SELECT r.gibbonRoleID, a.gibbonActionID
FROM gibbonRole r
CROSS JOIN gibbonAction a
INNER JOIN gibbonModule m ON a.gibbonModuleID = m.gibbonModuleID
WHERE m.name = 'GradeAnalytics'
  AND a.name = 'View Grade Analytics'
  AND r.name IN ('Admin', 'Teacher', 'Principal', 'Vice Principal', 'Head of Department')
  AND NOT EXISTS (
    SELECT 1 FROM gibbonPermission p
    WHERE p.gibbonRoleID = r.gibbonRoleID
    AND p.gibbonActionID = a.gibbonActionID
  );";

$count++;

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

// v1.0.4 - Add Reporting Cycle Grade Report action and permissions
$sql[$count][0] = '1.0.4';
$sql[$count][1] = "-- Add Reporting Cycle Grade Report action
INSERT INTO gibbonAction (
    gibbonModuleID,
    name,
    precedence,
    category,
    description,
    URLList,
    entryURL,
    entrySidebar,
    menuShow,
    categoryPermissionStaff,
    categoryPermissionStudent,
    categoryPermissionParent,
    categoryPermissionOther
)
SELECT
    m.gibbonModuleID,
    'Reporting Cycle Grade Report',
    5,
    'Reports',
    'Print or download student grades by reporting cycle and assessment.',
    'reportingCycleGradeReport.php,reportingCycleGradeReport_print.php',
    'reportingCycleGradeReport.php',
    'Y',
    'Y',
    'Y',
    'N',
    'N',
    'N'
FROM gibbonModule m
WHERE m.name = 'GradeAnalytics'
  AND NOT EXISTS (
    SELECT 1
    FROM gibbonAction a
    WHERE a.gibbonModuleID = m.gibbonModuleID
      AND a.name = 'Reporting Cycle Grade Report'
  );end

-- Add permissions for Reporting Cycle Grade Report
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
SELECT r.gibbonRoleID, a.gibbonActionID
FROM gibbonRole r
JOIN gibbonModule m ON m.name = 'GradeAnalytics'
JOIN gibbonAction a ON a.gibbonModuleID = m.gibbonModuleID
WHERE a.name = 'Reporting Cycle Grade Report'
  AND r.name IN ('Admin', 'Teacher', 'Principal', 'Vice Principal', 'Head of Department')
  AND NOT EXISTS (
    SELECT 1
    FROM gibbonPermission p
    WHERE p.gibbonRoleID = r.gibbonRoleID
      AND p.gibbonActionID = a.gibbonActionID
  );";

$count++;

// v1.0.3 - Add Bulk Broadsheet Export action and permissions
$sql[$count][0] = '1.0.3';
$sql[$count][1] = "-- Add Bulk Broadsheet Export action
INSERT INTO gibbonAction (
    gibbonModuleID,
    name,
    precedence,
    category,
    description,
    URLList,
    entryURL,
    entrySidebar,
    menuShow,
    categoryPermissionStaff,
    categoryPermissionStudent,
    categoryPermissionParent,
    categoryPermissionOther
)
SELECT
    m.gibbonModuleID,
    'Bulk Broadsheet Export',
    4,
    'Reports',
    'Export multiple broadsheets at once as a ZIP file.',
    'broadsheetBulkExport.php,broadsheetBulkExportProcess.php',
    'broadsheetBulkExport.php',
    'Y',
    'Y',
    'Y',
    'N',
    'N',
    'N'
FROM gibbonModule m
WHERE m.name = 'GradeAnalytics'
  AND NOT EXISTS (
    SELECT 1
    FROM gibbonAction a
    WHERE a.gibbonModuleID = m.gibbonModuleID
      AND a.name = 'Bulk Broadsheet Export'
  );end
-- Add permissions for Bulk Broadsheet Export
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID)
SELECT r.gibbonRoleID, a.gibbonActionID
FROM gibbonRole r
JOIN gibbonModule m ON m.name = 'GradeAnalytics'
JOIN gibbonAction a ON a.gibbonModuleID = m.gibbonModuleID
WHERE a.name = 'Bulk Broadsheet Export'
  AND r.name IN ('Admin', 'Teacher', 'Principal', 'Vice Principal', 'Head of Department')
  AND NOT EXISTS (
    SELECT 1
    FROM gibbonPermission p
    WHERE p.gibbonRoleID = r.gibbonRoleID
      AND p.gibbonActionID = a.gibbonActionID
  );";

$count++;

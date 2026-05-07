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

// Basic variables
$name        = 'Principal Dashboard';
$description = 'A central data hub for principals: markbook grades, internal assessments, attendance, and at-risk student analytics with interactive, filterable charts.';
$entryURL    = 'dashboard.php';
$type        = 'Additional';
$category    = 'Dashboard';
$version     = '1.0.1';
$author      = 'Asley Smith';
$url         = 'https://gibbonedu.org';

// Action rows
$actionRows[] = [
    'name'                      => 'View Principal Dashboard',
    'precedence'                => '0',
    'category'                  => 'Dashboard',
    'description'               => 'View the principal central dashboard with grades, assessments, attendance and at-risk analytics.',
    'URLList'                   => 'dashboard.php',
    'entryURL'                  => 'dashboard.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

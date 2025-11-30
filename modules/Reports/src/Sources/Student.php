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

namespace Gibbon\Module\Reports\Sources;

use Gibbon\Module\Reports\DataSource;

class Student extends DataSource
{
    /**
     * Convert percentage grade to letter grade
     * @param float|string $percentage
     * @return string|null
     */
    public static function percentageToLetterGrade($percentage)
    {
        if ($percentage === null || $percentage === '') {
            return null;
        }

        $pct = floatval($percentage);

        if ($pct >= 90) return 'A+';
        if ($pct >= 85) return 'A';
        if ($pct >= 80) return 'A-';
        if ($pct >= 75) return 'B+';
        if ($pct >= 70) return 'B';
        if ($pct >= 65) return 'B-';
        if ($pct >= 60) return 'C+';
        if ($pct >= 55) return 'C';
        if ($pct >= 50) return 'C-';
        if ($pct >= 45) return 'D';

        return 'F';
    }

    /**
     * Convert letter grade to GPA points (4.0 scale)
     * @param string $letterGrade
     * @return float|null
     */
    public static function letterGradeToGPA($letterGrade)
    {
        $gpaScale = [
            'A+' => 4.00,
            'A'  => 4.00,
            'A-' => 3.70,
            'B+' => 3.30,
            'B'  => 3.00,
            'B-' => 2.70,
            'C+' => 2.30,
            'C'  => 2.00,
            'C-' => 1.70,
            'D'  => 1.00,
            'F'  => 0.00,
        ];

        return $gpaScale[$letterGrade] ?? null;
    }

    /**
     * Convert percentage directly to GPA points
     * @param float|string $percentage
     * @return float|null
     */
    public static function percentageToGPA($percentage)
    {
        $letterGrade = self::percentageToLetterGrade($percentage);
        return self::letterGradeToGPA($letterGrade);
    }

    public function getSchema()
    {
        $gender = rand(0, 99) > 50 ? 'female' : 'male';
        return [
            'gibbonPersonID'     => ['randomNumber', 8],
            'surname'            => ['lastName'],
            'firstName'          => ['firstName', $gender],
            'preferredName'      => ['sameAs', 'firstName'],
            'officialName'       => ['sameAs', 'firstName surname'],
            'image_240'          => $gender == 'female' ? 'modules/Reports/img/placeholder-female.jpg' : 'modules/Reports/img/placeholder-male.jpg',
            'dob'                => ['date', 'Y-m-d'],
            'email'              => ['safeEmail'],
            'nameInCharacters'   => 'TEST',
            'studentID'          => ['randomNumber', 8],
            'dayType'            => ['randomElement', ['Full Day', 'Half Day']],

            '#'                  => ['randomDigit'], // Random Year Group Number
            '%'                  => ['randomDigit'], // Random Form Group Number

            'gibbonYearGroupID'  => 0,
            'yearGroupName'      => ['sameAs', 'Year #'],
            'yearGroupNameShort' => ['sameAs', 'Y0#'],

            'gibbonFormGroupID'  => 0,
            'formGroupName'      => ['sameAs', 'Y0#.%'],
            'formGroupNameShort' => ['sameAs', 'Y0#.%'],
        ];
    }

    public function getData($ids = [])
    {
        $data = ['gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID']];
        $sql = "SELECT
                gibbonPerson.gibbonPersonID,
                gibbonPerson.surname,
                gibbonPerson.firstName,
                gibbonPerson.preferredName,
                gibbonPerson.officialName,
                CONCAT(gibbonPerson.preferredName, ' ', gibbonPerson.surname) as fullName,
                gibbonPerson.image_240,
                gibbonPerson.dob,
                gibbonPerson.email,
                gibbonPerson.nameInCharacters,
                gibbonPerson.studentID,
                gibbonPerson.dayType,
                gibbonPerson.gender,
                gibbonPerson.status,
                gibbonYearGroup.gibbonYearGroupID,
                gibbonYearGroup.name as yearGroupName,
                gibbonYearGroup.nameShort as yearGroupNameShort,
                gibbonFormGroup.gibbonFormGroupID,
                gibbonFormGroup.name as formGroupName,
                gibbonFormGroup.nameShort as formGroupNameShort
                FROM gibbonStudentEnrolment
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                JOIN gibbonYearGroup ON (gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                WHERE gibbonStudentEnrolmentID=:gibbonStudentEnrolmentID";

        return $this->db()->selectOne($sql, $data);
    }
}

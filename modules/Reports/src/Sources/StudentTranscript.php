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

class StudentTranscript extends DataSource
{
    public function getSchema()
    {
        return [
            0 => [
                'schoolYearName'    => '2023-24',
                'yearGroupName'     => 'Year 10',
                'terms' => [
                    0 => [
                        'termName'  => 'Term 1',
                        'firstDay'  => '2023-09-01',
                        'lastDay'   => '2023-12-15',
                        'courses' => [
                            0 => [
                                'courseName'    => 'Mathematics',
                                'courseCode'    => 'MATH10',
                                'creditHours'   => 1.0,
                                'finalGrade'    => 'A',
                                'gpaPoints'     => 4.0,
                                'teacherName'   => 'Mr. Smith',
                            ],
                            1 => [
                                'courseName'    => 'English Literature',
                                'courseCode'    => 'ENG10',
                                'creditHours'   => 1.0,
                                'finalGrade'    => 'B+',
                                'gpaPoints'     => 3.3,
                                'teacherName'   => 'Ms. Johnson',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getData($ids = [])
    {
        // Validate required parameters
        if (empty($ids['gibbonStudentEnrolmentID'])) {
            return [];
        }

        $data = array(
            'gibbonStudentEnrolmentID' => $ids['gibbonStudentEnrolmentID'],
            'today' => date('Y-m-d')
        );

        // Debug parameter binding (commented out to prevent header issues)
        // error_log('StudentTranscript data parameters: ' . print_r($data, true));
        
        // Get student enrollment history with year groups and terms
        $sql = "SELECT DISTINCT 
                    sy.gibbonSchoolYearID,
                    sy.name as schoolYearName,
                    sy.sequenceNumber as schoolYearSequence,
                    yg.name as yearGroupName,
                    yg.sequenceNumber as yearGroupSequence,
                    syt.gibbonSchoolYearTermID,
                    syt.name as termName,
                    syt.nameShort as termNameShort,
                    syt.sequenceNumber as termSequence,
                    syt.firstDay,
                    syt.lastDay
                FROM gibbonStudentEnrolment se
                JOIN gibbonSchoolYear sy ON sy.gibbonSchoolYearID = se.gibbonSchoolYearID
                JOIN gibbonYearGroup yg ON yg.gibbonYearGroupID = se.gibbonYearGroupID
                JOIN gibbonSchoolYearTerm syt ON syt.gibbonSchoolYearID = sy.gibbonSchoolYearID
                WHERE se.gibbonPersonID = (
                    SELECT gibbonPersonID 
                    FROM gibbonStudentEnrolment 
                    WHERE gibbonStudentEnrolmentID = :gibbonStudentEnrolmentID
                )
                ORDER BY sy.sequenceNumber ASC, syt.sequenceNumber ASC";

        // Check if database connection is available
        if (!$this->db()) {
            return [];
        }

        try {
            // Only pass the parameter needed for this query
            $enrollmentParams = array('gibbonStudentEnrolmentID' => $data['gibbonStudentEnrolmentID']);
            $result = $this->db()->select($sql, $enrollmentParams);
            if (!$result) {
                return [];
            }

            // Additional check for result validity
            if (!method_exists($result, 'fetchAll')) {
                @error_log('StudentTranscript: Invalid result object returned');
                return [];
            }

            $enrollmentData = $result->fetchAll();
            if (!$enrollmentData) {
                $enrollmentData = [];
            }
        } catch (\Error $e) {
            // Handle fatal errors including PDO initialization errors
            @error_log('StudentTranscript fatal error: ' . $e->getMessage());
            return [];
        } catch (\PDOException $e) {
            // Handle PDO specific errors
            @error_log('StudentTranscript PDO error: ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            // Handle any other database errors
            @error_log('StudentTranscript database error: ' . $e->getMessage());
            return [];
        }
        
        // Get course grades using Internal Assessment data
        $gradeSql = "SELECT 
                        sy.name as schoolYearName,
                        yg.name as yearGroupName,
                        syt.name as termName,
                        syt.firstDay,
                        syt.lastDay,
                        c.name as courseName,
                        c.nameShort as courseCode,
                        cc.name as className,
                        1.0 as creditHours,
                        AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) as avgPercentage,
                        CASE
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 90 THEN 'A+'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 85 THEN 'A'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 80 THEN 'A-'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 75 THEN 'B+'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 70 THEN 'B'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 65 THEN 'B-'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 60 THEN 'C+'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 55 THEN 'C'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 50 THEN 'C-'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 45 THEN 'D'
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) IS NULL THEN NULL
                            ELSE 'F'
                        END as finalGrade,
                        CASE
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 90 THEN 4.00
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 85 THEN 4.00
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 80 THEN 3.70
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 75 THEN 3.30
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 70 THEN 3.00
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 65 THEN 2.70
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 60 THEN 2.30
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 55 THEN 2.00
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 50 THEN 1.70
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) >= 45 THEN 1.00
                            WHEN AVG(CAST(iae.attainmentValue AS DECIMAL(5,2))) IS NULL THEN NULL
                            ELSE 0.00
                        END as gpaPoints,
                        GROUP_CONCAT(DISTINCT CONCAT(p.preferredName, ' ', p.surname) ORDER BY p.surname, p.preferredName SEPARATOR ', ') as teacherName
                FROM gibbonStudentEnrolment se
                JOIN gibbonSchoolYear sy ON sy.gibbonSchoolYearID = se.gibbonSchoolYearID
                JOIN gibbonYearGroup yg ON yg.gibbonYearGroupID = se.gibbonYearGroupID
                JOIN gibbonSchoolYearTerm syt ON syt.gibbonSchoolYearID = sy.gibbonSchoolYearID
                JOIN gibbonInternalAssessmentEntry iae ON iae.gibbonPersonIDStudent = se.gibbonPersonID
                JOIN gibbonInternalAssessmentColumn iac ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
                JOIN gibbonCourseClassPerson ccp ON ccp.gibbonCourseClassID = iac.gibbonCourseClassID
                    AND ccp.gibbonPersonID = se.gibbonPersonID AND ccp.role = 'Student'
                JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
                JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID AND c.gibbonSchoolYearID = sy.gibbonSchoolYearID
                LEFT JOIN gibbonCourseClassPerson tccp ON tccp.gibbonCourseClassID = cc.gibbonCourseClassID
                    AND tccp.role = 'Teacher'
                LEFT JOIN gibbonPerson p ON p.gibbonPersonID = tccp.gibbonPersonID
                WHERE se.gibbonPersonID = (
                    SELECT gibbonPersonID
                    FROM gibbonStudentEnrolment
                    WHERE gibbonStudentEnrolmentID = :gibbonStudentEnrolmentID
                )
                AND iac.complete = 'Y'
                AND iac.completeDate <= :today
                AND iac.completeDate BETWEEN syt.firstDay AND syt.lastDay
                AND iac.type = 'Exam'
                GROUP BY
                    sy.gibbonSchoolYearID,
                    sy.name,
                    yg.name,
                    syt.gibbonSchoolYearTermID,
                    syt.name,
                    syt.firstDay,
                    syt.lastDay,
                    c.gibbonCourseID,
                    c.name,
                    c.nameShort,
                    cc.gibbonCourseClassID,
                    cc.name
                HAVING COUNT(iae.gibbonInternalAssessmentEntryID) > 0
                ORDER BY sy.sequenceNumber ASC, syt.sequenceNumber ASC, c.orderBy ASC, c.nameShort ASC";

        // Execute grades query with error handling
        try {
            // Pass both parameters needed for this query
            $gradeParams = array(
                'gibbonStudentEnrolmentID' => $data['gibbonStudentEnrolmentID'],
                'today' => $data['today']
            );
            $result = $this->db()->select($gradeSql, $gradeParams);
            if (!$result) {
                $gradeResults = [];
            } else {
                // Additional check for result validity
                if (!method_exists($result, 'fetchAll')) {
                    @error_log('StudentTranscript grades: Invalid result object returned');
                    $gradeResults = [];
                } else {
                    $gradeResults = $result->fetchAll();
                    if (!$gradeResults) {
                        $gradeResults = [];
                    }
                }
            }
        } catch (\Error $e) {
            // Handle fatal errors including PDO initialization errors
            @error_log('StudentTranscript grades fatal error: ' . $e->getMessage());
            $gradeResults = [];
        } catch (\PDOException $e) {
            // Handle PDO specific errors
            @error_log('StudentTranscript grades PDO error: ' . $e->getMessage());
            $gradeResults = [];
        } catch (\Exception $e) {
            // Handle any other database errors
            @error_log('StudentTranscript grades query error: ' . $e->getMessage());
            $gradeResults = [];
        }

        // Organize data by year and term
        $transcriptData = [];
        $yearTerms = [];

        // First pass: organize by year and term structure
        foreach ($enrollmentData as $enrollment) {
            $yearKey = $enrollment['schoolYearName'];
            $termKey = $enrollment['termName'];

            if (!isset($yearTerms[$yearKey])) {
                $yearTerms[$yearKey] = [
                    'schoolYearName' => $enrollment['schoolYearName'],
                    'yearGroupName'  => $enrollment['yearGroupName'],
                    'terms' => []
                ];
            }

            if (!isset($yearTerms[$yearKey]['terms'][$termKey])) {
                $yearTerms[$yearKey]['terms'][$termKey] = [
                    'termName'  => $enrollment['termName'],
                    'firstDay'  => $enrollment['firstDay'],
                    'lastDay'   => $enrollment['lastDay'],
                    'courses'   => []
                ];
            }
        }

        // Second pass: add course grades
        foreach ($gradeResults as $grade) {
            $yearKey = $grade['schoolYearName'];
            $termKey = $grade['termName'];

            if (isset($yearTerms[$yearKey]['terms'][$termKey])) {
                $yearTerms[$yearKey]['terms'][$termKey]['courses'][] = [
                    'courseName'    => $grade['courseName'],
                    'courseCode'    => $grade['courseCode'],
                    'creditHours'   => floatval($grade['creditHours']),
                    'avgPercentage' => $grade['avgPercentage'] ? round($grade['avgPercentage'], 1) : null,
                    'finalGrade'    => $grade['finalGrade'] ?: 'In Progress',
                    'status'        => '',
                    'hasFinalGrade' => $grade['finalGrade'] ? 1 : 0,
                    'gpaPoints'     => floatval($grade['gpaPoints']),
                    'teacherName'   => $grade['teacherName']
                ];
            }
        }

        // Convert to final format
        foreach ($yearTerms as $year) {
            $year['terms'] = array_values($year['terms']);
            $transcriptData[] = $year;
        }

        return $transcriptData;
    }
}

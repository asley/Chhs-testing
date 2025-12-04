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

namespace Gibbon\Module\GradeAnalytics;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Grade Analytics Gateway
 *
 * @version v29
 * @since   v29
 */
class GradeAnalyticsGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonInternalAssessmentEntry';

    /**
     * Get all courses for the current school year
     */
    public function selectCourses($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT gibbonCourseID as value, name
                FROM gibbonCourse
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get all form groups for the current school year
     */
    public function selectFormGroups($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT gibbonFormGroupID as value, name
                FROM gibbonFormGroup
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get all teaching staff
     */
    public function selectTeachers()
    {
        $sql = "SELECT DISTINCT p.gibbonPersonID as value,
                CONCAT(p.preferredName, ' ', p.surname) as name
                FROM gibbonPerson p
                JOIN gibbonStaff s ON p.gibbonPersonID = s.gibbonPersonID
                WHERE p.status = 'Full'
                AND s.type = 'Teaching'
                ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql);
    }

    /**
     * Get year groups with enrolled students
     */
    public function selectYearGroups($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT DISTINCT yg.gibbonYearGroupID as value, yg.name
                FROM gibbonStudentEnrolment se
                JOIN gibbonYearGroup yg ON se.gibbonYearGroupID = yg.gibbonYearGroupID
                WHERE se.gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY yg.sequenceNumber";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get distinct assessment types from internal assessments
     */
    public function selectAssessmentTypes()
    {
        $sql = "SELECT DISTINCT type
                FROM gibbonInternalAssessmentColumn
                WHERE type IS NOT NULL AND type != ''
                ORDER BY type";

        return $this->db()->select($sql);
    }

    /**
     * Get reporting cycles (terms) for the current school year
     */
    public function selectReportingCycles($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT gibbonReportingCycleID as value, name
                FROM gibbonReportingCycle
                WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                ORDER BY sequenceNumber, name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get distinct assessment names from internal assessments
     */
    public function selectAssessmentColumns($gibbonSchoolYearID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT DISTINCT
                    iac.name as value,
                    iac.name as name
                FROM gibbonInternalAssessmentColumn iac
                JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
                JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
                WHERE c.gibbonSchoolYearID = :gibbonSchoolYearID
                AND iac.name IS NOT NULL
                AND iac.name != ''
                ORDER BY iac.name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get grade distribution data with filters
     */
    public function selectGradeDistribution($gibbonSchoolYearID, $filters = [])
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
        $whereConditions = ['c.gibbonSchoolYearID = :gibbonSchoolYearID', "ct.role = 'Teacher'", 'e.attainmentValue IS NOT NULL'];

        if (!empty($filters['courseID'])) {
            $data['courseID'] = $filters['courseID'];
            $whereConditions[] = 'c.gibbonCourseID = :courseID';
        }

        if (!empty($filters['formGroupID'])) {
            if (\is_array($filters['formGroupID'])) {
                $placeholders = [];
                foreach ($filters['formGroupID'] as $index => $id) {
                    $placeholders[] = ":formGroupID{$index}";
                    $data["formGroupID{$index}"] = $id;
                }
                $whereConditions[] = 'se.gibbonFormGroupID IN ('.implode(',', $placeholders).')';
            } else {
                $data['formGroupID'] = $filters['formGroupID'];
                $whereConditions[] = 'se.gibbonFormGroupID = :formGroupID';
            }
        }

        if (!empty($filters['teacherID'])) {
            $data['teacherID'] = $filters['teacherID'];
            $whereConditions[] = 'ct.gibbonPersonID = :teacherID';
        }

        if (!empty($filters['yearGroup'])) {
            $data['yearGroup'] = $filters['yearGroup'];
            $whereConditions[] = 'se.gibbonYearGroupID = :yearGroup';
        }

        if (!empty($filters['assessmentType'])) {
            $data['assessmentType'] = $filters['assessmentType'];
            $whereConditions[] = 'iac.type = :assessmentType';
        }

        $whereSQL = implode(' AND ', $whereConditions);

        $sql = "WITH grade_ranges AS (
                    SELECT 'A' as grade UNION SELECT 'B' UNION SELECT 'C' UNION SELECT 'D' UNION SELECT 'F'
                ),
                student_grades AS (
                    SELECT
                        e.gibbonInternalAssessmentEntryID,
                        CASE
                            WHEN e.attainmentValue >= 85 THEN 'A'
                            WHEN e.attainmentValue >= 70 THEN 'B'
                            WHEN e.attainmentValue >= 55 THEN 'C'
                            WHEN e.attainmentValue >= 40 THEN 'D'
                            ELSE 'F'
                        END as grade
                    FROM gibbonInternalAssessmentEntry e
                    JOIN gibbonInternalAssessmentColumn iac ON e.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
                    JOIN gibbonCourseClass cc ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
                    JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
                    LEFT JOIN gibbonStudentEnrolment se ON e.gibbonPersonIDStudent = se.gibbonPersonID
                        AND se.gibbonSchoolYearID = c.gibbonSchoolYearID
                    LEFT JOIN gibbonCourseClassPerson ct ON cc.gibbonCourseClassID = ct.gibbonCourseClassID
                    WHERE {$whereSQL}
                )
                SELECT
                    gr.grade,
                    COALESCE(COUNT(DISTINCT sg.gibbonInternalAssessmentEntryID), 0) as count
                FROM grade_ranges gr
                LEFT JOIN student_grades sg ON gr.grade = sg.grade
                GROUP BY gr.grade
                ORDER BY
                    CASE gr.grade
                        WHEN 'A' THEN 1
                        WHEN 'B' THEN 2
                        WHEN 'C' THEN 3
                        WHEN 'D' THEN 4
                        WHEN 'F' THEN 5
                    END";

        return $this->db()->select($sql, $data);
    }

    /**
     * Query students for prize giving report with criteria
     */
    public function queryPrizeGivingReport(QueryCriteria $criteria, $gibbonSchoolYearID, $filters = [])
    {
        $query = $this
            ->newQuery()
            ->from('gibbonPerson s')
            ->cols([
                's.gibbonPersonID',
                's.preferredName',
                's.surname',
                'fg.name as formGroup',
                'c.name as courseName',
                'iac.name as assessmentName',
                'me.attainmentValue as grade'
            ])
            ->innerJoin('gibbonStudentEnrolment se', 'se.gibbonPersonID = s.gibbonPersonID')
            ->innerJoin('gibbonFormGroup fg', 'fg.gibbonFormGroupID = se.gibbonFormGroupID')
            ->innerJoin('gibbonCourseClassPerson ccp', 'ccp.gibbonPersonID = s.gibbonPersonID')
            ->innerJoin('gibbonCourseClass cc', 'cc.gibbonCourseClassID = ccp.gibbonCourseClassID')
            ->innerJoin('gibbonCourse c', 'c.gibbonCourseID = cc.gibbonCourseID')
            ->innerJoin('gibbonInternalAssessmentColumn iac', 'iac.gibbonCourseClassID = cc.gibbonCourseClassID')
            ->innerJoin('gibbonInternalAssessmentEntry me', 'me.gibbonPersonIDStudent = s.gibbonPersonID AND me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID')
            ->where("s.status = 'Full'")
            ->where("ccp.role = 'Student'")
            ->where('se.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->where('c.gibbonSchoolYearID = :gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        // Apply filters
        if (!empty($filters['courseID'])) {
            $query->where('c.gibbonCourseID = :courseID')
                  ->bindValue('courseID', $filters['courseID']);
        }

        if (!empty($filters['formGroupID'])) {
            if (\is_array($filters['formGroupID'])) {
                $placeholders = [];
                foreach ($filters['formGroupID'] as $index => $id) {
                    $placeholders[] = ":formGroupID{$index}";
                    $query->bindValue("formGroupID{$index}", $id);
                }
                $query->where('fg.gibbonFormGroupID IN ('.implode(',', $placeholders).')');
            } else {
                $query->where('fg.gibbonFormGroupID = :formGroupID')
                      ->bindValue('formGroupID', $filters['formGroupID']);
            }
        }

        if (!empty($filters['yearGroup'])) {
            $query->where('se.gibbonYearGroupID = :yearGroup')
                  ->bindValue('yearGroup', $filters['yearGroup']);
        }

        if (!empty($filters['assessmentType'])) {
            $query->where('iac.type = :assessmentType')
                  ->bindValue('assessmentType', $filters['assessmentType']);
        }

        // Apply grade threshold criteria
        if (!empty($filters['gradeThreshold']) && !empty($filters['operator'])) {
            $validOperators = ['>', '>=', '<', '<=', '='];
            $operator = \in_array($filters['operator'], $validOperators) ? $filters['operator'] : '>';

            $gradeCondition = "(
                CASE
                    WHEN me.attainmentValue REGEXP '^[0-9]+(\\\\.[0-9]+)?%?\$' THEN
                        CAST(REPLACE(me.attainmentValue, '%', '') AS DECIMAL(10,2)) {$operator} :gradeThreshold
                    WHEN me.attainmentValue IN ('A*', 'A+', 'A') THEN
                        90 {$operator} :gradeThreshold
                    WHEN me.attainmentValue IN ('B+', 'B') THEN
                        75 {$operator} :gradeThreshold
                    WHEN me.attainmentValue IN ('C+', 'C') THEN
                        65 {$operator} :gradeThreshold
                    ELSE FALSE
                END
            )";

            $query->where($gradeCondition)
                  ->bindValue('gradeThreshold', floatval($filters['gradeThreshold']));
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get students for prize giving report (non-paginated)
     */
    public function selectPrizeGivingStudents($gibbonSchoolYearID, $filters = [])
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];

        $sql = "SELECT DISTINCT
                s.gibbonPersonID,
                s.preferredName,
                s.surname,
                fg.name as formGroup,
                c.name as courseName,
                iac.name as assessmentName,
                me.attainmentValue as grade
            FROM gibbonPerson s
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonFormGroup fg ON fg.gibbonFormGroupID = se.gibbonFormGroupID
            JOIN gibbonCourseClassPerson ccp ON ccp.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
            JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            JOIN gibbonInternalAssessmentColumn iac ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
            JOIN gibbonInternalAssessmentEntry me ON me.gibbonPersonIDStudent = s.gibbonPersonID
                AND me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
            WHERE s.status = 'Full'
            AND ccp.role = 'Student'
            AND se.gibbonSchoolYearID = :gibbonSchoolYearID
            AND c.gibbonSchoolYearID = :gibbonSchoolYearID";

        if (!empty($filters['courseID'])) {
            $sql .= " AND c.gibbonCourseID = :courseID";
            $data['courseID'] = $filters['courseID'];
        }

        if (!empty($filters['formGroupID'])) {
            if (\is_array($filters['formGroupID'])) {
                $placeholders = [];
                foreach ($filters['formGroupID'] as $index => $id) {
                    $placeholder = ":formGroupID{$index}";
                    $placeholders[] = $placeholder;
                    $data["formGroupID{$index}"] = $id;
                }
                $sql .= " AND fg.gibbonFormGroupID IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND fg.gibbonFormGroupID = :formGroupID";
                $data['formGroupID'] = $filters['formGroupID'];
            }
        }

        if (!empty($filters['yearGroup'])) {
            $sql .= " AND se.gibbonYearGroupID = :yearGroup";
            $data['yearGroup'] = $filters['yearGroup'];
        }

        if (!empty($filters['assessmentType'])) {
            $sql .= " AND iac.type = :assessmentType";
            $data['assessmentType'] = $filters['assessmentType'];
        }

        if (!empty($filters['gradeThreshold']) && !empty($filters['operator'])) {
            $validOperators = ['>', '>=', '<', '<=', '='];
            $operator = \in_array($filters['operator'], $validOperators) ? $filters['operator'] : '>';
            $threshold = \floatval($filters['gradeThreshold']);

            // First, ensure we only get records with valid grades
            $sql .= " AND me.attainmentValue IS NOT NULL AND TRIM(me.attainmentValue) != ''";

            // Create a subquery to convert grades to numeric values, then filter
            $sql .= " AND (
                CASE
                    -- Numeric grades (remove % and spaces, then convert)
                    WHEN me.attainmentValue REGEXP '^[0-9]+(\\.[0-9]+)?%?$' THEN
                        CAST(REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '') AS DECIMAL(10,2))
                    -- Letter grades mapped to numeric equivalents
                    WHEN me.attainmentValue IN ('A*', 'A+', 'A', 'a*', 'a+', 'a') THEN 90
                    WHEN me.attainmentValue IN ('B+', 'b+') THEN 75
                    WHEN me.attainmentValue IN ('B', 'b') THEN 70
                    WHEN me.attainmentValue IN ('C+', 'c+') THEN 65
                    WHEN me.attainmentValue IN ('C', 'c') THEN 55
                    WHEN me.attainmentValue IN ('D+', 'd+') THEN 50
                    WHEN me.attainmentValue IN ('D', 'd') THEN 40
                    WHEN me.attainmentValue IN ('E', 'e', 'F', 'f') THEN 30
                    ELSE NULL
                END IS NOT NULL
                AND
                CASE
                    -- Numeric grades (remove % and spaces, then convert)
                    WHEN me.attainmentValue REGEXP '^[0-9]+(\\.[0-9]+)?%?$' THEN
                        CAST(REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '') AS DECIMAL(10,2))
                    -- Letter grades mapped to numeric equivalents
                    WHEN me.attainmentValue IN ('A*', 'A+', 'A', 'a*', 'a+', 'a') THEN 90
                    WHEN me.attainmentValue IN ('B+', 'b+') THEN 75
                    WHEN me.attainmentValue IN ('B', 'b') THEN 70
                    WHEN me.attainmentValue IN ('C+', 'c+') THEN 65
                    WHEN me.attainmentValue IN ('C', 'c') THEN 55
                    WHEN me.attainmentValue IN ('D+', 'd+') THEN 50
                    WHEN me.attainmentValue IN ('D', 'd') THEN 40
                    WHEN me.attainmentValue IN ('E', 'e', 'F', 'f') THEN 30
                END {$operator} :gradeThreshold
            )";
            $data['gradeThreshold'] = $threshold;
        }

        $sql .= " ORDER BY
            CASE
                WHEN me.attainmentValue REGEXP '^[0-9]+(\\.[0-9]+)?%?$' THEN
                    CAST(REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '') AS DECIMAL(10,2))
                WHEN me.attainmentValue IN ('A*', 'A+', 'A', 'a*', 'a+', 'a') THEN 90
                WHEN me.attainmentValue IN ('B+', 'b+') THEN 75
                WHEN me.attainmentValue IN ('B', 'b') THEN 70
                WHEN me.attainmentValue IN ('C+', 'c+') THEN 65
                WHEN me.attainmentValue IN ('C', 'c') THEN 55
                WHEN me.attainmentValue IN ('D+', 'd+') THEN 50
                WHEN me.attainmentValue IN ('D', 'd') THEN 40
                WHEN me.attainmentValue IN ('E', 'e', 'F', 'f') THEN 30
                ELSE 0
            END DESC, s.surname, s.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get student final averages across all subjects
     * Calculates the average percentage grade for each student across all their enrolled courses
     */
    public function selectStudentAverages($gibbonSchoolYearID, $filters = [])
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];

        $sql = "SELECT
                s.gibbonPersonID,
                s.preferredName,
                s.surname,
                fg.name as formGroup,
                yg.name as yearGroup,
                COUNT(DISTINCT c.gibbonCourseID) as totalCourses,
                ROUND(AVG(
                    CAST(
                        REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '')
                        AS DECIMAL(10,2)
                    )
                ), 2) as finalAverage
            FROM gibbonPerson s
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonFormGroup fg ON fg.gibbonFormGroupID = se.gibbonFormGroupID
            JOIN gibbonYearGroup yg ON yg.gibbonYearGroupID = se.gibbonYearGroupID
            JOIN gibbonCourseClassPerson ccp ON ccp.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
            JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            JOIN gibbonInternalAssessmentColumn iac ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
            JOIN gibbonInternalAssessmentEntry me ON me.gibbonPersonIDStudent = s.gibbonPersonID
                AND me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
            WHERE s.status = 'Full'
            AND ccp.role = 'Student'
            AND se.gibbonSchoolYearID = :gibbonSchoolYearID
            AND c.gibbonSchoolYearID = :gibbonSchoolYearID
            AND me.attainmentValue IS NOT NULL
            AND TRIM(me.attainmentValue) != ''";

        if (!empty($filters['formGroupID'])) {
            if (\is_array($filters['formGroupID'])) {
                $placeholders = [];
                foreach ($filters['formGroupID'] as $index => $id) {
                    $placeholder = ":formGroupID{$index}";
                    $placeholders[] = $placeholder;
                    $data["formGroupID{$index}"] = $id;
                }
                $sql .= " AND fg.gibbonFormGroupID IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND fg.gibbonFormGroupID = :formGroupID";
                $data['formGroupID'] = $filters['formGroupID'];
            }
        }

        if (!empty($filters['yearGroup'])) {
            $sql .= " AND se.gibbonYearGroupID = :yearGroup";
            $data['yearGroup'] = $filters['yearGroup'];
        }

        if (!empty($filters['assessmentType'])) {
            $sql .= " AND iac.type = :assessmentType";
            $data['assessmentType'] = $filters['assessmentType'];
        }

        if (!empty($filters['assessmentName'])) {
            $sql .= " AND iac.name = :assessmentName";
            $data['assessmentName'] = $filters['assessmentName'];
        }

        $sql .= " GROUP BY s.gibbonPersonID, s.preferredName, s.surname, fg.name, yg.name
                  HAVING finalAverage IS NOT NULL
                  ORDER BY finalAverage DESC, s.surname, s.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get individual student's subject-wise grades with average
     */
    public function selectStudentSubjectGrades($gibbonPersonID, $gibbonSchoolYearID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID
        ];

        $sql = "SELECT
                c.name as courseName,
                iac.name as assessmentName,
                iac.type as assessmentType,
                me.attainmentValue as grade,
                CAST(
                    REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '')
                    AS DECIMAL(10,2)
                ) as numericGrade
            FROM gibbonPerson s
            JOIN gibbonCourseClassPerson ccp ON ccp.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
            JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            JOIN gibbonInternalAssessmentColumn iac ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
            JOIN gibbonInternalAssessmentEntry me ON me.gibbonPersonIDStudent = s.gibbonPersonID
                AND me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
            WHERE s.gibbonPersonID = :gibbonPersonID
            AND c.gibbonSchoolYearID = :gibbonSchoolYearID
            AND ccp.role = 'Student'
            AND me.attainmentValue IS NOT NULL
            AND TRIM(me.attainmentValue) != ''
            ORDER BY c.name, iac.name";

        return $this->db()->select($sql, $data);
    }

    /**
     * Get broadsheet data - all students with their grades across all courses
     */
    public function selectBroadsheetData($gibbonSchoolYearID, $filters = [])
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];

        // Build the WHERE clause
        $whereConditions = [
            "s.status = 'Full'",
            "ccp.role = 'Student'",
            "se.gibbonSchoolYearID = :gibbonSchoolYearID",
            "c.gibbonSchoolYearID = :gibbonSchoolYearID",
            "me.attainmentValue IS NOT NULL",
            "TRIM(me.attainmentValue) != ''"
        ];

        if (!empty($filters['formGroupID'])) {
            if (\is_array($filters['formGroupID'])) {
                $placeholders = [];
                foreach ($filters['formGroupID'] as $index => $id) {
                    $placeholder = ":formGroupID{$index}";
                    $placeholders[] = $placeholder;
                    $data["formGroupID{$index}"] = $id;
                }
                $whereConditions[] = "fg.gibbonFormGroupID IN (" . implode(',', $placeholders) . ")";
            } else {
                $whereConditions[] = "fg.gibbonFormGroupID = :formGroupID";
                $data['formGroupID'] = $filters['formGroupID'];
            }
        }

        if (!empty($filters['yearGroup'])) {
            $whereConditions[] = "se.gibbonYearGroupID = :yearGroup";
            $data['yearGroup'] = $filters['yearGroup'];
        }

        if (!empty($filters['teacherID'])) {
            $whereConditions[] = "ct.gibbonPersonID = :teacherID";
            $data['teacherID'] = $filters['teacherID'];
        }

        if (!empty($filters['assessmentType'])) {
            $whereConditions[] = "iac.type = :assessmentType";
            $data['assessmentType'] = $filters['assessmentType'];
        }

        if (!empty($filters['assessmentName'])) {
            $whereConditions[] = "iac.name = :assessmentName";
            $data['assessmentName'] = $filters['assessmentName'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT
                s.gibbonPersonID,
                s.preferredName,
                s.surname,
                fg.name as formGroup,
                yg.name as yearGroup,
                c.name as courseName,
                CAST(
                    REPLACE(REPLACE(me.attainmentValue, '%', ''), ' ', '')
                    AS DECIMAL(10,2)
                ) as grade
            FROM gibbonPerson s
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonFormGroup fg ON fg.gibbonFormGroupID = se.gibbonFormGroupID
            JOIN gibbonYearGroup yg ON yg.gibbonYearGroupID = se.gibbonYearGroupID
            JOIN gibbonCourseClassPerson ccp ON ccp.gibbonPersonID = s.gibbonPersonID
            JOIN gibbonCourseClass cc ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
            JOIN gibbonCourse c ON c.gibbonCourseID = cc.gibbonCourseID
            LEFT JOIN gibbonCourseClassPerson ct ON ct.gibbonCourseClassID = cc.gibbonCourseClassID AND ct.role = 'Teacher'
            JOIN gibbonInternalAssessmentColumn iac ON iac.gibbonCourseClassID = cc.gibbonCourseClassID
            JOIN gibbonInternalAssessmentEntry me ON me.gibbonPersonIDStudent = s.gibbonPersonID
                AND me.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
            WHERE {$whereClause}
            ORDER BY s.surname, s.preferredName, c.name";

        $results = $this->db()->select($sql, $data);

        // Process results into broadsheet format
        $broadsheet = [];
        $studentData = [];

        foreach ($results as $row) {
            $studentKey = $row['gibbonPersonID'];

            if (!isset($studentData[$studentKey])) {
                $studentData[$studentKey] = [
                    'gibbonPersonID' => $row['gibbonPersonID'],
                    'preferredName' => $row['preferredName'],
                    'surname' => $row['surname'],
                    'formGroup' => $row['formGroup'],
                    'yearGroup' => $row['yearGroup'],
                    'courses' => [],
                    'grades' => []
                ];
            }

            $studentData[$studentKey]['courses'][$row['courseName']] = $row['grade'];
            $studentData[$studentKey]['grades'][] = $row['grade'];
        }

        // Calculate averages and prepare final data
        foreach ($studentData as $student) {
            if (!empty($student['grades'])) {
                $average = array_sum($student['grades']) / \count($student['grades']);
                $student['average'] = $average;
                $broadsheet[] = $student;
            }
        }

        // Sort by average descending
        usort($broadsheet, function($a, $b) {
            return $b['average'] <=> $a['average'];
        });

        return $broadsheet;
    }
}

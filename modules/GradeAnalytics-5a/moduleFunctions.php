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

/**
 * Get all available courses
 *
 * @param PDO $connection2
 * @return array
 */
function getCourses($connection2) {
    global $guid;
    try {
        error_log('Starting getCourses function');
        if (!isset($_SESSION[$guid]['gibbonSchoolYearID'])) {
            error_log('gibbonSchoolYearID not set in session');
            return array();
        }
        
        $data = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID']);
        error_log('School Year ID: ' . $_SESSION[$guid]['gibbonSchoolYearID']);
        
        // Simpler query first to debug
        $sql = "SELECT gibbonCourseID as value, name 
                FROM gibbonCourse 
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";
        
        error_log('Executing SQL: ' . $sql);
        $stmt = $connection2->prepare($sql);
        $stmt->execute($data);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Courses found: ' . count($results));
        error_log('Course query results: ' . print_r($results, true));
        
        return $results;
    } catch (PDOException $e) {
        error_log('Database error in getCourses: ' . $e->getMessage());
        error_log('SQL State: ' . $e->getCode());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return array();
    }
}

/**
 * Get all form groups
 *
 * @param PDO $connection2
 * @return array
 */
function getFormGroups($connection2) {
    global $guid;
    try {
        error_log('Starting getFormGroups function');
        if (!isset($_SESSION[$guid]['gibbonSchoolYearID'])) {
            error_log('gibbonSchoolYearID not set in session');
            return array();
        }
        
        $data = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID']);
        error_log('School Year ID: ' . $_SESSION[$guid]['gibbonSchoolYearID']);
        
        // Simpler query first to debug
        $sql = "SELECT gibbonFormGroupID as value, name 
                FROM gibbonFormGroup 
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID";
        
        error_log('Executing SQL: ' . $sql);
        $stmt = $connection2->prepare($sql);
        $stmt->execute($data);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Form Groups found: ' . count($results));
        error_log('Form Groups query results: ' . print_r($results, true));
        
        return $results;
    } catch (PDOException $e) {
        error_log('Database error in getFormGroups: ' . $e->getMessage());
        error_log('SQL State: ' . $e->getCode());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return array();
    }
}

/**
 * Get all teachers
 *
 * @param PDO $connection2
 * @return array
 */
function getTeachers($connection2) {
    try {
        error_log('Starting getTeachers function');
        
        // Simpler query first to debug
        $sql = "SELECT DISTINCT p.gibbonPersonID as value, 
                CONCAT(p.preferredName, ' ', p.surname) as name 
                FROM gibbonPerson p 
                JOIN gibbonStaff s ON (p.gibbonPersonID=s.gibbonPersonID) 
                WHERE p.status='Full' 
                AND s.type='Teaching'";
        
        error_log('Executing SQL: ' . $sql);
        $stmt = $connection2->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Teachers found: ' . count($results));
        error_log('Teachers query results: ' . print_r($results, true));
        
        return $results;
    } catch (PDOException $e) {
        error_log('Database error in getTeachers: ' . $e->getMessage());
        error_log('SQL State: ' . $e->getCode());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return array();
    }
}

/**
 * Get all assessment columns
 *
 * @param PDO $connection2
 * @param string $courseID Optional course ID to filter columns
 * @return array
 */
function getAssessmentColumns($connection2, $courseID = '') {
    try {
        error_log('Starting getAssessmentColumns function');
        
        $data = array();
        $sql = "SELECT DISTINCT iac.gibbonInternalAssessmentColumnID as value, 
                CONCAT(c.name, ' - ', iac.name) as name
                FROM gibbonInternalAssessmentColumn iac
                JOIN gibbonCourseClass cc ON (iac.gibbonCourseClassID=cc.gibbonCourseClassID)
                JOIN gibbonCourse c ON (cc.gibbonCourseID=c.gibbonCourseID)
                WHERE 1=1";

        if (!empty($courseID)) {
            $data['courseID'] = $courseID;
            $sql .= " AND cc.gibbonCourseID=:courseID";
        }

        $sql .= " ORDER BY c.name, iac.name";
        
        error_log('Executing SQL: ' . $sql);
        $stmt = $connection2->prepare($sql);
        $stmt->execute($data);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Assessment Columns found: ' . count($results));
        error_log('Assessment Columns query results: ' . print_r($results, true));
        
        return $results;
    } catch (PDOException $e) {
        error_log('Database error in getAssessmentColumns: ' . $e->getMessage());
        error_log('SQL State: ' . $e->getCode());
        error_log('Stack trace: ' . $e->getTraceAsString());
        return array();
    }
}

/**
 * Get unique assessment types from the gibbonMarkbookColumn table
 */
function getAssessmentTypes($connection2) {
    try {
        $data = array();
        $sql = "SELECT DISTINCT type 
                FROM gibbonMarkbookColumn 
                WHERE type IS NOT NULL AND type != '' 
                ORDER BY type";
                
        $result = $connection2->query($sql);
        
        while ($row = $result->fetch()) {
            $data[] = array(
                'value' => $row['type'],
                'name' => $row['type']
            );
        }
        return $data;
    } catch (Exception $e) {
        return array();
    }
}

/**
 * Get grade distribution data
 *
 * @param PDO $connection2
 * @param string $courseID
 * @param string $formGroupID
 * @param string $teacherID
 * @param string $assessmentType
 * @return array
 */
function getGradeDistribution($connection2, $courseID = null, $formGroupID = null, $teacherID = null, $assessmentType = null) {
    try {
        $data = array();
        $where = array();
        $params = array();

        // First, create a CTE for grade ranges
        $sql = "WITH GradeRanges AS (
                    SELECT 'A+' as grade, 90 as min_value, 100 as max_value
                    UNION ALL SELECT 'A', 80, 89.99
                    UNION ALL SELECT 'B', 70, 79.99
                    UNION ALL SELECT 'C', 60, 69.99
                    UNION ALL SELECT 'D', 50, 59.99
                    UNION ALL SELECT 'F', 0, 49.99
                ),
                StudentGrades AS (
                    SELECT 
                        CASE 
                            WHEN me.attainmentValue >= 90 THEN 'A+'
                            WHEN me.attainmentValue >= 80 THEN 'A'
                            WHEN me.attainmentValue >= 70 THEN 'B'
                            WHEN me.attainmentValue >= 60 THEN 'C'
                            WHEN me.attainmentValue >= 50 THEN 'D'
                            ELSE 'F'
                        END as grade
                    FROM gibbonMarkbookEntry me
                    JOIN gibbonMarkbookColumn mc ON (me.gibbonMarkbookColumnID=mc.gibbonMarkbookColumnID)
                    JOIN gibbonCourseClass cc ON (mc.gibbonCourseClassID=cc.gibbonCourseClassID)
                    JOIN gibbonCourse c ON (cc.gibbonCourseID=c.gibbonCourseID)
                    WHERE me.attainmentValue IS NOT NULL";

        if (!empty($courseID)) {
            $where[] = "c.gibbonCourseID=:courseID";
            $params['courseID'] = $courseID;
        }

        if (!empty($formGroupID)) {
            $where[] = "cc.gibbonFormGroupID=:formGroupID";
            $params['formGroupID'] = $formGroupID;
        }

        if (!empty($teacherID)) {
            $where[] = "cc.gibbonPersonIDTeacher=:teacherID";
            $params['teacherID'] = $teacherID;
        }

        if (!empty($assessmentType)) {
            $where[] = "mc.type=:assessmentType";
            $params['assessmentType'] = $assessmentType;
        }

        if (!empty($where)) {
            $sql .= " AND " . implode(" AND ", $where);
        }

        // Complete the query to get counts for each grade
        $sql .= "),
                GradeCounts AS (
                    SELECT grade, COUNT(*) as count
                    FROM StudentGrades
                    GROUP BY grade
                )
                SELECT gr.grade, COALESCE(gc.count, 0) as count
                FROM GradeRanges gr
                LEFT JOIN GradeCounts gc ON gr.grade = gc.grade
                ORDER BY 
                    CASE gr.grade 
                        WHEN 'A+' THEN 1
                        WHEN 'A' THEN 2
                        WHEN 'B' THEN 3
                        WHEN 'C' THEN 4
                        WHEN 'D' THEN 5
                        WHEN 'F' THEN 6
                    END";

        $stmt = $connection2->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        // Add debug logging
        error_log('Grade Distribution Query: ' . $sql);
        error_log('Parameters: ' . print_r($params, true));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log('Results: ' . print_r($results, true));

        return $results;
    } catch (Exception $e) {
        error_log('Error in getGradeDistribution: ' . $e->getMessage());
        return array();
    }
}

function getStudentProgress($connection2, $courseID = '', $formGroupID = '', $teacherID = '') {
    try {
        $params = array();
        $sql = "SELECT 
                    p.surname, p.preferredName,
                    i.name as assessmentName,
                    ia.result,
                    ia.dateSubmitted
                FROM gibbonInternalAssessmentResult ia
                JOIN gibbonInternalAssessment i ON (ia.gibbonInternalAssessmentID = i.gibbonInternalAssessmentID)
                JOIN gibbonCourseClass cc ON (i.gibbonCourseClassID = cc.gibbonCourseClassID)
                JOIN gibbonPerson p ON (ia.gibbonPersonID = p.gibbonPersonID)
                WHERE 1=1";

        if (!empty($courseID)) {
            $sql .= " AND cc.gibbonCourseID = :courseID";
            $params['courseID'] = $courseID;
        }

        if (!empty($formGroupID)) {
            $sql .= " AND ia.gibbonFormGroupID = :formGroupID";
            $params['formGroupID'] = $formGroupID;
        }

        if (!empty($teacherID)) {
            $sql .= " AND cc.gibbonPersonID = :teacherID";
            $params['teacherID'] = $teacherID;
        }

        $sql .= " ORDER BY p.surname, p.preferredName, ia.dateSubmitted";
        $stmt = $connection2->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
}

function getClassPerformance($connection2, $courseID = '', $formGroupID = '', $teacherID = '') {
    try {
        $params = array();
        $sql = "SELECT 
                    cc.name as className,
                    COUNT(DISTINCT ia.gibbonPersonID) as totalStudents,
                    AVG(ia.result) as averageScore,
                    MIN(ia.result) as lowestScore,
                    MAX(ia.result) as highestScore
                FROM gibbonInternalAssessmentResult ia
                JOIN gibbonInternalAssessment i ON (ia.gibbonInternalAssessmentID = i.gibbonInternalAssessmentID)
                JOIN gibbonCourseClass cc ON (i.gibbonCourseClassID = cc.gibbonCourseClassID)
                WHERE 1=1";

        if (!empty($courseID)) {
            $sql .= " AND cc.gibbonCourseID = :courseID";
            $params['courseID'] = $courseID;
        }

        if (!empty($formGroupID)) {
            $sql .= " AND ia.gibbonFormGroupID = :formGroupID";
            $params['formGroupID'] = $formGroupID;
        }

        if (!empty($teacherID)) {
            $sql .= " AND cc.gibbonPersonID = :teacherID";
            $params['teacherID'] = $teacherID;
        }

        $sql .= " GROUP BY cc.gibbonCourseClassID, cc.name ORDER BY cc.name";
        $stmt = $connection2->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return array();
    }
} 
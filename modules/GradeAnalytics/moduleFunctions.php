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
 * @deprecated Use GradeAnalyticsGateway::selectCourses() instead
 *
 * @param PDO $connection2 Kept for backward compatibility, but no longer used
 * @return array
 * @phpstan-ignore-next-line
 */
function getCourses($connection2) {
    global $guid, $container;
    unset($connection2); // Suppress unused parameter warning
    try {
        if (!isset($_SESSION[$guid]['gibbonSchoolYearID'])) {
            return array();
        }

        $gateway = $container->get(\Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway::class);
        $result = $gateway->selectCourses($_SESSION[$guid]['gibbonSchoolYearID']);

        return $result->fetchAll();
    } catch (Exception $e) {
        error_log('Error in getCourses: ' . $e->getMessage());
        return array();
    }
}

/**
 * Get all form groups
 * @deprecated Use GradeAnalyticsGateway::selectFormGroups() instead
 *
 * @param PDO $connection2 Kept for backward compatibility, but no longer used
 * @return array
 * @phpstan-ignore-next-line
 */
function getFormGroups($connection2) {
    global $guid, $container;
    unset($connection2); // Suppress unused parameter warning
    try {
        if (!isset($_SESSION[$guid]['gibbonSchoolYearID'])) {
            return array();
        }

        $gateway = $container->get(\Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway::class);
        $result = $gateway->selectFormGroups($_SESSION[$guid]['gibbonSchoolYearID']);

        return $result->fetchAll();
    } catch (Exception $e) {
        error_log('Error in getFormGroups: ' . $e->getMessage());
        return array();
    }
}

/**
 * Get all teachers
 * @deprecated Use GradeAnalyticsGateway::selectTeachers() instead
 *
 * @param PDO $connection2 Kept for backward compatibility, but no longer used
 * @return array
 * @phpstan-ignore-next-line
 */
function getTeachers($connection2) {
    global $container;
    unset($connection2); // Suppress unused parameter warning
    try {
        $gateway = $container->get(\Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway::class);
        $result = $gateway->selectTeachers();

        return $result->fetchAll();
    } catch (Exception $e) {
        error_log('Error in getTeachers: ' . $e->getMessage());
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
 * Get available year groups for grade analytics
 * @deprecated Use GradeAnalyticsGateway::selectYearGroups() instead
 *
 * @param PDO $connection2 Kept for backward compatibility, but no longer used
 * @return array Array of year groups with value and name
 * @phpstan-ignore-next-line
 */
function getGradeAnalyticsYearGroups($connection2) {
    global $guid, $container;
    unset($connection2); // Suppress unused parameter warning
    try {
        if (!isset($_SESSION[$guid]['gibbonSchoolYearID'])) {
            return array();
        }

        $gateway = $container->get(\Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway::class);
        $result = $gateway->selectYearGroups($_SESSION[$guid]['gibbonSchoolYearID']);

        return $result->fetchAll();
    } catch (Exception $e) {
        error_log('Error in getGradeAnalyticsYearGroups: ' . $e->getMessage());
        return array();
    }
}

/**
 * Get internal assessment types
 * @deprecated Use GradeAnalyticsGateway::selectAssessmentTypes() instead
 *
 * @param PDO $connection2 Kept for backward compatibility, but no longer used
 * @return array Array of assessment types
 * @phpstan-ignore-next-line
 */
function getInternalAssessmentTypes($connection2) {
    global $container;
    unset($connection2); // Suppress unused parameter warning
    try {
        $gateway = $container->get(\Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway::class);
        $result = $gateway->selectAssessmentTypes();

        $types = array();
        while ($row = $result->fetch()) {
            $types[] = $row['type'];
        }

        return $types;
    } catch (Exception $e) {
        error_log('Error in getInternalAssessmentTypes: ' . $e->getMessage());
        return array();
    }
}

/**
 * Get grade distribution data
 * @deprecated Use GradeAnalyticsGateway::selectGradeDistribution() instead
 *
 * @param PDO $connection2 Kept for backward compatibility, but no longer used
 * @param string $courseID
 * @param string $formGroupID
 * @param string $teacherID
 * @param string $yearGroup
 * @param string $assessmentType
 * @return array
 * @phpstan-ignore-next-line
 */
function getGradeDistribution($connection2, $courseID = null, $formGroupID = null, $teacherID = null, $yearGroup = null, $assessmentType = null) {
    global $guid, $container;
    unset($connection2); // Suppress unused parameter warning
    try {
        if (!isset($_SESSION[$guid]['gibbonSchoolYearID'])) {
            return array();
        }

        $gateway = $container->get(\Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway::class);

        $filters = [
            'courseID' => $courseID,
            'formGroupID' => $formGroupID,
            'teacherID' => $teacherID,
            'yearGroup' => $yearGroup,
            'assessmentType' => $assessmentType
        ];

        $result = $gateway->selectGradeDistribution($_SESSION[$guid]['gibbonSchoolYearID'], $filters);

        return $result->fetchAll();
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
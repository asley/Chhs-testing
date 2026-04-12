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
 * Get all school years for filter dropdown.
 */
function pdGetSchoolYears(PDO $connection2): array
{
    $sql = "SELECT gibbonSchoolYearID AS value, name
            FROM gibbonSchoolYear
            ORDER BY sequenceNumber DESC";
    $stmt = $connection2->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get year groups for the given school year.
 */
function pdGetYearGroups(PDO $connection2, string $schoolYearID): array
{
    $sql = "SELECT DISTINCT yg.gibbonYearGroupID AS value, yg.name
            FROM gibbonYearGroup yg
            JOIN gibbonStudentEnrolment se ON se.gibbonYearGroupID = yg.gibbonYearGroupID
            WHERE se.gibbonSchoolYearID = :yearID
            ORDER BY yg.sequenceNumber";
    $stmt = $connection2->prepare($sql);
    $stmt->execute([':yearID' => $schoolYearID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get form groups, optionally filtered by year group.
 */
function pdGetFormGroups(PDO $connection2, string $schoolYearID, string $yearGroupID = ''): array
{
    $params = [':yearID' => $schoolYearID];
    $yearFilter = '';
    if ($yearGroupID !== '') {
        $yearFilter = ' AND se.gibbonYearGroupID = :yearGroupID';
        $params[':yearGroupID'] = $yearGroupID;
    }

    $sql = "SELECT DISTINCT fg.gibbonFormGroupID AS value, fg.name
            FROM gibbonFormGroup fg
            JOIN gibbonStudentEnrolment se ON se.gibbonFormGroupID = fg.gibbonFormGroupID
            WHERE se.gibbonSchoolYearID = :yearID
            {$yearFilter}
            ORDER BY fg.name";
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all staff teachers who have course classes in the given school year.
 */
function pdGetTeachers(PDO $connection2, string $schoolYearID): array
{
    $sql = "SELECT DISTINCT p.gibbonPersonID AS value,
                   CONCAT(p.preferredName, ' ', p.surname) AS name
            FROM gibbonPerson p
            JOIN gibbonCourseClassPerson ccp ON ccp.gibbonPersonID = p.gibbonPersonID AND ccp.role = 'Teacher'
            JOIN gibbonCourseClass gc ON gc.gibbonCourseClassID = ccp.gibbonCourseClassID
            JOIN gibbonCourse c ON c.gibbonCourseID = gc.gibbonCourseID
            WHERE c.gibbonSchoolYearID = :yearID
              AND p.status = 'Full'
            ORDER BY p.surname, p.preferredName";
    $stmt = $connection2->prepare($sql);
    $stmt->execute([':yearID' => $schoolYearID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * KPI: overall markbook average percentage for the filtered cohort.
 * Student-weighted: average each student's markbook mean, then average across students.
 */
function pdKpiMarkbookAvg(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): float
{
    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "SELECT ROUND(AVG(studentAvg), 1) AS avg
            FROM (
                SELECT
                    se.gibbonPersonID,
                    AVG(me.attainmentValue) AS studentAvg
                FROM gibbonStudentEnrolment se
                JOIN gibbonMarkbookEntry me
                    ON me.gibbonPersonIDStudent = se.gibbonPersonID
                JOIN gibbonMarkbookColumn mc
                    ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
                JOIN gibbonCourseClass gc
                    ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
                JOIN gibbonCourse c
                    ON c.gibbonCourseID = gc.gibbonCourseID
                WHERE se.gibbonSchoolYearID = :yearID
                  AND c.gibbonSchoolYearID = :yearID
                  AND me.attainmentValue IS NOT NULL
                  {$filters}
                GROUP BY se.gibbonPersonID
            ) AS studentAverages";
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) ($row['avg'] ?? 0);
}

/**
 * KPI: pass rate percentage based on each student's mean markbook score (>= 65).
 */
function pdKpiPassRate(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): float
{
    $passMark = 65.0;
    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "SELECT
                COUNT(CASE WHEN studentAvg >= {$passMark} THEN 1 END) AS passed,
                COUNT(*) AS total
            FROM (
                SELECT
                    se.gibbonPersonID,
                    AVG(me.attainmentValue) AS studentAvg
                FROM gibbonStudentEnrolment se
                JOIN gibbonMarkbookEntry me
                    ON me.gibbonPersonIDStudent = se.gibbonPersonID
                JOIN gibbonMarkbookColumn mc
                    ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
                JOIN gibbonCourseClass gc
                    ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
                JOIN gibbonCourse c
                    ON c.gibbonCourseID = gc.gibbonCourseID
                WHERE se.gibbonSchoolYearID = :yearID
                  AND c.gibbonSchoolYearID = :yearID
                  AND me.attainmentValue IS NOT NULL
                  {$filters}
                GROUP BY se.gibbonPersonID
            ) AS studentAverages";
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (empty($row['total'])) return 0.0;
    return round(($row['passed'] / $row['total']) * 100, 1);
}

/**
 * KPI: overall internal assessment average for the filtered cohort.
 * Student-weighted: average each student's IA mean, then average across students.
 */
function pdKpiInternalAssessmentAvg(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): float
{
    if (!pdTableExists($connection2, 'gibbonInternalAssessmentColumn') || !pdTableExists($connection2, 'gibbonInternalAssessmentEntry')) {
        return 0.0;
    }

    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "SELECT ROUND(AVG(studentAvg), 1) AS avg
            FROM (
                SELECT
                    se.gibbonPersonID,
                    AVG(
                        CASE
                            WHEN TRIM(iae.attainmentValue) REGEXP '^[0-9]+([.][0-9]+)?%?$'
                                THEN CAST(REPLACE(TRIM(iae.attainmentValue), '%', '') AS DECIMAL(6,2))
                            ELSE 0
                        END
                    ) AS studentAvg
                FROM gibbonStudentEnrolment se
                JOIN gibbonInternalAssessmentEntry iae
                    ON iae.gibbonPersonIDStudent = se.gibbonPersonID
                JOIN gibbonInternalAssessmentColumn iac
                    ON iac.gibbonInternalAssessmentColumnID = iae.gibbonInternalAssessmentColumnID
                JOIN gibbonCourseClass gc
                    ON gc.gibbonCourseClassID = iac.gibbonCourseClassID
                JOIN gibbonCourse c
                    ON c.gibbonCourseID = gc.gibbonCourseID
                WHERE se.gibbonSchoolYearID = :yearID
                  AND c.gibbonSchoolYearID = :yearID
                  AND iae.attainmentValue IS NOT NULL
                  AND iae.attainmentValue != ''
                  {$filters}
                GROUP BY se.gibbonPersonID
            ) AS studentAverages";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);

    return (float) ($stmt->fetchColumn() ?: 0.0);
}

/**
 * KPI: chronic absenteeism rate based on absences within the selected school year.
 * Returns percentage of enrolled students with more than 18 absences.
 */
function pdKpiChronicAbsenteeism(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): float
{
    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    // Total enrolled students
    $sqlTotal = "SELECT COUNT(DISTINCT se.gibbonPersonID) AS total
                 FROM gibbonStudentEnrolment se
                 WHERE se.gibbonSchoolYearID = :yearID
                   {$filters}";
    $stmt = $connection2->prepare($sqlTotal);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    if ($total === 0) return 0.0;

    $yearRange = pdGetSchoolYearDateRange($connection2, $schoolYearID);
    $params[':yearStart'] = $yearRange['firstDay'];
    $params[':yearEnd'] = $yearRange['lastDay'];

    // Count per-student absences vs a fixed threshold (18) within the selected school year.
    $sqlChronicSimple = "SELECT COUNT(*) AS chronic FROM (
        SELECT al.gibbonPersonID, COUNT(*) AS absences
        FROM gibbonAttendanceLogPerson al
        JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = al.gibbonPersonID
        WHERE se.gibbonSchoolYearID = :yearID
          AND al.direction = 'Out'
          AND al.date BETWEEN :yearStart AND :yearEnd
          {$filters}
        GROUP BY al.gibbonPersonID
        HAVING absences > 18
    ) AS chronic_students";

    $stmtC = $connection2->prepare($sqlChronicSimple);
    $stmtC->execute($params);
    $chronic = (int) $stmtC->fetchColumn();

    return round(($chronic / $total) * 100, 1);
}

/**
 * KPI: number of at-risk students (student average below 65 OR high absence).
 */
function pdKpiAtRiskCount(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): int
{
    $passMark = 65.0;
    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);
    $yearRange = pdGetSchoolYearDateRange($connection2, $schoolYearID);
    $params[':yearStart'] = $yearRange['firstDay'];
    $params[':yearEnd'] = $yearRange['lastDay'];

    $sql = "SELECT COUNT(*) AS cnt
            FROM (
                SELECT
                    se.gibbonPersonID,
                    mb.studentAvg AS avgGrade,
                    COALESCE(att.absences, 0) AS absences
                FROM gibbonStudentEnrolment se
                JOIN gibbonPerson p
                    ON p.gibbonPersonID = se.gibbonPersonID
                LEFT JOIN (
                    SELECT
                        me.gibbonPersonIDStudent AS gibbonPersonID,
                        AVG(me.attainmentValue) AS studentAvg
                    FROM gibbonMarkbookEntry me
                    JOIN gibbonMarkbookColumn mc
                        ON mc.gibbonMarkbookColumnID = me.gibbonMarkbookColumnID
                    JOIN gibbonCourseClass gc
                        ON gc.gibbonCourseClassID = mc.gibbonCourseClassID
                    JOIN gibbonCourse c
                        ON c.gibbonCourseID = gc.gibbonCourseID
                    WHERE c.gibbonSchoolYearID = :yearID
                      AND me.attainmentValue IS NOT NULL
                    GROUP BY me.gibbonPersonIDStudent
                ) mb
                    ON mb.gibbonPersonID = se.gibbonPersonID
                LEFT JOIN (
                    SELECT
                        al.gibbonPersonID,
                        COUNT(*) AS absences
                    FROM gibbonAttendanceLogPerson al
                    WHERE al.direction = 'Out'
                      AND al.date BETWEEN :yearStart AND :yearEnd
                    GROUP BY al.gibbonPersonID
                ) att
                    ON att.gibbonPersonID = se.gibbonPersonID
                WHERE se.gibbonSchoolYearID = :yearID
                  AND p.status = 'Full'
                  {$filters}
            ) AS risk
            WHERE (risk.avgGrade IS NOT NULL AND risk.avgGrade < {$passMark})
               OR risk.absences > 18";
    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Build SQL filter fragment for year group / form group (appended after WHERE clauses).
 * Populates $params by reference.
 */
function pdBuildEnrolmentFilters(string $yearGroupID, string $formGroupID, array &$params): string
{
    $filters = '';
    if ($yearGroupID !== '') {
        $filters .= ' AND se.gibbonYearGroupID = :yearGroupID';
        $params[':yearGroupID'] = $yearGroupID;
    }
    if ($formGroupID !== '') {
        $filters .= ' AND se.gibbonFormGroupID = :formGroupID';
        $params[':formGroupID'] = $formGroupID;
    }
    return $filters;
}

/**
 * Get the date range (first/last day) for the selected school year.
 *
 * @return array{firstDay:string,lastDay:string}
 */
function pdGetSchoolYearDateRange(PDO $connection2, string $schoolYearID): array
{
    $stmt = $connection2->prepare("SELECT firstDay, lastDay FROM gibbonSchoolYear WHERE gibbonSchoolYearID = :yearID LIMIT 1");
    $stmt->execute([':yearID' => $schoolYearID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($row['firstDay']) || empty($row['lastDay'])) {
        $today = date('Y-m-d');
        return ['firstDay' => $today, 'lastDay' => $today];
    }

    return [
        'firstDay' => $row['firstDay'],
        'lastDay' => $row['lastDay'],
    ];
}

/**
 * Check whether a table exists in the current database.
 */
function pdTableExists(PDO $connection2, string $tableName): bool
{
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $stmt = $connection2->prepare('SHOW TABLES LIKE :tableName');
        $stmt->execute([':tableName' => $tableName]);
        $cache[$tableName] = (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

/**
 * KPI: total number of enrolled students in the filtered cohort.
 */
function pdKpiStudentCount(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): int
{
    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "SELECT COUNT(DISTINCT se.gibbonPersonID)
            FROM gibbonStudentEnrolment se
            JOIN gibbonPerson p ON p.gibbonPersonID = se.gibbonPersonID
            WHERE se.gibbonSchoolYearID = :yearID
              AND p.status = 'Full'
              {$filters}";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * KPI: overall attendance rate (%) for the selected date range.
 */
function pdKpiAttendanceRate(PDO $connection2, string $schoolYearID, string $dateFrom, string $dateTo, string $yearGroupID = '', string $formGroupID = ''): float
{
    $params = [
        ':yearID' => $schoolYearID,
        ':dateFrom' => $dateFrom,
        ':dateTo' => $dateTo,
    ];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "WITH cohort AS (
                SELECT se.gibbonPersonID
                FROM gibbonStudentEnrolment se
                WHERE se.gibbonSchoolYearID = :yearID
                  {$filters}
            )
            SELECT
                COUNT(*) AS totalLogs,
                SUM(CASE WHEN al.direction = 'Out' THEN 1 ELSE 0 END) AS absentLogs
            FROM gibbonAttendanceLogPerson al
            JOIN cohort co
                ON co.gibbonPersonID = al.gibbonPersonID
            WHERE al.date BETWEEN :dateFrom AND :dateTo";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalLogs = (int) ($row['totalLogs'] ?? 0);
    if ($totalLogs === 0) {
        return 0.0;
    }

    $absentLogs = (int) ($row['absentLogs'] ?? 0);
    return round((($totalLogs - $absentLogs) / $totalLogs) * 100, 1);
}

/**
 * KPI: behaviour summary for the selected date range.
 *
 * @return array{
 *   total:int,
 *   positive:int,
 *   negative:int,
 *   observation:int,
 *   topNegativeDescriptors: array<int, array{descriptor:string,count:int}>
 * }
 */
function pdKpiBehaviourSummary(PDO $connection2, string $schoolYearID, string $dateFrom, string $dateTo, string $yearGroupID = '', string $formGroupID = ''): array
{
    if (!pdTableExists($connection2, 'gibbonBehaviour')) {
        return [
            'total' => 0,
            'positive' => 0,
            'negative' => 0,
            'observation' => 0,
            'topNegativeDescriptors' => [],
        ];
    }

    $params = [
        ':yearID' => $schoolYearID,
        ':dateFrom' => $dateFrom,
        ':dateTo' => $dateTo,
    ];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "SELECT
                COUNT(DISTINCT b.gibbonBehaviourID) AS total,
                COUNT(DISTINCT CASE WHEN b.type = 'Positive' THEN b.gibbonBehaviourID END) AS positiveCnt,
                COUNT(DISTINCT CASE WHEN b.type = 'Negative' THEN b.gibbonBehaviourID END) AS negativeCnt,
                COUNT(DISTINCT CASE WHEN b.type = 'Observation' THEN b.gibbonBehaviourID END) AS observationCnt
            FROM gibbonBehaviour b
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = b.gibbonPersonID
            WHERE b.gibbonSchoolYearID = :yearID
              AND se.gibbonSchoolYearID = :yearID
              AND b.date BETWEEN :dateFrom AND :dateTo
              {$filters}";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $sqlDescriptors = "SELECT b.descriptor, COUNT(DISTINCT b.gibbonBehaviourID) AS descriptorCount
                       FROM gibbonBehaviour b
                       JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = b.gibbonPersonID
                       WHERE b.gibbonSchoolYearID = :yearID
                         AND se.gibbonSchoolYearID = :yearID
                         AND b.date BETWEEN :dateFrom AND :dateTo
                         AND b.type = 'Negative'
                         AND b.descriptor IS NOT NULL
                         AND b.descriptor != ''
                         {$filters}
                       GROUP BY b.descriptor
                       ORDER BY descriptorCount DESC, b.descriptor ASC
                       LIMIT 4";

    $stmtDesc = $connection2->prepare($sqlDescriptors);
    $stmtDesc->execute($params);
    $descriptorRows = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

    $topNegativeDescriptors = [];
    foreach ($descriptorRows as $row) {
        $topNegativeDescriptors[] = [
            'descriptor' => (string) $row['descriptor'],
            'count' => (int) $row['descriptorCount'],
        ];
    }

    return [
        'total' => (int) ($summary['total'] ?? 0),
        'positive' => (int) ($summary['positiveCnt'] ?? 0),
        'negative' => (int) ($summary['negativeCnt'] ?? 0),
        'observation' => (int) ($summary['observationCnt'] ?? 0),
        'topNegativeDescriptors' => $topNegativeDescriptors,
    ];
}

/**
 * List students with behaviour issues (negative records) in the selected date range.
 *
 * @return array<int, array{personID:int,studentName:string,issueCount:int,lastDate:?string}>
 */
function pdBehaviourIssueStudents(PDO $connection2, string $schoolYearID, string $dateFrom, string $dateTo, string $yearGroupID = '', string $formGroupID = '', int $limit = 0): array
{
    if (!pdTableExists($connection2, 'gibbonBehaviour')) {
        return [];
    }

    $params = [
        ':yearID' => $schoolYearID,
        ':dateFrom' => $dateFrom,
        ':dateTo' => $dateTo,
    ];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);
    $limitClause = '';
    if ($limit > 0) {
        $limitClause = ' LIMIT ' . max(1, min(500, $limit));
    }

    $sql = "SELECT
                p.gibbonPersonID AS personID,
                CONCAT(p.preferredName, ' ', p.surname) AS studentName,
                COUNT(DISTINCT b.gibbonBehaviourID) AS issueCount,
                MAX(b.date) AS lastDate
            FROM gibbonBehaviour b
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = b.gibbonPersonID
            JOIN gibbonPerson p ON p.gibbonPersonID = b.gibbonPersonID
            WHERE b.gibbonSchoolYearID = :yearID
              AND se.gibbonSchoolYearID = :yearID
              AND p.status = 'Full'
              AND b.date BETWEEN :dateFrom AND :dateTo
              AND b.type = 'Negative'
              {$filters}
            GROUP BY p.gibbonPersonID, p.preferredName, p.surname
            ORDER BY issueCount DESC, lastDate DESC, p.surname ASC, p.preferredName ASC
            {$limitClause}";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'personID' => (int) $row['personID'],
            'studentName' => (string) $row['studentName'],
            'issueCount' => (int) $row['issueCount'],
            'lastDate' => $row['lastDate'] ?: null,
        ];
    }

    return $result;
}

/**
 * KPI: badge award summary for the selected cohort.
 *
 * @return array{
 *   enabled:bool,
 *   awards:int,
 *   recipients:int,
 *   activeBadges:int,
 *   licenseAwards:int
 * }
 */
function pdKpiBadgeSummary(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = ''): array
{
    $hasBadgeStudents = pdTableExists($connection2, 'badgesBadgeStudent');
    $hasBadges = pdTableExists($connection2, 'badgesBadge');

    if (!$hasBadgeStudents || !$hasBadges) {
        return [
            'enabled' => false,
            'awards' => 0,
            'recipients' => 0,
            'activeBadges' => 0,
            'licenseAwards' => 0,
        ];
    }

    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);

    $sql = "SELECT
                COUNT(DISTINCT bss.badgesBadgeStudentID) AS awards,
                COUNT(DISTINCT bss.gibbonPersonID) AS recipients,
                COUNT(DISTINCT CASE WHEN bb.license = 'Y' THEN bss.badgesBadgeStudentID END) AS licenseAwards
            FROM badgesBadgeStudent bss
            JOIN badgesBadge bb ON bb.badgesBadgeID = bss.badgesBadgeID
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = bss.gibbonPersonID
            WHERE bss.gibbonSchoolYearID = :yearID
              AND se.gibbonSchoolYearID = :yearID
              {$filters}";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtActive = $connection2->prepare("SELECT COUNT(*) FROM badgesBadge WHERE active = 'Y'");
    $stmtActive->execute();
    $activeBadges = (int) $stmtActive->fetchColumn();

    return [
        'enabled' => true,
        'awards' => (int) ($row['awards'] ?? 0),
        'recipients' => (int) ($row['recipients'] ?? 0),
        'activeBadges' => $activeBadges,
        'licenseAwards' => (int) ($row['licenseAwards'] ?? 0),
    ];
}

/**
 * List students who have badge awards in the selected cohort.
 *
 * @return array<int, array{personID:int,studentName:string,awardCount:int,lastAwardDate:?string}>
 */
function pdBadgeAwardedStudents(PDO $connection2, string $schoolYearID, string $yearGroupID = '', string $formGroupID = '', int $limit = 0): array
{
    $hasBadgeStudents = pdTableExists($connection2, 'badgesBadgeStudent');
    $hasBadges = pdTableExists($connection2, 'badgesBadge');
    if (!$hasBadgeStudents || !$hasBadges) {
        return [];
    }

    $params = [':yearID' => $schoolYearID];
    $filters = pdBuildEnrolmentFilters($yearGroupID, $formGroupID, $params);
    $limitClause = '';
    if ($limit > 0) {
        $limitClause = ' LIMIT ' . max(1, min(500, $limit));
    }

    $sql = "SELECT
                p.gibbonPersonID AS personID,
                CONCAT(p.preferredName, ' ', p.surname) AS studentName,
                COUNT(DISTINCT bss.badgesBadgeStudentID) AS awardCount,
                MAX(bss.date) AS lastAwardDate
            FROM badgesBadgeStudent bss
            JOIN badgesBadge bb ON bb.badgesBadgeID = bss.badgesBadgeID
            JOIN gibbonStudentEnrolment se ON se.gibbonPersonID = bss.gibbonPersonID
            JOIN gibbonPerson p ON p.gibbonPersonID = bss.gibbonPersonID
            WHERE bss.gibbonSchoolYearID = :yearID
              AND se.gibbonSchoolYearID = :yearID
              AND p.status = 'Full'
              {$filters}
            GROUP BY p.gibbonPersonID, p.preferredName, p.surname
            ORDER BY awardCount DESC, lastAwardDate DESC, p.surname ASC, p.preferredName ASC
            {$limitClause}";

    $stmt = $connection2->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'personID' => (int) $row['personID'],
            'studentName' => (string) $row['studentName'],
            'awardCount' => (int) $row['awardCount'],
            'lastAwardDate' => $row['lastAwardDate'] ?: null,
        ];
    }

    return $result;
}

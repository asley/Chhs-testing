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

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Principal Dashboard/dashboard.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$absoluteURL = (string) $session->get('absoluteURL');
$sessionYearID = (string) $session->get('gibbonSchoolYearID');
$dashboardPath = '/modules/Principal Dashboard/dashboard.php';
$dashboardURL = $absoluteURL . '/index.php?q=/modules/Principal%20Dashboard/dashboard.php';

$page->breadcrumbs->add(__('Principal Dashboard'));

$schoolYears = pdGetSchoolYears($connection2);
if (empty($schoolYears)) {
    $page->addError(__('No school years are available for this dashboard.'));
    return;
}

$yearValues = array_map(static function (array $row): string {
    return (string) ($row['value'] ?? '');
}, $schoolYears);

$selectedYearID = isset($_GET['gibbonSchoolYearID']) ? (string) $_GET['gibbonSchoolYearID'] : $sessionYearID;
if (!in_array($selectedYearID, $yearValues, true)) {
    $selectedYearID = $yearValues[0];
}

$yearRange = pdGetSchoolYearDateRange($connection2, $selectedYearID);
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';

$dateFromInput = isset($_GET['dateFrom']) ? (string) $_GET['dateFrom'] : $yearRange['firstDay'];
$dateToInput = isset($_GET['dateTo']) ? (string) $_GET['dateTo'] : $yearRange['lastDay'];

$dateFrom = preg_match($datePattern, $dateFromInput) ? $dateFromInput : $yearRange['firstDay'];
$dateTo = preg_match($datePattern, $dateToInput) ? $dateToInput : $yearRange['lastDay'];
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$yearGroups = pdGetYearGroups($connection2, $selectedYearID);
$yearGroupValues = array_map(static function (array $row): string {
    return (string) ($row['value'] ?? '');
}, $yearGroups);

$selectedYearGrp = isset($_GET['yearGroupID']) ? (string) $_GET['yearGroupID'] : '';
if ($selectedYearGrp !== '' && !in_array($selectedYearGrp, $yearGroupValues, true)) {
    $selectedYearGrp = '';
}

$formGroups = pdGetFormGroups($connection2, $selectedYearID, $selectedYearGrp);
$formGroupValues = array_map(static function (array $row): string {
    return (string) ($row['value'] ?? '');
}, $formGroups);

$selectedFormGrp = isset($_GET['formGroupID']) ? (string) $_GET['formGroupID'] : '';
if ($selectedFormGrp !== '' && !in_array($selectedFormGrp, $formGroupValues, true)) {
    $selectedFormGrp = '';
}

$teachers = pdGetTeachers($connection2, $selectedYearID);
$teacherValues = array_map(static function (array $row): string {
    return (string) ($row['value'] ?? '');
}, $teachers);

$selectedTeacher = isset($_GET['teacherID']) ? (string) $_GET['teacherID'] : '';
if ($selectedTeacher !== '' && !in_array($selectedTeacher, $teacherValues, true)) {
    $selectedTeacher = '';
}

$studentCount = pdKpiStudentCount($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$teacherCount = count($teachers);
$studentTeacherRatio = $teacherCount > 0 ? round($studentCount / $teacherCount, 1) : 0.0;
$staffingHealth = $teacherCount > 0
    ? min(100.0, round((25.0 / max(1.0, $studentTeacherRatio)) * 100.0, 1))
    : 0.0;

$kpiAttendanceRate = pdKpiAttendanceRate($connection2, $selectedYearID, $dateFrom, $dateTo, $selectedYearGrp, $selectedFormGrp);
$kpiMarkbookAvg = pdKpiMarkbookAvg($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$kpiInternalAssessmentAvg = pdKpiInternalAssessmentAvg($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$kpiPassRate = pdKpiPassRate($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$kpiAbsenteeism = pdKpiChronicAbsenteeism($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$kpiAtRisk = pdKpiAtRiskCount($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);

$behaviourSummary = pdKpiBehaviourSummary($connection2, $selectedYearID, $dateFrom, $dateTo, $selectedYearGrp, $selectedFormGrp);
$behaviourStudentsAll = pdBehaviourIssueStudents($connection2, $selectedYearID, $dateFrom, $dateTo, $selectedYearGrp, $selectedFormGrp, 0);
$behaviourStudents = array_slice($behaviourStudentsAll, 0, 8);

$studentsFlaggedCount = count($behaviourStudentsAll);
$behaviourTotal = (int) ($behaviourSummary['total'] ?? 0);
$positiveCount = (int) ($behaviourSummary['positive'] ?? 0);
$negativeCount = (int) ($behaviourSummary['negative'] ?? 0);
$observationCount = (int) ($behaviourSummary['observation'] ?? 0);
$topNegativeDescriptors = $behaviourSummary['topNegativeDescriptors'] ?? [];

$positiveShare = $behaviourTotal > 0 ? round(($positiveCount / $behaviourTotal) * 100, 1) : 0.0;
$negativeShare = $behaviourTotal > 0 ? round(($negativeCount / $behaviourTotal) * 100, 1) : 0.0;
$observationShare = $behaviourTotal > 0 ? round(($observationCount / $behaviourTotal) * 100, 1) : 0.0;
$behaviourFlagRate = $studentCount > 0 ? round(($studentsFlaggedCount / $studentCount) * 100, 1) : 0.0;

$badgeSummary = pdKpiBadgeSummary($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$badgeStudentsAll = pdBadgeAwardedStudents($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp, 0);
$badgeStudents = array_slice($badgeStudentsAll, 0, 8);

$badgeReach = $studentCount > 0 ? round(((int) ($badgeSummary['recipients'] ?? 0) / $studentCount) * 100, 1) : 0.0;
$repeatRecipients = 0;
foreach ($badgeStudentsAll as $badgeStudent) {
    if (((int) ($badgeStudent['awardCount'] ?? 0)) > 1) {
        $repeatRecipients++;
    }
}

$atRiskRate = $studentCount > 0 ? round(($kpiAtRisk / $studentCount) * 100, 1) : 0.0;
$attendanceRisk = max(0.0, min(100.0, 100.0 - $kpiAbsenteeism));
$ratioHealth = $teacherCount > 0
    ? min(100.0, round((20.0 / max(1.0, $studentTeacherRatio)) * 100.0, 1))
    : 0.0;

$clampPercent = static function ($value): float {
    return max(0.0, min(100.0, (float) $value));
};

$kpiRows = [
    [
        [
            'title' => __('Students'),
            'code' => 'ENR',
            'value' => number_format($studentCount),
            'sub' => __('Enrolled in selected cohort'),
            'ring' => 100.0,
            'ringText' => __('Cohort'),
            'tone' => 'teal',
        ],
        [
            'title' => __('Teachers'),
            'code' => 'STAFF',
            'value' => number_format($teacherCount),
            'sub' => __('Active class teachers this year'),
            'ring' => $clampPercent($staffingHealth),
            'ringText' => number_format($staffingHealth, 1) . '%',
            'tone' => 'blue',
        ],
    ],
    [
        [
            'title' => __('Student : Teacher'),
            'code' => 'RATIO',
            'value' => number_format($studentTeacherRatio, 1),
            'sub' => __('Students per teacher'),
            'ring' => $clampPercent($ratioHealth),
            'ringText' => number_format($ratioHealth, 1) . '%',
            'tone' => 'green',
        ],
        [
            'title' => __('Attendance Rate'),
            'code' => 'ATT',
            'value' => number_format($kpiAttendanceRate, 1) . '%',
            'sub' => __('Within selected date range'),
            'ring' => $clampPercent($kpiAttendanceRate),
            'ringText' => number_format($kpiAttendanceRate, 1) . '%',
            'tone' => 'teal',
        ],
    ],
    [
        [
            'title' => __('Markbook Average'),
            'code' => 'ACA',
            'value' => number_format($kpiMarkbookAvg, 1) . '%',
            'sub' => __('Average of each student\'s markbook mean'),
            'ring' => $clampPercent($kpiMarkbookAvg),
            'ringText' => number_format($kpiMarkbookAvg, 1) . '%',
            'tone' => 'blue',
        ],
        [
            'title' => __('Internal Assessment Avg'),
            'code' => 'IA',
            'value' => number_format($kpiInternalAssessmentAvg, 1) . '%',
            'sub' => __('Average of each student\'s IA mean'),
            'ring' => $clampPercent($kpiInternalAssessmentAvg),
            'ringText' => number_format($kpiInternalAssessmentAvg, 1) . '%',
            'tone' => 'teal',
        ],
    ],
    [
        [
            'title' => __('Pass Rate'),
            'code' => 'PASS',
            'value' => number_format($kpiPassRate, 1) . '%',
            'sub' => __('Students with markbook score 50% and above'),
            'ring' => $clampPercent($kpiPassRate),
            'ringText' => number_format($kpiPassRate, 1) . '%',
            'tone' => 'green',
        ],
        [
            'title' => __('Chronic Absenteeism'),
            'code' => 'RISK',
            'value' => number_format($kpiAbsenteeism, 1) . '%',
            'sub' => __('Students absent more than 18 days'),
            'ring' => $clampPercent($attendanceRisk),
            'ringText' => number_format($kpiAbsenteeism, 1) . '%',
            'tone' => 'amber',
        ],
    ],
    [
        [
            'title' => __('At-Risk Students'),
            'code' => 'ALERT',
            'value' => number_format($kpiAtRisk),
            'sub' => __('Low grades or high absence profile'),
            'ring' => $clampPercent($atRiskRate),
            'ringText' => number_format($atRiskRate, 1) . '%',
            'tone' => 'red',
        ],
        [
            'title' => __('Behaviour Flags'),
            'code' => 'BHV',
            'value' => number_format($studentsFlaggedCount),
            'sub' => __('Students with negative behaviour records'),
            'ring' => $clampPercent($behaviourFlagRate),
            'ringText' => number_format($behaviourFlagRate, 1) . '%',
            'tone' => 'red',
        ],
    ],
];

$jsConfig = json_encode([
    'baseURL' => $absoluteURL,
    'yearID' => $selectedYearID,
    'yearGroupID' => $selectedYearGrp,
    'formGroupID' => $selectedFormGrp,
    'teacherID' => $selectedTeacher,
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

if ($jsConfig === false) {
    $jsConfig = '{}';
}

$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$studentProfileBaseURL = $absoluteURL . '/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=';

echo "<script src='https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js'></script>";
echo "<script>window.PD = {$jsConfig};</script>";
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap');

/* ── Design tokens ─────────────────────────────────────────── */
.pd-page {
    width: 100%;
    box-sizing: border-box;
    --pd-bg: #f0f4f9;
    --pd-surface: #ffffff;
    --pd-border: #dce5ef;
    --pd-shadow-sm: 0 1px 3px rgba(15,35,60,.07), 0 4px 14px rgba(15,35,60,.05);
    --pd-shadow-md: 0 2px 8px rgba(15,35,60,.08), 0 12px 32px rgba(15,35,60,.07);
    --pd-text: #1a3450;
    --pd-muted: #526a82;
    --pd-radius: 14px;

    /* Category accent colours */
    --cat-enrol: #2563eb;
    --cat-enrol-bg: #eff6ff;
    --cat-enrol-border: #bfdbfe;

    --cat-academic: #0891b2;
    --cat-academic-bg: #ecfeff;
    --cat-academic-border: #a5f3fc;

    --cat-attend: #059669;
    --cat-attend-bg: #ecfdf5;
    --cat-attend-border: #a7f3d0;

    --cat-risk: #dc2626;
    --cat-risk-bg: #fef2f2;
    --cat-risk-border: #fecaca;

    font-family: 'Sora', system-ui, sans-serif;
    color: var(--pd-text);
}

/* ── Shell ──────────────────────────────────────────────────── */
.pd-shell {
    background: var(--pd-bg);
    /* Break out of Gibbon's px-4/px-8 content padding so panels fill the white box edge-to-edge */
    width: calc(100% + 2rem);
    margin-left: -1rem;
    margin-right: -1rem;
    box-sizing: border-box;
    padding: 0 1rem 24px;
}

@media (min-width: 640px) {
    .pd-shell {
        width: calc(100% + 4rem);
        margin-left: -2rem;
        margin-right: -2rem;
        padding: 0 2rem 24px;
    }
}

/* Ensure every layout container fills its parent */
.pd-section,
.pd-filter-card,
.pd-panel-grid,
.pd-kpi-grid {
    width: 100%;
    box-sizing: border-box;
}

/* ── Filter bar ─────────────────────────────────────────────── */
.pd-filter-card {
    background: var(--pd-surface);
    border: 1px solid var(--pd-border);
    border-radius: var(--pd-radius);
    box-shadow: var(--pd-shadow-sm);
    padding: 16px;
    margin-bottom: 20px;
}

.pd-filter-form {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
    align-items: end;
}

.pd-filter-group label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    color: var(--pd-muted);
    letter-spacing: 0.07em;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.pd-filter-group select,
.pd-filter-group input[type="date"] {
    width: 100%;
    border: 1.5px solid #d0dceb;
    border-radius: 8px;
    height: 38px;
    padding: 0 10px;
    font-family: inherit;
    font-size: 13px;
    color: var(--pd-text);
    background: #fafcff;
    transition: border-color .15s, box-shadow .15s;
}

.pd-filter-group select:focus,
.pd-filter-group input[type="date"]:focus {
    border-color: var(--cat-enrol);
    box-shadow: 0 0 0 3px rgba(37,99,235,.14);
    outline: none;
}

.pd-filter-actions { display: flex; gap: 8px; }

.pd-btn {
    border: 1.5px solid transparent;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 14px;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    white-space: nowrap;
    transition: background .15s, box-shadow .15s;
}
.pd-btn.apply { background: var(--cat-enrol); color: #fff; }
.pd-btn.apply:hover { background: #1d4ed8; }
.pd-btn.reset { background: #f1f5fb; border-color: #d0dceb; color: #3b5878; }
.pd-btn.reset:hover { background: #e4ecf7; }

/* ── Category section headers ───────────────────────────────── */
.pd-section {
    margin-bottom: 20px;
}

.pd-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--pd-border);
}

.pd-section-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.pd-section-label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.pd-section-desc {
    font-size: 12px;
    color: var(--pd-muted);
    margin-left: auto;
    font-weight: 600;
}

/* Category colour bindings */
.pd-section.enrol .pd-section-dot   { background: var(--cat-enrol); }
.pd-section.enrol .pd-section-label { color: var(--cat-enrol); }
.pd-section.enrol .pd-section-header { border-color: var(--cat-enrol-border); }

.pd-section.academic .pd-section-dot   { background: var(--cat-academic); }
.pd-section.academic .pd-section-label { color: var(--cat-academic); }
.pd-section.academic .pd-section-header { border-color: var(--cat-academic-border); }

.pd-section.attend .pd-section-dot   { background: var(--cat-attend); }
.pd-section.attend .pd-section-label { color: var(--cat-attend); }
.pd-section.attend .pd-section-header { border-color: var(--cat-attend-border); }

.pd-section.risk .pd-section-dot   { background: var(--cat-risk); }
.pd-section.risk .pd-section-label { color: var(--cat-risk); }
.pd-section.risk .pd-section-header { border-color: var(--cat-risk-border); }

/* ── KPI stat cards ─────────────────────────────────────────── */
.pd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 12px;
}

.pd-kpi-card {
    background: var(--pd-surface);
    border: 1.5px solid var(--pd-border);
    border-radius: var(--pd-radius);
    box-shadow: var(--pd-shadow-sm);
    padding: 18px 18px 16px;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: box-shadow .18s, transform .18s;
}
.pd-kpi-card:hover {
    box-shadow: var(--pd-shadow-md);
    transform: translateY(-1px);
}

/* Left accent bar */
.pd-kpi-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: var(--pd-radius) 0 0 var(--pd-radius);
}

/* Category accent per section */
.pd-section.enrol   .pd-kpi-card::before { background: var(--cat-enrol); }
.pd-section.enrol   .pd-kpi-card { border-color: var(--cat-enrol-border); background: linear-gradient(135deg, var(--cat-enrol-bg) 0%, #fff 60%); }
.pd-section.academic .pd-kpi-card::before { background: var(--cat-academic); }
.pd-section.academic .pd-kpi-card { border-color: var(--cat-academic-border); background: linear-gradient(135deg, var(--cat-academic-bg) 0%, #fff 60%); }
.pd-section.attend  .pd-kpi-card::before { background: var(--cat-attend); }
.pd-section.attend  .pd-kpi-card { border-color: var(--cat-attend-border); background: linear-gradient(135deg, var(--cat-attend-bg) 0%, #fff 60%); }
.pd-section.risk    .pd-kpi-card::before { background: var(--cat-risk); }
.pd-section.risk    .pd-kpi-card { border-color: var(--cat-risk-border); background: linear-gradient(135deg, var(--cat-risk-bg) 0%, #fff 60%); }

.pd-kpi-badge {
    display: inline-flex;
    align-items: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 2px 8px;
    border-radius: 999px;
    align-self: flex-start;
    margin-bottom: 6px;
}
.pd-section.enrol    .pd-kpi-badge { background: var(--cat-enrol-bg);    color: var(--cat-enrol);    border: 1px solid var(--cat-enrol-border); }
.pd-section.academic .pd-kpi-badge { background: var(--cat-academic-bg); color: var(--cat-academic); border: 1px solid var(--cat-academic-border); }
.pd-section.attend   .pd-kpi-badge { background: var(--cat-attend-bg);   color: var(--cat-attend);   border: 1px solid var(--cat-attend-border); }
.pd-section.risk     .pd-kpi-badge { background: var(--cat-risk-bg);     color: var(--cat-risk);     border: 1px solid var(--cat-risk-border); }

.pd-kpi-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
    color: var(--pd-text);
    letter-spacing: -0.02em;
}

.pd-kpi-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--pd-text);
    line-height: 1.3;
}

.pd-kpi-sub {
    font-size: 11.5px;
    color: var(--pd-muted);
    line-height: 1.35;
    font-weight: 500;
}

/* Ring gauge (kept for backward compat, hidden in new layout) */
.pd-ring { display: none; }

/* ── Panels (charts / tables / behaviour) ───────────────────── */
.pd-panel-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
    width: 100%;
    box-sizing: border-box;
    align-items: stretch;
}

/* Each panel in a 2-col grid: exactly half width minus half the gap */
.pd-panel-grid > .pd-panel {
    flex: 0 0 calc(50% - 8px);
    width: calc(50% - 8px);
    max-width: calc(50% - 8px);
    box-sizing: border-box;
    min-width: 0;
    overflow: hidden;
}

.pd-panel-grid.cols-3 > .pd-panel {
    flex: 0 0 calc(33.333% - 11px);
    width: calc(33.333% - 11px);
    max-width: calc(33.333% - 11px);
}

.pd-panel {
    background: var(--pd-surface);
    border: 1px solid var(--pd-border);
    border-radius: var(--pd-radius);
    box-shadow: var(--pd-shadow-sm);
    padding: 18px;
    display: flex;
    flex-direction: column;
    min-height: 400px;
    box-sizing: border-box;
}

.pd-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--pd-border);
}

.pd-panel-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--pd-text);
    line-height: 1.2;
}

.pd-panel-subtitle {
    margin: 4px 0 0;
    font-size: 12px;
    color: var(--pd-muted);
    line-height: 1.4;
    font-weight: 500;
}

/* ── Behaviour/badge mini stats ─────────────────────────────── */
.pd-mini-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}

.pd-mini-card {
    border: 1px solid #dce9f5;
    background: #f7fbff;
    border-radius: 10px;
    padding: 10px 12px;
}

.pd-mini-label {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    font-weight: 700;
    color: var(--pd-muted);
    margin-bottom: 3px;
}

.pd-mini-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 26px;
    font-weight: 700;
    color: var(--pd-text);
    line-height: 1;
}

.pd-mini-sub {
    font-size: 12px;
    font-weight: 600;
    color: var(--pd-muted);
}

/* Progress bars */
.pd-balance-row, .pd-cohort-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--pd-muted);
    margin-bottom: 5px;
}

.pd-balance-bar, .pd-cohort-bar {
    height: 8px;
    border-radius: 999px;
    background: #e3edf7;
    overflow: hidden;
    margin-bottom: 12px;
}

.pd-balance-fill {
    height: 100%;
    background: linear-gradient(90deg, #059669 0%, #059669 50%, #dc2626 50%, #dc2626 100%);
    width: 100%;
}

.pd-cohort-fill {
    height: 100%;
    background: var(--cat-academic);
}

/* Lists */
.pd-list-title {
    margin: 10px 0 6px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 800;
    color: var(--pd-muted);
}

.pd-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 5px;
}

.pd-list li {
    border: 1px solid #dce9f5;
    border-radius: 8px;
    padding: 7px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    background: #f8fbff;
}

.pd-list a {
    color: #1d5fa8;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}
.pd-list a:hover { text-decoration: underline; }

.pd-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 22px;
    padding: 0 7px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    color: #1a3450;
    border-radius: 999px;
    background: #dde9f6;
}

.pd-empty {
    border: 1px dashed #c9d9ea;
    border-radius: 9px;
    color: #6b8399;
    background: #f7fbff;
    padding: 12px;
    font-size: 13px;
    font-weight: 600;
}

/* ── Panel tools (pagination controls) ──────────────────────── */
.pd-panel-tools {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.pd-tool-btn, .pd-tool-select {
    border: 1px solid #ccd8e8;
    border-radius: 7px;
    background: #f5f9ff;
    color: #4f6982;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 10px;
    cursor: pointer;
}
.pd-tool-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.pd-tool-range {
    min-width: 96px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: #4a6480;
}

/* ── Charts / tables ────────────────────────────────────────── */
.pd-chart-host, .pd-table-host {
    min-height: 280px;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.pd-loading {
    min-height: 260px;
    border: 1px dashed #c5d5e5;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6a8099;
    font-size: 13px;
    font-weight: 600;
    background: #f8fbff;
    text-align: center;
    padding: 12px;
}

.pd-table-wrap {
    overflow: auto;
    border: 1px solid #cddae9;
    border-radius: 10px;
}

.pd-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 620px;
}

.pd-table th {
    background: #edf3fb;
    color: #4a6480;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 11px;
    font-weight: 800;
    border-bottom: 1px solid #c8d8e8;
    padding: 9px 12px;
    text-align: left;
}

.pd-table td {
    border-bottom: 1px solid #e1eaf5;
    padding: 8px 12px;
    font-size: 12.5px;
    color: #253f58;
    font-weight: 600;
}

.pd-table tr:last-child td { border-bottom: none; }
.pd-table td a { color: #1e5a90; text-decoration: none; font-weight: 700; }
.pd-table td a:hover { text-decoration: underline; }

.pd-risk-badge {
    display: inline-block;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 9px;
}
.pd-risk-badge.green  { color: #065f46; background: #d1fae5; }
.pd-risk-badge.amber  { color: #92400e; background: #fef3c7; }
.pd-risk-badge.red    { color: #991b1b; background: #fee2e2; }

/* ── Modal ──────────────────────────────────────────────────── */
.pd-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10,25,45,.48);
    z-index: 9000;
    padding: 24px;
}
.pd-modal-overlay.active { display: flex; justify-content: center; align-items: center; }

.pd-modal {
    width: min(940px, 97vw);
    max-height: 88vh;
    overflow: auto;
    border-radius: var(--pd-radius);
    border: 1px solid #bfd0e4;
    background: #fff;
    box-shadow: 0 20px 60px rgba(10,25,45,.28);
    padding: 20px;
}

.pd-modal-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 14px;
}

.pd-modal-title {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: var(--pd-text);
}

.pd-modal-subtitle {
    margin: 3px 0 0;
    font-size: 12px;
    color: var(--pd-muted);
    font-weight: 600;
}

.pd-modal-close {
    border: none;
    background: #edf3fb;
    color: #3a5872;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
}
.pd-modal-close:hover { background: #dae5f4; }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .pd-filter-form { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .pd-kpi-grid    { grid-template-columns: repeat(2, 1fr); }

    /* Stack panels to single column */
    .pd-panel-grid > .pd-panel,
    .pd-panel-grid.cols-3 > .pd-panel {
        flex: 0 0 100%;
        width: 100%;
        max-width: 100%;
    }

    .pd-panel { min-height: auto; }
}

@media (max-width: 680px) {
    .pd-filter-form     { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pd-kpi-grid        { grid-template-columns: 1fr; }
    .pd-filter-actions  { width: 100%; }
    .pd-btn             { width: 100%; }
}

@media (max-width: 480px) {
    .pd-filter-form { grid-template-columns: 1fr; }
}
</style>

<div class="pd-page">
    <div class="pd-shell">

        <!-- ── Filter bar ──────────────────────────────────────── -->
        <section class="pd-filter-card">
            <form method="get" action="<?= $h($absoluteURL) ?>/index.php" id="pd-filter-form" class="pd-filter-form">
                <input type="hidden" name="q" value="<?= $h($dashboardPath) ?>">

                <div class="pd-filter-group">
                    <label for="pd-year"><?= $h(__('Academic Year')) ?></label>
                    <select name="gibbonSchoolYearID" id="pd-year">
                        <?php foreach ($schoolYears as $schoolYear): ?>
                            <option value="<?= $h($schoolYear['value']) ?>" <?= ((string) $schoolYear['value'] === $selectedYearID ? 'selected' : '') ?>>
                                <?= $h($schoolYear['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pd-filter-group">
                    <label for="pd-year-group"><?= $h(__('Year Group')) ?></label>
                    <select name="yearGroupID" id="pd-year-group">
                        <option value=""><?= $h(__('All Year Groups')) ?></option>
                        <?php foreach ($yearGroups as $yearGroup): ?>
                            <option value="<?= $h($yearGroup['value']) ?>" <?= ((string) $yearGroup['value'] === $selectedYearGrp ? 'selected' : '') ?>>
                                <?= $h($yearGroup['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pd-filter-group">
                    <label for="pd-form-group"><?= $h(__('Form Group')) ?></label>
                    <select name="formGroupID" id="pd-form-group">
                        <option value=""><?= $h(__('All Form Groups')) ?></option>
                        <?php foreach ($formGroups as $formGroup): ?>
                            <option value="<?= $h($formGroup['value']) ?>" <?= ((string) $formGroup['value'] === $selectedFormGrp ? 'selected' : '') ?>>
                                <?= $h($formGroup['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pd-filter-group">
                    <label for="pd-teacher"><?= $h(__('Teacher')) ?></label>
                    <select name="teacherID" id="pd-teacher">
                        <option value=""><?= $h(__('All Teachers')) ?></option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $h($teacher['value']) ?>" <?= ((string) $teacher['value'] === $selectedTeacher ? 'selected' : '') ?>>
                                <?= $h($teacher['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pd-filter-group">
                    <label for="pd-from"><?= $h(__('Date From')) ?></label>
                    <input type="date" name="dateFrom" id="pd-from" value="<?= $h($dateFrom) ?>">
                </div>

                <div class="pd-filter-group">
                    <label for="pd-to"><?= $h(__('Date To')) ?></label>
                    <input type="date" name="dateTo" id="pd-to" value="<?= $h($dateTo) ?>">
                </div>

                <div class="pd-filter-actions">
                    <button type="submit" class="pd-btn apply"><?= $h(__('Apply Filters')) ?></button>
                    <a class="pd-btn reset" href="<?= $h($dashboardURL) ?>"><?= $h(__('Reset')) ?></a>
                </div>
            </form>
        </section>

        <!-- ── ENROLMENT ─────────────────────────────────────── -->
        <section class="pd-section enrol">
            <div class="pd-section-header">
                <span class="pd-section-dot"></span>
                <span class="pd-section-label"><?= $h(__('Enrolment')) ?></span>
                <span class="pd-section-desc"><?= $h(__('Staffing &amp; cohort size')) ?></span>
            </div>
            <div class="pd-kpi-grid">
                <?php foreach ($kpiRows[0] as $card): ?>
                    <article class="pd-kpi-card">
                        <span class="pd-kpi-badge"><?= $h($card['code']) ?></span>
                        <div class="pd-kpi-value"><?= $h($card['value']) ?></div>
                        <div class="pd-kpi-title"><?= $h($card['title']) ?></div>
                        <div class="pd-kpi-sub"><?= $h($card['sub']) ?></div>
                    </article>
                <?php endforeach; ?>
                <?php foreach ($kpiRows[1] as $card): ?>
                    <article class="pd-kpi-card">
                        <span class="pd-kpi-badge"><?= $h($card['code']) ?></span>
                        <div class="pd-kpi-value"><?= $h($card['value']) ?></div>
                        <div class="pd-kpi-title"><?= $h($card['title']) ?></div>
                        <div class="pd-kpi-sub"><?= $h($card['sub']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── ACADEMIC ──────────────────────────────────────── -->
        <section class="pd-section academic">
            <div class="pd-section-header">
                <span class="pd-section-dot"></span>
                <span class="pd-section-label"><?= $h(__('Academic')) ?></span>
                <span class="pd-section-desc"><?= $h(__('Markbook &amp; internal assessment averages')) ?></span>
            </div>
            <div class="pd-kpi-grid">
                <?php foreach ($kpiRows[2] as $card): ?>
                    <article class="pd-kpi-card">
                        <span class="pd-kpi-badge"><?= $h($card['code']) ?></span>
                        <div class="pd-kpi-value"><?= $h($card['value']) ?></div>
                        <div class="pd-kpi-title"><?= $h($card['title']) ?></div>
                        <div class="pd-kpi-sub"><?= $h($card['sub']) ?></div>
                    </article>
                <?php endforeach; ?>
                <?php foreach ($kpiRows[3] as $card): ?>
                    <article class="pd-kpi-card">
                        <span class="pd-kpi-badge"><?= $h($card['code']) ?></span>
                        <div class="pd-kpi-value"><?= $h($card['value']) ?></div>
                        <div class="pd-kpi-title"><?= $h($card['title']) ?></div>
                        <div class="pd-kpi-sub"><?= $h($card['sub']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── ATTENDANCE ────────────────────────────────────── -->
        <section class="pd-section attend">
            <div class="pd-section-header">
                <span class="pd-section-dot"></span>
                <span class="pd-section-label"><?= $h(__('Attendance')) ?></span>
                <span class="pd-section-desc"><?= $h($dateFrom) ?> — <?= $h($dateTo) ?></span>
            </div>
            <div class="pd-kpi-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                <?php $attendCard = $kpiRows[1][1]; /* Attendance Rate */ ?>
                <article class="pd-kpi-card">
                    <span class="pd-kpi-badge"><?= $h($attendCard['code']) ?></span>
                    <div class="pd-kpi-value"><?= $h($attendCard['value']) ?></div>
                    <div class="pd-kpi-title"><?= $h($attendCard['title']) ?></div>
                    <div class="pd-kpi-sub"><?= $h($attendCard['sub']) ?></div>
                </article>
                <?php $abCard = $kpiRows[3][1]; /* Chronic Absenteeism */ ?>
                <article class="pd-kpi-card">
                    <span class="pd-kpi-badge"><?= $h($abCard['code']) ?></span>
                    <div class="pd-kpi-value"><?= $h($abCard['value']) ?></div>
                    <div class="pd-kpi-title"><?= $h($abCard['title']) ?></div>
                    <div class="pd-kpi-sub"><?= $h($abCard['sub']) ?></div>
                </article>
            </div>
        </section>

        <!-- ── RISK & BEHAVIOUR ──────────────────────────────── -->
        <section class="pd-section risk">
            <div class="pd-section-header">
                <span class="pd-section-dot"></span>
                <span class="pd-section-label"><?= $h(__('Risk &amp; Behaviour')) ?></span>
                <span class="pd-section-desc"><?= $h(__('Students requiring attention')) ?></span>
            </div>
            <div class="pd-kpi-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); margin-bottom: 16px;">
                <?php foreach ($kpiRows[4] as $card): ?>
                    <article class="pd-kpi-card">
                        <span class="pd-kpi-badge"><?= $h($card['code']) ?></span>
                        <div class="pd-kpi-value"><?= $h($card['value']) ?></div>
                        <div class="pd-kpi-title"><?= $h($card['title']) ?></div>
                        <div class="pd-kpi-sub"><?= $h($card['sub']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── Behaviour & Badges ────────────────────────────── -->
        <div class="pd-panel-grid" style="margin-bottom: 20px;">
            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Behaviour Snapshot')) ?></h2>
                        <p class="pd-panel-subtitle">
                            <?= $h(__('Positive vs negative record mix')) ?>
                            <?= $h(__('from')) ?>
                            <?= $h($dateFrom) ?>
                            <?= $h(__('to')) ?>
                            <?= $h($dateTo) ?>.
                        </p>
                    </div>
                </header>

                <div class="pd-mini-grid">
                    <div class="pd-mini-card">
                        <div class="pd-mini-label"><?= $h(__('Total Records')) ?></div>
                        <div class="pd-mini-value"><?= $h(number_format($behaviourTotal)) ?></div>
                    </div>
                    <div class="pd-mini-card">
                        <div class="pd-mini-label"><?= $h(__('Positive')) ?></div>
                        <div class="pd-mini-value"><?= $h(number_format($positiveCount)) ?></div>
                    </div>
                    <div class="pd-mini-card">
                        <div class="pd-mini-label"><?= $h(__('Negative')) ?></div>
                        <div class="pd-mini-value"><?= $h(number_format($negativeCount)) ?></div>
                    </div>
                    <div class="pd-mini-card">
                        <div class="pd-mini-label"><?= $h(__('Observation')) ?></div>
                        <div class="pd-mini-value"><?= $h(number_format($observationCount)) ?></div>
                    </div>
                    <div class="pd-mini-card">
                        <div class="pd-mini-label"><?= $h(__('Observation Share')) ?></div>
                        <div class="pd-mini-sub"><?= $h(number_format($observationShare, 1)) ?>%</div>
                    </div>
                    <div class="pd-mini-card">
                        <div class="pd-mini-label"><?= $h(__('Students Flagged')) ?></div>
                        <div class="pd-mini-value"><?= $h(number_format($studentsFlaggedCount)) ?></div>
                    </div>
                </div>

                <div class="pd-balance-row">
                    <span><?= $h(__('Behaviour Balance')) ?></span>
                    <span><?= $h(number_format($positiveShare, 1)) ?>% <?= $h(__('positive')) ?> / <?= $h(number_format($negativeShare, 1)) ?>% <?= $h(__('negative')) ?></span>
                </div>
                <div class="pd-balance-bar" aria-hidden="true">
                    <div class="pd-balance-fill"></div>
                </div>

                <h3 class="pd-list-title"><?= $h(__('Top Negative Descriptors')) ?></h3>
                <?php if (!empty($topNegativeDescriptors)): ?>
                    <ul class="pd-list">
                        <?php foreach ($topNegativeDescriptors as $descriptor): ?>
                            <li>
                                <span><?= $h($descriptor['descriptor']) ?></span>
                                <span class="pd-chip"><?= $h((int) $descriptor['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="pd-empty"><?= $h(__('No negative behaviour descriptors found for the selected filters.')) ?></div>
                <?php endif; ?>

                <h3 class="pd-list-title"><?= $h(__('Students with Behaviour Issues')) ?></h3>
                <?php if (!empty($behaviourStudents)): ?>
                    <ul class="pd-list">
                        <?php foreach ($behaviourStudents as $student): ?>
                            <li>
                                <a href="<?= $h($studentProfileBaseURL . (int) $student['personID']) ?>"><?= $h($student['studentName']) ?></a>
                                <span class="pd-chip"><?= $h((int) $student['issueCount']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="pd-empty"><?= $h(__('No students are currently flagged for negative behaviour in this date range.')) ?></div>
                <?php endif; ?>
            </article>

            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Badge Activity')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Recognition distribution for the selected academic year.')) ?></p>
                    </div>
                </header>

                <?php if (!(bool) ($badgeSummary['enabled'] ?? false)): ?>
                    <div class="pd-empty"><?= $h(__('Badge tables are not available in this installation.')) ?></div>
                <?php else: ?>
                    <div class="pd-mini-grid">
                        <div class="pd-mini-card">
                            <div class="pd-mini-label"><?= $h(__('Awards Issued')) ?></div>
                            <div class="pd-mini-value"><?= $h((int) ($badgeSummary['awards'] ?? 0)) ?></div>
                        </div>
                        <div class="pd-mini-card">
                            <div class="pd-mini-label"><?= $h(__('Recipients')) ?></div>
                            <div class="pd-mini-value"><?= $h((int) ($badgeSummary['recipients'] ?? 0)) ?></div>
                        </div>
                        <div class="pd-mini-card">
                            <div class="pd-mini-label"><?= $h(__('Active Badge Types')) ?></div>
                            <div class="pd-mini-value"><?= $h((int) ($badgeSummary['activeBadges'] ?? 0)) ?></div>
                        </div>
                        <div class="pd-mini-card">
                            <div class="pd-mini-label"><?= $h(__('License Awards')) ?></div>
                            <div class="pd-mini-value"><?= $h((int) ($badgeSummary['licenseAwards'] ?? 0)) ?></div>
                        </div>
                    </div>

                    <div class="pd-cohort-row">
                        <span><?= $h(__('Cohort Reach')) ?></span>
                        <span><?= $h(number_format($badgeReach, 1)) ?>% <?= $h(__('of students have at least one badge')) ?></span>
                    </div>
                    <div class="pd-cohort-bar" aria-hidden="true">
                        <div class="pd-cohort-fill" style="width: <?= $h(number_format($clampPercent($badgeReach), 1, '.', '')) ?>%;"></div>
                    </div>

                    <h3 class="pd-list-title"><?= $h(__('Students Awarded Badges')) ?></h3>
                    <?php if (!empty($badgeStudents)): ?>
                        <ul class="pd-list">
                            <?php foreach ($badgeStudents as $student): ?>
                                <li>
                                    <a href="<?= $h($studentProfileBaseURL . (int) $student['personID']) ?>"><?= $h($student['studentName']) ?></a>
                                    <span class="pd-chip"><?= $h((int) $student['awardCount']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="pd-empty"><?= $h(__('No badge recipients found for the selected filters.')) ?></div>
                    <?php endif; ?>

                    <h3 class="pd-list-title"><?= $h(__('Repeat Recipients')) ?></h3>
                    <div class="pd-mini-card">
                        <div class="pd-mini-value"><?= $h($repeatRecipients) ?></div>
                        <div class="pd-mini-sub"><?= $h(__('Students with more than one badge award')) ?></div>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <!-- ── Academic charts ──────────────────────────────── -->
        <div class="pd-panel-grid">
            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Markbook Grade Distribution')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Student count by each student\'s average markbook grade.')) ?></p>
                    </div>
                </header>
                <div id="chart-markbook-dist" class="pd-chart-host">
                    <div class="pd-loading"><?= $h(__('Loading chart...')) ?></div>
                </div>
            </article>

            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('IA vs Markbook — Class Averages')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Student-weighted class averages, side-by-side. Use paging to review in chunks.')) ?></p>
                    </div>
                </header>
                <div class="pd-panel-tools" id="pd-comp-tools">
                    <button type="button" class="pd-tool-btn" id="pd-comp-prev"><?= $h(__('Prev')) ?></button>
                    <span class="pd-tool-range" id="pd-comp-range">0 of 0</span>
                    <button type="button" class="pd-tool-btn" id="pd-comp-next"><?= $h(__('Next')) ?></button>
                    <select id="pd-comp-size" class="pd-tool-select" aria-label="<?= $h(__('Classes per page')) ?>">
                        <option value="12">12</option>
                        <option value="21" selected>21</option>
                        <option value="30">30</option>
                    </select>
                </div>
                <div id="chart-comparison" class="pd-chart-host">
                    <div class="pd-loading"><?= $h(__('Loading chart...')) ?></div>
                </div>
            </article>

            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Internal Assessment Trends')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Average % per assessment column over time. Click a marker to drill down into individual scores.')) ?></p>
                    </div>
                </header>
                <div id="chart-ia-trend" class="pd-chart-host">
                    <div class="pd-loading"><?= $h(__('Loading chart...')) ?></div>
                </div>
            </article>

            <!-- ── Attendance charts ─────────────────────────── -->
            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Daily Attendance Trend')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Attendance percentage over the selected date range.')) ?></p>
                    </div>
                </header>
                <div id="chart-attendance" class="pd-chart-host">
                    <div class="pd-loading"><?= $h(__('Loading chart...')) ?></div>
                </div>
            </article>

            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Absence Heatmap')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Absence density by day-of-week and week number.')) ?></p>
                    </div>
                </header>
                <div id="chart-heatmap" class="pd-chart-host">
                    <div class="pd-loading"><?= $h(__('Loading chart...')) ?></div>
                </div>
            </article>

            <!-- ── At-Risk table ─────────────────────────────── -->
            <article class="pd-panel">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('At-Risk Students')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Flagged by low markbook average (&lt;50%) or high absenteeism (&gt;18 days).')) ?></p>
                    </div>
                </header>
                <div id="at-risk-container" class="pd-table-host">
                    <div class="pd-loading"><?= $h(__('Loading students...')) ?></div>
                </div>
            </article>
        </div>

    </div><!-- /.pd-shell -->
</div><!-- /.pd-page -->

<div id="pd-modal" class="pd-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="pd-modal-title">
    <div class="pd-modal">
        <div class="pd-modal-head">
            <div>
                <h3 id="pd-modal-title" class="pd-modal-title"><?= $h(__('Assessment Breakdown')) ?></h3>
                <p id="pd-modal-subtitle" class="pd-modal-subtitle"></p>
            </div>
            <button type="button" id="pd-modal-close" class="pd-modal-close" aria-label="<?= $h(__('Close')) ?>">&times;</button>
        </div>
        <div id="pd-modal-body">
            <div class="pd-loading"><?= $h(__('Loading...')) ?></div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var chartRefs = {};
    var comparisonState = {
        labels: [],
        ia: [],
        markbook: [],
        page: 0,
        pageSize: 21
    };
    var iaTrendColumnIDs = [];

    var moduleAjaxBase = '/modules/Principal%20Dashboard/ajax/';
    var palette = {
        blue: '#2a5f9e',
        teal: '#238f9f',
        green: '#1aa56f',
        amber: '#d48811',
        red: '#ce4b4b',
        slate: '#5a7089'
    };

    function hasApex() {
        return typeof window.ApexCharts !== 'undefined';
    }

    function clampPercent(value) {
        var n = Number(value);
        if (!isFinite(n)) {
            return 0;
        }
        if (n < 0) {
            return 0;
        }
        if (n > 100) {
            return 100;
        }
        return n;
    }

    function escHtml(value) {
        var text = value === null || value === undefined ? '' : String(value);
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function fmtPct(value) {
        var n = Number(value);
        if (!isFinite(n)) {
            return 'N/A';
        }
        return n.toFixed(1) + '%';
    }

    function truncateLabel(value, maxLength) {
        var text = value === null || value === undefined ? '' : String(value);
        if (text.length <= maxLength) {
            return text;
        }
        return text.slice(0, maxLength - 3) + '...';
    }

    function buildParams(extra) {
        var params = new URLSearchParams({
            yearID: PD.yearID || '',
            yearGroupID: PD.yearGroupID || '',
            formGroupID: PD.formGroupID || '',
            teacherID: PD.teacherID || '',
            dateFrom: PD.dateFrom || '',
            dateTo: PD.dateTo || ''
        });

        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (key) {
                params.set(key, extra[key]);
            });
        }

        return params.toString();
    }

    function endpointURL(endpoint, extraParams) {
        return PD.baseURL + moduleAjaxBase + endpoint + '?' + buildParams(extraParams);
    }

    function fetchJSON(endpoint, extraParams) {
        return fetch(endpointURL(endpoint, extraParams), {
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        });
    }

    function setLoading(containerID, message) {
        var el = document.getElementById(containerID);
        if (!el) {
            return;
        }
        el.innerHTML = '<div class="pd-loading">' + escHtml(message) + '</div>';
    }

    function setEmpty(containerID, message) {
        var el = document.getElementById(containerID);
        if (!el) {
            return;
        }
        el.innerHTML = '<div class="pd-loading">' + escHtml(message) + '</div>';
    }

    function destroyChart(containerID) {
        if (chartRefs[containerID]) {
            chartRefs[containerID].destroy();
            chartRefs[containerID] = null;
        }
    }

    function renderChart(containerID, options) {
        var el = document.getElementById(containerID);
        if (!el || !hasApex()) {
            return;
        }

        destroyChart(containerID);
        el.innerHTML = '';

        chartRefs[containerID] = new ApexCharts(el, options);
        chartRefs[containerID].render();
    }

    function loadMarkbookDistribution() {
        setLoading('chart-markbook-dist', 'Loading chart...');

        fetchJSON('markbookDistribution.php').then(function (res) {
            if (!res.success || !res.data || !Array.isArray(res.data.labels)) {
                setEmpty('chart-markbook-dist', 'No markbook distribution data available.');
                return;
            }

            renderChart('chart-markbook-dist', {
                chart: {
                    type: 'bar',
                    height: 320,
                    toolbar: { show: true }
                },
                series: [{
                    name: 'Students',
                    data: Array.isArray(res.data.values) ? res.data.values : []
                }],
                xaxis: {
                    categories: res.data.labels,
                    labels: {
                        rotate: 0,
                        style: {
                            colors: '#4d6781',
                            fontSize: '11px'
                        }
                    }
                },
                yaxis: {
                    title: {
                        text: 'Student Count'
                    },
                    labels: {
                        style: {
                            colors: '#4d6781'
                        }
                    }
                },
                colors: [palette.teal],
                plotOptions: {
                    bar: {
                        borderRadius: 5,
                        columnWidth: '56%'
                    }
                },
                dataLabels: { enabled: false },
                grid: {
                    borderColor: '#dbe6f2',
                    strokeDashArray: 3
                },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return value + ' students';
                        }
                    }
                }
            });
        }).catch(function () {
            setEmpty('chart-markbook-dist', 'Failed to load markbook distribution.');
        });
    }

    function updateComparisonControls() {
        var prevButton = document.getElementById('pd-comp-prev');
        var nextButton = document.getElementById('pd-comp-next');
        var rangeText = document.getElementById('pd-comp-range');

        var total = comparisonState.labels.length;
        var start = total === 0 ? 0 : (comparisonState.page * comparisonState.pageSize) + 1;
        var end = Math.min(total, (comparisonState.page + 1) * comparisonState.pageSize);

        if (rangeText) {
            rangeText.textContent = total === 0 ? '0 of 0' : (start + '-' + end + ' of ' + total);
        }

        if (prevButton) {
            prevButton.disabled = comparisonState.page <= 0;
        }

        if (nextButton) {
            nextButton.disabled = end >= total;
        }
    }

    function renderComparisonPage() {
        var total = comparisonState.labels.length;

        if (total === 0) {
            setEmpty('chart-comparison', 'No class comparison data available.');
            updateComparisonControls();
            return;
        }

        var maxPage = Math.max(0, Math.ceil(total / comparisonState.pageSize) - 1);
        if (comparisonState.page > maxPage) {
            comparisonState.page = maxPage;
        }

        var start = comparisonState.page * comparisonState.pageSize;
        var end = Math.min(total, start + comparisonState.pageSize);

        var pageLabels = comparisonState.labels.slice(start, end).map(function (label) {
            return truncateLabel(label, 22);
        });

        var pageIA = comparisonState.ia.slice(start, end);
        var pageMarkbook = comparisonState.markbook.slice(start, end);

        renderChart('chart-comparison', {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: true }
            },
            series: [
                {
                    name: 'Internal Assessment Avg %',
                    data: pageIA
                },
                {
                    name: 'Markbook Avg %',
                    data: pageMarkbook
                }
            ],
            colors: [palette.blue, palette.teal],
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    columnWidth: '66%'
                }
            },
            xaxis: {
                categories: pageLabels,
                labels: {
                    rotate: -30,
                    trim: true,
                    style: {
                        colors: '#4f6a83',
                        fontSize: '11px'
                    }
                }
            },
            yaxis: {
                min: 0,
                max: 100,
                tickAmount: 5,
                title: {
                    text: 'Average Score (%)'
                },
                labels: {
                    style: {
                        colors: '#4f6a83'
                    }
                }
            },
            legend: {
                position: 'top'
            },
            dataLabels: {
                enabled: false
            },
            grid: {
                borderColor: '#dbe6f2',
                strokeDashArray: 3
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return fmtPct(value);
                    }
                }
            }
        });

        updateComparisonControls();
    }

    function loadComparison() {
        setLoading('chart-comparison', 'Loading chart...');

        fetchJSON('comparison.php').then(function (res) {
            if (!res.success || !res.data || !Array.isArray(res.data.labels)) {
                comparisonState.labels = [];
                comparisonState.ia = [];
                comparisonState.markbook = [];
                renderComparisonPage();
                return;
            }

            comparisonState.labels = res.data.labels;
            comparisonState.ia = Array.isArray(res.data.ia) ? res.data.ia : [];
            comparisonState.markbook = Array.isArray(res.data.markbook) ? res.data.markbook : [];
            comparisonState.page = 0;

            renderComparisonPage();
        }).catch(function () {
            setEmpty('chart-comparison', 'Failed to load class comparison.');
            comparisonState.labels = [];
            comparisonState.ia = [];
            comparisonState.markbook = [];
            updateComparisonControls();
        });
    }

    function openDrillDown(columnID, columnName) {
        var modal = document.getElementById('pd-modal');
        var modalTitle = document.getElementById('pd-modal-title');
        var modalSubtitle = document.getElementById('pd-modal-subtitle');
        var modalBody = document.getElementById('pd-modal-body');

        if (!modal || !modalTitle || !modalSubtitle || !modalBody) {
            return;
        }

        modalTitle.textContent = columnName || 'Assessment Breakdown';
        modalSubtitle.textContent = 'Individual student scores for this assessment column.';
        modalBody.innerHTML = '<div class="pd-loading">Loading...</div>';
        modal.classList.add('active');

        fetchJSON('assessmentDrillDown.php', {
            columnID: columnID
        }).then(function (res) {
            if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                modalBody.innerHTML = '<div class="pd-loading">No student data found for this assessment.</div>';
                return;
            }

            var html = '';
            html += '<div class="pd-table-wrap">';
            html += '<table class="pd-table">';
            html += '<thead><tr>';
            html += '<th>Rank</th><th>Student</th><th>Score</th><th>Status</th><th>Comment</th>';
            html += '</tr></thead><tbody>';

            res.data.forEach(function (student) {
                var score = Number(student.scorePct);
                var statusClass = 'red';
                var statusLabel = 'Support';

                if (isFinite(score) && score >= 65) {
                    statusClass = 'green';
                    statusLabel = 'Strong';
                } else if (isFinite(score) && score >= 50) {
                    statusClass = 'amber';
                    statusLabel = 'Borderline';
                }

                var profileURL = PD.baseURL + '/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=' + encodeURIComponent(student.personID);
                var comment = student.comment ? escHtml(student.comment) : '&mdash;';

                html += '<tr>';
                html += '<td>' + escHtml(student.rank) + ' / ' + escHtml(student.classTotal) + '</td>';
                html += '<td><a href="' + profileURL + '">' + escHtml(student.name) + '</a></td>';
                html += '<td>' + escHtml(fmtPct(score)) + '</td>';
                html += '<td><span class="pd-risk-badge ' + statusClass + '">' + statusLabel + '</span></td>';
                html += '<td>' + comment + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '</div>';
            modalBody.innerHTML = html;
        }).catch(function () {
            modalBody.innerHTML = '<div class="pd-loading">Failed to load drilldown data.</div>';
        });
    }

    function loadIATrend() {
        setLoading('chart-ia-trend', 'Loading chart...');

        fetchJSON('assessmentTrend.php').then(function (res) {
            if (!res.success || !res.data || !Array.isArray(res.data.series) || res.data.series.length === 0) {
                setEmpty('chart-ia-trend', 'No internal assessment trend data available.');
                iaTrendColumnIDs = [];
                return;
            }

            iaTrendColumnIDs = Array.isArray(res.data.columnIDs) ? res.data.columnIDs : [];
            var categories = Array.isArray(res.data.labels) ? res.data.labels.map(function (label) {
                return truncateLabel(label, 24);
            }) : [];
            var fullLabels = Array.isArray(res.data.labels) ? res.data.labels : [];

            renderChart('chart-ia-trend', {
                chart: {
                    type: 'line',
                    height: 320,
                    toolbar: { show: true },
                    events: {
                        markerClick: function (event, chartContext, config) {
                            var index = config.dataPointIndex;
                            if (index < 0) {
                                return;
                            }
                            var columnID = iaTrendColumnIDs[index];
                            if (!columnID) {
                                return;
                            }
                            var columnName = fullLabels[index] || 'Assessment';
                            openDrillDown(columnID, columnName);
                        }
                    }
                },
                series: res.data.series,
                colors: [palette.blue],
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                markers: {
                    size: 4,
                    hover: {
                        sizeOffset: 3
                    }
                },
                xaxis: {
                    categories: categories,
                    labels: {
                        rotate: -30,
                        style: {
                            colors: '#4f6a83',
                            fontSize: '11px'
                        }
                    }
                },
                yaxis: {
                    min: 0,
                    max: 100,
                    title: {
                        text: 'Average Score (%)'
                    },
                    labels: {
                        style: {
                            colors: '#4f6a83'
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: false
                },
                grid: {
                    borderColor: '#dbe6f2',
                    strokeDashArray: 3
                },
                annotations: {
                    yaxis: [{
                        y: 50,
                        borderColor: palette.red,
                        strokeDashArray: 5,
                        label: {
                            text: 'Pass Threshold (50%)',
                            style: {
                                color: '#fff',
                                background: palette.red,
                                fontSize: '11px'
                            }
                        }
                    }]
                },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return fmtPct(value);
                        }
                    }
                }
            });
        }).catch(function () {
            setEmpty('chart-ia-trend', 'Failed to load internal assessment trend.');
        });
    }

    function loadAttendanceTrend() {
        setLoading('chart-attendance', 'Loading chart...');

        fetchJSON('attendanceTrend.php').then(function (res) {
            if (!res.success || !res.data || !Array.isArray(res.data.labels)) {
                setEmpty('chart-attendance', 'No attendance trend data available.');
                return;
            }

            renderChart('chart-attendance', {
                chart: {
                    type: 'line',
                    height: 320,
                    toolbar: { show: true }
                },
                series: [{
                    name: 'Attendance %',
                    data: Array.isArray(res.data.values) ? res.data.values : []
                }],
                colors: [palette.green],
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                markers: {
                    size: 0
                },
                xaxis: {
                    categories: res.data.labels,
                    labels: {
                        rotate: -30,
                        style: {
                            colors: '#4f6a83',
                            fontSize: '11px'
                        }
                    }
                },
                yaxis: {
                    min: 0,
                    max: 100,
                    title: {
                        text: 'Attendance (%)'
                    },
                    labels: {
                        style: {
                            colors: '#4f6a83'
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                grid: {
                    borderColor: '#dbe6f2',
                    strokeDashArray: 3
                },
                annotations: {
                    yaxis: [{
                        y: 90,
                        borderColor: palette.amber,
                        strokeDashArray: 4,
                        label: {
                            text: 'Target 90%',
                            style: {
                                color: '#fff',
                                background: palette.amber,
                                fontSize: '11px'
                            }
                        }
                    }]
                },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return fmtPct(value);
                        }
                    }
                }
            });
        }).catch(function () {
            setEmpty('chart-attendance', 'Failed to load attendance trend.');
        });
    }

    function loadHeatmap() {
        setLoading('chart-heatmap', 'Loading chart...');

        fetchJSON('attendanceHeatmap.php').then(function (res) {
            if (!res.success || !res.data || !Array.isArray(res.data.series)) {
                setEmpty('chart-heatmap', 'No attendance heatmap data available.');
                return;
            }

            var hasValues = res.data.series.some(function (seriesRow) {
                return Array.isArray(seriesRow.data) && seriesRow.data.some(function (point) {
                    return Number(point.y) > 0;
                });
            });

            if (!hasValues) {
                setEmpty('chart-heatmap', 'No absences recorded in the selected date range.');
                return;
            }

            renderChart('chart-heatmap', {
                chart: {
                    type: 'heatmap',
                    height: 320,
                    toolbar: { show: false }
                },
                series: res.data.series,
                plotOptions: {
                    heatmap: {
                        shadeIntensity: 0.6,
                        radius: 2,
                        colorScale: {
                            ranges: [
                                { from: 0, to: 0, color: '#eef4fb', name: '0' },
                                { from: 1, to: 3, color: '#cfe3f4', name: '1-3' },
                                { from: 4, to: 8, color: '#8fb8de', name: '4-8' },
                                { from: 9, to: 15, color: '#4f88c4', name: '9-15' },
                                { from: 16, to: 1000, color: '#2e5f96', name: '16+' }
                            ]
                        }
                    }
                },
                dataLabels: {
                    enabled: false
                },
                xaxis: {
                    labels: {
                        rotate: -35,
                        style: {
                            colors: '#4f6a83',
                            fontSize: '11px'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#4f6a83',
                            fontSize: '11px'
                        }
                    }
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return Number(value) + ' absences';
                        }
                    }
                }
            });
        }).catch(function () {
            setEmpty('chart-heatmap', 'Failed to load attendance heatmap.');
        });
    }

    function gradeBadgeClass(avgGrade) {
        var grade = Number(avgGrade);
        if (!isFinite(grade)) {
            return 'amber';
        }
        if (grade >= 65) {
            return 'green';
        }
        if (grade >= 50) {
            return 'amber';
        }
        return 'red';
    }

    function absenceBadgeClass(absences) {
        var value = Number(absences);
        if (!isFinite(value)) {
            return 'amber';
        }
        if (value > 18) {
            return 'red';
        }
        if (value > 10) {
            return 'amber';
        }
        return 'green';
    }

    function loadAtRiskStudents() {
        setLoading('at-risk-container', 'Loading students...');

        fetchJSON('atRisk.php').then(function (res) {
            if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                setEmpty('at-risk-container', 'No at-risk students found for the selected filters.');
                return;
            }

            var html = '';
            html += '<div class="pd-table-wrap">';
            html += '<table class="pd-table">';
            html += '<thead><tr>';
            html += '<th>#</th><th>Student</th><th>Year Group</th><th>Form Group</th><th>Avg Grade</th><th>Absences</th><th>Risk</th>';
            html += '</tr></thead><tbody>';

            res.data.forEach(function (student, index) {
                var gradeClass = gradeBadgeClass(student.avgGrade);
                var absenceClass = absenceBadgeClass(student.absences);
                var riskClass = (gradeClass === 'red' || absenceClass === 'red')
                    ? 'red'
                    : (gradeClass === 'amber' || absenceClass === 'amber' ? 'amber' : 'green');

                var riskLabel = riskClass === 'red' ? 'High' : (riskClass === 'amber' ? 'Moderate' : 'Low');
                var profileURL = PD.baseURL + '/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=' + encodeURIComponent(student.personID);

                html += '<tr>';
                html += '<td>' + escHtml(index + 1) + '</td>';
                html += '<td><a href="' + profileURL + '">' + escHtml(student.name) + '</a></td>';
                html += '<td>' + escHtml(student.yearGroup || '-') + '</td>';
                html += '<td>' + escHtml(student.formGroup || '-') + '</td>';
                html += '<td><span class="pd-risk-badge ' + gradeClass + '">' + escHtml(fmtPct(student.avgGrade)) + '</span></td>';
                html += '<td><span class="pd-risk-badge ' + absenceClass + '">' + escHtml(student.absences) + ' days</span></td>';
                html += '<td><span class="pd-risk-badge ' + riskClass + '">' + riskLabel + '</span></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '</div>';

            document.getElementById('at-risk-container').innerHTML = html;
        }).catch(function () {
            setEmpty('at-risk-container', 'Failed to load at-risk student list.');
        });
    }

    function closeModal() {
        var modal = document.getElementById('pd-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    function bindEvents() {
        var prevButton = document.getElementById('pd-comp-prev');
        var nextButton = document.getElementById('pd-comp-next');
        var sizeSelect = document.getElementById('pd-comp-size');

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                if (comparisonState.page > 0) {
                    comparisonState.page -= 1;
                    renderComparisonPage();
                }
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                var maxPage = Math.max(0, Math.ceil(comparisonState.labels.length / comparisonState.pageSize) - 1);
                if (comparisonState.page < maxPage) {
                    comparisonState.page += 1;
                    renderComparisonPage();
                }
            });
        }

        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                var nextSize = Number(sizeSelect.value);
                if (isFinite(nextSize) && nextSize > 0) {
                    comparisonState.pageSize = nextSize;
                    comparisonState.page = 0;
                    renderComparisonPage();
                }
            });
        }

        var modal = document.getElementById('pd-modal');
        var modalClose = document.getElementById('pd-modal-close');

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }

        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }

    function bootstrap() {
        bindEvents();
        updateComparisonControls();

        loadAtRiskStudents();

        if (!hasApex()) {
            [
                'chart-markbook-dist',
                'chart-comparison',
                'chart-ia-trend',
                'chart-attendance',
                'chart-heatmap'
            ].forEach(function (containerID) {
                setEmpty(containerID, 'Chart library failed to load.');
            });
            return;
        }

        loadMarkbookDistribution();
        loadComparison();
        loadIATrend();
        loadAttendanceTrend();
        loadHeatmap();
    }

    bootstrap();
}());
</script>

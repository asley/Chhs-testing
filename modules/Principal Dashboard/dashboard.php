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
//$markbookKpis = pdGetMarkbookKpiAggregate($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
$attendanceRiskKpis = pdGetAttendanceRiskAggregate($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
//$kpiMarkbookAvg = $markbookKpis['avg'] ?? 0.0;
//$kpiInternalAssessmentAvg = pdKpiInternalAssessmentAvg($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);
//$kpiPassRate = $markbookKpis['passRate'] ?? 0.0;
$kpiAbsenteeism = $attendanceRiskKpis['totalStudents'] > 0
    ? round(($attendanceRiskKpis['chronicCount'] / $attendanceRiskKpis['totalStudents']) * 100, 1)
    : 0.0;
$kpiAtRisk = $attendanceRiskKpis['atRiskCount'];

$behaviourSummary = pdKpiBehaviourSummary($connection2, $selectedYearID, $dateFrom, $dateTo, $selectedYearGrp, $selectedFormGrp);
$behaviourStudents = pdBehaviourIssueStudents($connection2, $selectedYearID, $dateFrom, $dateTo, $selectedYearGrp, $selectedFormGrp, 8);
$studentsFlaggedCount = pdCountBehaviourIssueStudents($connection2, $selectedYearID, $dateFrom, $dateTo, $selectedYearGrp, $selectedFormGrp);
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
$badgeStudents = pdBadgeAwardedStudents($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp, 8);

$badgeReach = $studentCount > 0 ? round(((int) ($badgeSummary['recipients'] ?? 0) / $studentCount) * 100, 1) : 0.0;
$repeatRecipients = pdCountRepeatBadgeRecipients($connection2, $selectedYearID, $selectedYearGrp, $selectedFormGrp);

$atRiskRate = $studentCount > 0 ? round(($kpiAtRisk / $studentCount) * 100, 1) : 0.0;
$ratioHealth = $teacherCount > 0
    ? min(100.0, round((20.0 / max(1.0, $studentTeacherRatio)) * 100.0, 1))
    : 0.0;
$studentsDataURL = $absoluteURL . '/index.php?q=/modules/Students/student_view.php';
$teachersDataURL = $absoluteURL . '/index.php?q=/modules/Staff/staff_view.php';

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
            'href' => $studentsDataURL,
        ],
        [
            'title' => __('Teachers'),
            'code' => 'STAFF',
            'value' => number_format($teacherCount),
            'sub' => __('Active class teachers this year'),
            'ring' => $clampPercent($staffingHealth),
            'ringText' => number_format($staffingHealth, 1) . '%',
            'tone' => 'blue',
            'href' => $teachersDataURL,
        ],
        [
            'title' => __('Student : Teacher'),
            'code' => 'RATIO',
            'value' => number_format($studentTeacherRatio, 1),
            'sub' => __('Students per teacher'),
            'ring' => $clampPercent($ratioHealth),
            'ringText' => number_format($ratioHealth, 1) . '%',
            'tone' => 'green',
            'href' => '#section-enrolment',
        ],
    ],
    [
        [
            'title' => __('Attendance Rate'),
            'code' => 'ATT',
            'value' => number_format($kpiAttendanceRate, 1) . '%',
            'sub' => __('Within selected date range'),
            'ring' => $clampPercent($kpiAttendanceRate),
            'ringText' => number_format($kpiAttendanceRate, 1) . '%',
            'tone' => 'teal',
            'href' => '#panel-attendance-trend',
        ],
        [
            'title' => __('Chronic Absenteeism'),
            'code' => 'RISK',
            'value' => number_format($kpiAbsenteeism, 1) . '%',
            'sub' => __('Students absent more than 18 days'),
            'ring' => $clampPercent($kpiAbsenteeism),
            'ringText' => number_format($kpiAbsenteeism, 1) . '%',
            'tone' => 'amber',
            'href' => '#panel-absence-heatmap',
        ],
        [
            'title' => __('At-Risk Students'),
            'code' => 'ALERT',
            'value' => number_format($kpiAtRisk),
            'sub' => __('Low grades or high absence profile'),
            'ring' => $clampPercent($atRiskRate),
            'ringText' => number_format($atRiskRate, 1) . '%',
            'tone' => 'red',
            'href' => '#panel-at-risk-data',
        ],
        [
            'title' => __('Behaviour Flags'),
            'code' => 'BHV',
            'value' => number_format($studentsFlaggedCount),
            'sub' => __('Students with negative behaviour records'),
            'ring' => $clampPercent($behaviourFlagRate),
            'ringText' => number_format($behaviourFlagRate, 1) . '%',
            'tone' => 'red',
            'href' => '#panel-behaviour-snapshot',
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

$renderKpiCard = static function (array $card) use ($h): string {
    $gaugeValue = max(0.0, min(100.0, (float) ($card['ring'] ?? 0.0)));
    $gaugeValueText = number_format($gaugeValue, 1, '.', '');
    $gaugeAngle = number_format($gaugeValue * 1.8, 1, '.', '') . 'deg';
    $gaugeColor = '#16a34a';
    if ($gaugeValue < 45.0) {
        $gaugeColor = '#dc2626';
    } elseif ($gaugeValue < 70.0) {
        $gaugeColor = '#d97706';
    }

    $html = '';
    $html .= '<article class="pd-kpi-card" style="--gauge-value:' . $h($gaugeValueText) . ';--gauge-angle:' . $h($gaugeAngle) . ';--gauge-color:' . $h($gaugeColor) . ';">';
    $html .= '<span class="pd-kpi-badge">' . $h($card['code'] ?? '') . '</span>';
    $html .= '<div class="pd-kpi-main">';
    $html .= '<div class="pd-kpi-gauge" aria-hidden="true">';
    $html .= '<div class="pd-kpi-gauge-track"></div>';
    $html .= '<div class="pd-kpi-gauge-scale"></div>';
    $html .= '<div class="pd-kpi-gauge-arc"></div>';
    $html .= '<div class="pd-kpi-gauge-mask"></div>';
    $html .= '<div class="pd-kpi-gauge-needle"></div>';
    $html .= '<div class="pd-kpi-gauge-hub"></div>';
    $html .= '<div class="pd-kpi-gauge-text">' . $h($card['ringText'] ?? '') . '</div>';
    $html .= '</div>';
    $html .= '<div class="pd-kpi-meta">';
    $html .= '<div class="pd-kpi-value">' . $h($card['value'] ?? '') . '</div>';
    $html .= '<div class="pd-kpi-title">' . $h($card['title'] ?? '') . '</div>';
    $html .= '<div class="pd-kpi-sub">' . $h($card['sub'] ?? '') . '</div>';
    if (!empty($card['href'])) {
        $html .= '<a class="pd-kpi-link" href="' . $h($card['href']) . '">View data</a>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</article>';

    return $html;
};

$studentProfileBaseURL = $absoluteURL . '/index.php?q=/modules/Students/student_view_details.php&allStudents=on&subpage=Overview&gibbonPersonID=';

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
    --pd-shadow-sm: 0 2px 6px rgba(15,35,60,.08), 0 10px 24px rgba(15,35,60,.06);
    --pd-shadow-md: 0 6px 16px rgba(15,35,60,.12), 0 20px 42px rgba(15,35,60,.10);
    --pd-text: #1a3450;
    --pd-muted: #526a82;
    --pd-radius: 14px;

    /* Category accent colours */
    --cat-enrol: #2563eb;
    --cat-enrol-bg: #eff6ff;
    --cat-enrol-border: #bfdbfe;

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

/* Category colour bindings */
.pd-section.enrol .pd-section-dot   { background: var(--cat-enrol); }
.pd-section.enrol .pd-section-label { color: var(--cat-enrol); }
.pd-section.enrol .pd-section-header { border-color: var(--cat-enrol-border); }

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
    padding: 12px 14px 14px;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: box-shadow .18s, transform .18s;
}
.pd-kpi-card:hover {
    box-shadow: var(--pd-shadow-md);
    transform: translateY(-2px);
}

/* Left accent bar */
.pd-kpi-card::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 3px;
    border-radius: var(--pd-radius) var(--pd-radius) 0 0;
}

/* Category accent per section */
.pd-section.enrol   .pd-kpi-card::before { background: var(--cat-enrol); }
.pd-section.enrol   .pd-kpi-card { border-color: var(--cat-enrol-border); background: linear-gradient(135deg, var(--cat-enrol-bg) 0%, #fff 60%); }
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
    margin-bottom: 1px;
}
.pd-section.enrol    .pd-kpi-badge { background: var(--cat-enrol-bg);    color: var(--cat-enrol);    border: 1px solid var(--cat-enrol-border); }
.pd-section.academic .pd-kpi-badge { background: var(--cat-academic-bg); color: var(--cat-academic); border: 1px solid var(--cat-academic-border); }
.pd-section.attend   .pd-kpi-badge { background: var(--cat-attend-bg);   color: var(--cat-attend);   border: 1px solid var(--cat-attend-border); }
.pd-section.risk     .pd-kpi-badge { background: var(--cat-risk-bg);     color: var(--cat-risk);     border: 1px solid var(--cat-risk-border); }

.pd-kpi-main {
    display: grid;
    grid-template-columns: 104px 1fr;
    gap: 12px;
    align-items: center;
}

.pd-kpi-meta {
    min-width: 0;
}

.pd-kpi-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 34px;
    font-weight: 700;
    line-height: 1;
    color: var(--pd-text);
    letter-spacing: -0.02em;
}

.pd-kpi-title {
    font-size: 12px;
    font-weight: 700;
    color: var(--pd-text);
    line-height: 1.3;
    margin-top: 4px;
}

.pd-kpi-sub {
    font-size: 11px;
    color: var(--pd-muted);
    line-height: 1.35;
    font-weight: 500;
}

.pd-kpi-link {
    display: inline-flex;
    align-items: center;
    margin-top: 6px;
    font-size: 11px;
    font-weight: 700;
    color: #1e5a90;
    text-decoration: none;
    border-bottom: 1px dashed #9bb6d2;
    width: fit-content;
}

.pd-kpi-link:hover {
    color: #174b79;
    border-bottom-color: #174b79;
}

/* Semi-gauge indicator */
.pd-kpi-gauge {
    position: relative;
    width: 104px;
    height: 72px;
    flex-shrink: 0;
}

.pd-kpi-gauge-track,
.pd-kpi-gauge-scale,
.pd-kpi-gauge-arc {
    position: absolute;
    left: 0;
    top: 0;
    width: 104px;
    height: 104px;
    border-radius: 50%;
    clip-path: inset(0 0 50% 0);
}

.pd-kpi-gauge-track {
    background: conic-gradient(from -90deg, #d2dfeb 0deg, #d2dfeb 180deg, transparent 180deg);
}

.pd-kpi-gauge-scale {
    background: repeating-conic-gradient(
        from -90deg,
        rgba(56, 74, 95, 0.26) 0deg 1.2deg,
        transparent 1.2deg 15deg
    );
    opacity: 0.8;
}

.pd-kpi-gauge-arc {
    background:
        conic-gradient(
            from -90deg,
            var(--gauge-color, #16a34a) var(--gauge-angle, 0deg),
            transparent 0deg
        );
}

.pd-kpi-gauge-mask {
    position: absolute;
    left: 16px;
    top: 16px;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    clip-path: inset(0 0 50% 0);
}

.pd-kpi-gauge-needle {
    position: absolute;
    left: 50px;
    top: 13px;
    width: 3px;
    height: 38px;
    border-radius: 2px;
    background: linear-gradient(180deg, #2e4359 0%, #5e748a 100%);
    transform-origin: bottom center;
    transform: rotate(calc(-90deg + var(--gauge-angle, 0deg)));
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.6);
}

.pd-kpi-gauge-hub {
    position: absolute;
    left: 46px;
    top: 46px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #2f4359;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(17, 31, 46, 0.25);
}

.pd-kpi-gauge-text {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 3px;
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10.5px;
    font-weight: 700;
    color: #4c637a;
    letter-spacing: 0.04em;
}

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

/* ── Panel insight KPIs ────────────────────────────────────── */
.pd-insight-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 10px;
}

.pd-insight-loading {
    grid-column: 1 / -1;
    border: 1px dashed #c5d5e5;
    border-radius: 10px;
    color: #6a8099;
    font-size: 12px;
    font-weight: 700;
    background: #f8fbff;
    padding: 10px 12px;
}

.pd-insight-card {
    border: 1px solid #dce9f5;
    background: #f7fbff;
    border-radius: 10px;
    padding: 8px 10px;
    min-height: 58px;
}

.pd-insight-label {
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 800;
    color: #5b738c;
    margin-bottom: 4px;
}

.pd-insight-main {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pd-insight-gauge {
    --value: 0;
    --gcolor: #16a34a;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: conic-gradient(var(--gcolor) calc(var(--value) * 1%), #d8e4f0 0);
    position: relative;
    flex-shrink: 0;
}

.pd-insight-gauge::after {
    content: '';
    position: absolute;
    inset: 5px;
    border-radius: 50%;
    background: #fff;
}

.pd-insight-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 19px;
    font-weight: 700;
    color: #1e3d5c;
    line-height: 1;
}

.pd-insight-sub {
    font-size: 11px;
    font-weight: 700;
    color: #5b738c;
    margin-top: 2px;
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

.pd-table-note {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: 700;
    color: #4f6982;
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

section[id],
article[id] {
    scroll-margin-top: 16px;
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
    .pd-kpi-value   { font-size: 31px; }
    .pd-kpi-main    { grid-template-columns: 96px 1fr; }

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
    .pd-insight-row     { grid-template-columns: 1fr; }
    .pd-filter-actions  { width: 100%; }
    .pd-btn             { width: 100%; }
    .pd-kpi-value       { font-size: 28px; }
    .pd-kpi-main        { grid-template-columns: 88px 1fr; }
    .pd-kpi-gauge       { width: 88px; height: 62px; }
    .pd-kpi-gauge-track,
    .pd-kpi-gauge-scale,
    .pd-kpi-gauge-arc   { width: 88px; height: 88px; }
    .pd-kpi-gauge-mask  { width: 60px; height: 60px; left: 14px; top: 14px; }
    .pd-kpi-gauge-needle { left: 42px; top: 12px; height: 32px; }
    .pd-kpi-gauge-hub   { left: 39px; top: 39px; width: 10px; height: 10px; }
    .pd-kpi-gauge-text  { font-size: 10px; }
}

@media (max-width: 480px) {
    .pd-filter-form { grid-template-columns: 1fr; }
    .pd-kpi-main { grid-template-columns: 1fr; justify-items: center; text-align: center; gap: 4px; }
    .pd-kpi-meta { text-align: center; }
    .pd-kpi-badge { align-self: center; }
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

        <!-- ── ENROLMENT & STAFFING ──────────────────────────── -->
        <section class="pd-section enrol" id="section-enrolment">
            <div class="pd-section-header">
                <span class="pd-section-dot"></span>
                <span class="pd-section-label"><?= $h(__('Enrolment & Staffing')) ?></span>
            </div>
            <div class="pd-kpi-grid">
                <?php foreach ($kpiRows[0] as $card): ?>
                    <?= $renderKpiCard($card) ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── SCHOOL HEALTH & SAFETY ────────────────────────── -->
        <section class="pd-section attend" id="section-attendance">
            <div class="pd-section-header">
                <span class="pd-section-dot"></span>
                <span class="pd-section-label"><?= $h(__('School Health & Safety')) ?></span>
            </div>
            <div class="pd-kpi-grid" style="grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));">
                <?php foreach ($kpiRows[1] as $card): ?>
                    <?= $renderKpiCard($card) ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── Behaviour & Badges ────────────────────────────── -->
        <div class="pd-panel-grid" style="margin-bottom: 20px;">
            <article class="pd-panel" id="panel-behaviour-snapshot">
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

            <article class="pd-panel" id="panel-badge-activity">
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

        <!-- ── Academic Overview ────────────────────────────── -->
        <div class="pd-panel-grid">
            <article class="pd-panel" style="flex: 0 0 100%; width: 100%; max-width: 100%; min-height: auto; margin-bottom: 20px;">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Academic Overview')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Detailed academic performance, grade distributions, and internal assessment analytics are managed in the Grade Analytics module.')) ?></p>
                    </div>
                </header>
                <div style="display: flex; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; background: #f8fbff; border-radius: 12px; border: 1.5px dashed #dce5ef;">
                    <div style="max-width: 500px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📊</div>
                        <h3 style="font-size: 20px; font-weight: 700; color: #1a3450; margin-bottom: 12px;"><?= $h(__('In-depth Grade Analytics')) ?></h3>
                        <p style="color: #526a82; margin-bottom: 24px; line-height: 1.6;"><?= $h(__('Access comprehensive visualizations of markbook progress, IA trends, and class-by-class comparisons directly in the dedicated Grade Analytics dashboard.')) ?></p>
                        <a href="<?= $h($absoluteURL . '/index.php?q=/modules/GradeAnalytics/gradeDashboard.php') ?>" class="pd-btn apply" style="padding: 12px 24px; font-size: 15px; text-decoration: none;">
                            <?= $h(__('Go to Grade Analytics Dashboard')) ?>
                        </a>
                    </div>
                </div>
            </article>
        </div>

        <div class="pd-panel-grid">
            <!-- ── Attendance charts ─────────────────────────── -->
            <article class="pd-panel" id="panel-attendance-trend">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('Attendance Representation by Week')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Weekly 100% stacked distribution of daily attendance bands.')) ?></p>
                    </div>
                </header>
                <div id="attendance-insights" class="pd-insight-row">
                    <div class="pd-insight-loading"><?= $h(__('Loading summary...')) ?></div>
                </div>
                <div id="chart-attendance" class="pd-chart-host">
                    <div class="pd-loading"><?= $h(__('Loading chart...')) ?></div>
                </div>
            </article>

            <article class="pd-panel" id="panel-absence-heatmap">
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
            <article class="pd-panel" id="panel-at-risk-data">
                <header class="pd-panel-head">
                    <div>
                        <h2 class="pd-panel-title"><?= $h(__('At-Risk Students')) ?></h2>
                        <p class="pd-panel-subtitle"><?= $h(__('Flagged by low markbook average (&lt;65%) or high absenteeism (&gt;18 days).')) ?></p>
                    </div>
                    <button type="button" id="pd-at-risk-refresh" class="pd-btn" title="<?= $h(__('Refresh at-risk data')) ?>"
                            style="margin-left:auto;white-space:nowrap;">&#8635; Refresh</button>
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
    var atRiskState = {
        page: 1,
        pageSize: 25,
        total: 0,
        rows: [],
        hasMore: false,
        loading: false
    };

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
            return response.text().then(function (text) {
                var payload = text === null || text === undefined ? '' : String(text).trim();

                try {
                    return JSON.parse(payload);
                } catch (parseError) {
                    // Some environments leak PHP warnings before JSON. Try extracting the JSON envelope.
                    var start = payload.indexOf('{');
                    var end = payload.lastIndexOf('}');
                    if (start !== -1 && end > start) {
                        var candidate = payload.slice(start, end + 1);
                        try {
                            return JSON.parse(candidate);
                        } catch (ignored) {
                            // Fall through to throw a descriptive error.
                        }
                    }

                    var sample = payload.slice(0, 180).replace(/\s+/g, ' ');
                    throw new Error('Invalid JSON response from ' + endpoint + (sample ? ': ' + sample : ''));
                }
            });
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

    function setInsightLoading(containerID, message) {
        var el = document.getElementById(containerID);
        if (!el) {
            return;
        }
        el.innerHTML = '<div class="pd-insight-loading">' + escHtml(message) + '</div>';
    }

    function insightGaugeColor(percent, inverse) {
        var value = clampPercent(percent);
        if (inverse) {
            if (value <= 30) {
                return palette.green;
            }
            if (value <= 55) {
                return palette.amber;
            }
            return palette.red;
        }

        if (value < 50) {
            return palette.red;
        }
        if (value < 70) {
            return palette.amber;
        }
        return palette.green;
    }

    function fmtInt(value) {
        var n = Number(value);
        if (!isFinite(n)) {
            return '0';
        }
        return Math.round(n).toLocaleString();
    }

    function renderInsightCards(containerID, cards) {
        var el = document.getElementById(containerID);
        if (!el) {
            return;
        }

        if (!Array.isArray(cards) || cards.length === 0) {
            el.innerHTML = '<div class="pd-insight-loading">No summary available for current filters.</div>';
            return;
        }

        var html = cards.map(function (card) {
            var gauge = clampPercent(card && card.gauge);
            var gaugeColor = card && card.gaugeColor ? card.gaugeColor : insightGaugeColor(gauge, !!(card && card.inverse));
            var label = card && card.label ? String(card.label) : '';
            var valueText = card && card.valueText ? String(card.valueText) : 'N/A';
            var subText = card && card.subText ? String(card.subText) : '';

            return '' +
                '<div class="pd-insight-card">' +
                    '<div class="pd-insight-label">' + escHtml(label) + '</div>' +
                    '<div class="pd-insight-main">' +
                        '<div class="pd-insight-gauge" style="--value:' + escHtml(gauge) + ';--gcolor:' + escHtml(gaugeColor) + ';"></div>' +
                        '<div>' +
                            '<div class="pd-insight-value">' + escHtml(valueText) + '</div>' +
                            '<div class="pd-insight-sub">' + escHtml(subText) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }).join('');

        el.innerHTML = html;
    }

    function buildWeeklyAttendanceBands(labels, values) {
        var weekly = {};
        var weeklyOrder = [];
        var totalDays = 0;
        var below80Days = 0;
        var warningDays = 0;
        var onTargetDays = 0;
        var attendanceSum = 0;

        (labels || []).forEach(function (label, index) {
            var value = Number((values || [])[index]);
            var date = new Date(label);

            if (!isFinite(value) || !(date instanceof Date) || isNaN(date.getTime())) {
                return;
            }

            var weekKey = String(label).slice(0, 7);
            if (!weekly[weekKey]) {
                weekly[weekKey] = {
                    label: weekKey,
                    low: 0,
                    warning: 0,
                    onTarget: 0,
                    total: 0
                };
                weeklyOrder.push(weekKey);
            }

            weekly[weekKey].total += 1;
            totalDays += 1;
            attendanceSum += value;

            if (value < 80) {
                weekly[weekKey].low += 1;
                below80Days += 1;
            } else if (value < 90) {
                weekly[weekKey].warning += 1;
                warningDays += 1;
            } else {
                weekly[weekKey].onTarget += 1;
                onTargetDays += 1;
            }
        });

        var categories = [];
        var lowSeries = [];
        var warningSeries = [];
        var onTargetSeries = [];

        weeklyOrder.forEach(function (weekKey) {
            var bucket = weekly[weekKey];
            if (!bucket || bucket.total === 0) {
                return;
            }

            categories.push(bucket.label);
            lowSeries.push(Number(((bucket.low / bucket.total) * 100).toFixed(1)));
            warningSeries.push(Number(((bucket.warning / bucket.total) * 100).toFixed(1)));
            onTargetSeries.push(Number(((bucket.onTarget / bucket.total) * 100).toFixed(1)));
        });

        return {
            labels: categories,
            series: [
                { name: 'Days < 80%', data: lowSeries },
                { name: 'Days 80-89%', data: warningSeries },
                { name: 'Days >= 90%', data: onTargetSeries }
            ],
            summary: {
                avgDaily: totalDays > 0 ? Number((attendanceSum / totalDays).toFixed(1)) : 0,
                below80Pct: totalDays > 0 ? Number(((below80Days / totalDays) * 100).toFixed(1)) : 0,
                warningPct: totalDays > 0 ? Number(((warningDays / totalDays) * 100).toFixed(1)) : 0,
                onTargetPct: totalDays > 0 ? Number(((onTargetDays / totalDays) * 100).toFixed(1)) : 0,
                totalDays: totalDays,
                weeksTracked: categories.length
            }
        };
    }

    function loadAttendanceTrend() {
        setLoading('chart-attendance', 'Loading chart...');
        setInsightLoading('attendance-insights', 'Loading summary...');

        fetchJSON('attendanceTrend.php').then(function (res) {
            if (!res.success || !res.data || !Array.isArray(res.data.labels)) {
                setEmpty('chart-attendance', 'No attendance trend data available.');
                setInsightLoading('attendance-insights', 'No summary available for current filters.');
                return;
            }

            var weeklyBands = buildWeeklyAttendanceBands(
                Array.isArray(res.data.labels) ? res.data.labels : [],
                Array.isArray(res.data.values) ? res.data.values : []
            );

            if (!Array.isArray(weeklyBands.labels) || weeklyBands.labels.length === 0) {
                setEmpty('chart-attendance', 'No attendance trend data available.');
                setInsightLoading('attendance-insights', 'No summary available for current filters.');
                return;
            }

            var summary = weeklyBands.summary || {};
            var avgDaily = Number(summary.avgDaily);
            var below80Pct = Number(summary.below80Pct);
            var onTargetPct = Number(summary.onTargetPct);
            var warningPct = Number(summary.warningPct);
            var totalDays = Number(summary.totalDays);
            var weeksTracked = Number(summary.weeksTracked);

            if (!isFinite(avgDaily)) {
                avgDaily = 0;
            }
            if (!isFinite(below80Pct)) {
                below80Pct = 0;
            }
            if (!isFinite(onTargetPct)) {
                onTargetPct = 0;
            }
            if (!isFinite(warningPct)) {
                warningPct = 0;
            }
            if (!isFinite(totalDays) || totalDays < 0) {
                totalDays = 0;
            }
            if (!isFinite(weeksTracked) || weeksTracked < 0) {
                weeksTracked = 0;
            }

            renderInsightCards('attendance-insights', [
                {
                    label: 'Average Daily Attendance',
                    gauge: avgDaily,
                    valueText: fmtPct(avgDaily),
                    subText: fmtInt(totalDays) + ' school days analysed'
                },
                {
                    label: 'Days < 80%',
                    gauge: below80Pct,
                    valueText: fmtPct(below80Pct),
                    subText: 'Low-attendance days',
                    inverse: true
                },
                {
                    label: 'Days 80-89%',
                    gauge: warningPct,
                    valueText: fmtPct(warningPct),
                    subText: fmtInt(weeksTracked) + ' weeks tracked',
                    inverse: true
                },
                {
                    label: 'Days >= 90%',
                    gauge: onTargetPct,
                    valueText: fmtPct(onTargetPct),
                    subText: 'On-target attendance'
                }
            ]);

            renderChart('chart-attendance', {
                chart: {
                    type: 'bar',
                    height: 320,
                    stacked: true,
                    stackType: '100%',
                    toolbar: { show: true }
                },
                series: weeklyBands.series,
                colors: [palette.red, palette.amber, palette.green],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 3,
                        columnWidth: '72%'
                    }
                },
                xaxis: {
                    categories: weeklyBands.labels,
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
                    tickAmount: 5,
                    title: {
                        text: 'Distribution (%)'
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
                    position: 'top'
                },
                grid: {
                    borderColor: '#dbe6f2',
                    strokeDashArray: 3
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (value) {
                            return fmtPct(value);
                        }
                    }
                }
            });
        }).catch(function (error) {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('PD attendance trend load error:', error);
            }
            setEmpty('chart-attendance', 'Failed to load attendance trend.');
            setInsightLoading('attendance-insights', 'Failed to load summary.');
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

    function renderAtRiskStudents() {
        if (!Array.isArray(atRiskState.rows) || atRiskState.rows.length === 0) {
            setEmpty('at-risk-container', 'No at-risk students found for the selected filters.');
            return;
        }

        var totalAtRisk = Number(atRiskState.total);
        if (!isFinite(totalAtRisk) || totalAtRisk < 0) {
            totalAtRisk = atRiskState.rows.length;
        }

        var html = '';
        if (totalAtRisk > atRiskState.rows.length) {
            html += '<div class="pd-table-note">Showing ' + escHtml(atRiskState.rows.length) + ' of ' + escHtml(totalAtRisk) + ' at-risk students.</div>';
        } else {
            html += '<div class="pd-table-note">Showing ' + escHtml(totalAtRisk) + ' at-risk students.</div>';
        }
        html += '<div class="pd-table-wrap">';
        html += '<table class="pd-table">';
        html += '<thead><tr>';
        html += '<th>#</th><th>Student</th><th>Year Group</th><th>Form Group</th><th>Avg Grade</th><th>Absences</th><th>Risk</th>';
        html += '</tr></thead><tbody>';

        atRiskState.rows.forEach(function (student, index) {
            var gradeClass = gradeBadgeClass(student.avgGrade);
            var absenceClass = absenceBadgeClass(student.absences);
            var riskClass = (gradeClass === 'red' || absenceClass === 'red')
                ? 'red'
                : (gradeClass === 'amber' || absenceClass === 'amber' ? 'amber' : 'green');

            var riskLabel = riskClass === 'red' ? 'High' : (riskClass === 'amber' ? 'Moderate' : 'Low');
            var profileURL = PD.baseURL + '/index.php?q=/modules/Students/student_view_details.php&gibbonPersonID=' + encodeURIComponent(student.personID) + '&allStudents=on&subpage=Overview';

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

        if (atRiskState.hasMore) {
            html += '<div class="pd-table-note" style="margin-top:12px;">';
            html += '<button type="button" class="pd-btn apply" id="pd-at-risk-load-more"' + (atRiskState.loading ? ' disabled' : '') + '>';
            html += atRiskState.loading ? 'Loading more...' : 'Load More';
            html += '</button>';
            html += '</div>';
        }

        document.getElementById('at-risk-container').innerHTML = html;

        var loadMoreButton = document.getElementById('pd-at-risk-load-more');
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', function () {
                loadAtRiskStudents(true);
            });
        }
    }

    function loadAtRiskStudents(append) {
        if (atRiskState.loading) {
            return Promise.resolve();
        }

        if (!append) {
            atRiskState.page = 1;
            atRiskState.total = 0;
            atRiskState.rows = [];
            atRiskState.hasMore = false;
            setLoading('at-risk-container', 'Loading students...');
        }

        atRiskState.loading = true;

        return fetchJSON('atRisk.php', {
            page: atRiskState.page,
            pageSize: atRiskState.pageSize
        }).then(function (res) {
            if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                if (append && atRiskState.rows.length > 0) {
                    atRiskState.hasMore = false;
                    renderAtRiskStudents();
                    return;
                }

                setEmpty('at-risk-container', 'No at-risk students found for the selected filters.');
                return;
            }

            var totalAtRisk = Number(res.meta && res.meta.total);
            if (!isFinite(totalAtRisk) || totalAtRisk < 0) {
                totalAtRisk = (append ? atRiskState.rows.length : 0) + res.data.length;
            }

            atRiskState.total = totalAtRisk;
            atRiskState.rows = append ? atRiskState.rows.concat(res.data) : res.data.slice();
            atRiskState.hasMore = Boolean(res.meta && res.meta.hasMore);
            atRiskState.page += 1;

            renderAtRiskStudents();
        }).catch(function () {
            setEmpty('at-risk-container', 'Failed to load at-risk student list.');
        }).finally(function () {
            atRiskState.loading = false;
        });
    }

    function closeModal() {
        var modal = document.getElementById('pd-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    function bindEvents() {
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

        var refreshBtn = document.getElementById('pd-at-risk-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                if (atRiskState.loading) { return; }
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing…';
                loadAtRiskStudents(false).finally(function () {
                    refreshBtn.disabled = false;
                    refreshBtn.textContent = '↻ Refresh';
                });
            });
        }
    }

    function bootstrap() {
        bindEvents();

        loadAtRiskStudents();

        if (!hasApex()) {
            [
                'chart-attendance',
                'chart-heatmap'
            ].forEach(function (containerID) {
                setEmpty(containerID, 'Chart library failed to load.');
            });
            return;
        }

        loadAttendanceTrend();
        loadHeatmap();
    }

    bootstrap();
}());
</script>

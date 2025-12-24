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

// Gibbon includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/../../functions.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Services\Format;
use Gibbon\Forms\Form;

// Get database from container
$pdo = $container->get('db');

$page->breadcrumbs->add(__('Student AI Tutor Usage'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/teacher_student_usage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    echo '<h2>' . __('Student AI Tutor Usage Monitor') . '</h2>';
    echo '<p>' . __('View and monitor AI Tutor conversations from your students. Filter by class to review conversations for specific assignments.') . '</p>';

    // Get filter parameters
    $gibbonPersonIDStudent = $_GET['gibbonPersonIDStudent'] ?? '';
    $gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $flaggedOnly = $_GET['flaggedOnly'] ?? '';

    // Get current teacher's ID
    $currentTeacherID = $gibbon->session->get('gibbonPersonID');

    // Create filter form
    $form = Form::create('filter', $gibbon->session->get('absoluteURL') . '/index.php');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');
    $form->setMethod('GET');

    // Add hidden field for 'q' parameter to preserve page location
    $form->addHiddenValue('q', '/modules/aiTeacher/teacher_student_usage.php');

    // Get list of students (simplified query without roll group to avoid table issues)
    $sqlStudents = "SELECT p.gibbonPersonID, p.preferredName, p.surname
                    FROM gibbonPerson p
                    JOIN gibbonStudentEnrolment se ON p.gibbonPersonID = se.gibbonPersonID
                    WHERE se.gibbonSchoolYearID = :gibbonSchoolYearID
                    AND p.status = 'Full'
                    ORDER BY p.surname, p.preferredName";
    $resultStudents = $pdo->select($sqlStudents, ['gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID')]);

    $students = ['' => __('All Students')];
    while ($student = $resultStudents->fetch()) {
        $students[$student['gibbonPersonID']] = $student['surname'] . ', ' . $student['preferredName'];
    }

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDStudent', __('Student'));
        $select = $row->addSelect('gibbonPersonIDStudent')
            ->fromArray($students)
            ->selected($gibbonPersonIDStudent);

    // Get classes taught by the current teacher
    $sqlClasses = "SELECT DISTINCT cc.gibbonCourseClassID, cc.name, cc.nameShort, c.name as courseName
                   FROM gibbonCourseClass cc
                   JOIN gibbonCourse c ON cc.gibbonCourseID = c.gibbonCourseID
                   JOIN gibbonCourseClassPerson ccp ON cc.gibbonCourseClassID = ccp.gibbonCourseClassID
                   WHERE ccp.gibbonPersonID = :teacherID
                   AND ccp.role = 'Teacher'
                   AND c.gibbonSchoolYearID = :gibbonSchoolYearID
                   ORDER BY c.name, cc.nameShort";

    $resultClasses = $pdo->select($sqlClasses, [
        'teacherID' => $currentTeacherID,
        'gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID')
    ]);

    $classes = ['' => __('All Classes')];
    if ($resultClasses && $resultClasses->rowCount() > 0) {
        while ($class = $resultClasses->fetch()) {
            $classes[$class['gibbonCourseClassID']] = $class['courseName'] . ' - ' . $class['nameShort'];
        }
    }

    // Add class filter dropdown
    $row = $form->addRow();
        $row->addLabel('gibbonCourseClassID', __('Class'));
        $row->addSelect('gibbonCourseClassID')
            ->fromArray($classes)
            ->selected($gibbonCourseClassID);

    $row = $form->addRow();
        $row->addLabel('dateFrom', __('Date From'));
        $row->addDate('dateFrom')->setValue($dateFrom);

    $row = $form->addRow();
        $row->addLabel('dateTo', __('Date To'));
        $row->addDate('dateTo')->setValue($dateTo);

    $row = $form->addRow();
        $row->addLabel('flaggedOnly', __('Flagged Content Only'));
        $row->addCheckbox('flaggedOnly')->checked($flaggedOnly);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($gibbon->session);

    echo $form->getOutput();

    // Build query based on filters
    $data = [
        'dateFrom' => $dateFrom . ' 00:00:00',
        'dateTo' => $dateTo . ' 23:59:59',
        'gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID')
    ];

    $sql = "SELECT
                c.gibbonPersonID,
                c.sessionID,
                p.surname,
                p.preferredName,
                s.messageCount,
                s.startTime,
                s.lastActivity,
                s.topic,
                (SELECT CONCAT(course2.name, ' - ', cc2.nameShort)
                 FROM gibbonCourseClassPerson ccp2
                 JOIN gibbonCourseClass cc2 ON ccp2.gibbonCourseClassID = cc2.gibbonCourseClassID
                 JOIN gibbonCourse course2 ON cc2.gibbonCourseID = course2.gibbonCourseID
                 WHERE ccp2.gibbonPersonID = c.gibbonPersonID
                 AND ccp2.role = 'Student'
                 AND course2.gibbonSchoolYearID = :gibbonSchoolYearID
                 AND (c.gibbonCourseID IS NULL OR course2.gibbonCourseID = c.gibbonCourseID)
                 LIMIT 1) as className,
                MAX(CASE WHEN c.flagged = 1 THEN 1 ELSE 0 END) as hasFlagged,
                COUNT(CASE WHEN c.sender = 'student' THEN 1 END) as studentQuestions,
                MIN(CASE WHEN c.sender = 'student' THEN c.message END) as firstQuestion
            FROM aiTeacherStudentConversations c
            JOIN gibbonPerson p ON c.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN aiTeacherChatSessions s ON c.sessionID = s.sessionID
            WHERE c.timestamp BETWEEN :dateFrom AND :dateTo";

    if (!empty($gibbonPersonIDStudent)) {
        $sql .= " AND c.gibbonPersonID = :gibbonPersonID";
        $data['gibbonPersonID'] = $gibbonPersonIDStudent;
    }

    if (!empty($gibbonCourseClassID)) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM gibbonCourseClass filtercc
            WHERE filtercc.gibbonCourseClassID = :gibbonCourseClassID
            AND filtercc.gibbonCourseID = c.gibbonCourseID
        )";
        $data['gibbonCourseClassID'] = $gibbonCourseClassID;
    }

    if (!empty($flaggedOnly)) {
        $sql .= " AND c.flagged = 1";
    }

    $sql .= " GROUP BY c.sessionID, c.gibbonPersonID, p.surname, p.preferredName, s.messageCount, s.startTime, s.lastActivity, s.topic";
    $sql .= " ORDER BY s.startTime DESC LIMIT 100";

    try {
        $result = $pdo->select($sql, $data);

        if ($result->rowCount() > 0) {
            echo '<h3>' . __('Conversations') . ' (' . $result->rowCount() . ' ' . __('conversations') . ')</h3>';

            echo '<table class="fullWidth colorOddEven" cellspacing="0">';
            echo '<thead>';
            echo '<tr class="head">';
            echo '<th style="width: 20%;">' . __('Student') . '</th>';
            echo '<th style="width: 12%;">' . __('Started') . '</th>';
            echo '<th style="width: 10%;">' . __('Messages') . '</th>';
            echo '<th style="width: 40%;">' . __('Topic / First Question') . '</th>';
            echo '<th style="width: 10%;">' . __('Status') . '</th>';
            echo '<th style="width: 8%;">' . __('Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            while ($row = $result->fetch()) {
                $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
                // Add class name if available
                if (!empty($row['className'])) {
                    $studentName .= ' <span style="color: #666; font-size: 0.9em;">(' . htmlspecialchars($row['className']) . ')</span>';
                }
                $hasFlagged = ($row['hasFlagged'] == 1);

                // Row styling
                $rowClass = '';
                if ($hasFlagged) {
                    $rowClass = 'error';
                }

                echo '<tr class="' . $rowClass . '">';

                // Student name with class
                echo '<td>' . $studentName . '</td>';

                // Start time
                echo '<td>' . Format::dateTime($row['startTime']) . '</td>';

                // Message count
                $messageCount = $row['messageCount'] ?? ($row['studentQuestions'] * 2);
                echo '<td>' . $messageCount . ' messages<br/>';
                echo '<small style="color: #666;">' . $row['studentQuestions'] . ' questions</small></td>';

                // Topic or first question
                echo '<td>';
                if (!empty($row['topic'])) {
                    // Show topic prominently
                    echo '<strong style="color: #667eea; font-size: 1.05em;">📌 ' . htmlspecialchars($row['topic']) . '</strong>';
                } else {
                    // Fallback to first question if no topic
                    $firstQuestion = htmlspecialchars($row['firstQuestion'] ?? 'N/A');
                    $truncated = strlen($firstQuestion) > 150 ? substr($firstQuestion, 0, 150) . '...' : $firstQuestion;
                    echo nl2br($truncated);
                }
                echo '</td>';

                // Status
                echo '<td>';
                if ($hasFlagged) {
                    echo '<span class="badge" style="background-color: #cc0000; color: white;">';
                    echo '⚠️ Flagged';
                    echo '</span>';
                } else {
                    echo '<span class="badge" style="background-color: #28a745; color: white;">';
                    echo '✓ OK';
                    echo '</span>';
                }
                echo '</td>';

                // Actions
                echo '<td>';
                echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/teacher_conversation_view.php&sessionID=' . $row['sessionID'] . '">';
                echo __('View');
                echo '</a>';
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            // Summary statistics
            $result->execute();
            $stats = [
                'totalConversations' => $result->rowCount(),
                'totalMessages' => 0,
                'totalQuestions' => 0,
                'flaggedConversations' => 0,
                'uniqueStudents' => []
            ];

            while ($row = $result->fetch()) {
                $messageCount = $row['messageCount'] ?? ($row['studentQuestions'] * 2);
                $stats['totalMessages'] += $messageCount;
                $stats['totalQuestions'] += $row['studentQuestions'];

                if ($row['hasFlagged'] == 1) {
                    $stats['flaggedConversations']++;
                }

                $stats['uniqueStudents'][$row['gibbonPersonID']] = true;
            }

            echo '<div class="linkTop">';
            echo '<h4>' . __('Statistics') . '</h4>';
            echo '<ul>';
            echo '<li><strong>' . __('Total Conversations') . ':</strong> ' . $stats['totalConversations'] . '</li>';
            echo '<li><strong>' . __('Unique Students') . ':</strong> ' . count($stats['uniqueStudents']) . '</li>';
            echo '<li><strong>' . __('Total Messages') . ':</strong> ' . $stats['totalMessages'] . '</li>';
            echo '<li><strong>' . __('Student Questions') . ':</strong> ' . $stats['totalQuestions'] . '</li>';
            echo '<li><strong>' . __('Conversations with Flagged Content') . ':</strong> ' . $stats['flaggedConversations'] . '</li>';
            echo '</ul>';
            echo '</div>';

        } else {
            echo '<div class="warning">';
            echo __('No conversations found for the selected filters.');
            echo '</div>';
        }

    } catch (Exception $e) {
        echo '<div class="error">';
        echo __('An error occurred while loading usage data.');
        echo '</div>';
        error_log("Error in teacher_student_usage: " . $e->getMessage());
    }
}

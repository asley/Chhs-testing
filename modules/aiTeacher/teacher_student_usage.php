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
use Gibbon\Tables\DataTable;

// Get database from container
$pdo = $container->get('db');

$page->breadcrumbs->add(__('Student AI Tutor Usage'));

// Check if user has access
if (isActionAccessible($guid, $connection2, '/modules/aiTeacher/teacher_student_usage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    echo '<h2>' . __('Student AI Tutor Usage Monitor') . '</h2>';
    echo '<p>' . __('View what questions your students are asking the AI Tutor and monitor their conversations.') . '</p>';

    // Get filter parameters
    $gibbonPersonIDStudent = $_GET['gibbonPersonIDStudent'] ?? '';
    $gibbonRollGroupID = $_GET['gibbonRollGroupID'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $flaggedOnly = $_GET['flaggedOnly'] ?? '';

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
    $resultStudents = $pdo->executeQuery(['gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID')], $sqlStudents);

    $students = ['' => __('All Students')];
    while ($student = $resultStudents->fetch()) {
        $students[$student['gibbonPersonID']] = $student['surname'] . ', ' . $student['preferredName'];
    }

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDStudent', __('Student'));
        $select = $row->addSelect('gibbonPersonIDStudent')
            ->fromArray($students);
        if (!empty($gibbonPersonIDStudent)) {
            $select->selected($gibbonPersonIDStudent);
        }

    // Get list of roll groups/classes
    $sqlRollGroups = "SELECT gibbonRollGroupID, name, nameShort
                      FROM gibbonRollGroup
                      WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                      ORDER BY name";
    $resultRollGroups = $pdo->executeQuery(['gibbonSchoolYearID' => $gibbon->session->get('gibbonSchoolYearID')], $sqlRollGroups);

    $rollGroups = ['' => __('All Classes')];
    while ($rollGroup = $resultRollGroups->fetch()) {
        $rollGroups[$rollGroup['gibbonRollGroupID']] = $rollGroup['name'];
    }

    $row = $form->addRow();
        $row->addLabel('gibbonRollGroupID', __('Class'));
        $selectRollGroup = $row->addSelect('gibbonRollGroupID')
            ->fromArray($rollGroups);
        if (!empty($gibbonRollGroupID)) {
            $selectRollGroup->selected($gibbonRollGroupID);
        }

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
        'dateTo' => $dateTo . ' 23:59:59'
    ];

    $sql = "SELECT
                c.gibbonPersonID,
                c.sessionID,
                c.message,
                c.sender,
                c.timestamp,
                c.flagged,
                c.flagReason,
                c.rating,
                p.surname,
                p.preferredName,
                s.messageCount,
                s.startTime,
                s.lastActivity,
                rg.name as rollGroup
            FROM aiTeacherStudentConversations c
            JOIN gibbonPerson p ON c.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN aiTeacherChatSessions s ON c.sessionID = s.sessionID
            LEFT JOIN gibbonStudentEnrolment se ON p.gibbonPersonID = se.gibbonPersonID AND se.gibbonSchoolYearID = :gibbonSchoolYearID
            LEFT JOIN gibbonRollGroup rg ON se.gibbonRollGroupID = rg.gibbonRollGroupID
            WHERE c.timestamp BETWEEN :dateFrom AND :dateTo";

    $data['gibbonSchoolYearID'] = $gibbon->session->get('gibbonSchoolYearID');

    if (!empty($gibbonPersonIDStudent)) {
        $sql .= " AND c.gibbonPersonID = :gibbonPersonID";
        $data['gibbonPersonID'] = $gibbonPersonIDStudent;
    }

    if (!empty($gibbonRollGroupID)) {
        $sql .= " AND se.gibbonRollGroupID = :gibbonRollGroupID";
        $data['gibbonRollGroupID'] = $gibbonRollGroupID;
    }

    if (!empty($flaggedOnly)) {
        $sql .= " AND c.flagged = 1";
    }

    $sql .= " ORDER BY c.timestamp DESC LIMIT 500";

    try {
        $result = $pdo->executeQuery($data, $sql);

        if ($result->rowCount() > 0) {
            echo '<h3>' . __('Conversation History') . ' (' . $result->rowCount() . ' ' . __('messages') . ')</h3>';

            echo '<table class="fullWidth colorOddEven" cellspacing="0">';
            echo '<thead>';
            echo '<tr class="head">';
            echo '<th style="width: 15%;">' . __('Student') . '</th>';
            echo '<th style="width: 10%;">' . __('Date/Time') . '</th>';
            echo '<th style="width: 8%;">' . __('Sender') . '</th>';
            echo '<th style="width: 45%;">' . __('Message') . '</th>';
            echo '<th style="width: 10%;">' . __('Status') . '</th>';
            echo '<th style="width: 12%;">' . __('Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            while ($row = $result->fetch()) {
                $studentName = Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
                // Add class name if available
                if (!empty($row['rollGroup'])) {
                    $studentName .= ' <span style="color: #666; font-size: 0.9em;">(' . htmlspecialchars($row['rollGroup']) . ')</span>';
                }
                $isStudent = ($row['sender'] === 'student');
                $isFlagged = ($row['flagged'] == 1);

                // Row styling
                $rowClass = '';
                if ($isFlagged) {
                    $rowClass = 'error';
                }

                echo '<tr class="' . $rowClass . '">';

                // Student name with class
                echo '<td>' . $studentName . '</td>';

                // Timestamp
                echo '<td>' . Format::dateTime($row['timestamp']) . '</td>';

                // Sender
                $senderIcon = $isStudent ? '👤 Student' : '🤖 AI';
                echo '<td>' . $senderIcon . '</td>';

                // Message (truncated if too long)
                $message = htmlspecialchars($row['message']);
                $truncated = strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message;
                echo '<td>' . nl2br($truncated) . '</td>';

                // Status
                echo '<td>';
                if ($isFlagged) {
                    echo '<span class="badge" style="background-color: #cc0000; color: white;">';
                    echo '⚠️ Flagged: ' . ucfirst($row['flagReason']);
                    echo '</span>';
                } else if ($isStudent && $row['rating']) {
                    echo '<span class="badge">';
                    echo $row['rating'] === 'helpful' ? '👍 Helpful' : '👎 Not Helpful';
                    echo '</span>';
                }
                echo '</td>';

                // Actions
                echo '<td>';
                echo '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/aiTeacher/teacher_conversation_view.php&sessionID=' . $row['sessionID'] . '">';
                echo __('View Full Conversation');
                echo '</a>';
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';

            // Summary statistics
            $result->execute();
            $stats = [
                'totalMessages' => $result->rowCount(),
                'studentMessages' => 0,
                'aiMessages' => 0,
                'flaggedMessages' => 0,
                'uniqueSessions' => []
            ];

            while ($row = $result->fetch()) {
                if ($row['sender'] === 'student') {
                    $stats['studentMessages']++;
                } else {
                    $stats['aiMessages']++;
                }

                if ($row['flagged'] == 1) {
                    $stats['flaggedMessages']++;
                }

                $stats['uniqueSessions'][$row['sessionID']] = true;
            }

            echo '<div class="linkTop">';
            echo '<h4>' . __('Statistics') . '</h4>';
            echo '<ul>';
            echo '<li><strong>' . __('Total Messages') . ':</strong> ' . $stats['totalMessages'] . '</li>';
            echo '<li><strong>' . __('Student Questions') . ':</strong> ' . $stats['studentMessages'] . '</li>';
            echo '<li><strong>' . __('AI Responses') . ':</strong> ' . $stats['aiMessages'] . '</li>';
            echo '<li><strong>' . __('Flagged Messages') . ':</strong> ' . $stats['flaggedMessages'] . '</li>';
            echo '<li><strong>' . __('Unique Conversations') . ':</strong> ' . count($stats['uniqueSessions']) . '</li>';
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

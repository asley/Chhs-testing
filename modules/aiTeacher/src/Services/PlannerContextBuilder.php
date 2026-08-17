<?php

namespace Gibbon\Module\aiTeacher\Services;

class PlannerContextBuilder
{
    private $connection2;
    private $guid;

    public function __construct(\PDO $connection2, string $guid)
    {
        $this->connection2 = $connection2;
        $this->guid = $guid;
    }

    public function buildForLesson(string $gibbonPlannerEntryID, string $gibbonPersonID): array
    {
        if (!isActionAccessible($this->guid, $this->connection2, '/modules/Planner/planner_edit.php')) {
            throw new \RuntimeException('You do not have access to edit Planner lessons.');
        }

        $highestAction = getHighestGroupedAction($this->guid, '/modules/Planner/planner_edit.php', $this->connection2);
        if ($highestAction === false) {
            throw new \RuntimeException('Planner edit permission could not be determined.');
        }

        if ($highestAction === 'Lesson Planner_viewEditAllClasses') {
            $data = ['gibbonPlannerEntryID' => $gibbonPlannerEntryID];
            $sql = "SELECT gibbonPlannerEntry.*, gibbonCourse.gibbonCourseID, gibbonCourse.name AS courseName,
                    gibbonCourse.nameShort AS courseNameShort, gibbonCourseClass.nameShort AS classNameShort,
                    gibbonCourseClass.gibbonCourseClassID
                FROM gibbonPlannerEntry
                JOIN gibbonCourseClass ON gibbonPlannerEntry.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
                JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                WHERE gibbonPlannerEntry.gibbonPlannerEntryID = :gibbonPlannerEntryID";
        } else {
            $data = [
                'gibbonPlannerEntryID' => $gibbonPlannerEntryID,
                'gibbonPersonID' => $gibbonPersonID,
            ];
            $sql = "SELECT gibbonPlannerEntry.*, gibbonCourse.gibbonCourseID, gibbonCourse.name AS courseName,
                    gibbonCourse.nameShort AS courseNameShort, gibbonCourseClass.nameShort AS classNameShort,
                    gibbonCourseClass.gibbonCourseClassID
                FROM gibbonPlannerEntry
                JOIN gibbonCourseClass ON gibbonPlannerEntry.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
                JOIN gibbonCourseClassPerson ON gibbonCourseClassPerson.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID
                JOIN gibbonCourse ON gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID
                WHERE gibbonCourseClassPerson.gibbonPersonID = :gibbonPersonID
                    AND gibbonCourseClassPerson.role = 'Teacher'
                    AND gibbonPlannerEntry.gibbonPlannerEntryID = :gibbonPlannerEntryID";
        }

        $result = $this->connection2->prepare($sql);
        $result->execute($data);
        if ($result->rowCount() !== 1) {
            throw new \RuntimeException('The selected Planner lesson does not exist, or you do not have access to it.');
        }

        $lesson = $result->fetch();
        $unit = $this->getUnit($lesson['gibbonUnitID'] ?? '');
        $blocks = $this->getUnitBlocks($lesson['gibbonUnitID'] ?? '');
        $outcomes = $this->getPlannerOutcomes($gibbonPlannerEntryID);

        return [
            'course' => [
                'id' => $lesson['gibbonCourseID'],
                'name' => $lesson['courseName'],
                'nameShort' => $lesson['courseNameShort'],
            ],
            'class' => [
                'id' => $lesson['gibbonCourseClassID'],
                'nameShort' => $lesson['classNameShort'],
            ],
            'unit' => $unit,
            'blocks' => $blocks,
            'outcomes' => $outcomes,
            'lesson' => [
                'id' => $lesson['gibbonPlannerEntryID'],
                'name' => $lesson['name'],
                'summary' => $lesson['summary'],
                'description' => $lesson['description'],
                'teachersNotes' => $lesson['teachersNotes'],
                'homework' => $lesson['homework'],
                'homeworkDetails' => $lesson['homeworkDetails'],
                'date' => $lesson['date'],
                'timeStart' => $lesson['timeStart'],
                'timeEnd' => $lesson['timeEnd'],
                'gibbonCourseClassID' => $lesson['gibbonCourseClassID'],
                'gibbonUnitID' => $lesson['gibbonUnitID'],
            ],
        ];
    }

    private function getUnit(?string $gibbonUnitID): array
    {
        if (empty($gibbonUnitID)) {
            return [];
        }

        $result = $this->connection2->prepare(
            'SELECT gibbonUnitID, name, description, tags, details FROM gibbonUnit WHERE gibbonUnitID = :gibbonUnitID'
        );
        $result->execute(['gibbonUnitID' => $gibbonUnitID]);

        return $result->fetch() ?: [];
    }

    private function getUnitBlocks(?string $gibbonUnitID): array
    {
        if (empty($gibbonUnitID)) {
            return [];
        }

        $result = $this->connection2->prepare(
            'SELECT title, type, length, contents, teachersNotes, sequenceNumber
            FROM gibbonUnitBlock
            WHERE gibbonUnitID = :gibbonUnitID
            ORDER BY sequenceNumber'
        );
        $result->execute(['gibbonUnitID' => $gibbonUnitID]);

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPlannerOutcomes(string $gibbonPlannerEntryID): array
    {
        $result = $this->connection2->prepare(
            'SELECT gibbonOutcome.name, gibbonOutcome.nameShort, gibbonPlannerEntryOutcome.content, gibbonPlannerEntryOutcome.sequenceNumber
            FROM gibbonPlannerEntryOutcome
            JOIN gibbonOutcome ON gibbonOutcome.gibbonOutcomeID = gibbonPlannerEntryOutcome.gibbonOutcomeID
            WHERE gibbonPlannerEntryOutcome.gibbonPlannerEntryID = :gibbonPlannerEntryID
            ORDER BY gibbonPlannerEntryOutcome.sequenceNumber'
        );
        $result->execute(['gibbonPlannerEntryID' => $gibbonPlannerEntryID]);

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }
}

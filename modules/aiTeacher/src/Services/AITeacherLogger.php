<?php

namespace Gibbon\Module\aiTeacher\Services;

class AITeacherLogger
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function logPlannerGeneration(array $data): void
    {
        try {
            $tableExists = $this->pdo
                ->executeQuery([], "SHOW TABLES LIKE 'aiTeacherPlannerGeneration'")
                ->rowCount() > 0;

            if (!$tableExists) {
                return;
            }

            $sql = "INSERT INTO aiTeacherPlannerGeneration
                SET gibbonPersonID = :gibbonPersonID,
                    gibbonPlannerEntryID = :gibbonPlannerEntryID,
                    gibbonCourseClassID = :gibbonCourseClassID,
                    gibbonUnitID = :gibbonUnitID,
                    subject = :subject,
                    outputType = :outputType,
                    promptHash = :promptHash,
                    provider = :provider,
                    status = :status,
                    error = :error";

            $this->pdo->executeQuery([
                'gibbonPersonID' => $data['gibbonPersonID'] ?? null,
                'gibbonPlannerEntryID' => $data['gibbonPlannerEntryID'] ?? null,
                'gibbonCourseClassID' => $data['gibbonCourseClassID'] ?? null,
                'gibbonUnitID' => $data['gibbonUnitID'] ?? null,
                'subject' => mb_substr($data['subject'] ?? '', 0, 100),
                'outputType' => mb_substr($data['outputType'] ?? '', 0, 50),
                'promptHash' => $data['promptHash'] ?? null,
                'provider' => mb_substr($data['provider'] ?? '', 0, 50),
                'status' => $data['status'] ?? 'Error',
                'error' => $data['error'] ?? null,
            ], $sql);
        } catch (\Exception $e) {
            error_log('AI Teacher generation log failed: '.$e->getMessage());
        }
    }
}


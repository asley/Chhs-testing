<?php

namespace Gibbon\Module\aiTeacher\Services;

class AITeacherSettingsService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSettings(string $scope = 'aiTeacher'): array
    {
        try {
            $tableExists = $this->pdo
                ->executeQuery([], "SHOW TABLES LIKE 'aiTeacherSettings'")
                ->rowCount() > 0;

            if (!$tableExists) {
                return [];
            }

            $result = $this->pdo->executeQuery(
                ['scope' => $scope],
                'SELECT name, value FROM aiTeacherSettings WHERE scope = :scope'
            );

            $settings = [];
            while ($row = $result->fetch()) {
                $settings[$row['name']] = $row['value'];
            }

            return $settings;
        } catch (\Exception $e) {
            error_log('AI Teacher settings read failed: '.$e->getMessage());
            return [];
        }
    }
}


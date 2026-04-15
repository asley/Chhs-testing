<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Contracts\Database\Connection;
use Gibbon\Database\Migrations\Migration;

/**
 * Google Drive Report Sync migration.
 */
class GoogleDriveReportSync extends Migration
{
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function migrate()
    {
        $partialFail = false;

        $partialFail = !$this->db->statement(
            "ALTER TABLE `gibbonReportArchiveEntry` ADD COLUMN IF NOT EXISTS `googleDriveFileID` VARCHAR(255) NULL DEFAULT NULL AFTER `filePath`;"
        ) || $partialFail;

        $partialFail = !$this->db->statement(
            "INSERT IGNORE INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Reports', 'googleDrive', 'Google Drive Sync', 'Service account credentials and folder ID for Google Drive report mirroring.', '');"
        ) || $partialFail;

        $partialFail = !$this->db->statement(
            "UPDATE `gibbonAction` SET URLList=CONCAT(URLList, ',archive_manage_googledrive.php') WHERE name='Manage Archives' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Reports') AND URLList NOT LIKE '%archive_manage_googledrive%';"
        ) || $partialFail;

        $partialFail = !$this->db->statement(
            "UPDATE `gibbonAction` SET URLList=CONCAT(URLList, ',archive_manage_googledriveSyncProcess.php') WHERE name='Manage Archives' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Reports') AND URLList NOT LIKE '%archive_manage_googledriveSyncProcess.php%';"
        ) || $partialFail;

        $partialFail = !$this->db->statement(
            "UPDATE `gibbonAction` SET URLList=CONCAT(URLList, ',googledrive_settings.php') WHERE name='Manage Archives' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Reports') AND URLList NOT LIKE '%googledrive_settings.php%';"
        ) || $partialFail;

        $partialFail = !$this->db->statement(
            "UPDATE `gibbonAction` SET URLList=CONCAT(URLList, ',googledrive_settingsProcess.php') WHERE name='Manage Archives' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Reports') AND URLList NOT LIKE '%googledrive_settingsProcess.php%';"
        ) || $partialFail;

        return !$partialFail;
    }
}

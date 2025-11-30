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

namespace Gibbon\Module\Committees\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class CommitteeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'committeesCommittee';
    private static $primaryKey = 'committeesCommitteeID';
    private static $searchableColumns = ['committeesCommittee.name'];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryCommittees(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols(['committeesCommittee.committeesCommitteeID', 'committeesCommittee.name', 'committeesCommittee.description', 'committeesCommittee.logo', 'committeesCommittee.active', 'committeesCommittee.signup', "COUNT(DISTINCT committeesMember.gibbonPersonID) as members", "seats.totalSeats", "COUNT(DISTINCT CASE WHEN memberRole.signup = 'Y' THEN committeesMember.gibbonPersonID  END) as usedSeats"])
            ->joinSubSelect(
                'LEFT',
                "SELECT DISTINCT committeesRole.committeesCommitteeID, SUM(CASE WHEN committeesRole.signup = 'Y' THEN committeesRole.seats ELSE 0 END) as totalSeats FROM committeesRole GROUP BY committeesRole.committeesCommitteeID",
                'seats',
                'seats.committeesCommitteeID=committeesCommittee.committeesCommitteeID'
            )
            ->leftJoin('committeesMember', 'committeesMember.committeesCommitteeID=committeesCommittee.committeesCommitteeID')
            ->leftJoin('committeesRole as memberRole', 'committeesMember.committeesRoleID=memberRole.committeesRoleID')
            ->where('committeesCommittee.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['committeesCommittee.committeesCommitteeID']);

        $criteria->addFilterRules([
            'active' => function ($query, $active) {
                return $query
                    ->where('committeesCommittee.active = :active')
                    ->bindValue('active', $active);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function queryCommitteesByMember(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from('committeesMember')
            ->cols(['committeesCommittee.committeesCommitteeID', 'committeesCommittee.name', 'committeesCommittee.description', 'committeesCommittee.active', 'committeesCommittee.signup', 'committeesRole.name as role'])
            ->leftJoin('committeesCommittee', 'committeesMember.committeesCommitteeID=committeesCommittee.committeesCommitteeID')
            ->leftJoin('committeesRole', 'committeesRole.committeesCommitteeID=committeesCommittee.committeesCommitteeID')
            ->where("committeesCommittee.active = 'Y'")
            ->where('committeesCommittee.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->where('committeesMember.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->groupBy(['committeesCommittee.committeesCommitteeID']);

        return $this->runQuery($query, $criteria);
    }

    public function isPersonCommitteeAdmin($committeesCommitteeID, $gibbonPersonID)
    {
        $data = ['committeesCommitteeID' => $committeesCommitteeID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonPerson.gibbonPersonID
                FROM gibbonPerson
                JOIN committeesMember ON (committeesMember.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN committeesRole ON (committeesRole.committeesRoleID=committeesMember.committeesRoleID)
                JOIN committeesCommittee ON (committeesRole.committeesCommitteeID=committeesCommittee.committeesCommitteeID)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID
                AND committeesCommittee.committeesCommitteeID=:committeesCommitteeID
                AND (committeesRole.type='Chair' OR committeesRole.type='Admin')";

        return !empty($this->db()->selectOne($sql, $data));
    }
}

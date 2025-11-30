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

class CommitteeMemberGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'committeesMember';
    private static $primaryKey = 'committeesMemberID';
    private static $searchableColumns = [''];
    
    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryMembersBySchoolYear(QueryCriteria $criteria, $gibbonSchoolYearID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['committeesMember.committeesMemberID', 'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.image_240', 'committeesRole.name as role', 'committeesCommittee.name as committee'])
            ->innerJoin('committeesCommittee', 'committeesCommittee.committeesCommitteeID=committeesMember.committeesCommitteeID')
            ->innerJoin('committeesRole', 'committeesRole.committeesRoleID=committeesMember.committeesRoleID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=committeesMember.gibbonPersonID')
            ->where("committeesCommittee.active = 'Y'")
            ->where('committeesCommittee.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        return $this->runQuery($query, $criteria);
    }

    
    public function queryMembersByCommittee(QueryCriteria $criteria, $committeesCommitteeID)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols(['committeesMember.committeesMemberID', 'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.image_240', 'committeesRole.name as role'])
            ->innerJoin('committeesCommittee', 'committeesCommittee.committeesCommitteeID=committeesMember.committeesCommitteeID')
            ->innerJoin('committeesRole', 'committeesRole.committeesRoleID=committeesMember.committeesRoleID')
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=committeesMember.gibbonPersonID')
            ->where('committeesMember.committeesCommitteeID=:committeesCommitteeID')
            ->bindValue('committeesCommitteeID', $committeesCommitteeID);

        return $this->runQuery($query, $criteria);
    }

    public function queryAvailableSeats(QueryCriteria $criteria, $committeesCommitteeID)
    {
        $query = $this
            ->newQuery()
            ->from('committeesRole')
            ->cols(['committeesRole.committeesRoleID', 'committeesRole.committeesCommitteeID', 'committeesRole.name as role', 'committeesRole.seats', 'COUNT(DISTINCT committeesMember.gibbonPersonID) as members'])
            ->innerJoin('committeesCommittee', 'committeesCommittee.committeesCommitteeID=committeesRole.committeesCommitteeID')
            ->leftJoin('committeesMember', 'committeesMember.committeesRoleID=committeesRole.committeesRoleID')
            ->where('committeesRole.committeesCommitteeID=:committeesCommitteeID')
            ->bindValue('committeesCommitteeID', $committeesCommitteeID)
            ->where("committeesCommittee.active = 'Y'")
            ->where("committeesRole.active = 'Y'")
            ->where("committeesRole.signup = 'Y'")
            ->groupBy(['committeesRole.committeesRoleID'])
            ->having('members < committeesRole.seats');

        return $this->runQuery($query, $criteria);
    }

    public function queryStaffWhoAreNotMembers(QueryCriteria $criteria, $gibbonSchoolYearID, $allStaff = false)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonStaff')
            ->cols(['gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.image_240', 'gibbonStaff.jobTitle', 'gibbonStaff.type'])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonStaff.gibbonPersonID')
            ->leftJoin('committeesMember', 'committeesMember.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('committeesCommittee', "committeesCommittee.committeesCommitteeID=committeesMember.committeesCommitteeID 
                        AND committeesCommittee.gibbonSchoolYearID=:gibbonSchoolYearID AND committeesCommittee.active = 'Y'")
            ->where("committeesCommittee.committeesCommitteeID IS NULL")
            ->where("committeesMember.committeesMemberID IS NULL")
            ->where("gibbonPerson.status='Full'")
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->groupBy(['gibbonStaff.gibbonPersonID']);

        if (!$criteria->hasFilter('all')) {
            $query->innerJoin('gibbonRole', 'FIND_IN_SET(gibbonRole.gibbonRoleID, gibbonPerson.gibbonRoleIDAll)')
                  ->where("(gibbonRole.name LIKE '%Leader%' OR gibbonRole.name LIKE '%Teacher%' OR gibbonStaff.type LIKE 'Teach')");
        }
        
        $criteria->addFilterRules([
            'type' => function ($query, $type) {
                return $query
                    ->where('gibbonStaff.type=:type')
                    ->bindValue('type', $type);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    public function selectChairPeopleByCommittee($committeesCommitteeID)
    {
        $committeesCommitteeIDList = is_array($committeesCommitteeID)? $committeesCommitteeID : [$committeesCommitteeID];

        $data = ['committeesCommitteeIDList' => implode(',', $committeesCommitteeIDList)];
        $sql = "SELECT committeesCommittee.committeesCommitteeID, gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName, gibbonPerson.surname
            FROM committeesCommittee 
            JOIN committeesRole ON (committeesRole.committeesCommitteeID=committeesCommittee.committeesCommitteeID)
            JOIN committeesMember ON (committeesMember.committeesRoleID=committeesRole.committeesRoleID)
            JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=committeesMember.gibbonPersonID)
            WHERE FIND_IN_SET(committeesCommittee.committeesCommitteeID, :committeesCommitteeIDList)
            AND committeesCommittee.active='Y' 
            AND committeesRole.active='Y' 
            AND committeesRole.type='Chair'
            ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }
}

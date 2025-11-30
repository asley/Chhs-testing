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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
use Gibbon\Tables\DataTable;
use Gibbon\Module\HousePoints\Domain\HousePointStudentGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('View points overall'));
if (isActionAccessible($guid, $connection2,"/modules/House Points/overall.php")==FALSE) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $housePointStudentGateway = $container->get(HousePointStudentGateway::class);
    $housePoints = $housePointStudentGateway->selectStudentPoints($session->get('gibbonPersonID'), $session->get('gibbonSchoolYearID'))->fetchAll();
    $housePointsAll = $housePointStudentGateway->selectStudentPointsSum($session->get('gibbonPersonID'), $session->get('gibbonSchoolYearID'))->fetch();

    $table = DataTable::create('housePoints');
    $table->setTitle('House Points');
    

    $table->addColumn('awardedDate', __('Date'));
    $table->addColumn('points', __('Points'));
    $table->addColumn('categoryName', __('Category'));
    $table->addColumn('reason', __('Reason'));
    $table->addColumn('teacherName', __('Awarded By'));
    
    echo $table->render($housePoints);
    
    //TODO: It may be worth creating a table object for a footer row in the future...
    echo "<b>Total: </b>". $housePointsAll['points'];
}

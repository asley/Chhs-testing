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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\HousePoints\Domain\HousePointStudentGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Tables\DataTable;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('View points class'));

if (isActionAccessible($guid, $connection2,"/modules/House Points/classpoints.php")==FALSE) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    
    $form = Form::create('classPoints', '');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('yearID', $session->get('gibbonSchoolYearID'));
    
    $classID = $_POST['classID'] ?? '' ;
    $row = $form->addRow();
        $row->addLabel('classID', __('Class'));
        $row->addSelectFormGroup('classID', $session->get('gibbonSchoolYearID'))->placeholder()->selected($classID);
    
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
    echo $form->getOutput();
    
    
    if(isset($_POST['classID'])){
        $housePointStudentGateway = $container->get(HousePointStudentGateway::class);
        $data = $housePointStudentGateway->selectClassStudentPoints($_POST['classID'], $_POST['yearID'])->fetchAll();
        
        $table = DataTable::create('classPoints');
        $table->setTitle('Class House Points');
        
        $userGateway = $container->get(UserGateway::class);
        $table->addColumn('gibbonPersonID', __('Name'))->format(function ($row) use ($userGateway) {
            $person = $userGateway->getByID($row['gibbonPersonID']);
            $output = Format::name($person['title'], $person['preferredName'], $person['surname'], 'Student');
            return $output;
        });
        $table->addColumn('total', __('Total Points'))->format(function ($row) {
            if ($row['total'] == ''){
                $output='0';
            } else {
                $output=$row['total'];
            }
            return $output;
        });
        $table->addColumn('houseName', __('House'))->format(function ($row) {
            if ($row['houseName'] == ''){
                $output='N/A';
            } else {
                $output=$row['houseName'];
            }
            return $output;
        });
    
        echo $table->render($data);
    }


}

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

use Gibbon\Forms\Form;
use Gibbon\Module\HousePoints\Domain\HousePointCategoryGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Award house points'));
if (isActionAccessible($guid, $connection2,"/modules/House Points/house.php")==FALSE) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
        $form = Form::create('awardForm', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/housePointsProcess.php', 'post');
        $form->setTitle('Award house points to house');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('yearID', $session->get('gibbonSchoolYearID'));
        $form->addHiddenValue('teacherID', $session->get('gibbonPersonID'));

        $sql = "SELECT gibbonHouse.gibbonHouseID AS value, gibbonHouse.name FROM gibbonHouse ORDER BY gibbonHouse.name";
        $row = $form->addRow();
            $row->addLabel('houseID', __('House'));
            $row->addSelect('houseID')->fromQuery($pdo, $sql)->required()->placeholder();

        $highestAction = getHighestGroupedAction($guid, '/modules/House Points/house.php', $connection2);
        $unlimitedPoints = ($highestAction == 'Award house points_unlimited');
        
        $housePointCategoryGateway = $container->get(HousePointCategoryGateway::class);
        $hpCategories = $housePointCategoryGateway->selectBy(['categoryType' => 'House']);
        $categories = array_reduce($hpCategories->fetchAll(), function($group, $item) use ($unlimitedPoints) { 
            if (empty($item['categoryPresets']) && !$unlimitedPoints) return $group; 

            $group[$item['categoryID']] = $item['categoryName'];
            return $group;
        }, array());

        $row = $form->addRow();
            $row->addLabel('categoryID', __('Category'));
            $row->addSelect('categoryID')->fromArray($categories)->required()->placeholder();

        $row = $form->addRow();
            $row->addLabel('points', __('Points'));
            $row->addTextField('points')->disabled()->placeholder(__('Select a category'));

        $reasons = $pdo->select("SELECT DISTINCT reason FROM hpPointHouse UNION SELECT DISTINCT reason from hpPointStudent ORDER BY reason")->fetchAll(\PDO::FETCH_COLUMN);
        $row = $form->addRow();
            $row->addLabel('reason', __('Reason'));
            $row->addTextField('reason')->maxLength(100)->required()->autocomplete($reasons);
        
        $row = $form->addRow();
            $row->addAlert(__('If the reason for awarding points already exists, ensure the text in the reason field is the same.'), "warning");

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();

        echo "<br><p id='msg' style='color:blue;'></p>";
        
        //TODO: rewrite the code to maybe use a chained or something rather than this weird ajax...
        //TODO: this may require additions to the core src due to the nature of how it works, why do I get myself into these situations? :/
        ?>
        <script>
            $('#awardForm #categoryID').change(function(){
                $.ajax({
                    url: "./modules/House Points/category_points_ajax.php",
                    data: {
                        categoryID: $('#categoryID').val()
                    },
                    type: 'POST',
                    dataType: 'json',
                    success: function(data) {
                        // console.log(data);
                        var parent = $('#points').parent();
                        $('#points').detach().remove();

                        if (data !== null) {
                            var points = $('<select/>').attr('name', 'points').attr('id', 'points').attr('class', 'standardWidth');
                            $.each(data, function(value, label) {
                                points.append($("<option/>").attr("value", parseInt(value)).text(label));
                            });
                            parent.append(points);
                        } else {
                            var points = $('<input type="text" id="points" name="points" value="1" class="standardWidth" maxlength="4" />');
                            parent.append(points);
                        }
                    }
                });
            });

            $('#awardForm').change(function() {
                $('#msg').html('');
                $('#submit').prop('disabled', false).css({'background-color': 'black', 'color': 'white'});
            });
        </script>
        <?php
}

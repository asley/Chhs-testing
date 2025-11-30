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
use Gibbon\Module\HousePoints\Domain\HousePointCategoryGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Categories'));
if (isActionAccessible($guid, $connection2,"/modules/House Points/category.php")==FALSE) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $housePointCategoryGateway = $container->get(HousePointCategoryGateway::class);
    $category = $housePointCategoryGateway->selectBy([])->fetchAll();

    $table = DataTable::create('housePoints');
    $table->setTitle('Categories');
    $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/' . $session->get('module') . '/categoryAdd.php')
            ->displayLabel();
    
    $table->addColumn('categoryName', __('Name'));
    
    $table->addActionColumn()
        ->addParam('categoryID')
        ->format(function ($row, $actions) use ($session) {
            $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/' . $session->get('module') . '/categoryEdit.php');
            $actions->addAction('delete', __('delete'))
                    ->setURL('/modules/' . $session->get('module') . '/categoryDelete.php');
        });
    
    echo $table->render($category);
}

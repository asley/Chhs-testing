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

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Categories'));
if (isActionAccessible($guid, $connection2,"/modules/House Points/category.php")==FALSE) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $form = Form::create('catform', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/categoryAddProcess.php', 'post');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('categoryName', __('Category Name'));
        $row->addTextField('categoryName')->required()->maxLength(45);

    $row = $form->addRow();
        $row->addLabel('categoryType', __('Type'));
        $row->addSelect('categoryType')->fromArray(array('House', 'Student'))->selected('House');

    $row = $form->addRow();
        $row->addLabel('categoryPresets', __('Presets'))
            ->description(__('Add preset comma-separated increments as Name: PointValue. Leave blank for unlimited.'))
            ->description(__(' eg: ThingOne: 1, ThingTwo: 5, ThingThree: 10'));
        $row->addTextArea('categoryPresets')->setRows(2);

    $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

    echo $form->getOutput();

}

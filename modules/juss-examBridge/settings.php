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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/settings.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('juss-examBridge'), 'index.php');
    $page->breadcrumbs->add(__('Bridge Settings'));

    $settingGateway = $container->get(SettingGateway::class);

    $form = Form::create('jussExamBridgeSettings', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/settingsProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $form->addRow()->addHeading('Connection', __('Connection'));

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'tcexamBaseUrl', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addURL($setting['name'])->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'bridgeKeyId', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addTextField($setting['name'])->setValue($setting['value'])->maxLength(100);

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'bridgeSharedSecret', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addPassword($setting['name'])->setValue($setting['value'])->maxLength(255);

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'signatureMaxSkewSeconds', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addNumber($setting['name'])->setValue($setting['value'])->minimum(30)->maximum(3600)->required();

    $form->addRow()->addHeading('Feature Flags', __('Feature Flags'));

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'enrollmentSyncEnabled', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'gradeSyncEnabled', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $setting = $settingGateway->getSettingByScope('juss-examBridge', 'dryRunEnabled', true);
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
}

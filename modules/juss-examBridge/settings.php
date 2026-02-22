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
    $settingDefinitions = [
        'tcexamBaseUrl' => [
            'nameDisplay' => 'TCExam Base URL',
            'description' => 'Base URL for the TCExam API endpoint, eg https://tcexam.example.com',
            'default' => '',
        ],
        'bridgeKeyId' => [
            'nameDisplay' => 'Bridge Key ID',
            'description' => 'Public key identifier for signed integration requests.',
            'default' => '',
        ],
        'bridgeSharedSecret' => [
            'nameDisplay' => 'Bridge Shared Secret',
            'description' => 'Shared secret used for HMAC request verification.',
            'default' => '',
        ],
        'bridgeServicePersonID' => [
            'nameDisplay' => 'Bridge Service Person ID',
            'description' => 'Person ID used as last editor for automated grade sync writes. Leave blank to fall back to System Administrator setting.',
            'default' => '',
        ],
        'signatureMaxSkewSeconds' => [
            'nameDisplay' => 'Max Signature Skew (Seconds)',
            'description' => 'Maximum allowed difference between request timestamp and server time.',
            'default' => '300',
        ],
        'enrollmentSyncEnabled' => [
            'nameDisplay' => 'Enrollment Sync Enabled',
            'description' => 'Enable class and enrollment sync from Gibbon to TCExam.',
            'default' => 'N',
        ],
        'gradeSyncEnabled' => [
            'nameDisplay' => 'Grade Sync Enabled',
            'description' => 'Enable grade push and write-back integration.',
            'default' => 'N',
        ],
        'dryRunEnabled' => [
            'nameDisplay' => 'Dry Run Mode',
            'description' => 'Validate sync operations without writing final assessment data.',
            'default' => 'Y',
        ],
    ];

    $getSetting = static function (SettingGateway $settingGateway, array $settingDefinitions, string $name) {
        $setting = $settingGateway->getSettingByScope('juss-examBridge', $name, true);
        $definition = $settingDefinitions[$name] ?? [];

        if (!is_array($setting)) {
            return [
                'name' => $name,
                'nameDisplay' => $definition['nameDisplay'] ?? $name,
                'description' => $definition['description'] ?? '',
                'value' => $definition['default'] ?? '',
            ];
        }

        $setting['name'] = $setting['name'] ?? $name;
        $setting['nameDisplay'] = $setting['nameDisplay'] ?? ($definition['nameDisplay'] ?? $name);
        $setting['description'] = $setting['description'] ?? ($definition['description'] ?? '');
        $setting['value'] = $setting['value'] ?? ($definition['default'] ?? '');

        return $setting;
    };

    $form = Form::create('jussExamBridgeSettings', $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/settingsProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $form->addRow()->addHeading('Connection', __('Connection'));

    $setting = $getSetting($settingGateway, $settingDefinitions, 'tcexamBaseUrl');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addURL($setting['name'])->setValue($setting['value']);

    $setting = $getSetting($settingGateway, $settingDefinitions, 'bridgeKeyId');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addTextField($setting['name'])->setValue($setting['value'])->maxLength(100);

    $setting = $getSetting($settingGateway, $settingDefinitions, 'bridgeSharedSecret');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addPassword($setting['name'])->setValue($setting['value'])->maxLength(255);

    $setting = $getSetting($settingGateway, $settingDefinitions, 'bridgeServicePersonID');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addNumber($setting['name'])->setValue($setting['value'])->minimum(1)->maximum(9999999999);

    $setting = $getSetting($settingGateway, $settingDefinitions, 'signatureMaxSkewSeconds');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addNumber($setting['name'])->setValue($setting['value'])->minimum(30)->maximum(3600)->required();

    $form->addRow()->addHeading('Feature Flags', __('Feature Flags'));

    $setting = $getSetting($settingGateway, $settingDefinitions, 'enrollmentSyncEnabled');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $setting = $getSetting($settingGateway, $settingDefinitions, 'gradeSyncEnabled');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $setting = $getSetting($settingGateway, $settingDefinitions, 'dryRunEnabled');
    $row = $form->addRow();
    $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
    $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
}

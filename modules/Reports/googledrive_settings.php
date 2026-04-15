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

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/Reports/archive_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage Archives'), 'archive_manage.php')
        ->add(__('Google Drive Settings'));

    $settingGateway = $container->get(SettingGateway::class);
    $raw = $settingGateway->getSettingByScope('Reports', 'googleDrive');
    $values = !empty($raw) ? json_decode($raw, true) : [];

    $page->return->addReturns([
        'success0' => __('Google Drive settings saved successfully.'),
        'error1'   => __('Validation failed. Check folder ID, domain, and JSON key format.'),
        'error2'   => __('Google Drive settings could not be saved.'),
        'error4'   => __('Google Account Email must be a real Workspace user email, not the service account email ending in iam.gserviceaccount.com.'),
    ]);

    if (!empty($values['serviceAccountJSON'])) {
        $page->addAlert(
            __('A service account JSON key is already saved and masked for security. Paste a new key only if you want to replace it.'),
            'message'
        );
    }

    $form = Form::create('googleDriveSettings', $session->get('absoluteURL').'/modules/Reports/googledrive_settingsProcess.php');
    $form->addHiddenValue('address', $session->get('address'));

    $form->addRow()->addHeading('Google Drive Report Sync', __('Google Drive Report Sync'))
        ->append(__('Mirror report PDFs to a Google Drive folder using a Google Workspace service account. Files remain on the server and are also accessible from Drive. Only staff with archive access can see Drive links.'));

    $row = $form->addRow();
        $row->addLabel('enabled', __('Enable Google Drive Sync'));
        $row->addYesNo('enabled')->required()->selected($values['enabled'] ?? 'N');

    $form->toggleVisibilityByClass('driveActive')->onSelect('enabled')->when('Y');

    $row = $form->addRow()->addClass('driveActive');
        $row->addLabel('folderId', __('Google Drive Folder ID'))
            ->description(__('The ID from the Drive folder URL: drive.google.com/drive/folders/{folder-id}. Share this folder with your service account email.'));
        $row->addTextField('folderId')->setValue($values['folderId'] ?? '')->maxLength(200);

    $row = $form->addRow()->addClass('driveActive');
        $row->addLabel('impersonateEmail', __('Google Account Email'))
            ->description(__('Your Google account email (e.g. admin@yourschool.org). Required when uploading to a personal Drive folder using domain-wide delegation. Leave blank if using a Shared Drive.'));
        $row->addTextField('impersonateEmail')->setValue($values['impersonateEmail'] ?? '')->maxLength(200);

    $row = $form->addRow()->addClass('driveActive');
        $row->addLabel('workspaceDomain', __('Workspace Domain'))
            ->description(__('Optional. If set, files are shared only with users in this Google Workspace domain (e.g. yourschool.org). If blank, the domain is derived from Google Account Email.'));
        $row->addTextField('workspaceDomain')->setValue($values['workspaceDomain'] ?? '')->maxLength(100);

    $row = $form->addRow()->addClass('driveActive');
        $row->addLabel('serviceAccountJSON', __('Service Account JSON Key'))
            ->description(__('Paste the full contents of the service account JSON key file downloaded from Google Cloud Console. This is stored securely in the database and is never re-displayed after save.'));
        $row->addTextArea('serviceAccountJSON')
            ->setRows(10)
            ->setValue(!empty($values['serviceAccountJSON']) ? '*** saved — paste new key to replace ***' : '')
            ->setAttribute('data-has-saved', !empty($values['serviceAccountJSON']) ? '1' : '0');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // Setup instructions
    echo '
    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded text-sm text-blue-900">
        <strong>'.__('Setup Instructions').'</strong>
        <ol class="mt-2 ml-4 list-decimal space-y-1">
            <li>'.__('In <a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a>, create a project and enable the <strong>Google Drive API</strong>.').'</li>
            <li>'.__('Create a <strong>Service Account</strong>, grant it no special roles, and generate a JSON key. Download the key file.').'</li>
            <li>'.__('Create a folder in Google Drive. Copy the folder ID from the URL.').'</li>
            <li>'.__('Share the Drive folder with the service account\'s email address (found in the JSON key as <code>client_email</code>). Give it <strong>Editor</strong> access.').'</li>
            <li>'.__('Paste the folder ID and JSON key contents into the fields above.').'</li>
        </ol>
    </div>';
}

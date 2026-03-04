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

require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/juss-examBridge/index.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('juss-examBridge'));

    $settingGateway = $container->get(SettingGateway::class);

    $tcexamBaseUrl = getJussExamBridgeSetting($settingGateway, 'tcexamBaseUrl');
    $bridgeKeyId = getJussExamBridgeSetting($settingGateway, 'bridgeKeyId');
    $bridgeSharedSecret = getJussExamBridgeSetting($settingGateway, 'bridgeSharedSecret');
    $bridgeServicePersonID = getJussExamBridgeSetting($settingGateway, 'bridgeServicePersonID');
    $signatureMaxSkewSeconds = getJussExamBridgeSetting($settingGateway, 'signatureMaxSkewSeconds', '300');
    $enrollmentSyncEnabled = getJussExamBridgeSetting($settingGateway, 'enrollmentSyncEnabled', 'N');
    $gradeSyncEnabled = getJussExamBridgeSetting($settingGateway, 'gradeSyncEnabled', 'N');
    $dryRunEnabled = getJussExamBridgeSetting($settingGateway, 'dryRunEnabled', 'Y');

    echo '<h2>';
    echo __('Integration Status');
    echo '</h2>';

    echo '<p>';
    echo __('Signed classes, enrollments and grades upsert APIs are available. Queue/scheduler and TCExam-side jobs are pending.');
    echo '</p>';

    echo '<table class="smallIntBorder fullWidth colorOddEven">';
    echo '<tr><td><b>' . __('TCExam Base URL') . '</b></td><td>' . ($tcexamBaseUrl ?: __('Not configured')) . '</td></tr>';
    echo '<tr><td><b>' . __('Bridge Key ID') . '</b></td><td>' . ($bridgeKeyId ?: __('Not configured')) . '</td></tr>';
    echo '<tr><td><b>' . __('Bridge Shared Secret') . '</b></td><td>' . getJussExamBridgeMaskedSecret($bridgeSharedSecret) . '</td></tr>';
    echo '<tr><td><b>' . __('Bridge Service Person ID') . '</b></td><td>' . ($bridgeServicePersonID ?: __('Auto (System Administrator)')) . '</td></tr>';
    echo '<tr><td><b>' . __('Max Signature Skew (Seconds)') . '</b></td><td>' . (int) $signatureMaxSkewSeconds . '</td></tr>';
    echo '<tr><td><b>' . __('Enrollment Sync Enabled') . '</b></td><td>' . ($enrollmentSyncEnabled === 'Y' ? __('Yes') : __('No')) . '</td></tr>';
    echo '<tr><td><b>' . __('Grade Sync Enabled') . '</b></td><td>' . ($gradeSyncEnabled === 'Y' ? __('Yes') : __('No')) . '</td></tr>';
    echo '<tr><td><b>' . __('Dry Run Mode') . '</b></td><td>' . ($dryRunEnabled === 'Y' ? __('Yes') : __('No')) . '</td></tr>';
    echo '</table>';

    echo '<p style="margin-top: 12px;">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/juss-examBridge/settings.php">';
    echo __('Open Bridge Settings');
    echo '</a>';
    echo '</p>';

    echo '<p style="margin-top: 8px;">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/juss-examBridge/mappings.php">';
    echo __('Open Bridge Mappings');
    echo '</a>';
    echo '</p>';
}

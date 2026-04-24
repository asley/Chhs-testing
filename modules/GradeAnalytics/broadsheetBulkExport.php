<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Module\GradeAnalytics\GradeAnalyticsGateway;
use Gibbon\Session\TokenHandler;

require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/GradeAnalytics/broadsheetBulkExport.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Grade Analytics'), 'gradeDashboard.php')
        ->add(__('Bulk Broadsheet Export'));

    $gateway = $container->get(GradeAnalyticsGateway::class);
    $tokenHandler = $container->get(TokenHandler::class);
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    $formGroups     = $gateway->selectFormGroups($gibbonSchoolYearID)->fetchAll(\PDO::FETCH_KEY_PAIR);
    $assessments    = $gateway->selectAssessmentColumns($gibbonSchoolYearID)->fetchAll(\PDO::FETCH_KEY_PAIR);

    $absoluteURL = $session->get('absoluteURL');
    $processURL  = $absoluteURL . '/modules/GradeAnalytics/broadsheetBulkExportProcess.php';

    echo '<h2>' . __('Bulk Broadsheet Export') . '</h2>';
    echo '<p>' . __('Select one or more form groups and one or more assessments. A ZIP file will be downloaded containing one CSV per combination.') . '</p>';
    ?>

    <form method="post" action="<?= htmlspecialchars($processURL) ?>">
        <input type="hidden" name="address" value="<?= htmlspecialchars($session->get('address')) ?>">
        <input type="hidden" name="csrftoken" value="<?= htmlspecialchars($tokenHandler->getCSRF()) ?>">
        <input type="hidden" name="nonce" value="<?= htmlspecialchars($tokenHandler->getNonce()) ?>">
        <input type="hidden" name="gibbonSchoolYearID" value="<?= htmlspecialchars($gibbonSchoolYearID) ?>">

        <div style="display: flex; gap: 32px; flex-wrap: wrap; margin-bottom: 24px;">

            <!-- Form Groups -->
            <div style="flex: 1; min-width: 260px;">
                <h3 style="margin-bottom: 8px;"><?= __('Form Groups') ?></h3>
                <div style="margin-bottom: 6px;">
                    <label style="cursor:pointer;">
                        <input type="checkbox" id="checkAllFormGroups"> <strong><?= __('Select All') ?></strong>
                    </label>
                </div>
                <div style="border: 1px solid #ccc; border-radius: 4px; padding: 10px; max-height: 320px; overflow-y: auto;">
                    <?php foreach ($formGroups as $id => $name): ?>
                    <div>
                        <label style="cursor:pointer; display:block; padding: 2px 0;">
                            <input type="checkbox" name="formGroupIDs[]" value="<?= htmlspecialchars($id) ?>" class="fg-check">
                            <?= htmlspecialchars($name) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Assessments -->
            <div style="flex: 1; min-width: 260px;">
                <h3 style="margin-bottom: 8px;"><?= __('Assessments') ?></h3>
                <div style="margin-bottom: 6px;">
                    <label style="cursor:pointer;">
                        <input type="checkbox" id="checkAllAssessments"> <strong><?= __('Select All') ?></strong>
                    </label>
                </div>
                <div style="border: 1px solid #ccc; border-radius: 4px; padding: 10px; max-height: 320px; overflow-y: auto;">
                    <?php foreach ($assessments as $name => $label): ?>
                    <div>
                        <label style="cursor:pointer; display:block; padding: 2px 0;">
                            <input type="checkbox" name="assessmentNames[]" value="<?= htmlspecialchars($name) ?>" class="as-check">
                            <?= htmlspecialchars($label) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <div>
            <button type="submit" class="button" style="font-size: 1em; padding: 10px 24px;">
                <?= __('Download ZIP') ?>
            </button>
            <span style="margin-left: 12px; color: #666; font-size: 0.9em;">
                <?= __('One CSV per form group + assessment combination.') ?>
            </span>
        </div>
    </form>

    <script>
    document.getElementById('checkAllFormGroups').addEventListener('change', function() {
        document.querySelectorAll('.fg-check').forEach(function(cb) { cb.checked = this.checked; }, this);
    });
    document.getElementById('checkAllAssessments').addEventListener('change', function() {
        document.querySelectorAll('.as-check').forEach(function(cb) { cb.checked = this.checked; }, this);
    });
    </script>

    <?php
}

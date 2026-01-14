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

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\Reports\Domain\ReportGateway;
use Gibbon\Forms\Prefab\BulkActionForm;
use Gibbon\Module\Reports\Contexts\ContextFactory;
use Gibbon\Module\Reports\Domain\ReportTemplateGateway;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\Reports\Domain\ReportArchiveEntryGateway;

if (isActionAccessible($guid, $connection2, '/modules/Reports/reports_generate_single.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonReportID = $_GET['gibbonReportID'] ?? '';
    $gibbonYearGroupID = $_GET['gibbonYearGroupID'] ?? '';
    $contextData = $_GET['contextData'] ?? '';
    $search = $_GET['search'] ?? '';

    $page->breadcrumbs
        ->add(__('Generate Reports'), 'reports_generate.php')
        ->add(__('Run'), 'reports_generate_batch.php', ['gibbonReportID' => $gibbonReportID])
        ->add(__('Single'));


    $reportGateway = $container->get(ReportGateway::class);
    $reportArchiveEntryGateway = $container->get(ReportArchiveEntryGateway::class);

    $report = $reportGateway->getByID($gibbonReportID);
    $templateData = $container->get(ReportTemplateGateway::class)->getByID($report['gibbonReportTemplateID'] ?? '');

    if (empty($gibbonReportID) || empty($report)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $context = ContextFactory::create($templateData['context']);

    $ids = $context->getIdentifiers($pdo, $report['gibbonReportID'], $contextData, true);
    $ids = array_map(function ($report) use ($gibbonReportID, &$reportArchiveEntryGateway) {
        $report['archive'] = $reportArchiveEntryGateway->getRecentArchiveEntryByReport($gibbonReportID, 'Single', $report['gibbonPersonID'], 'Staff', true, true);
        return $report;
    }, $ids);

    // FORM
    $form = BulkActionForm::create('bulkAction', $session->get('absoluteURL').'/modules/Reports/reports_generate_singleProcess.php');

    $form->addHiddenValue('gibbonReportID', $gibbonReportID);
    $form->addHiddenValue('contextData', $contextData);
    $form->addHiddenValue('search', $search);

    $bulkActions = array(
        'Generate' => __('Generate'),
        'Delete' => __('Delete'),
        'BulkDownload' => __('BulkDownload'),
    );

    $col = $form->createBulkActionColumn($bulkActions);
        $col->addSelect('status')
            ->fromArray(['Draft' => __('Draft'), 'Final' => __('Final')])
            ->required()
            ->setClass('status w-32');
        $col->addSubmit(__('Go'));

    $form->toggleVisibilityByClass('status')->onSelect('action')->when('Generate');

    // Data TABLE
    $table = $form->addRow()->addDataTable('reportsGenerate', $reportGateway->newQueryCriteria(true))->withData(new DataSet($ids));

    $table->addMetaData('bulkActions', $col);

    if (!empty(array_filter(array_column($ids, 'formGroup')))) {
        $table->addColumn('formGroup', __('Form Group'))
            ->notSortable()
            ->width('10%')
            ->format(function($values) {
                return $values['formGroup'] ?? __('Unknown');
            });
    }

    $table->addColumn('name', __('Name'))->notSortable()->format($context->getFormatter());

    $table->addColumn('timestamp', __('Last Created'))
        ->notSortable()
        ->format(function ($report) use ($gibbonReportID, &$reportArchiveEntryGateway) {
            if ($report['archive']) {
                $tag = '<span class="tag ml-2 '.($report['archive']['status'] == 'Final' ? 'success' : 'dull').'">'.__($report['archive']['status']).'</span>';
                $title = Format::dateTimeReadable($report['archive']['timestampModified']);
                $url = './modules/Reports/archive_byStudent_download.php?gibbonReportArchiveEntryID='.$report['archive']['gibbonReportArchiveEntryID'].'&gibbonPersonID='.$report['gibbonPersonID'];
                return Format::link($url, $title).$tag;
            }

            return '';
        });

    $debugMode = $container->get(SettingGateway::class)->getSettingByScope('Reports', 'debugMode');

    $table->addActionColumn()
        ->addParam('gibbonReportID', $gibbonReportID)
        ->format(function ($report, $actions) use ($debugMode) {
            if ($debugMode == 'Y') {
                $actions->addAction('inspect', __('Inspect'))
                        ->setIcon('search')
                        ->modalWindow('900', '550')
                        ->addParam('gibbonStudentEnrolmentID', $report['gibbonStudentEnrolmentID'] ?? '')
                        ->setURL('/modules/Reports/reports_generate_singleDebug.php');
            }

            if ($report['archive']) {
                $actions->addAction('view', __('View'))
                        ->directLink()
                        ->addParam('action', 'view')
                        ->addParam('gibbonPersonID', $report['gibbonPersonID'] ?? '')
                        ->addParam('gibbonReportArchiveEntryID', $report['archive']['gibbonReportArchiveEntryID'] ?? '')
                        ->setURL('/modules/Reports/archive_byStudent_download.php');

                $actions->addAction('download', __('Download'))
                        ->directLink()
                        ->setIcon('download')
                        ->addParam('gibbonPersonID', $report['gibbonPersonID'] ?? '')
                        ->addParam('gibbonReportArchiveEntryID', $report['archive']['gibbonReportArchiveEntryID'] ?? '')
                        ->setURL('/modules/Reports/archive_byStudent_download.php');
            }
        });

    $table->addCheckboxColumn('identifier', 'gibbonStudentEnrolmentID');


    echo $form->getOutput();

    // Add JavaScript to handle BulkDownload action using hidden iframe
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('bulkAction');
        if (form) {
            form.addEventListener('submit', function(e) {
                var actionSelect = form.querySelector('select[name="action"]');
                if (actionSelect && actionSelect.value === 'BulkDownload') {
                    e.preventDefault(); // Prevent normal form submission
                    e.stopPropagation(); // Stop event from triggering other handlers

                    // Create hidden iframe for download
                    var iframeName = 'downloadFrame_' + Date.now();
                    var iframe = document.createElement('iframe');
                    iframe.name = iframeName;
                    iframe.style.display = 'none';
                    document.body.appendChild(iframe);

                    // Set form to submit to iframe
                    form.setAttribute('target', iframeName);

                    // Submit the form to the hidden iframe
                    form.submit();

                    // Reset form target
                    setTimeout(function() {
                        form.removeAttribute('target');
                    }, 100);

                    // Refresh page after download to clear loading state and show confirmation
                    setTimeout(function() {
                        // Add success message to URL and reload
                        var currentUrl = window.location.href;
                        var separator = currentUrl.indexOf('?') > -1 ? '&' : '?';
                        // Remove any existing return parameter
                        currentUrl = currentUrl.replace(/[&?]return=[^&]*/g, '');
                        window.location.href = currentUrl + separator + 'return=success1';
                    }, 1500); // Wait 1.5 seconds for download to start

                    return false;
                }
                // For other actions (Generate, Delete), allow normal form submission
            });
        }
    });
    </script>
    <?php

    // Show download success message if redirected after bulk download
    if (isset($_GET['return']) && $_GET['return'] === 'success1') {
        echo '<div class="success" style="padding: 15px; margin: 15px 0; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; font-size: 14px;">';
        echo '<strong>✓ Download Complete!</strong> Your ZIP file has been downloaded. Check your downloads folder.';
        echo '</div>';
    }
}

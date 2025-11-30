<?php

//Module includes
require_once './modules/House Points/moduleFunctions.php';

use Gibbon\Module\HousePoints\Domain\HousePointHouseGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;



/*
if (isActionAccessible($guid, $connection2, '/modules/House Points/overall.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo 'You do not have access to this action.';
    echo '</div>';

} else {
*/
    require_once $session->get('absolutePath').'/modules/House Points/src/Domain/HousePointHouseGateway.php';
    global $container;

    $yearID = $session->get('gibbonSchoolYearID');
    $housePointHouseGateway = $container->get(HousePointHouseGateway::class);
    // POINT TOTALS DATATABLE
    $pointsList = $housePointHouseGateway->selectAllPoints($yearID);
    

    $gridRenderer = new GridView($container->get('twig'));
    $totalsTable = $container->get(DataTable::class)->setRenderer($gridRenderer);
    $totalsTable->setTitle(__('Overall House Points'));
    $totalsTable->addMetaData('hidePagination', true);
    $totalsTable->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/3 my-2 text-center');

    $totalsTable->addColumn('Crest')
        ->format(function ($row) {
            $class = '';
            return Format::photo($row['houseLogo'], 'md', $class);
    });
    $totalsTable->addColumn('House')
        ->setClass('text-lg text-gray-600 leading-snug')
        ->format(function ($row) {
            return !empty($row['houseName']) ? $row['houseName'] : __('Unknown');
    });
    $totalsTable->addColumn('Total')
        ->setClass('text-base text-gray-600 leading-snug')
        ->format(function ($row) {
            return !empty($row['total']) ? $row['total'] : '0';
    });

    $hook = $totalsTable->render($pointsList->toDataSet());

    // EVENT POINTS DATATABLE
    $eventPointsList = $housePointHouseGateway->selectEventsList($yearID);
    $eventPointsList = parseEventsList($eventPointsList);

    $eventsTable = $container->get(DataTable::class);
    $eventsTable->setTitle(__('House Points By Event'));
    $eventsTable->addMetaData('hidePagination', true);
    $eventsTable->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');
    
    $eventsTable->addColumn('reason', 'Event');
    foreach($eventPointsList['houses'] as $house) {
        $eventsTable->addColumn($house, $house);
    }

    // Re-format NULL cell values to '0'
    foreach($eventPointsList['houses'] as $house) {
        $eventsTable->getColumn($house)->format(function ($values) use ($house) { 
            return !empty($values[$house]) ? $values[$house] : '0';
        });
    }

    $hook .= $eventsTable->render($eventPointsList['events']);
    return $hook;
//}

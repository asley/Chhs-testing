<?php
include  "../../gibbon.php";

$categoryID = isset($_POST['categoryID'])? $_POST['categoryID'] : '';

if (empty($categoryID)) {
    die('');
}

$data = array('categoryID' => $categoryID);
$sql = "SELECT categoryPresets FROM hpCategory WHERE categoryID=:categoryID";
$result = $pdo->executeQuery($data, $sql);

if (!$result || $result->rowCount() == 0) {
    die('');
} else {
    $presets = array();
    $presetsText = $result->fetchColumn(0);
    if (empty($presetsText)) {
        die('');
    }

    $presetGroups = array_map('trim', explode(',', $presetsText));
    foreach ($presetGroups as $index => $preset) {
        $presetValues = array_map('trim', explode(':', $preset));
        list($name, $points) = array_pad($presetValues, 2, false);
        $presets[$points.chr(($index+65))] = ($name != $points)? $name.': '.$points.' points' : $points.' points';
    }

    die(json_encode($presets));
}

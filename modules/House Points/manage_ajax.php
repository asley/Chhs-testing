<?php
include  '../../gibbon.php';

$dbh = $connection2;
$action = $_POST['action'];

switch ($action) {
    case 'deleteItemStudent':
        $hpID = $_POST['hpID'];

        $data = array(
            'hpID' => $hpID
        );
        $sql = "DELETE FROM hpPointStudent
            WHERE hpPointStudent.hpID = :hpID";
        $rs = $dbh->prepare($sql);
        echo $rs->execute($data);
        break;
    
    case 'deleteItemHouse':
        $hpID = $_POST['hpID'];

        $data = array(
            'hpID' => $hpID
        );
        $sql = "DELETE FROM hpPointHouse
            WHERE hpPointHouse.hpID = :hpID";
        $rs = $dbh->prepare($sql);
        echo $rs->execute($data);
        break;
}

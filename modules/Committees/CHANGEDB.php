<?php
//USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

//v1.0.00
$sql[$count][0] = "1.0.00";
$sql[$count][1] = "-- First version, nothing to update";

//v1.0.01
$sql[$count][0] = "1.0.01";
$sql[$count][1] = "ALTER TABLE `committeesRole` CHANGE `type` `type` ENUM('Chair','Admin','Member') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Member';end";

//v1.0.02
$sql[$count][0] = "1.0.02";
$sql[$count][1] = "";

//v1.1.00
$sql[$count][0] = "1.1.00";
$sql[$count][1] = "";

//v1.2.00
$sql[$count][0] = "1.2.00";
$sql[$count][1] = "";

//v1.2.01
$sql[$count][0] = "1.2.01";
$sql[$count][1] = "";

//v1.2.02
$sql[$count][0] = "1.2.02";
$sql[$count][1] = "";

//v1.3.00
$sql[$count][0] = "1.3.00";
$sql[$count][1] = "";

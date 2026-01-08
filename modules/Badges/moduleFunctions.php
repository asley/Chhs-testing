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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

function getBadges($connection2, $guid, $gibbonPersonID)
{
    global $session;

    $output = '';

    // Add modal HTML for badge preview
    $output .= '<div id="badgeModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.8);">';
    $output .= '  <div style="position:relative; background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:80%; max-width:800px; border-radius:8px;">';
    $output .= '    <span id="closeBadgeModal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>';
    $output .= '    <div id="badgeModalContent" style="text-align:center; padding:20px;"></div>';
    $output .= '  </div>';
    $output .= '</div>';

    // Add JavaScript for modal functionality
    $output .= '<script>';
    $output .= 'function showBadgeModal(imageSrc, badgeName, category, comment, date) {';
    $output .= '  var modal = document.getElementById("badgeModal");';
    $output .= '  var modalContent = document.getElementById("badgeModalContent");';
    $output .= '  var html = "<img src=\"" + imageSrc + "\" style=\"max-width:100%; max-height:500px; margin-bottom:20px;\"><br>";';
    $output .= '  html += "<h2>" + badgeName + "</h2>";';
    $output .= '  if (category) html += "<p style=\"font-style:italic; color:#666;\">" + category + "</p>";';
    $output .= '  if (comment) html += "<p>" + comment + "</p>";';
    $output .= '  if (date) html += "<p style=\"color:#999; font-size:0.9em;\">Awarded: " + date + "</p>";';
    $output .= '  html += "<a href=\"" + imageSrc + "\" download=\"" + badgeName.replace(/[^a-z0-9]/gi, "_") + ".png\" style=\"display:inline-block; margin-top:20px; padding:10px 20px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:5px; font-weight:bold;\">Download Badge</a>";';
    $output .= '  modalContent.innerHTML = html;';
    $output .= '  modal.style.display = "block";';
    $output .= '}';
    $output .= 'document.addEventListener("DOMContentLoaded", function() {';
    $output .= '  var closeBtn = document.getElementById("closeBadgeModal");';
    $output .= '  var modal = document.getElementById("badgeModal");';
    $output .= '  if (closeBtn) closeBtn.onclick = function() { modal.style.display = "none"; };';
    $output .= '  window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } };';
    $output .= '});';
    $output .= '</script>';

    //Licenses
    try {
        $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT badgesBadgeStudent.*, badgesBadge.name AS award, badgesBadge.logo AS logo, badgesBadge.category AS category, gibbonSchoolYear.name AS year FROM badgesBadgeStudent JOIN badgesBadge ON (badgesBadgeStudent.badgesBadgeID=badgesBadge.badgesBadgeID) JOIN gibbonSchoolYear ON (badgesBadgeStudent.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID) WHERE gibbonPersonID=:gibbonPersonID AND license=\'Y\' ORDER BY gibbonSchoolYear.sequenceNumber DESC, date DESC';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        echo "<div class='error'>".$e->getMessage().'</div>';
    }
    if ($result->rowCount() > 0) {
        $columns = 3;
        $count = 0;

        $output .= '<h3>';
        $output .= __m('Licenses');
        $output .= '</h3>';

        while ($row = $result->fetch()) {
            // $awardYears[$row['year']][1] = array("$innerCount" => $row['award']);
            // $awardYears[$row['year']][2] = array("$innerCount" => $row['logo']);
            // $awardYears[$row['year']][3] = array("$innerCount" => $row['category']);
            // $awardYears[$row['year']][4] = array("$innerCount" => $row['comment']);

            //Spit out licenses
            if ($count % $columns == 0) {
                if ($count == 0) {
                    $output .= "<table class='margin-bottom: 10px; smallIntBorder' cellspacing='0' style='width:100%'>";
                }
                $output .= '<tr>';
            }

            $output .= "<td style='padding-top: 15px!important; padding-bottom: 15px!important; width:33%; text-align: center; vertical-align: top'>";

            $licenseImage = '';
            if ($row['logo'] != '') {
                $licenseImage = $session->get('absoluteURL').'/'.$row['logo'];
            } else {
                $licenseImage = $session->get('absoluteURL').'/themes/'.$session->get('gibbonThemeName')."/img/anonymous_240_square.jpg";
            }

            // Make license badge clickable
            $badgeName = htmlspecialchars($row['award'], ENT_QUOTES);
            $category = htmlspecialchars($row['category'], ENT_QUOTES);
            $comment = !empty($row['comment']) ? htmlspecialchars($row['comment'], ENT_QUOTES) : '';
            $date = htmlspecialchars($row['year'], ENT_QUOTES);

            $output .= "<img onclick='showBadgeModal(\"$licenseImage\", \"$badgeName\", \"$category\", \"$comment\", \"$date\")' style='margin-bottom: 20px; max-width: 150px; cursor: pointer; transition: transform 0.2s;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"' src='$licenseImage' title='Click to enlarge and download'/><br/>";

            $output .= '<b>'.$row['award'].'</b><br/>';
            $output .= '<span class=\'emphasis small\'>'.$row['category'].'</span><br/>';
            if (!empty($row['comment'])) {
                $output .= '<span class=\'emphasis small\'>'.$row['comment'].'</span><br/>';
            }
            $output .= '</td>';

            if ($count % $columns == ($columns - 1)) {
                $output .= '</tr>';
            }
            ++$count;
        }

        if ($count % $columns != 0) {
            for ($i = 0;$i < $columns - ($count % $columns);++$i) {
                $output .= '<td></td>';
            }
            $output .= '</tr>';
        }

        $output .= '</table>';
    }

    //Badges
    try {
        $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT badgesBadgeStudent.*, badgesBadge.name AS award, badgesBadge.logo AS logo, badgesBadge.category AS category, gibbonSchoolYear.name AS year FROM badgesBadgeStudent JOIN badgesBadge ON (badgesBadgeStudent.badgesBadgeID=badgesBadge.badgesBadgeID) JOIN gibbonSchoolYear ON (badgesBadgeStudent.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID) WHERE gibbonPersonID=:gibbonPersonID ORDER BY gibbonSchoolYear.sequenceNumber DESC, date DESC';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        echo "<div class='error'>".$e->getMessage().'</div>';
    }
    if ($result->rowCount() < 1) {
        $output .= "<div class='warning'>";
        $output .= __('There are no records to display.');
        $output .= '</div>';
    } else {
        //Prep array of awards
        $awardYears = array();
        $innerCount = 0;
        while ($row = $result->fetch()) {
            $awardYears[$row['year']][0] = $row['year'];
            if (isset($awardYears[$row['year']][1]) == false) { //No data, so start adding data
                $innerCount = 0;
                $awardYears[$row['year']][1] = array("$innerCount" => $row['award']);
                $awardYears[$row['year']][2] = array("$innerCount" => $row['logo']);
                $awardYears[$row['year']][3] = array("$innerCount" => $row['category']);
                $awardYears[$row['year']][4] = array("$innerCount" => $row['comment']);
                ++$innerCount;
            } else { //Already data, so start appending
                $awardYears[$row['year']][1][$innerCount] = $row['award'];
                $awardYears[$row['year']][2][$innerCount] = $row['logo'];
                $awardYears[$row['year']][3][$innerCount] = $row['category'];
                $awardYears[$row['year']][4][$innerCount] = $row['comment'];
                ++$innerCount;
            }
        }

        //Spit out awards from array
        $columns = 3;
        foreach ($awardYears as $awardYear) { //Spit out years
            $output .= '<h3>';
            $output .= $awardYear[0];
            $output .= '</h3>';

            $count = 0;
            foreach ($awardYear[1] as $awards) {
                if ($count % $columns == 0) {
                    if ($count == 0) {
                        $output .= "<table class='margin-bottom: 10px; smallIntBorder' cellspacing='0' style='width:100%'>";
                    }
                    $output .= '<tr>';
                }

                $output .= "<td style='padding-top: 15px!important; padding-bottom: 15px!important; width:33%; text-align: center; vertical-align: top'>";

                $badgeImage = '';
                if ($awardYear[2][$count] != '') {
                    $badgeImage = $session->get('absoluteURL').'/'.$awardYear[2][$count];
                } else {
                    $badgeImage = $session->get('absoluteURL').'/themes/'.$session->get('gibbonThemeName')."/img/anonymous_240_square.jpg";
                }

                // Make badge clickable with larger view and download option
                $badgeName = htmlspecialchars($awards, ENT_QUOTES);
                $category = htmlspecialchars($awardYear[3][$count], ENT_QUOTES);
                $comment = array_key_exists($count,$awardYear[4]) ? htmlspecialchars($awardYear[4][$count], ENT_QUOTES) : '';
                $date = htmlspecialchars($awardYear[0], ENT_QUOTES);

                $output .= "<img onclick='showBadgeModal(\"$badgeImage\", \"$badgeName\", \"$category\", \"$comment\", \"$date\")' style='margin-bottom: 20px; max-width: 150px; cursor: pointer; transition: transform 0.2s;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"' src='$badgeImage' title='Click to enlarge and download'/><br/>";

                $output .= '<b>'.$awards.'</b><br/>';
                $output .= '<span class=\'emphasis small\'>'.$awardYear[3][$count].'</span><br/>';
                if(array_key_exists($count,$awardYear[4]))
                {
                    $output .= '<span class=\'emphasis small\'>'.$awardYear[4][$count].'</span><br/>';
                }
                $output .= '</td>';

                if ($count % $columns == ($columns - 1)) {
                    $output .= '</tr>';
                }
                ++$count;
            }

            if ($count % $columns != 0) {
                for ($i = 0;$i < $columns - ($count % $columns);++$i) {
                    $output .= '<td></td>';
                }
                $output .= '</tr>';
            }

            $output .= '</table>';
        }
    }

    return $output;
}

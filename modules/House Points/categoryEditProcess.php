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
use Gibbon\Module\HousePoints\Domain\HousePointCategoryGateway;

require_once '../../gibbon.php';

if (!$session->has('gibbonPersonID') || !$session->has('gibbonRoleIDPrimary')
    || !isActionAccessible($guid, $connection2, '/modules/House Points/category.php')) {
    die(__('Your request failed because you do not have access to this action.'));
} else {
    $URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module') . '/category.php';
    
    $categoryID = $_POST['categoryID'] ?? null;
    $categoryName = $_POST['categoryName'] ?? null;
    $categoryType = $_POST['categoryType'] ?? 'House';
    $categoryPresets = $_POST['categoryPresets'] ?? null;


    if (($categoryID || $categoryName || $categoryType) != NULL) {
        
        $data = [
            'categoryName' => $categoryName,
            'categoryType' => $categoryType,
            'categoryPresets' => $categoryPresets,
        ];
        
        $housePointCategoryGateway = $container->get(HousePointCategoryGateway::class);
        $housePointCategoryID = $housePointCategoryGateway->update($categoryID, $data);
        if ($housePointCategoryID === false) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }
        //Success 0
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit();
    
    } else {
        $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
    }
  
}

?>

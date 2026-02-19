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

function getJussExamBridgeSetting(SettingGateway $settingGateway, $name, $default = '')
{
    $value = $settingGateway->getSettingByScope('juss-examBridge', $name);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function getJussExamBridgeMaskedSecret($secret)
{
    if (empty($secret)) {
        return __('Not configured');
    }

    if (mb_strlen($secret) <= 6) {
        return str_repeat('*', mb_strlen($secret));
    }

    return mb_substr($secret, 0, 2) . str_repeat('*', mb_strlen($secret) - 4) . mb_substr($secret, -2);
}

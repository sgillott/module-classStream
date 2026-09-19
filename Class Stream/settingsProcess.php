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

use Gibbon\Data\Validator;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/settings.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/settings.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Each value is checked against its own allowed list; anything else is refused as a whole.
$allowed = [
    'defaultStudentAccess' => ['Post', 'Comment', 'None'],
    'showHomework'         => ['Y', 'N'],
    'showLessons'          => ['Y', 'N'],
    'parentView'           => ['Own child', 'All redacted', 'None'],
    'notifyOnPost'         => ['Y', 'N'],
];

foreach ($allowed as $name => $values) {
    if (!in_array($_POST[$name] ?? '', $values, true)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
}

$settingGateway = $container->get(SettingGateway::class);
$partialFail = false;

foreach (array_keys($allowed) as $name) {
    $updated = $settingGateway->updateSettingByScope('Class Stream', $name, $_POST[$name]);
    $partialFail = $partialFail || !$updated;
}

$URL .= $partialFail ? '&return=warning1' : '&return=success0';
header("Location: {$URL}");

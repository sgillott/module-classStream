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
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\MuteGateway;
use Gibbon\Module\ClassStream\Domain\PostGateway;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/stream_people.php&gibbonCourseClassID='.$gibbonCourseClassID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_people.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canManage']) {
    header("Location: {$URL}&return=error0");
    exit;
}

// Only current students of this class can be muted. IDs are compared as integers because the
// posted value and the zerofilled column render with different padding.
$students = $container->get(PostGateway::class)->selectStudentsByClass($gibbonCourseClassID)->fetchAll();
$studentIDs = array_map('intval', array_column($students, 'gibbonPersonID'));

$requested = array_map('intval', (array) ($_POST['muted'] ?? []));
$requested = array_intersect($requested, $studentIDs);

$muteGateway = $container->get(MuteGateway::class);
$current = array_map('intval', array_keys($muteGateway->selectMutedByClass($gibbonCourseClassID)->fetchKeyPair()));

$partialFail = false;
$gibbonPersonIDMutedBy = $session->get('gibbonPersonID');

foreach (array_diff($requested, $current) as $gibbonPersonID) {
    $partialFail = $muteGateway->mute($gibbonCourseClassID, $gibbonPersonID, $gibbonPersonIDMutedBy) === false || $partialFail;
}

foreach (array_diff($current, $requested) as $gibbonPersonID) {
    $partialFail = !$muteGateway->unmute($gibbonCourseClassID, $gibbonPersonID) || $partialFail;
}

header("Location: {$URL}".($partialFail ? '&return=warning1' : '&return=success0'));

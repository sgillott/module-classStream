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

use Gibbon\FileUploader;
use Gibbon\Data\Validator;
use Gibbon\Module\ClassStream\Theme;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\LinkGateway;
use Gibbon\Module\ClassStream\Domain\ClassSettingsGateway;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);
$URLBack = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/stream_customise.php&gibbonCourseClassID='.$gibbonCourseClassID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_customise.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canManage']) {
    header("Location: {$URL}&return=error0");
    exit;
}

$colour = $_POST['colour'] ?? '';
$studentAccess = $_POST['studentAccess'] ?? '';
$plannerDisplay = $_POST['plannerDisplay'] ?? '';

if (!isset(Theme::PALETTE[$colour])
    || !in_array($studentAccess, ['', 'Post', 'Comment', 'None'], true)
    || !in_array($plannerDisplay, ['Details', 'Condensed', 'Hidden'], true)) {
    header("Location: {$URLBack}&return=error1");
    exit;
}

$data = [
    'colour'         => $colour,
    'studentAccess'  => $studentAccess != '' ? $studentAccess : null,
    'plannerDisplay' => $plannerDisplay,
];

// Header image: keep the current one unless a new file arrives, or the form's own delete control
// has cleared the hidden headerImage field that echoes the current path.
$currentImage = $access['settings']['headerImage'];
$partialFail = false;

if (!empty($_FILES['headerImage']['tmp_name'])) {
    $fileUploader = $container->get(FileUploader::class);
    $fileUploader->setFileExtensions(['jpg', 'jpeg', 'gif', 'png', 'webp']);
    $newImage = $fileUploader->uploadAndResizeImage($_FILES['headerImage'], 'classStreamHeader_'.$gibbonCourseClassID, 1600, 85);

    if (!empty($newImage)) {
        $data['headerImage'] = $newImage;
    } else {
        $partialFail = true;
    }
} elseif (!empty($currentImage) && empty($_POST['headerImage'])) {
    $data['headerImage'] = null;
}

$saved = $container->get(ClassSettingsGateway::class)->saveSettingsByClass($gibbonCourseClassID, $data);

if ($saved === false) {
    header("Location: {$URLBack}&return=error2");
    exit;
}

// The old header file is only unlinked once the new row is safely saved.
if (array_key_exists('headerImage', $data) && !empty($currentImage) && $currentImage != $data['headerImage']) {
    $path = $session->get('absolutePath').'/'.$currentImage;
    if (strpos($currentImage, 'uploads/') === 0 && is_file($path)) {
        unlink($path);
    }
}

// LINKED CLASSES
// Only classes the person may post in are accepted; anything else posted is dropped. Outgoing
// links this class no longer wants are removed; a sync that is taken away also removes the
// link back. 1-way syncs other classes set towards this one are theirs and stay untouched.
$linkable = classStreamLinkableClasses($container, $session, $scope, $access['class']['gibbonSchoolYearID']);
unset($linkable[$gibbonCourseClassID]);
$allowed = array_map('strval', array_keys($linkable));

$clean = function ($list) use ($allowed) {
    $ids = array_map('strval', (array) $list);
    return array_values(array_intersect(array_unique(array_filter($ids)), $allowed));
};
$sync = $clean($_POST['sync'] ?? []);
$mirror = array_values(array_diff($clean($_POST['mirror'] ?? []), $sync));

$linkGateway = $container->get(LinkGateway::class);
$targets = array_keys($linkGateway->selectTargetsByClass($gibbonCourseClassID)->fetchKeyPair());
$sources = array_keys($linkGateway->selectSourcesByClass($gibbonCourseClassID)->fetchKeyPair());
$wasSynced = array_intersect($targets, $sources);
$wanted = array_merge($mirror, $sync);
$gibbonPersonID = $session->get('gibbonPersonID');

foreach ($targets as $target) {
    if (!in_array((string) $target, $wanted)) {
        $linkGateway->unlink($gibbonCourseClassID, $target);
        if (in_array($target, $wasSynced)) {
            $linkGateway->unlink($target, $gibbonCourseClassID);
        }
    }
}
foreach ($wanted as $target) {
    $linkGateway->link($gibbonCourseClassID, $target, $gibbonPersonID);
}
foreach ($sync as $target) {
    $linkGateway->link($target, $gibbonCourseClassID, $gibbonPersonID);
}
foreach ($wasSynced as $target) {
    if (in_array((string) $target, $mirror)) {
        $linkGateway->unlink($target, $gibbonCourseClassID);
    }
}

header("Location: {$URL}".($partialFail ? '&return=warning1' : '&return=success0'));

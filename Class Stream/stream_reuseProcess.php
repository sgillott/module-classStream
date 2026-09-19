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
use Gibbon\Module\ClassStream\Domain\PostGateway;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$sourceClassID = $_POST['source'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);
$URLBack = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/stream_reuse.php&gibbonCourseClassID='.$gibbonCourseClassID.'&source='.$sourceClassID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_reuse.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canManage']) {
    header("Location: {$URL}&return=error0");
    exit;
}

// The source must be a class the person has taught; the posts must belong to it.
$postGateway = $container->get(PostGateway::class);
$taught = array_column($postGateway->selectTaughtClassesByPerson($session->get('gibbonPersonID'))->fetchAll(), 'gibbonCourseClassID');
if ($sourceClassID == $gibbonCourseClassID || !in_array($sourceClassID, $taught)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$selected = array_unique(array_filter((array) ($_POST['posts'] ?? [])));
if (empty($selected)) {
    header("Location: {$URLBack}&return=error1");
    exit;
}

$now = date('Y-m-d H:i:s');
$overrides = ['gibbonPersonID' => $session->get('gibbonPersonID'), 'pinned' => 'N', 'timestamp' => $now, 'timestampModified' => $now, 'timestampPublished' => null, 'notified' => 'N'];
$copied = 0;

// Oldest first, so the copies keep the source's order on the new stream.
foreach (array_reverse($selected) as $classStreamPostID) {
    $post = $postGateway->getPostByID($classStreamPostID);
    if (empty($post) || $post['gibbonCourseClassID'] != $sourceClassID) continue;

    // Copies arrive as drafts: the teacher publishes or schedules each one from the Drafts page.
    $copyID = classStreamCopyPost($container, $post, $gibbonCourseClassID, $overrides);
    if (!empty($copyID)) {
        $copied++;
    }
}

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream/stream_drafts.php&gibbonCourseClassID='.$gibbonCourseClassID;
header("Location: {$URL}".($copied == count($selected) ? '&return=success0' : '&return=warning1'));

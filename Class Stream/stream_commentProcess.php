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
use Gibbon\Module\ClassStream\Domain\CommentGateway;
use Gibbon\Module\ClassStream\Domain\PlannerItemGateway;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$targetType = $_POST['targetType'] ?? '';
$targetID = $_POST['targetID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);
$fragment = '#'.($targetType == 'planner' ? 'planner' : 'post').$targetID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_view.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canComment']) {
    header("Location: {$URL}&return=error0");
    exit;
}

// The target must be a post of this class, or a Planner lesson of this class that the person's
// role may see (the Planner's own viewable flags), so nobody comments on a lesson hidden from them.
if ($targetType == 'post') {
    $post = $container->get(PostGateway::class)->getPostByID($targetID);
    $targetValid = !empty($post) && $post['gibbonCourseClassID'] == $gibbonCourseClassID;
    $foreignTable = CommentGateway::TARGET_POST;
} elseif ($targetType == 'planner') {
    $item = $container->get(PlannerItemGateway::class)->getItemByID($targetID);
    $targetValid = !empty($item) && $item['gibbonCourseClassID'] == $gibbonCourseClassID && PlannerItemGateway::isVisibleTo($item, $access['viewingAs']);
    $foreignTable = CommentGateway::TARGET_PLANNER;
} else {
    $targetValid = false;
}

if (!$targetValid) {
    header("Location: {$URL}&return=error0");
    exit;
}

// Comments are plain text. The sanitizer above has already stripped tags; what is left is
// trimmed and capped at the same length the textarea allows.
$comment = trim($_POST['comment'] ?? '');
if ($comment == '') {
    header("Location: {$URL}&return=error1{$fragment}");
    exit;
}
$comment = mb_substr($comment, 0, 2000);

$gibbonModuleID = classStreamModuleID($container);
$inserted = $container->get(CommentGateway::class)->insertComment($foreignTable, $targetID, $gibbonModuleID, $session->get('gibbonPersonID'), $comment);

header("Location: {$URL}".($inserted ? '&return=success0' : '&return=error2').$fragment);

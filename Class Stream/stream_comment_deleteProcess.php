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
$gibbonDiscussionID = $_POST['gibbonDiscussionID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_view.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$streamAccess = $container->get(StreamAccess::class);
$access = $scope !== false ? $streamAccess->forClass($scope, $gibbonCourseClassID) : null;

$gibbonModuleID = classStreamModuleID($container);
$commentGateway = $container->get(CommentGateway::class);
$comment = $commentGateway->getCommentByID($gibbonDiscussionID, $gibbonModuleID);

// The comment's target (a post or a Planner lesson) must belong to this class, and the person
// must be the comment's author or manage the class. The class check stops a comment being
// deleted through a class the person manages when its target lives in one they do not.
$targetClassID = null;
$fragment = '';
if (!empty($comment) && $comment['foreignTable'] == CommentGateway::TARGET_POST) {
    $post = $container->get(PostGateway::class)->getPostByID($comment['foreignTableID']);
    $targetClassID = $post['gibbonCourseClassID'] ?? null;
    $fragment = '#post'.$comment['foreignTableID'];
} elseif (!empty($comment) && $comment['foreignTable'] == CommentGateway::TARGET_PLANNER) {
    $item = $container->get(PlannerItemGateway::class)->getItemByID($comment['foreignTableID']);
    $targetClassID = $item['gibbonCourseClassID'] ?? null;
    $fragment = '#planner'.$comment['foreignTableID'];
}

if (empty($access) || empty($comment) || empty($targetClassID) || $targetClassID != $gibbonCourseClassID || !$streamAccess->canDeleteComment($access, $comment)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$deleted = $commentGateway->delete($gibbonDiscussionID);

header("Location: {$URL}".($deleted ? '&return=success0' : '&return=error2').$fragment);

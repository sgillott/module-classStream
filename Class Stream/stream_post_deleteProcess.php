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
$classStreamPostID = $_POST['classStreamPostID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_view.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$streamAccess = $container->get(StreamAccess::class);
$access = $scope !== false ? $streamAccess->forClass($scope, $gibbonCourseClassID) : null;
$postGateway = $container->get(PostGateway::class);
$post = $postGateway->getPostByID($classStreamPostID);

if (empty($access) || empty($post) || $post['gibbonCourseClassID'] != $gibbonCourseClassID || !$streamAccess->canEditPost($access, $post)) {
    header("Location: {$URL}&return=error0");
    exit;
}

// A draft or scheduled post was deleted from the Drafts page: go back there.
if (empty($post['timestampPublished']) || $post['timestampPublished'] > date('Y-m-d H:i:s')) {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream/stream_drafts.php&gibbonCourseClassID='.$gibbonCourseClassID;
}

if (!classStreamDeletePost($container, $session, $post)) {
    header("Location: {$URL}&return=error2");
    exit;
}

header("Location: {$URL}&return=success0");

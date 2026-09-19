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

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$classStreamPostID = $_POST['classStreamPostID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);
$URLBack = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/stream_post_edit.php&gibbonCourseClassID='.$gibbonCourseClassID.'&classStreamPostID='.$classStreamPostID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_post_edit.php') == false) {
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

$body = $_POST['body'] ?? '';
$type = ($_POST['type'] ?? '') == 'Material' ? 'Material' : 'Announcement';
$title = $type == 'Material' ? mb_substr(trim($_POST['title'] ?? ''), 0, 120) : null;

if (trim(strip_tags($body, '<img><iframe>')) == '' || ($type == 'Material' && $title == '')) {
    header("Location: {$URLBack}&return=error1");
    exit;
}

$data = [
    'type'              => $type,
    'title'             => $title,
    'body'              => $body,
    'timestampModified' => date('Y-m-d H:i:s'),
];

// Publish state. "now" on an already published post keeps its original time; on a draft or a
// scheduled post it publishes at this moment. Anything else replaces the time as chosen.
$publish = $_POST['publish'] ?? 'now';
$wasLive = !empty($post['timestampPublished']) && $post['timestampPublished'] <= date('Y-m-d H:i:s');
if (!($publish == 'now' && $wasLive)) {
    $data['timestampPublished'] = classStreamPublishTime($publish, $_POST['publishDate'] ?? '', $_POST['publishTime'] ?? '');
    if (!$wasLive) $data['notified'] = 'N';
}
if ($access['canManage']) {
    $data['pinned'] = ($_POST['pinned'] ?? '') == 'Y' ? 'Y' : 'N';
}

if (!$postGateway->update($classStreamPostID, $data)) {
    header("Location: {$URLBack}&return=error2");
    exit;
}

$allSaved = classStreamSaveAttachments($container, $classStreamPostID, $_POST['links'] ?? '', $_FILES['attachments'] ?? []);
classStreamSyncCopies($container, $session, $classStreamPostID);
classStreamPublishDue($container, $session, $access['class']);

$updated = $postGateway->getPostByID($classStreamPostID);
if (empty($updated['timestampPublished']) || $updated['timestampPublished'] > date('Y-m-d H:i:s')) {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream/stream_drafts.php&gibbonCourseClassID='.$gibbonCourseClassID;
    header("Location: {$URL}".($allSaved ? '&return=success0' : '&return=warning1'));
    exit;
}

header("Location: {$URL}".($allSaved ? '&return=success0' : '&return=warning1').'#post'.$classStreamPostID);

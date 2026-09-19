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

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;

require_once __DIR__.'/moduleFunctions.php';

// The delete confirmation, opened in a modal from the Drafts table. DeleteForm carries every GET
// parameter across as a hidden field, so the process script gets the class and post IDs.
if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_post_delete.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$classStreamPostID = $_GET['classStreamPostID'] ?? '';
$streamAccess = $container->get(StreamAccess::class);
$access = $scope !== false ? $streamAccess->forClass($scope, $gibbonCourseClassID) : null;
$post = $container->get(PostGateway::class)->getPostByID($classStreamPostID);

if (empty($access) || empty($post) || $post['gibbonCourseClassID'] != $gibbonCourseClassID || !$streamAccess->canEditPost($access, $post)) {
    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    return;
}

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_post_deleteProcess.php');
echo $form->getOutput();

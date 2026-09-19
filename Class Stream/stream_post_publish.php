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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;

require_once __DIR__.'/moduleFunctions.php';

// "Publish now" confirmation, opened in a modal from the Drafts table. A plain link cannot carry
// the CSRF token and nonce a process script needs, so the click opens this small form instead.
if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_post_publish.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$classStreamPostID = $_GET['classStreamPostID'] ?? '';
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;
$post = $container->get(PostGateway::class)->getPostByID($classStreamPostID);

if (empty($access) || !$access['canManage'] || empty($post) || $post['gibbonCourseClassID'] != $gibbonCourseClassID) {
    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    return;
}

$excerpt = $post['type'] == 'Material' ? $post['title'] : Format::truncate(trim(strip_tags($post['body'])), 90);

$form = Form::createBlank('publishPost', $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_post_publishProcess.php');
$form->addClass('font-sans text-xs text-gray-700');
$form->addHiddenValue('address', $_GET['q'] ?? '');
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);
$form->addHiddenValue('classStreamPostID', $classStreamPostID);

$col = $form->addRow()->addColumn()->addClass('mb-4');
    $col->addContent(__m('Publish this post to the stream now?'))->wrap('<div class="font-bold text-base mb-2">', '</div>');
    $col->addContent(htmlspecialchars($excerpt))->wrap('<div class="mb-2">', '</div>');
    $col->addContent(__m('The class will be notified, and the post will be copied to any synced classes.'));

$form->addRow()->addClass('mt-6')->addConfirmSubmit(__m('Publish Now'));

echo $form->getOutput();

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
use Gibbon\Session\TokenHandler;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\PostAttachmentGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_post_edit.php') == false) {
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

$className = $access['class']['course'].'.'.$access['class']['class'];

$page->breadcrumbs
    ->add(__m('My Streams'), 'stream.php')
    ->add($className, 'stream_view.php', ['gibbonCourseClassID' => $gibbonCourseClassID])
    ->add(__m('Edit Post'));

$form = Form::create('postEdit', $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_post_editProcess.php');
$form->setTitle(__m('Edit Post'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);
$form->addHiddenValue('classStreamPostID', $classStreamPostID);

$row = $form->addRow();
    $row->addLabel('type', __m('Type'));
    $row->addSelect('type')->fromArray(['Announcement' => __m('Announcement'), 'Material' => __m('Material')])->selected($post['type'])->required();

$form->toggleVisibilityByClass('materialTitle')->onSelect('type')->when('Material');
$row = $form->addRow()->addClass('materialTitle');
    $row->addLabel('title', __m('Title'));
    $row->addTextField('title')->maxLength(120)->setValue($post['title'] ?? '');

$col = $form->addRow()->addColumn();
    $col->addLabel('body', __m('Post'));
    $col->addEditor('body', $guid)->setRows(10)->required()->setValue($post['body']);

// Existing attachments, each with its own remove button. The button posts to a different script
// than the form's own action, so it uses formaction and skips the form's validation.
$attachments = $container->get(PostAttachmentGateway::class)->selectAttachmentsByPost($classStreamPostID)->fetchAll();
if (!empty($attachments)) {
    $tokenHandler = $container->get(TokenHandler::class);
    $removeURL = $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_post_attachment_deleteProcess.php';

    $list = '<table class="smallIntBorder w-full">';
    foreach ($attachments as $attachment) {
        $href = $attachment['type'] == 'File' ? $session->get('absoluteURL').'/'.$attachment['location'] : $attachment['location'];
        $list .= '<tr><td class="text-xs uppercase text-gray-500 w-16">'.__m($attachment['type']).'</td>';
        $list .= '<td><a href="'.htmlspecialchars($href).'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($attachment['name']).'</a></td>';
        $list .= '<td class="w-24 text-right"><button type="submit" class="text-red-700 underline bg-transparent border-0 cursor-pointer" formaction="'.$removeURL.'" formnovalidate name="classStreamPostAttachmentID" value="'.$attachment['classStreamPostAttachmentID'].'" onclick="return confirm(\''.__m('Remove this attachment?').'\')">'.__('Remove').'</button></td></tr>';
    }
    $list .= '</table>';

    $col = $form->addRow()->addColumn();
        $col->addLabel('current', __m('Current Attachments'));
        $col->addContent($list);
}

$col = $form->addRow()->addColumn();
    $col->addLabel('links', __m('Add Links'))->description(__m('One web address per line, starting with http:// or https://'));
    $col->addTextArea('links')->setRows(3);

$row = $form->addRow();
    $row->addLabel('attachments', __m('Add Files'));
    $row->addFileUpload('attachments')->uploadMultiple(true);

if ($access['canManage']) {
    $row = $form->addRow();
        $row->addLabel('pinned', __m('Pin to top'));
        $row->addCheckbox('pinned')->setValue('Y')->checked($post['pinned']);
}

// A published post stays published unless the teacher changes it here; a scheduled one shows its time.
$isDraft = empty($post['timestampPublished']);
$isScheduled = !$isDraft && $post['timestampPublished'] > date('Y-m-d H:i:s');
$publishSelected = $isDraft ? 'draft' : ($isScheduled ? 'schedule' : 'now');
$publishDate = $isScheduled ? Format::date(substr($post['timestampPublished'], 0, 10)) : '';
$publishTime = $isScheduled ? substr($post['timestampPublished'], 11, 5) : '';

// When it goes on the stream: now, a draft to finish later, or a chosen date and time. A
// scheduled post is announced to the class on the first stream view after its time.
$row = $form->addRow();
    $row->addLabel('publish', __m('Publish'));
    $row->addSelect('publish')->fromArray(['now' => ($isDraft || $isScheduled ? __m('Now') : __m('Published (keep as is)')), 'draft' => __m('Save as draft'), 'schedule' => __m('Schedule for a date and time')])->selected($publishSelected)->required();

$form->toggleVisibilityByClass('publishSchedule')->onSelect('publish')->when('schedule');
$row = $form->addRow()->addClass('publishSchedule');
    $row->addLabel('publishDate', __m('Publish on'));
    $col = $row->addColumn('publishDate');
    $col->addDate('publishDate')->addClass('mr-2')->setValue($publishDate);
    $col->addTime('publishTime')->setValue($publishTime);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

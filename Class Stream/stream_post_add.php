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
use Gibbon\Module\ClassStream\StreamAccess;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_post_add.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canPost']) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$className = $access['class']['course'].'.'.$access['class']['class'];

$page->breadcrumbs
    ->add(__m('My Streams'), 'stream.php')
    ->add($className, 'stream_view.php', ['gibbonCourseClassID' => $gibbonCourseClassID])
    ->add(__m('New Post'));

$form = Form::create('postAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_post_addProcess.php');
$form->setTitle(__m('Announce something to {class}', ['class' => $className]));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);

$typeOptions = ['Announcement' => __m('Announcement'), 'Material' => __m('Material')];
if ($access['canManage']) {
    $typeOptions['Homework'] = __m('Homework');
}

$row = $form->addRow();
    $row->addLabel('type', __m('Type'))->description(__m('Material is for resources students come back to: it carries a title. Homework adds straight to the Planner, on the class\'s most recent lesson.'));
    $row->addSelect('type')->fromArray($typeOptions)->selected('Announcement')->required();

$form->toggleVisibilityByClass('materialTitle')->onSelect('type')->when('Material');
$row = $form->addRow()->addClass('materialTitle');
    $row->addLabel('title', __m('Title'));
    $row->addTextField('title')->maxLength(120);

// Announcement and Material share the same post fields. Homework hides all of these: it writes to
// the Planner instead, never to a stream post.
$form->toggleVisibilityByClass('postOnlyFields')->onSelect('type')->when(['Announcement', 'Material']);

$col = $form->addRow()->addClass('postOnlyFields')->addColumn();
    $col->addLabel('body', __m('Post'));
    $col->addEditor('body', $guid)->setRows(10)->showMedia()->required();

$col = $form->addRow()->addClass('postOnlyFields')->addColumn();
    $col->addLabel('links', __m('Links'))->description(__m('One web address per line, starting with http:// or https://'));
    $col->addTextArea('links')->setRows(3);

$row = $form->addRow()->addClass('postOnlyFields');
    $row->addLabel('attachments', __m('Files'));
    $row->addFileUpload('attachments')->uploadMultiple(true);

if ($access['canManage']) {
    $row = $form->addRow()->addClass('postOnlyFields');
        $row->addLabel('pinned', __m('Pin to top'))->description(__m('Pinned posts stay above everything else on the stream.'));
        $row->addYesNo('pinned')->checked('N');
}

$row = $form->addRow()->addClass('postOnlyFields');
    $row->addLabel('parentsCanView', __m('Parents can view'))->description(__m('Switch off to keep this post out of parents\' view of the stream.'));
    $row->addYesNo('parentsCanView')->checked('Y');

// When it goes on the stream: now, a draft to finish later, or a chosen date and time. A
// scheduled post is announced to the class on the first stream view after its time.
$row = $form->addRow()->addClass('postOnlyFields');
    $row->addLabel('publish', __m('Publish'));
    $row->addSelect('publish')->fromArray(['now' => __m('Now'), 'draft' => __m('Save as draft'), 'schedule' => __m('Schedule for a date and time')])->selected('now')->required();

$form->toggleVisibilityByClass('publishSchedule')->onSelect('publish')->when('schedule');
$row = $form->addRow()->addClass('publishSchedule');
    $row->addLabel('publishDate', __m('Publish on'));
    $col = $row->addColumn('publishDate');
    $col->addDate('publishDate')->addClass('mr-2')->setValue('');
    $col->addTime('publishTime')->setValue('');

// Homework: found on the class's most recent lesson (a Planner entry there is reused if one
// exists, or created), never on a stream post. Mirrors core's own Planner homework fields.
if ($access['canManage']) {
    $form->toggleVisibilityByClass('homeworkFields')->onSelect('type')->when('Homework');

    $row = $form->addRow()->addClass('homeworkFields');
        $row->addLabel('homeworkDueDate', __m('Due Date'))->description(__m('Date is required, time is optional.'));
        $col = $row->addColumn('homeworkDueDate');
        $col->addDate('homeworkDueDate')->addClass('mr-2')->setValue('')->required();
        $col->addTime('homeworkDueDateTime')->setValue('');

    $row = $form->addRow()->addClass('homeworkFields');
        $row->addLabel('homeworkTimeCap', __m('Time Cap?'))->description(__m('The maximum time, in minutes, for students to work on this.'));
        $row->addNumber('homeworkTimeCap');

    $col = $form->addRow()->addClass('homeworkFields')->addColumn();
        $col->addLabel('homeworkDetails', __m('Homework Details'));
        $col->addEditor('homeworkDetails', $guid)->setRows(10)->showMedia()->required();

    $form->toggleVisibilityByClass('homeworkSubmissionFields')->onSelect('homeworkSubmission')->when('Y');
    $row = $form->addRow()->addClass('homeworkFields');
        $row->addLabel('homeworkSubmission', __m('Online Submission?'));
        $row->addYesNo('homeworkSubmission')->checked('N');

    $row = $form->addRow()->addClass('homeworkSubmissionFields');
        $row->addLabel('homeworkSubmissionType', __m('Submission Type'));
        $row->addSelect('homeworkSubmissionType')->fromArray(['Link' => __('Link'), 'File' => __('File'), 'Link/File' => __('Link/File')])->selected('Link/File');

    $row = $form->addRow()->addClass('homeworkSubmissionFields');
        $row->addLabel('homeworkSubmissionRequired', __m('Submission Required'));
        $row->addSelect('homeworkSubmissionRequired')->fromArray(['Optional' => __('Optional'), 'Required' => __('Required')])->selected('Required');
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__m('Post'));

echo $form->getOutput();

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

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_drafts.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canManage']) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$className = $access['class']['course'].'.'.$access['class']['class'];

$page->breadcrumbs
    ->add(__m('My Streams'), 'stream.php')
    ->add($className, 'stream_view.php', ['gibbonCourseClassID' => $gibbonCourseClassID])
    ->add(__m('Drafts'));

$moduleURL = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream';
$processURL = $session->get('absoluteURL').'/modules/Class Stream';

echo '<h2>'.__m('Drafts and Scheduled Posts').'</h2>';
echo '<p class="text-sm text-gray-700">'.__m('Posts that are not on the stream yet. A draft waits for you; a scheduled post goes live at its time and the class is told on the first visit after that. Edit a post to change when it publishes, or publish a draft straight away.').'</p>';
echo '<div class="cs-actions">';
echo '<a class="cs-button" href="'.$moduleURL.'/stream_post_add.php&gibbonCourseClassID='.$gibbonCourseClassID.'">'.icon('solid', 'edit', 'size-4 inline-block -mt-px mr-1').__m('New Post').'</a>';
echo '<a class="cs-button" href="'.$moduleURL.'/stream_reuse.php&gibbonCourseClassID='.$gibbonCourseClassID.'">'.icon('solid', 'copy', 'size-4 inline-block -mt-px mr-1').__m('Reuse Posts from Another Class').'</a>';
echo '</div>';

$posts = $container->get(PostGateway::class)->selectPendingPostsByClass($gibbonCourseClassID)->fetchAll();

if (empty($posts)) {
    echo $page->getBlankSlate(__m('No drafts or scheduled posts.'));
    return;
}

$table = DataTable::create('classStreamDrafts');

$table->addColumn('state', __m('State'))
    ->width('12%')
    ->format(
        function ($post) {
            if (empty($post['timestampPublished'])) {
                return Format::tag(__m('Draft'), 'dull');
            }
            return Format::tag(__m('Scheduled'), 'message').'<div class="cs-draft-state">'.Format::dateTime($post['timestampPublished']).'</div>';
        }
    );
$table->addColumn('type', __m('Type'))
    ->width('10%')
    ->format(
        function ($post) {
            return __m($post['type']);
        }
    );
$table->addColumn('post', __m('Post'))
    ->format(
        function ($post) {
            $excerpt = $post['type'] == 'Material' ? $post['title'] : Format::truncate(trim(strip_tags($post['body'])), 120);
            return htmlspecialchars($excerpt).'<div class="cs-draft-state">'.__m('Last saved {time}', ['time' => Format::relativeTime($post['timestampModified'])]).'</div>';
        }
    );
$table->addColumn('attachmentCount', __m('Files'))->width('8%');

// Edit opens the post form; Publish now and Delete open a small confirmation in a modal, which
// carries the CSRF token and nonce the process scripts require.
$table->addActionColumn()
    ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
    ->addParam('classStreamPostID')
    ->format(
        function ($post, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Class Stream/stream_post_edit.php');

            $actions->addAction('publish', __m('Publish Now'))
                ->setIcon('accept')
                ->setURL('/modules/Class Stream/stream_post_publish.php')
                ->modalWindow(650, 280);

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Class Stream/stream_post_delete.php');
        }
    );

echo $table->render($posts);

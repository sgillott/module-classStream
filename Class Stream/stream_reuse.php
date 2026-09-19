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
use Gibbon\Module\ClassStream\Domain\PostAttachmentGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_reuse.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$sourceClassID = $_GET['source'] ?? '';
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canManage']) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$className = $access['class']['course'].'.'.$access['class']['class'];

$page->breadcrumbs
    ->add(__m('My Streams'), 'stream.php')
    ->add($className, 'stream_view.php', ['gibbonCourseClassID' => $gibbonCourseClassID])
    ->add(__m('Reuse Posts'));

// STEP 1: which class to take posts from. Every class the person has taught, any year, with the
// year shown so an archived class is easy to spot. The current class is not a source of itself.
$postGateway = $container->get(PostGateway::class);
$sources = [];
$sourceLabels = [];
foreach ($postGateway->selectTaughtClassesByPerson($session->get('gibbonPersonID'))->fetchAll() as $class) {
    if ($class['gibbonCourseClassID'] == $gibbonCourseClassID) continue;
    $sources[$class['yearName']][$class['gibbonCourseClassID']] = $class['name'].' - '.$class['courseName'].' ('.$class['postCount'].')';
    $sourceLabels[$class['gibbonCourseClassID']] = $class['name'].' ('.$class['yearName'].')';
}

$form = Form::create('reuseSource', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(__m('Reuse posts in {class}', ['class' => $className]));
$form->setDescription(__m('Copy announcements and materials from another class you have taught, this year or a past one, into this class. Copies arrive as drafts, in your name, with the same links and files, for you to publish or schedule one by one. Comments are not copied.'));
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/Class Stream/stream_reuse.php');
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);

$row = $form->addRow();
    $row->addLabel('source', __m('Copy from'));
    $row->addSelect('source')->fromArray($sources)->selected($sourceClassID)->placeholder()->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSearchSubmit($session);

echo $form->getOutput();

// STEP 2: the posts of that class, each with a checkbox
if (!isset($sourceLabels[$sourceClassID])) {
    return;
}

$posts = $postGateway->selectAllPostsByClass($sourceClassID)->fetchAll();
$attachments = $container->get(PostAttachmentGateway::class)->selectAttachmentsByPosts(array_column($posts, 'classStreamPostID'))->fetchGrouped();

if (empty($posts)) {
    echo $page->getBlankSlate(__m('That class has no posts to copy.'));
    return;
}

$form = Form::create('reusePosts', $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_reuseProcess.php');
$form->setTitle(__m('Posts in {class}', ['class' => $sourceLabels[$sourceClassID]]));
$form->setClass('w-full');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);
$form->addHiddenValue('source', $sourceClassID);

$table = '<table class="w-full colorOddEven smallIntBorder">';
$table .= '<tr class="head"><th class="w-10 text-center"><input type="checkbox" id="csReuseAll" title="'.__m('Select all').'"></th><th class="w-24">'.__('Date').'</th><th class="w-24">'.__m('Type').'</th><th>'.__m('Post').'</th><th class="w-24 text-center">'.__m('Attachments').'</th></tr>';
foreach ($posts as $post) {
    $excerpt = $post['type'] == 'Material' ? $post['title'] : Format::truncate(trim(strip_tags($post['body'])), 120);
    $table .= '<tr>';
    $table .= '<td class="text-center"><input type="checkbox" name="posts[]" class="csReuseRow" value="'.$post['classStreamPostID'].'"></td>';
    $table .= '<td>'.Format::date($post['timestampPublished'] ?: $post['timestamp']).'</td>';
    $table .= '<td>'.__m($post['type']).'</td>';
    $table .= '<td>'.htmlspecialchars($excerpt).'</td>';
    $table .= '<td class="text-center">'.count($attachments[$post['classStreamPostID']] ?? []).'</td>';
    $table .= '</tr>';
}
$table .= '</table>';

$form->addRow()->addContent($table);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__m('Copy Selected'));

echo $form->getOutput();

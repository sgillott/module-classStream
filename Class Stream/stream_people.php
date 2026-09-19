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
use Gibbon\Domain\Timetable\CourseClassPersonGateway;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\MuteGateway;
use Gibbon\Module\ClassStream\Domain\ViewGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_people.php') == false) {
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
    ->add(__m('People'));

$teachers = $container->get(CourseClassPersonGateway::class)->selectTeachersByClass($gibbonCourseClassID)->fetchAll();
$students = $container->get(ViewGateway::class)->selectInsightsByClass($gibbonCourseClassID, classStreamModuleID($container))->fetchAll();
$muted = $container->get(MuteGateway::class)->selectMutedByClass($gibbonCourseClassID)->fetchKeyPair();

// TEACHERS
echo '<h3>'.__m('Teachers').'</h3>';
if (empty($teachers)) {
    echo $page->getBlankSlate();
} else {
    echo '<ul class="list-none pl-0 mb-6">';
    foreach ($teachers as $teacher) {
        echo '<li class="flex items-center py-1">'.Format::userPhoto($teacher['image_240'], 'xs', 'mr-3').Format::name($teacher['title'], $teacher['preferredName'], $teacher['surname'], 'Staff').'</li>';
    }
    echo '</ul>';
}

// STUDENTS: the roster with each student's use of the stream (visits from classStreamView, which
// every stream render updates; comments and posts counted live) and the mute checkboxes. One form,
// one Save: the process script reconciles the ticked set against the stored set, so unticking
// un-mutes.
$weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
$monthAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

$form = Form::create('people', $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_people_muteProcess.php');
$form->setTitle(__m('Students'));
$form->setDescription(__m('A visit is counted each time the student opens this stream. A muted student can still read the stream, but cannot post or comment in this class: tick to mute, untick to unmute, then save. Click a column heading to sort.'));
$form->setClass('w-full');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);

if (empty($students)) {
    $form->addRow()->addContent($page->getBlankSlate());
} else {
    $total = count($students);
    $never = count(array_filter($students, function ($s) { return empty($s['lastVisit']); }));
    $thisWeek = count(array_filter($students, function ($s) use ($weekAgo) { return !empty($s['lastVisit']) && $s['lastVisit'] >= $weekAgo; }));
    $stale = count(array_filter($students, function ($s) use ($monthAgo) { return !empty($s['lastVisit']) && $s['lastVisit'] < $monthAgo; }));
    $comments = array_sum(array_column($students, 'commentCount'));

    $summary = '<div class="cs-insights">';
    foreach ([
        __m('{count} of {total} students have visited in the last 7 days', ['count' => $thisWeek, 'total' => $total]),
        __m('{count} have never visited', ['count' => $never]),
        __m('{count} last visited more than 30 days ago', ['count' => $stale]),
        __m('{count} comments by students in all', ['count' => $comments]),
    ] as $line) {
        $summary .= '<div class="cs-insight">'.$line.'</div>';
    }
    $summary .= '</div>';
    $form->addRow()->addContent($summary);

    // The header checkbox ticks or unticks every student row (id="csMuteAll", wired in js/module.js).
    // Its starting state is set here, since module.js cannot rely on a page-load event. The other
    // headings sort the rows client-side (data-sort on each cell), also in module.js.
    $mutedCount = count(array_filter($students, function ($student) use ($muted) { return isset($muted[$student['gibbonPersonID']]); }));
    $allMuted = $mutedCount == count($students);

    $table = '<table class="w-full colorOddEven smallIntBorder" id="csPeople">';
    $table .= '<tr class="head">'
        .'<th class="cs-sort" data-type="text">'.__('Name').'</th>'
        .'<th class="cs-sort w-40" data-type="number">'.__m('Last Visited').'</th>'
        .'<th class="cs-sort w-20 text-center" data-type="number">'.__m('Visits').'</th>'
        .'<th class="cs-sort w-24 text-center" data-type="number">'.__m('Comments').'</th>'
        .'<th class="cs-sort w-20 text-center" data-type="number">'.__m('Posts').'</th>'
        .'<th class="w-24 text-center">'.__m('Mute').' <input type="checkbox" id="csMuteAll" class="ml-1 align-middle" title="'.__m('Mute or unmute everyone').'" '.($allMuted ? 'checked' : '').'></th>'
        .'</tr>';
    foreach ($students as $student) {
        $isMuted = isset($muted[$student['gibbonPersonID']]);
        $name = Format::name('', $student['preferredName'], $student['surname'], 'Student', true);

        if (empty($student['lastVisit'])) {
            $visited = Format::tag(__m('Never'), 'error');
        } else {
            $visited = Format::relativeTime($student['lastVisit']);
            if ($student['lastVisit'] < $monthAgo) $visited = Format::tag($visited, 'warning');
        }

        $table .= '<tr>';
        $table .= '<td class="flex items-center" data-sort="'.htmlspecialchars($student['surname'].' '.$student['preferredName']).'">'.Format::userPhoto($student['image_240'], 'xs', 'mr-3').$name;
        $table .= $isMuted ? ' '.Format::tag(__m('Muted'), 'cs-muted-tag ml-2', Format::dateTime($muted[$student['gibbonPersonID']])) : '';
        $table .= '</td>';
        $table .= '<td data-sort="'.(empty($student['lastVisit']) ? 0 : strtotime($student['lastVisit'])).'">'.$visited.'</td>';
        $table .= '<td class="text-center" data-sort="'.intval($student['visitCount']).'">'.intval($student['visitCount']).'</td>';
        $table .= '<td class="text-center" data-sort="'.intval($student['commentCount']).'">'.intval($student['commentCount']).'</td>';
        $table .= '<td class="text-center" data-sort="'.intval($student['postCount']).'">'.intval($student['postCount']).'</td>';
        $table .= '<td class="text-center"><input type="checkbox" name="muted[]" class="csMuteRow" value="'.$student['gibbonPersonID'].'" '.($isMuted ? 'checked' : '').'></td>';
        $table .= '</tr>';
    }
    $table .= '</table>';

    $form->addRow()->addContent($table);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
}

echo $form->getOutput();

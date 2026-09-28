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
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ClassStream\Theme;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\LinkGateway;
use Gibbon\Module\ClassStream\Domain\AssessmentItemGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_customise.php') == false) {
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
$settings = $access['settings'];

$page->breadcrumbs
    ->add(__m('My Streams'), 'stream.php')
    ->add($className, 'stream_view.php', ['gibbonCourseClassID' => $gibbonCourseClassID])
    ->add(__m('Class Settings'));

$settingGateway = $container->get(SettingGateway::class);
$showLessons = $settingGateway->getSettingByScope('Class Stream', 'showLessons') == 'Y';
$defaultAccess = $settingGateway->getSettingByScope('Class Stream', 'defaultStudentAccess');

$form = Form::create('customise', $session->get('absoluteURL').'/modules/'.$session->get('module').'/stream_customiseProcess.php');
$form->setTitle(__m('Class Settings: {class}', ['class' => $className]));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonCourseClassID', $gibbonCourseClassID);

$form->addRow()->addHeading(__m('Appearance'));

// Colour swatches: a radio per palette entry, drawn as a filled circle with a tick on the chosen one.
$swatches = '<div class="cs-swatches">';
foreach (Theme::PALETTE as $key => $colour) {
    $checked = $key == $settings['colourKey'] ? 'checked' : '';
    $swatches .= '<label class="cs-swatch" style="background-color: '.$colour['hex'].'" title="'.__m($colour['name']).'">';
    $swatches .= '<input type="radio" name="colour" value="'.$key.'" '.$checked.'>';
    $swatches .= icon('solid', 'check', '');
    $swatches .= '</label>';
}
$swatches .= '</div>';

$row = $form->addRow();
    $row->addLabel('colour', __m('Theme Colour'));
    $row->addContent($swatches);

$row = $form->addRow();
    $row->addLabel('headerImage', __m('Header Image'))->description(__m('Optional. Shown across the top of the stream, over the theme colour. Wide images work best.'));
    $row->addFileUpload('headerImage')
        ->accepts('.jpg,.jpeg,.gif,.png,.webp')
        ->setAttachment('headerImage', $session->get('absoluteURL'), $settings['headerImage']);

$form->addRow()->addHeading(__m('Stream'));

$accessLabels = [
    'Post'    => __m('Students can post and comment'),
    'Comment' => __m('Students can only comment'),
    'None'    => __m('Only teachers can post or comment'),
];
$row = $form->addRow();
    $row->addLabel('studentAccess', __m('Students'));
    $row->addSelect('studentAccess')
        ->fromArray(['' => __m('School default ({default})', ['default' => $accessLabels[$defaultAccess] ?? $defaultAccess])] + $accessLabels)
        ->selected($settings['studentAccessStored']);

$row = $form->addRow();
    $row->addLabel('visibleToParents', __m('Visible to Parents'))->description(__m('Allow parents of students in this class to open and view its stream.'));
    $row->addYesNo('visibleToParents')->selected($settings['visibleToParents'])->required();

$row = $form->addRow();
if ($showLessons) {
    $plannerOptions = [
        'Condensed' => __m('Show homework details and lesson summaries'),
        'Details'   => __m('Show homework and lesson details'),
        'Hidden'    => __m('Hide Planner items'),
    ];
    $row->addLabel('plannerDisplay', __m('Planner on the stream'))->description(__m('Choose how homework and lessons from the Planner appear on this stream.'));
    $row->addSelect('plannerDisplay')->fromArray($plannerOptions)->selected($settings['plannerDisplay'])->required();
} else {
    $row->addLabel('plannerVisible', __m('Planner on the stream'))->description(__m('Show full homework details from the Planner, including the Upcoming panel.'));
    $row->addYesNo('plannerVisible')->selected($settings['plannerDisplay'] == 'Hidden' ? 'N' : 'Y')->required();
}

$assessmentTypes = $container->get(AssessmentItemGateway::class)->selectAssessmentTypesByClass($gibbonCourseClassID)->fetchAll(\PDO::FETCH_COLUMN);
$assessmentTypeOptions = array_combine($assessmentTypes, $assessmentTypes) ?: [];
$selectedAssessmentTypes = $settings['assessmentTypes'] ?? $assessmentTypes;
$selectedAssessmentTypes = array_values(array_filter($selectedAssessmentTypes, function ($type) use ($assessmentTypes) {
    return in_array($type, $assessmentTypes, true);
}));

$row = $form->addRow();
    $row->addLabel('showAssessments', __m('Assessments on the stream'))
        ->description(__m('Show markbook columns on this class stream as soon as they are created.'));
    $row->addYesNo('showAssessments')->selected($settings['showAssessments'])->required();

$row = $form->addRow();
    $row->addClass('classStreamAssessmentTypes');
    $row->addLabel('assessmentTypes', __m('Assessment types'))->description(__m('Choose which markbook types appear on this stream.'));
    if (!empty($assessmentTypeOptions)) {
        $row->addCheckbox('assessmentTypes')->fromArray($assessmentTypeOptions)->checked($selectedAssessmentTypes)->addCheckAllNone();
    } else {
        $row->addContent('<span class="text-xs text-gray-600">'.__m('No markbook assessment types have been used in this class yet.').'</span>');
    }
$form->toggleVisibilityByClass('classStreamAssessmentTypes')->onClick('showAssessments')->when('Y');

// LINKED CLASSES
// 1-way sync: staff posts made here are copied to the chosen classes. 2-way sync: staff posts made
// in either class appear in both. Only classes the person could post in are offered, so nobody can
// push posts into a class they do not teach.
$linkGateway = $container->get(LinkGateway::class);
$targets = array_keys($linkGateway->selectTargetsByClass($gibbonCourseClassID)->fetchKeyPair());
$sources = array_keys($linkGateway->selectSourcesByClass($gibbonCourseClassID)->fetchKeyPair());
$synced = array_values(array_intersect($targets, $sources));
$mirrored = array_values(array_diff($targets, $synced));
$incomingOnly = array_values(array_diff($sources, $synced));

$linkable = classStreamLinkableClasses($container, $session, $scope, $access['class']['gibbonSchoolYearID']);
unset($linkable[$gibbonCourseClassID]);

$form->addRow()->addHeading(__m('Linked Classes'));

if (empty($linkable)) {
    $form->addRow()->addContent('<p class="text-xs text-gray-600">'.__m('You have no other class to link this one to.').'</p>');
} else {
    $row = $form->addRow();
        $row->addLabel('mirror', __m('1-way Sync (push) to'))->description(__m('Announcements and materials posted by staff here are also posted in these classes. Student posts and comments stay in their original class.'));
        $row->addSelect('mirror')->fromArray($linkable)->selectMultiple()->selected($mirrored)->setSize(6);

    $row = $form->addRow();
        $row->addLabel('sync', __m('2-way Sync (push & pull) with'))->description(__m('Announcements and materials posted by staff in any linked class appear in all of them. Student posts and comments stay in their original class.'));
        $row->addSelect('sync')->fromArray($linkable)->selectMultiple()->selected($synced)->setSize(6);
}

if (!empty($incomingOnly)) {
    $names = [];
    $allTargets = $linkGateway->selectSourcesByClass($gibbonCourseClassID)->fetchKeyPair();
    foreach ($incomingOnly as $id) { $names[] = $allTargets[$id] ?? $id; }
    $form->addRow()->addContent('<p class="text-xs text-gray-600">'.__m('Also receiving posts synced from: {classes}. That is set in those classes\' own Class Settings.', ['classes' => htmlspecialchars(implode(', ', $names))]).'</p>');
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

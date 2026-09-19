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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\ViewGateway;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
if ($scope === false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$gibbonPersonID = $session->get('gibbonPersonID');
$gibbonPersonIDStudent = '';
$child = null;

if ($scope == 'parent') {
    // A parent sees their children's classes. With more than one child they choose which first,
    // and the choice travels with every link on the pages that follow.
    $children = $container->get(StreamAccess::class)->childrenOfCurrentParent();

    if (empty($children)) {
        $page->breadcrumbs->add(__m('My Streams'));
        echo $page->getBlankSlate(__m('None of your children is enrolled this year.'));
        return;
    }

    $requested = intval($_GET['gibbonPersonIDStudent'] ?? 0);
    if (count($children) == 1) {
        $child = reset($children);
    } elseif (isset($children[$requested])) {
        $child = $children[$requested];
    } else {
        $child = null;
    }

    if (count($children) > 1) {
        $form = Form::create('chooseChild', $session->get('absoluteURL').'/index.php', 'get');
        $form->setTitle(__('Choose'));
        $form->setClass('noIntBorder w-full');
        $form->addHiddenValue('q', '/modules/Class Stream/stream.php');

        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDStudent', __('Student'));
            $row->addSelect('gibbonPersonIDStudent')
                ->fromArray(Format::nameListArray(array_values($children), 'Student'))
                ->selected($child['gibbonPersonID'] ?? '')
                ->placeholder();

        $row = $form->addRow();
            $row->addFooter();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    if (empty($child)) {
        $page->breadcrumbs->add(__m('My Streams'));
        return;
    }

    $gibbonPersonID = $child['gibbonPersonID'];
    $gibbonPersonIDStudent = $child['gibbonPersonID'];
}

// The streams belong to the child, not the parent: say whose they are.
$heading = !empty($child) ? classStreamHeading($child) : __m('My Streams');
$page->breadcrumbs->add($heading);

$postGateway = $container->get(PostGateway::class);

// "Find a Class": any class for the _allClasses action, the departments' classes for a Head of
// Department. Own classes are cards below it like everyone else's.
if ($scope == 'all' || $scope == 'department') {
    $form = Form::create('findClass', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle($scope == 'all' ? __m('Find a Class') : __m('Find a Department Class'));
    $form->setClass('noIntBorder w-full');
    $form->addHiddenValue('q', '/modules/Class Stream/stream_view.php');

    $row = $form->addRow();
        $row->addLabel('gibbonCourseClassID', __('Class'));
    if ($scope == 'all') {
        $row->addSelectClass('gibbonCourseClassID', $gibbonSchoolYearID)->placeholder()->required();
    } else {
        $options = [];
        foreach ($postGateway->selectDepartmentClassesByPerson($gibbonSchoolYearID, $gibbonPersonID)->fetchAll() as $class) {
            $options[$class['department']][$class['gibbonCourseClassID']] = $class['course'].'.'.$class['class'].' - '.$class['courseName'];
        }
        $row->addSelect('gibbonCourseClassID')->fromArray($options)->placeholder()->required();
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Go'));

    echo $form->getOutput();
}

$classes = $postGateway->selectClassesByPerson($gibbonSchoolYearID, $gibbonPersonID)->fetchAll();

// "N new": posts by others since the viewer last opened each stream; a stream never opened
// counts every post. Parents are counted against their own visits, not the child's.
$newCounts = $container->get(ViewGateway::class)->selectNewCountsByPerson($session->get('gibbonPersonID'))->fetchKeyPair();

echo $page->fetchFromTemplate('streamCards.twig.html', [
    'heading'               => $heading,
    'classes'               => array_map(function ($class) use ($newCounts) { return classStreamDecorateCard($class, $newCounts); }, $classes),
    'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
]);

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

use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\StreamRenderer;

require_once __DIR__.'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_view.php') == false) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$scope = classStreamScope($guid, $connection2);
$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$gibbonPersonIDStudent = $scope == 'parent' ? ($_GET['gibbonPersonIDStudent'] ?? '') : '';

$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID, $gibbonPersonIDStudent) : null;

if (empty($access)) {
    $page->addError(__('The selected record does not exist, or you do not have access to it.'));
    return;
}

$className = $access['class']['course'].'.'.$access['class']['class'];

$isParent = $access['role'] == 'Parent';
$crumb = $isParent ? classStreamHeading($container->get(StreamAccess::class)->childrenOfCurrentParent()[intval($gibbonPersonIDStudent)]) : __m('My Streams');
$page->breadcrumbs
    ->add($crumb, 'stream.php', $isParent ? ['gibbonPersonIDStudent' => $gibbonPersonIDStudent] : [])
    ->add($className);

$plannerAccessible = isActionAccessible($guid, $connection2, '/modules/Planner/planner.php');
$links = StreamRenderer::quickLinks(
    $gibbonCourseClassID,
    $plannerAccessible,
    isActionAccessible($guid, $connection2, '/modules/Departments/department_course_class.php'),
    isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_view.php'),
    $isParent ? $access['childID'] : ''
);

echo $container->get(StreamRenderer::class)->render(
    $access,
    $links,
    $plannerAccessible,
    $page,
    intval($_GET['page'] ?? 1),
    $gibbonPersonIDStudent,
    classStreamModuleID($container)
);

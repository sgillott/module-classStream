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

/*
 * The "Class Stream" dashboard tab. Core's Staff, Student and Parental dashboards include this
 * file and put the string it returns into a tab (see the $hooks rows in manifest.php).
 *
 * It runs inside another module's page, so it sets up what a page of this module would get for
 * free: the PSR-4 namespace, the templates folder, the stylesheet. It must return, not echo.
 *
 * What the tab shows:
 *  - staff and students: when one of the person's classes is timetabled right now, that class's
 *    whole stream, with a "Now" strip above it; otherwise the "Now / Next" strip (if a lesson is
 *    still to come today) and the My Streams cards with their new-post counts;
 *  - parents: the cards of each child's classes.
 */

use Gibbon\Services\ModuleLoader;
use Gibbon\Contracts\Services\Session;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\StreamRenderer;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\ViewGateway;

global $container;

// The Parental Dashboard builds one dashboard per child and includes this file inside that loop,
// with the child's ID already in $gibbonPersonID. Keep it before anything below overwrites it.
$hookChildID = $gibbonPersonID ?? null;

$container->get(ModuleLoader::class)->registerModuleNamespace('Class Stream');
require_once __DIR__.'/moduleFunctions.php';

$session = $container->get(Session::class);
$page = $container->get('page');
$guid = $session->get('guid');
$connection2 = $container->get('db')->getConnection();

$container->get('twig')->getLoader()->prependPath($session->get('absolutePath').'/modules/Class Stream/templates');
$page->stylesheets->add('classStream', 'modules/Class Stream/css/module.css');

$scope = classStreamScope($guid, $connection2);
if ($scope === false) {
    return '';
}

$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$gibbonPersonID = $session->get('gibbonPersonID');
$postGateway = $container->get(PostGateway::class);
$streamAccess = $container->get(StreamAccess::class);
$moduleURL = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream';

$newCounts = $container->get(ViewGateway::class)->selectNewCountsByPerson($gibbonPersonID)->fetchKeyPair();
$decorate = function (array $class) use ($newCounts) {
    return classStreamDecorateCard($class, $newCounts);
};

// PARENTS: the cards of the child whose dashboard this is. If the dashboard passed no child (it
// always does, but stay safe), fall back to every child with a heading each.
if ($scope == 'parent') {
    $children = $streamAccess->childrenOfCurrentParent();
    $shown = isset($children[intval($hookChildID)]) ? [$children[intval($hookChildID)]] : $children;
    $withHeadings = count($shown) != 1 || !isset($children[intval($hookChildID)]);

    $output = '';
    foreach ($shown as $child) {
        $classes = $postGateway->selectClassesByPerson($gibbonSchoolYearID, $child['gibbonPersonID'])->fetchAll();
        if ($withHeadings) {
            $output .= '<h4 class="mt-2 mb-2">'.Gibbon\Services\Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true).'</h4>';
        }
        $output .= $page->fetchFromTemplate('streamCards.twig.html', [
            'compact'               => true,
            'classes'               => array_map($decorate, $classes),
            'gibbonPersonIDStudent' => $child['gibbonPersonID'],
        ]);
    }
    return '<div class="cs-dashboard">'.$output.'</div>';
}

// STAFF AND STUDENTS: what is on the timetable now?
$today = date('Y-m-d');
$now = date('H:i:s');
$lessons = $postGateway->selectTimetabledClassesByPersonAndDate($gibbonSchoolYearID, $gibbonPersonID, $today)->fetchAll();

$current = [];
$next = null;
foreach ($lessons as $lesson) {
    if ($lesson['timeStart'] <= $now && $lesson['timeEnd'] > $now) {
        $current[] = $lesson;
    } elseif ($lesson['timeStart'] > $now && $next === null) {
        $next = $lesson;
    }
}

$strip = function (array $lesson, $label) use ($moduleURL) {
    $url = $moduleURL.'/stream_view.php&gibbonCourseClassID='.$lesson['gibbonCourseClassID'];
    return '<a class="cs-now" href="'.$url.'">'
        .'<div><div class="cs-now-label">'.$label.'</div>'
        .'<div class="cs-now-title">'.htmlspecialchars($lesson['course'].'.'.$lesson['class']).' &middot; '.htmlspecialchars($lesson['courseName']).'</div>'
        .'<div class="cs-now-meta">'.htmlspecialchars($lesson['period']).', '.substr($lesson['timeStart'], 0, 5).' – '.substr($lesson['timeEnd'], 0, 5).'</div></div>'
        .'</a>';
};

$output = '';

// Exactly one class now: show its stream here, in full. Two at once (co-taught, a cover) means
// the person must choose, so both get a strip and the cards follow.
if (count($current) == 1) {
    $lesson = $current[0];
    $access = $streamAccess->forClass($scope, $lesson['gibbonCourseClassID']);

    if (!empty($access)) {
        $plannerAccessible = isActionAccessible($guid, $connection2, '/modules/Planner/planner.php');
        $canViewMarkbook = isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_view.php');
        $canEditMarkbookData = isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_edit_data.php');
        $links = StreamRenderer::quickLinks(
            $lesson['gibbonCourseClassID'],
            $plannerAccessible,
            isActionAccessible($guid, $connection2, '/modules/Departments/department_course_class.php'),
            $canViewMarkbook
        );

        $output .= '<div class="cs-dashboard-banner">'
            .'<span>'.__m('You are in {class} now ({period}, {start} – {end}).', ['class' => htmlspecialchars($lesson['course'].'.'.$lesson['class']), 'period' => htmlspecialchars($lesson['period']), 'start' => substr($lesson['timeStart'], 0, 5), 'end' => substr($lesson['timeEnd'], 0, 5)]).'</span>'
            .'<a href="'.$moduleURL.'/stream.php">'.__m('All my streams').'</a>'
            .'</div>';
        $output .= $container->get(StreamRenderer::class)->render($access, $links, $plannerAccessible, $page, 1, '', classStreamModuleID($container), [], $canViewMarkbook, $canEditMarkbookData);

        return '<div class="cs-dashboard">'.$output.'</div>';
    }
}

foreach ($current as $lesson) {
    $output .= $strip($lesson, __m('Now'));
}
if (empty($current) && $next !== null) {
    $output .= $strip($next, __m('Next'));
}

$classes = $postGateway->selectClassesByPerson($gibbonSchoolYearID, $gibbonPersonID)->fetchAll();

$output .= $page->fetchFromTemplate('streamCards.twig.html', [
    'compact'               => true,
    'classes'               => array_map($decorate, $classes),
    'gibbonPersonIDStudent' => '',
]);

return '<div class="cs-dashboard">'.$output.'</div>';

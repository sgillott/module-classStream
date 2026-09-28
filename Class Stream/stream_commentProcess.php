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

use Gibbon\Data\Validator;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\CommentGateway;
use Gibbon\Module\ClassStream\Domain\PlannerItemGateway;
use Gibbon\Module\ClassStream\Domain\AssessmentItemGateway;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$targetType = $_POST['targetType'] ?? '';
$targetID = $_POST['targetID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);
$fragmentPrefixes = ['post' => 'post', 'planner' => 'planner', 'assessment' => 'assessment'];
$fragmentPrefix = is_string($targetType) && isset($fragmentPrefixes[$targetType]) ? $fragmentPrefixes[$targetType] : 'post';
$fragment = '#'.$fragmentPrefix.$targetID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_view.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canComment']) {
    header("Location: {$URL}&return=error0");
    exit;
}

// A comment target must be one the current person can actually see on this stream. The predicates
// below are shared with StreamRenderer so crafted requests cannot reach hidden timeline items.
if ($targetType == 'post') {
    $post = $container->get(PostGateway::class)->getPostByID($targetID);
    $targetValid = !empty($post)
        && $post['gibbonCourseClassID'] == $gibbonCourseClassID
        && PostGateway::isVisibleOnStream($post);
    $foreignTable = CommentGateway::TARGET_POST;
} elseif ($targetType == 'planner') {
    $settingGateway = $container->get(SettingGateway::class);
    $showHomework = $settingGateway->getSettingByScope('Class Stream', 'showHomework') == 'Y';
    $showLessons = $settingGateway->getSettingByScope('Class Stream', 'showLessons') == 'Y';
    $plannerDisplay = $access['settings']['plannerDisplay'];
    $item = $container->get(PlannerItemGateway::class)->getItemByID($targetID);
    $targetValid = !empty($item)
        && $item['gibbonCourseClassID'] == $gibbonCourseClassID
        && PlannerItemGateway::isVisibleOnStream($item, $access['viewingAs'], $plannerDisplay, $showHomework, $showLessons);

    // A visible assessment replaces its linked non-homework lesson row in the timeline.
    if ($targetValid && $item['homework'] != 'Y' && $access['settings']['showAssessments'] == 'Y') {
        $assessmentTypes = $access['settings']['assessmentTypes'];
        foreach ($container->get(AssessmentItemGateway::class)->selectAssessmentsByPlannerEntry($targetID)->fetchAll() as $assessment) {
            if ($assessment['gibbonCourseClassID'] == $gibbonCourseClassID
                && AssessmentItemGateway::isVisibleOnStream($assessment, $access['viewingAs'], $access['settings']['showAssessments'], $assessmentTypes)) {
                $targetValid = false;
                break;
            }
        }
    }
    $foreignTable = CommentGateway::TARGET_PLANNER;
} elseif ($targetType == 'assessment') {
    $item = $container->get(AssessmentItemGateway::class)->getAssessmentByID($targetID);
    $targetValid = !empty($item)
        && $item['gibbonCourseClassID'] == $gibbonCourseClassID
        && AssessmentItemGateway::isVisibleOnStream($item, $access['viewingAs'], $access['settings']['showAssessments'], $access['settings']['assessmentTypes']);
    $foreignTable = CommentGateway::TARGET_ASSESSMENT;
} else {
    $targetValid = false;
}

if (!$targetValid) {
    header("Location: {$URL}&return=error0");
    exit;
}

// Comments are plain text. The sanitizer above has already stripped tags; what is left is
// trimmed and capped at the same length the textarea allows.
$comment = trim($_POST['comment'] ?? '');
if ($comment == '') {
    header("Location: {$URL}&return=error1{$fragment}");
    exit;
}
$comment = mb_substr($comment, 0, 2000);

$gibbonModuleID = classStreamModuleID($container);
$inserted = $container->get(CommentGateway::class)->insertComment($foreignTable, $targetID, $gibbonModuleID, $session->get('gibbonPersonID'), $comment);

header("Location: {$URL}".($inserted ? '&return=success0' : '&return=error2').$fragment);

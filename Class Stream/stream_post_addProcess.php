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
use Gibbon\Module\ClassStream\StreamAccess;
use Gibbon\Module\ClassStream\Domain\PostGateway;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML', 'homeworkDetails' => 'HTML']);

$gibbonCourseClassID = $_POST['gibbonCourseClassID'] ?? '';
$URL = classStreamViewURL($session, $gibbonCourseClassID);
$URLBack = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/stream_post_add.php&gibbonCourseClassID='.$gibbonCourseClassID;

if (isActionAccessible($guid, $connection2, '/modules/Class Stream/stream_post_add.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$scope = classStreamScope($guid, $connection2);
$access = $scope !== false ? $container->get(StreamAccess::class)->forClass($scope, $gibbonCourseClassID) : null;

if (empty($access) || !$access['canPost']) {
    header("Location: {$URL}&return=error0");
    exit;
}

$type = $_POST['type'] ?? '';
if (!in_array($type, ['Announcement', 'Material', 'Homework']) || ($type == 'Homework' && !$access['canManage'])) {
    $type = 'Announcement';
}

// Homework never becomes a stream post: it writes straight to the Planner, reusing (or creating)
// the entry on the class's most recent lesson, and shows on the stream through the same homework
// card a Planner-made lesson already gets.
if ($type == 'Homework') {
    $homeworkDetails = $_POST['homeworkDetails'] ?? '';
    $homeworkDueDateTime = classStreamHomeworkDueDateTime($_POST['homeworkDueDate'] ?? '', $_POST['homeworkDueDateTime'] ?? '');

    if (trim(strip_tags($homeworkDetails)) == '' || empty($homeworkDueDateTime)) {
        header("Location: {$URLBack}&return=error1");
        exit;
    }

    $submission = ($_POST['homeworkSubmission'] ?? '') == 'Y';
    $submissionType = $_POST['homeworkSubmissionType'] ?? '';
    if (!in_array($submissionType, ['Link', 'File', 'Link/File'])) {
        $submissionType = 'Link/File';
    }
    $submissionRequired = $_POST['homeworkSubmissionRequired'] ?? '';
    if (!in_array($submissionRequired, ['Optional', 'Required'])) {
        $submissionRequired = 'Required';
    }

    $homework = [
        'homeworkDueDateTime'        => $homeworkDueDateTime,
        'homeworkDetails'            => $homeworkDetails,
        'homeworkTimeCap'            => !empty($_POST['homeworkTimeCap']) ? intval($_POST['homeworkTimeCap']) : null,
        'homeworkSubmission'         => $submission ? 'Y' : 'N',
        'homeworkSubmissionDateOpen' => $submission ? date('Y-m-d') : null,
        'homeworkSubmissionType'     => $submission ? $submissionType : '',
        'homeworkSubmissionRequired' => $submission ? $submissionRequired : null,
    ];

    $gibbonPlannerEntryID = classStreamSaveHomework($container, $session->get('gibbonPersonID'), $access['class'], $homework);

    if (empty($gibbonPlannerEntryID)) {
        header("Location: {$URLBack}&return=error2");
        exit;
    }

    header("Location: {$URL}&return=success0#planner".sprintf('%014d', $gibbonPlannerEntryID));
    exit;
}

$body = $_POST['body'] ?? '';
$title = $type == 'Material' ? mb_substr(trim($_POST['title'] ?? ''), 0, 120) : null;

if (trim(strip_tags($body, '<img><iframe>')) == '' || ($type == 'Material' && $title == '')) {
    header("Location: {$URLBack}&return=error1");
    exit;
}

$postGateway = $container->get(PostGateway::class);

$data = [
    'gibbonCourseClassID' => $gibbonCourseClassID,
    'gibbonSchoolYearID'  => $access['class']['gibbonSchoolYearID'],
    'gibbonPersonID'      => $session->get('gibbonPersonID'),
    'type'                => $type,
    'title'               => $title,
    'body'                => $body,
    'pinned'              => ($access['canManage'] && ($_POST['pinned'] ?? '') == 'Y') ? 'Y' : 'N',
    'parentsCanView'      => ($_POST['parentsCanView'] ?? '') == 'Y' ? 'Y' : 'N',
    'timestamp'           => date('Y-m-d H:i:s'),
    'timestampModified'   => date('Y-m-d H:i:s'),
    'timestampPublished'  => classStreamPublishTime($_POST['publish'] ?? 'now', $_POST['publishDate'] ?? '', $_POST['publishTime'] ?? ''),
    'notified'            => 'N',
];
$classStreamPostID = $postGateway->insert($data);

if (empty($classStreamPostID)) {
    header("Location: {$URLBack}&return=error2");
    exit;
}

$allSaved = classStreamSaveAttachments($container, $classStreamPostID, $_POST['links'] ?? '', $_FILES['attachments'] ?? []);

classStreamSyncCopies($container, $session, $classStreamPostID);
classStreamPublishDue($container, $session, $access['class']);

// A draft or a scheduled post is not on the stream yet: land on the Drafts page instead.
if (empty($data['timestampPublished']) || $data['timestampPublished'] > date('Y-m-d H:i:s')) {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream/stream_drafts.php&gibbonCourseClassID='.$gibbonCourseClassID;
    header("Location: {$URL}".($allSaved ? '&return=success0' : '&return=warning1'));
    exit;
}

header("Location: {$URL}".($allSaved ? '&return=success0' : '&return=warning1').'#post'.$classStreamPostID);

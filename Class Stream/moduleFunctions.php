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

use Gibbon\FileUploader;
use Gibbon\Domain\System\ModuleGateway;
use Gibbon\Services\Format;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Planner\PlannerEntryGateway;
use Gibbon\Module\ClassStream\Theme;
use Gibbon\Module\ClassStream\Domain\LinkGateway;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\CommentGateway;
use Gibbon\Module\ClassStream\Domain\PostAttachmentGateway;

/**
 * Which grouped View Class Streams action the current role holds: 'all' (any class),
 * 'department' (own classes plus read/comment in the departments' classes), 'my' (own classes
 * only), 'parent' (own children's classes, read only) or false. Fails closed: anything
 * other than an explicit _allClasses is narrower, so an unexpected permission state never opens
 * every class.
 */
function classStreamScope($guid, $connection2)
{
    $highest = getHighestGroupedAction($guid, '/modules/Class Stream/stream.php', $connection2);

    if ($highest === 'View Class Streams_allClasses') return 'all';
    if ($highest === 'View Class Streams_departmentClassesView') return 'department';
    if ($highest === 'View Class Streams_myClasses') return 'my';
    if ($highest === 'View Class Streams_myChildrensClasses') return 'parent';

    return false;
}

/**
 * The My Streams heading for a parent: the child's name, since the streams are the child's.
 */
function classStreamHeading(array $child): string
{
    return __m("{name}'s Streams", ['name' => Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true)]);
}

/**
 * This module's gibbonModuleID, needed to tag the rows it writes to core's gibbonDiscussion table.
 */
function classStreamModuleID($container)
{
    $module = $container->get(ModuleGateway::class)->selectBy(['name' => 'Class Stream'])->fetch();

    return $module['gibbonModuleID'] ?? null;
}

/**
 * The stream page URL for a class, the place every process script returns to.
 */
function classStreamViewURL($session, $gibbonCourseClassID, $gibbonPersonIDStudent = '')
{
    $url = $session->get('absoluteURL').'/index.php?q=/modules/Class Stream/stream_view.php&gibbonCourseClassID='.$gibbonCourseClassID;

    return $url.(!empty($gibbonPersonIDStudent) ? '&gibbonPersonIDStudent='.$gibbonPersonIDStudent : '');
}

/**
 * Attach links and uploaded files to a post. Shared by the add and edit process scripts.
 *
 * @param string $links  The links textarea: one URL per line. A line that is not a URL is skipped.
 * @param array  $files  The $_FILES entry of a multiple file input (name[], tmp_name[], ...).
 * @return bool  true when every link and file was saved, false when any was skipped or failed.
 */
function classStreamSaveAttachments($container, $classStreamPostID, $links, $files): bool
{
    $attachmentGateway = $container->get(PostAttachmentGateway::class);
    $sequence = $attachmentGateway->getNextSequenceNumber($classStreamPostID);
    $allSaved = true;

    foreach (preg_split('/\R/', (string) $links) as $line) {
        $line = trim($line);
        if ($line == '') continue;

        if (!filter_var($line, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $line)) {
            $allSaved = false;
            continue;
        }

        $data = [
            'classStreamPostID' => $classStreamPostID,
            'type'              => 'Link',
            'name'              => parse_url($line, PHP_URL_HOST) ?: $line,
            'location'          => $line,
            'sequenceNumber'    => $sequence++,
        ];
        $allSaved = $attachmentGateway->insert($data) && $allSaved;
    }

    if (!empty($files['name']) && is_array($files['name'])) {
        $fileUploader = $container->get(FileUploader::class);

        foreach ($files['name'] as $index => $name) {
            if (empty($name) || ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) == UPLOAD_ERR_NO_FILE) continue;

            $file = [
                'name'     => $name,
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_OK,
            ];
            $location = $fileUploader->uploadFromPost($file);

            if (empty($location)) {
                $allSaved = false;
                continue;
            }

            $data = [
                'classStreamPostID' => $classStreamPostID,
                'type'              => 'File',
                'name'              => basename($name),
                'location'          => $location,
                'sequenceNumber'    => $sequence++,
            ];
            $allSaved = $attachmentGateway->insert($data) && $allSaved;
        }
    }

    return $allSaved;
}

/**
 * Remove an uploaded file from disk, unless another attachment row still points at it.
 */
function classStreamRemoveFile($container, $session, array $attachment): void
{
    if ($attachment['type'] != 'File') return;

    $stillUsed = $container->get(PostAttachmentGateway::class)->countByLocation($attachment['location']) > 0;
    $path = $session->get('absolutePath').'/'.$attachment['location'];

    if (!$stillUsed && strpos($attachment['location'], 'uploads/') === 0 && is_file($path)) {
        unlink($path);
    }
}

/**
 * Tell the members of a class (its teachers and current students, not the author) about a new
 * post, through Gibbon's own notification system, when the Notify On Post setting allows it.
 * Core sends the email copy itself for people who asked for notification emails.
 */
function classStreamNotifyPost($container, $session, array $class, array $post): void
{
    if ($container->get(SettingGateway::class)->getSettingByScope('Class Stream', 'notifyOnPost') != 'Y') return;

    $postGateway = $container->get(PostGateway::class);
    $teachers = $postGateway->selectTeachersByClass($class['gibbonCourseClassID'])->fetchAll();
    $students = $postGateway->selectStudentsByClass($class['gibbonCourseClassID'])->fetchAll();

    $author = $post['gibbonPersonID'];
    $className = $class['course'].'.'.$class['class'];
    $authorRow = $container->get(\Gibbon\Domain\User\UserGateway::class)->getByID($author, ['preferredName', 'surname']);
    $personName = Format::name('', $authorRow['preferredName'] ?? '', $authorRow['surname'] ?? '', 'Staff', false, true);
    $text = $post['type'] == 'Material'
        ? __m('{person} posted new material in {class}: {title}', ['person' => $personName, 'class' => $className, 'title' => $post['title']])
        : __m('{person} posted an announcement in {class}', ['person' => $personName, 'class' => $className]);
    $link = '/index.php?q=/modules/Class Stream/stream_view.php&gibbonCourseClassID='.$class['gibbonCourseClassID'];

    $sender = $container->get(NotificationSender::class);
    $sent = [];
    foreach (array_merge($teachers, $students) as $person) {
        $id = intval($person['gibbonPersonID']);
        if ($id == intval($author) || isset($sent[$id])) continue;
        $sent[$id] = true;
        $sender->addNotification($person['gibbonPersonID'], $text, 'Class Stream', $link);
    }
    $sender->sendNotifications();
}

/**
 * Make or refresh the copy of a post in another class. The copy keeps the original's type, title,
 * body and pinned flag (or the overrides given, as Reuse does for author and time) and records the
 * original in classStreamPostIDSource. Its attachments are replaced with the original's, pointing
 * at the same uploaded files. Comments are never copied. Returns the copy's ID.
 */
function classStreamCopyPost($container, array $post, $gibbonCourseClassIDTarget, array $overrides = [])
{
    $postGateway = $container->get(PostGateway::class);
    $attachmentGateway = $container->get(PostAttachmentGateway::class);

    $target = $postGateway->getClassInfoByID($gibbonCourseClassIDTarget);
    if (empty($target)) return null;

    $data = [
        'gibbonCourseClassID'     => $gibbonCourseClassIDTarget,
        'gibbonSchoolYearID'      => $target['gibbonSchoolYearID'],
        'gibbonPersonID'          => $post['gibbonPersonID'],
        'type'                    => $post['type'],
        'title'                   => $post['title'],
        'body'                    => $post['body'],
        'pinned'                  => $post['pinned'],
        'parentsCanView'          => $post['parentsCanView'],
        'classStreamPostIDSource' => $post['classStreamPostID'],
        'timestamp'               => $post['timestamp'],
        'timestampModified'       => $post['timestampModified'],
        'timestampPublished'      => $post['timestampPublished'],
        'notified'                => 'N',
    ];
    $data = $overrides + $data;

    $copy = $postGateway->getCopyInClass($post['classStreamPostID'], $gibbonCourseClassIDTarget);
    if (!empty($copy)) {
        $copyID = $copy['classStreamPostID'];
        unset($data['gibbonCourseClassID'], $data['gibbonSchoolYearID'], $data['classStreamPostIDSource'], $data['timestamp'], $data['notified']);
        $postGateway->update($copyID, $data);
    } else {
        $copyID = $postGateway->insert($data);
    }
    if (empty($copyID)) return null;

    $attachmentGateway->deleteByPost($copyID);
    foreach ($attachmentGateway->selectAttachmentsByPost($post['classStreamPostID'])->fetchAll() as $attachment) {
        $row = [
            'classStreamPostID' => $copyID,
            'type'              => $attachment['type'],
            'name'              => $attachment['name'],
            'location'          => $attachment['location'],
            'sequenceNumber'    => $attachment['sequenceNumber'],
        ];
        $attachmentGateway->insert($row);
    }

    return $copyID;
}

/**
 * Is this post a copy kept in step by a mirror or sync link, i.e. its source lives in a class
 * that currently links into this one? A post reused from a past class also carries a source, but
 * no link, so it behaves as an original: it can be mirrored on, and it survives the old post.
 */
function classStreamIsMirrorCopy($container, array $post): bool
{
    if (empty($post['classStreamPostIDSource'])) return false;

    $source = $container->get(PostGateway::class)->getPostByID($post['classStreamPostIDSource']);
    if (empty($source)) return false;

    $targets = $container->get(LinkGateway::class)->selectTargetsByClass($source['gibbonCourseClassID'])->fetchKeyPair();

    return isset($targets[$post['gibbonCourseClassID']]);
}

/**
 * Push a post to every class its class links to. Only a post that is on the stream now is
 * synced: drafts stay private to their class, and a scheduled post reaches the linked classes when
 * its time comes (classStreamPublishDue calls this then). A copy is never synced on again, so
 * links cannot loop. Called after a post is added, edited, published, or has an attachment removed.
 */
function classStreamSyncCopies($container, $session, $classStreamPostID): void
{
    $post = $container->get(PostGateway::class)->getPostByID($classStreamPostID);
    if (empty($post) || classStreamIsMirrorCopy($container, $post)) return;
    if (empty($post['timestampPublished']) || $post['timestampPublished'] > date('Y-m-d H:i:s')) return;

    foreach ($container->get(LinkGateway::class)->selectTargetsByClass($post['gibbonCourseClassID'])->fetchAll() as $target) {
        classStreamCopyPost($container, $post, $target['gibbonCourseClassIDTarget']);
        $targetClass = $container->get(PostGateway::class)->getClassInfoByID($target['gibbonCourseClassIDTarget']);
        if (!empty($targetClass)) {
            classStreamPublishDue($container, $session, $targetClass);
        }
    }
}

/**
 * Work out when a post goes on the stream from the add/edit form: now, a draft (null), or the
 * date and time chosen. A schedule with no usable date and time falls back to a draft, never to
 * a silent publish.
 */
function classStreamPublishTime($publish, $date, $time)
{
    if ($publish == 'now') return date('Y-m-d H:i:s');
    if ($publish != 'schedule') return null;

    $date = Format::dateConvert($date);
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;

    $time = trim((string) $time);
    if (!preg_match('/^\d{1,2}:\d{2}/', $time)) $time = '08:00';

    return date('Y-m-d H:i:s', strtotime($date.' '.substr($time, 0, 5)));
}

/**
 * Tell the class about every post of theirs that is on the stream and has not been announced yet,
 * and push it to the linked classes. Run whenever a stream is rendered or a post is saved, so a
 * scheduled post is announced, and synced, on the first visit after its time without any
 * background job. Emails go out here too: core's NotificationSender mails every recipient who has
 * notification emails switched on, in the same request.
 */
function classStreamPublishDue($container, $session, array $class): void
{
    $postGateway = $container->get(PostGateway::class);
    foreach ($postGateway->selectPostsDueForNotification($class['gibbonCourseClassID'])->fetchAll() as $post) {
        // Marked first, so the sync below (which announces the linked classes in turn) can never
        // come back round to this post.
        $postGateway->update($post['classStreamPostID'], ['notified' => 'Y']);
        classStreamNotifyPost($container, $session, $class, $post);
        classStreamSyncCopies($container, $session, $post['classStreamPostID']);
    }
}

/**
 * Remove one post with everything hanging off it: attachments (files only when nothing else
 * still points at them), comments, and, for an original, its copies in other classes.
 */
function classStreamDeletePost($container, $session, array $post): bool
{
    $postGateway = $container->get(PostGateway::class);
    $attachmentGateway = $container->get(PostAttachmentGateway::class);

    // Copies kept by a live link go with the original. A post reused elsewhere from this one is
    // its own post now: it stays, and forgets where it came from.
    $targets = $container->get(LinkGateway::class)->selectTargetsByClass($post['gibbonCourseClassID'])->fetchKeyPair();
    foreach ($postGateway->selectCopiesBySource($post['classStreamPostID'])->fetchAll() as $copy) {
        if (isset($targets[$copy['gibbonCourseClassID']])) {
            classStreamDeletePost($container, $session, $copy);
        } else {
            $postGateway->update($copy['classStreamPostID'], ['classStreamPostIDSource' => null]);
        }
    }

    $attachments = $attachmentGateway->selectAttachmentsByPost($post['classStreamPostID'])->fetchAll();
    $attachmentGateway->deleteByPost($post['classStreamPostID']);
    foreach ($attachments as $attachment) {
        classStreamRemoveFile($container, $session, $attachment);
    }
    $container->get(CommentGateway::class)->deleteByPost($post['classStreamPostID']);

    return $postGateway->delete($post['classStreamPostID']);
}

/**
 * Turn a class row from PostGateway::selectClassesByPerson() into what the cards template needs:
 * the palette colour, the new-post count, and the teachers in the school's formal staff format
 * (the same one the stream's Teachers panel uses).
 */
function classStreamDecorateCard(array $class, array $newCounts): array
{
    $key = Theme::keyForClass($class['gibbonCourseClassID'], $class['colour']);
    $class['colourKey'] = $key;
    $class['colour'] = Theme::colour($key);
    $class['newCount'] = isset($newCounts[$class['gibbonCourseClassID']]) ? intval($newCounts[$class['gibbonCourseClassID']]) : intval($class['postCount']);

    $names = [];
    foreach (array_filter(explode(';;', (string) $class['teachers'])) as $teacher) {
        [$title, $preferredName, $surname] = array_pad(explode('|', $teacher, 3), 3, '');
        $names[] = Format::name($title, $preferredName, $surname, 'Staff', false, false);
    }
    $class['teachers'] = implode(', ', $names);

    return $class;
}

/**
 * Combine a required due date with an optional time into the datetime gibbonPlannerEntry expects,
 * the same rule core's own Planner add form uses: no time given defaults to 21:00.
 */
function classStreamHomeworkDueDateTime($date, $time): ?string
{
    $date = Format::dateConvert($date);
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;

    $time = trim((string) $time);
    if (!preg_match('/^\d{1,2}:\d{2}/', $time)) $time = '21:00';

    return $date.' '.substr($time, 0, 5).':00';
}

/**
 * A "Homework" stream post never becomes a classStreamPost: it writes straight into core's
 * gibbonPlannerEntry, so the stream shows it through the same homework card a Planner-made lesson
 * already gets (PlannerItemGateway), never a duplicate. Finds the class's most recent timetabled
 * lesson up to today and reuses its Planner entry if one exists there already, touching only the
 * homework fields (never its name, description, teacher's notes or outcomes); otherwise creates a
 * new entry dated to that lesson, or to today if the class has no resolvable timetable slot.
 * Returns the gibbonPlannerEntryID used, or null if the write failed.
 */
function classStreamSaveHomework($container, $gibbonPersonID, array $class, array $homework): ?string
{
    $postGateway = $container->get(PostGateway::class);
    $entryGateway = $container->get(PlannerEntryGateway::class);

    $slot = $postGateway->selectMostRecentLessonSlot($class['gibbonCourseClassID'], $class['gibbonSchoolYearID']);
    $date = $slot['date'] ?? date('Y-m-d');
    $timeStart = $slot['timeStart'] ?? null;
    $timeEnd = $slot['timeEnd'] ?? null;

    $match = ['gibbonCourseClassID' => $class['gibbonCourseClassID'], 'date' => $date];
    if (!empty($timeStart)) {
        $match['timeStart'] = $timeStart;
    }
    $existing = $entryGateway->selectBy($match)->fetch();

    $homeworkData = [
        'homework'                                => 'Y',
        'homeworkDueDateTime'                     => $homework['homeworkDueDateTime'],
        'homeworkDetails'                          => $homework['homeworkDetails'],
        'homeworkTimeCap'                          => $homework['homeworkTimeCap'],
        'homeworkLocation'                         => 'Out of Class',
        'homeworkSubmission'                       => $homework['homeworkSubmission'],
        'homeworkSubmissionDateOpen'                => $homework['homeworkSubmissionDateOpen'],
        'homeworkSubmissionType'                    => $homework['homeworkSubmissionType'],
        'homeworkSubmissionRequired'                => $homework['homeworkSubmissionRequired'],
        'homeworkSubmissionDrafts'                  => null,
        'homeworkCrowdAssess'                       => 'N',
        'homeworkCrowdAssessOtherTeachersRead'      => 'N',
        'homeworkCrowdAssessClassmatesRead'         => 'N',
        'homeworkCrowdAssessOtherStudentsRead'      => 'N',
        'homeworkCrowdAssessSubmitterParentsRead'   => 'N',
        'homeworkCrowdAssessClassmatesParentsRead'  => 'N',
        'homeworkCrowdAssessOtherParentsRead'       => 'N',
        'gibbonPersonIDLastEdit'                    => $gibbonPersonID,
    ];

    if (!empty($existing)) {
        return $entryGateway->update($existing['gibbonPlannerEntryID'], $homeworkData) ? $existing['gibbonPlannerEntryID'] : null;
    }

    $newEntry = [
        'gibbonCourseClassID'   => $class['gibbonCourseClassID'],
        'date'                  => $date,
        'timeStart'             => $timeStart,
        'timeEnd'               => $timeEnd,
        'name'                  => mb_substr($class['course'].'.'.$class['class'], 0, 50),
        'summary'               => '',
        'description'           => '',
        'teachersNotes'         => '',
        'viewableStudents'      => 'Y',
        'viewableParents'       => 'Y',
        'gibbonPersonIDCreator' => $gibbonPersonID,
    ] + $homeworkData;

    $gibbonPlannerEntryID = $entryGateway->insert($newEntry);

    return !empty($gibbonPlannerEntryID) ? $gibbonPlannerEntryID : null;
}

/**
 * The classes the current person may link this class to, or reuse posts from: with the
 * _allClasses action any class of the year, otherwise the classes they teach. Keyed by
 * gibbonCourseClassID, value "COURSE.CLASS - Course name". $gibbonSchoolYearID null = every year.
 */
function classStreamLinkableClasses($container, $session, $scope, $gibbonSchoolYearID = null): array
{
    $options = [];

    if ($scope == 'all' && !empty($gibbonSchoolYearID)) {
        foreach ($container->get(\Gibbon\Domain\Timetable\CourseGateway::class)->selectClassesBySchoolYear($gibbonSchoolYearID)->fetchAll() as $class) {
            $options[$class['gibbonCourseClassID']] = $class['course'].'.'.$class['class'].' - '.$class['courseName'];
        }
        return $options;
    }

    foreach ($container->get(PostGateway::class)->selectTaughtClassesByPerson($session->get('gibbonPersonID'))->fetchAll() as $class) {
        if (!empty($gibbonSchoolYearID) && $class['gibbonSchoolYearID'] != $gibbonSchoolYearID) continue;
        $options[$class['gibbonCourseClassID']] = $class['name'].' - '.$class['courseName'];
    }

    return $options;
}

<?php
namespace Gibbon\Module\ClassStream;

use Gibbon\Http\Url;
use Gibbon\Data\Validator;
use Gibbon\Session\TokenHandler;
use Gibbon\Contracts\Services\Session;
use League\Container\ContainerAwareTrait;
use League\Container\ContainerAwareInterface;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseEnrolmentGateway;
use Gibbon\Module\ClassStream\Domain\LinkGateway;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\ViewGateway;
use Gibbon\Module\ClassStream\Domain\CommentGateway;
use Gibbon\Module\ClassStream\Domain\PlannerItemGateway;
use Gibbon\Module\ClassStream\Domain\AssessmentItemGateway;
use Gibbon\Module\ClassStream\Domain\PostAttachmentGateway;

/**
 * Stream Renderer
 *
 * Builds the HTML of one class's stream (banner, side panel, timeline of posts, Planner rows and
 * markbook assessments, comments) for an access context already resolved by StreamAccess. Used by stream_view.php and
 * by the dashboard hook, so the two can never drift apart.
 *
 * @version v0.3.00
 * @since   v0.3.00
 */
class StreamRenderer implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const PAGE_SIZE = 15;

    protected $session;
    protected $settingGateway;
    protected $validator;
    protected $tokenHandler;
    protected $streamAccess;
    protected $postGateway;
    protected $viewGateway;
    protected $commentGateway;
    protected $plannerItemGateway;
    protected $assessmentItemGateway;
    protected $attachmentGateway;
    protected $enrolmentGateway;
    protected $linkGateway;

    public function __construct(
        Session $session,
        SettingGateway $settingGateway,
        Validator $validator,
        TokenHandler $tokenHandler,
        StreamAccess $streamAccess,
        PostGateway $postGateway,
        ViewGateway $viewGateway,
        CommentGateway $commentGateway,
        PlannerItemGateway $plannerItemGateway,
        AssessmentItemGateway $assessmentItemGateway,
        PostAttachmentGateway $attachmentGateway,
        CourseEnrolmentGateway $enrolmentGateway,
        LinkGateway $linkGateway
    ) {
        $this->session = $session;
        $this->settingGateway = $settingGateway;
        $this->validator = $validator;
        $this->tokenHandler = $tokenHandler;
        $this->streamAccess = $streamAccess;
        $this->postGateway = $postGateway;
        $this->viewGateway = $viewGateway;
        $this->commentGateway = $commentGateway;
        $this->plannerItemGateway = $plannerItemGateway;
        $this->assessmentItemGateway = $assessmentItemGateway;
        $this->attachmentGateway = $attachmentGateway;
        $this->enrolmentGateway = $enrolmentGateway;
        $this->linkGateway = $linkGateway;
    }

    /**
     * @param array  $access     From StreamAccess::forClass()
     * @param array  $links      Quick links [['name' => , 'url' => ], ...], decided by the caller
     *                           because they depend on isActionAccessible() for other modules.
     * @param bool   $plannerAccessible  Whether the Planner's Homework page may be linked.
     * @param object $page       The core Page, for fetchFromTemplate().
     * @param int    $pageNumber Timeline page, 1-based.
     * @param string $gibbonPersonIDStudent  The child, when a parent is looking.
     * @param string $gibbonModuleID  This module's ID, for the gibbonDiscussion rows.
     * @param array  $extra      Extra template variables (the dashboard hook adds a heading).
     */
    public function render(array $access, array $links, bool $plannerAccessible, $page, int $pageNumber, $gibbonPersonIDStudent, $gibbonModuleID, array $extra = []): string
    {
        $class = $access['class'];
        $gibbonCourseClassID = $class['gibbonCourseClassID'];
        $className = $class['course'].'.'.$class['class'];
        $viewingAs = $access['viewingAs'];
        $isParent = $access['role'] == 'Parent';
        $childID = $isParent ? intval($access['childID']) : 0;
        $gibbonPersonID = $this->session->get('gibbonPersonID');

        // The person has now seen this stream: the new-post counts start from here.
        $this->viewGateway->touch($gibbonPersonID, $gibbonCourseClassID);

        // Scheduled posts whose time has come are announced on this visit.
        classStreamPublishDue($this->getContainer(), $this->session, $class);
        $draftCount = $access['canManage'] ? $this->postGateway->countPendingPostsByClass($gibbonCourseClassID) : 0;

        // PARENT LENS
        // What the school lets a parent see of the students' side of a stream. Every rule is applied
        // here in PHP before anything reaches the template, so a hidden post or a redacted name is
        // never sent to the browser at all.
        $studentIDs = [];
        if ($isParent) {
            $studentIDs = array_map('intval', array_column($this->postGateway->selectStudentsByClass($gibbonCourseClassID)->fetchAll(), 'gibbonPersonID'));
        }
        $isStudentAuthor = function ($personID) use ($isParent, $studentIDs) {
            return $isParent && in_array(intval($personID), $studentIDs);
        };
        $isOtherStudent = function ($personID) use ($isStudentAuthor, $childID) {
            return $isStudentAuthor($personID) && intval($personID) != $childID;
        };
        $redact = function (array $row) {
            $row['title'] = '';
            $row['preferredName'] = __m('Student');
            $row['surname'] = '';
            $row['image_240'] = '';
            return $row;
        };
        $parentView = $access['parentView'];

        // POSTS
        $posts = $this->postGateway->selectPostsByClass($gibbonCourseClassID, $gibbonModuleID)->fetchAll();

        // Staff see where a mirrored or synced copy came from; students just see the post.
        $mirrorSources = $access['isStaff'] ? $this->linkGateway->selectSourcesByClass($gibbonCourseClassID)->fetchKeyPair() : [];

        if ($isParent && $parentView == 'None') {
            // None: only what teachers post.
            $posts = array_values(array_filter($posts, function ($post) use ($isStudentAuthor) {
                return !$isStudentAuthor($post['gibbonPersonID']);
            }));
        } elseif ($isParent && $parentView == 'Own child') {
            // Own child: another student's post is not shown at all.
            $posts = array_values(array_filter($posts, function ($post) use ($isOtherStudent) {
                return !$isOtherStudent($post['gibbonPersonID']);
            }));
        }

        // PLANNER ROWS
        // Virtual: read straight from gibbonPlannerEntry at render time, never stored. The school
        // setting says what may appear at all; the class teacher's Class Settings choice says how, or
        // whether, it shows.
        $plannerDisplay = $access['settings']['plannerDisplay'];
        $showHomework = $this->settingGateway->getSettingByScope('Class Stream', 'showHomework') == 'Y';
        $showLessons = $this->settingGateway->getSettingByScope('Class Stream', 'showLessons') == 'Y';

        $plannerItems = [];
        if ($plannerDisplay != 'Hidden' && ($showHomework || $showLessons)) {
            $plannerItems = $this->plannerItemGateway->selectItemsByClass($gibbonCourseClassID, $viewingAs, $showLessons)->fetchAll();
        }

        $assessmentItems = [];
        $assessmentTypes = $access['settings']['assessmentTypes'];
        if ($access['settings']['showAssessments'] == 'Y' && (!is_array($assessmentTypes) || !empty($assessmentTypes))) {
            $assessmentItems = $this->assessmentItemGateway->selectAssessmentsByClass($gibbonCourseClassID, $viewingAs, $assessmentTypes)->fetchAll();
        }

        // TIMELINE
        // Pinned posts first, then posts and planner rows together by date, newest first; paged in PHP.
        $timeline = [];
        foreach ($posts as $post) {
            $post['kind'] = 'post';
            $post['sort'] = ($post['pinned'] == 'Y' ? '9' : '0').$post['timestampPublished'];
            $timeline[] = $post;
        }
        foreach ($plannerItems as $item) {
            $item['kind'] = 'planner';
            $item['sort'] = '0'.$item['date'].' '.($item['timeStart'] ?: '00:00:00');
            $item['isHomework'] = $item['homework'] == 'Y';
            $timeline[] = $item;
        }
        foreach ($assessmentItems as $item) {
            $item['kind'] = 'assessment';
            $item['sort'] = '0'.$item['date'].' 00:00:00 '.sprintf('%014d', (int) $item['gibbonMarkbookColumnID']);
            if (trim((string) $item['name']) == '') {
                $item['name'] = __m('Assessment');
            }
            $timeline[] = $item;
        }
        usort($timeline, function ($a, $b) {
            return strcmp($b['sort'], $a['sort']);
        });

        $pageCount = max(1, (int) ceil(count($timeline) / self::PAGE_SIZE));
        $pageNumber = min($pageCount, max(1, $pageNumber));
        $pageItems = array_slice($timeline, ($pageNumber - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        // ATTACHMENTS AND COMMENTS, for this page only
        $postIDs = [];
        $plannerIDs = [];
        $assessmentIDs = [];
        foreach ($pageItems as $item) {
            if ($item['kind'] == 'post') $postIDs[] = $item['classStreamPostID'];
            elseif ($item['kind'] == 'planner') $plannerIDs[] = $item['gibbonPlannerEntryID'];
            else $assessmentIDs[] = $item['gibbonMarkbookColumnID'];
        }

        $attachments = $this->attachmentGateway->selectAttachmentsByPosts($postIDs)->fetchGrouped();
        $postComments = $this->commentGateway->selectCommentsByTargets(CommentGateway::TARGET_POST, $postIDs, $gibbonModuleID)->fetchGrouped();
        $plannerComments = $this->commentGateway->selectCommentsByTargets(CommentGateway::TARGET_PLANNER, $plannerIDs, $gibbonModuleID)->fetchGrouped();
        $assessmentComments = $this->commentGateway->selectCommentsByTargets(CommentGateway::TARGET_ASSESSMENT, $assessmentIDs, $gibbonModuleID)->fetchGrouped();

        $streamAccess = $this->streamAccess;
        $prepareComments = function (array $comments) use ($streamAccess, $access, $isParent, $parentView, $isOtherStudent, $redact) {
            if ($isParent && $parentView == 'None') {
                return [];
            }
            $prepared = [];
            foreach ($comments as $comment) {
                if ($isOtherStudent($comment['gibbonPersonID'])) {
                    if ($parentView != 'All redacted') continue;
                    $comment = $redact($comment);
                }
                $comment['canDelete'] = $streamAccess->canDeleteComment($access, $comment);
                $prepared[] = $comment;
            }
            return $prepared;
        };

        foreach ($pageItems as $index => $item) {
            if ($item['kind'] == 'post') {
                $id = $item['classStreamPostID'];
                if ($isOtherStudent($item['gibbonPersonID'])) {
                    $item = $redact($item);
                }
                $item['body'] = $this->validator->sanitizeRichText($item['body']);
                $item['attachments'] = $attachments[$id] ?? [];
                $item['comments'] = $prepareComments($postComments[intval($id)] ?? []);
                $item['canEdit'] = $this->streamAccess->canEditPost($access, $item);
                $item['mirroredFrom'] = !empty($item['sourceClassID']) && isset($mirrorSources[$item['sourceClassID']]) ? $mirrorSources[$item['sourceClassID']] : '';
                $item['edited'] = !empty($item['timestampModified']) && $item['timestampModified'] != $item['timestamp'];
            } elseif ($item['kind'] == 'planner') {
                $id = $item['gibbonPlannerEntryID'];
                $item['details'] = $plannerDisplay == 'Details'
                    ? $this->validator->sanitizeRichText($item['isHomework'] ? $item['homeworkDetails'] : $item['description'])
                    : '';
                $item['comments'] = $prepareComments($plannerComments[intval($id)] ?? []);
                // The Planner's parent branch needs search=<child> or it refuses the page.
                $item['plannerURL'] = Url::fromModuleRoute('Planner', 'planner_view_full')->withQueryParams(['gibbonPlannerEntryID' => $id, 'viewBy' => 'class', 'gibbonCourseClassID' => $gibbonCourseClassID] + ($isParent ? ['search' => $access['childID']] : []));
            } else {
                $id = $item['gibbonMarkbookColumnID'];
                $item['details'] = $this->validator->sanitizeRichText($item['description']);
                $item['comments'] = $prepareComments($assessmentComments[intval($id)] ?? []);
            }
            $pageItems[$index] = $item;
        }

        // SIDE PANEL
        $teachers = $this->postGateway->selectTeachersByClass($gibbonCourseClassID)->fetchAll();
        $studentCount = $this->enrolmentGateway->getClassStudentCount($gibbonCourseClassID);

        $upcoming = $showHomework && $plannerDisplay != 'Hidden'
            ? $this->plannerItemGateway->selectUpcomingHomeworkByClass($gibbonCourseClassID, $viewingAs)->fetchAll()
            : [];

        $viewURL = 'stream_view.php&gibbonCourseClassID='.$gibbonCourseClassID.(!empty($gibbonPersonIDStudent) ? '&gibbonPersonIDStudent='.$gibbonPersonIDStudent : '');

        // One CSRF token and one nonce shared by every small form on the page (comment boxes,
        // delete buttons). A submit reloads the page, which issues a fresh nonce.
        return $page->fetchFromTemplate('stream.twig.html', $extra + [
            'gibbonCourseClassID' => $gibbonCourseClassID,
            'gibbonPersonIDStudent' => $gibbonPersonIDStudent,
            'viewURL'      => $viewURL,
            'class'        => $class,
            'className'    => $className,
            'access'       => $access,
            'draftCount'   => $draftCount,
            'settings'     => $access['settings'],
            'items'        => $pageItems,
            'pageNumber'   => $pageNumber,
            'pageCount'    => $pageCount,
            'teachers'     => $teachers,
            'studentCount' => $studentCount,
            'upcoming'     => $upcoming,
            'plannerAccessible' => $plannerAccessible,
            'plannerSearch' => $isParent ? '&search='.$access['childID'] : '',
            'links'        => $links,
            'csrftoken'    => $this->tokenHandler->getCSRF(),
            'nonce'        => $this->tokenHandler->getNonce(),
            'address'      => $this->session->get('address'),
            'gibbonPersonID' => $gibbonPersonID,
        ]);
    }

    /**
     * The quick links for a class, given which core pages the person may open. Kept here so the
     * page and the hook build the same list.
     */
    public static function quickLinks($gibbonCourseClassID, bool $plannerAccessible, bool $participantsAccessible, bool $markbookAccessible, $gibbonPersonIDStudent = ''): array
    {
        // A parent's Planner pages want search=<child>; everyone else's ignore it.
        $child = !empty($gibbonPersonIDStudent) ? ['search' => $gibbonPersonIDStudent] : [];
        $links = [];
        if ($plannerAccessible) {
            $links[] = ['name' => __('Planner'), 'url' => Url::fromModuleRoute('Planner', 'planner')->withQueryParams(['gibbonCourseClassID' => $gibbonCourseClassID, 'viewBy' => 'class'] + $child)];
            $links[] = ['name' => __('Homework'), 'url' => Url::fromModuleRoute('Planner', 'planner_deadlines')->withQueryParams(['gibbonCourseClassIDFilter' => $gibbonCourseClassID] + $child)];
        }
        if ($participantsAccessible) {
            $links[] = ['name' => __('Participants'), 'url' => Url::fromModuleRoute('Departments', 'department_course_class')->withQueryParam('gibbonCourseClassID', $gibbonCourseClassID)];
        }
        if ($markbookAccessible) {
            $links[] = ['name' => __('Markbook'), 'url' => Url::fromModuleRoute('Markbook', 'markbook_view')->withQueryParam('gibbonCourseClassID', $gibbonCourseClassID)];
        }

        return $links;
    }
}

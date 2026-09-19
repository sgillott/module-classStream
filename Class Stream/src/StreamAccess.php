<?php
namespace Gibbon\Module\ClassStream;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Timetable\CourseGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\ClassStream\Domain\PostGateway;
use Gibbon\Module\ClassStream\Domain\MuteGateway;
use Gibbon\Module\ClassStream\Domain\ClassSettingsGateway;

/**
 * Stream Access
 *
 * Answers "what may the current person do in this class's stream?" in one place, so every page
 * and process script applies the same rules. The action permission (checked by isActionAccessible
 * before this runs) only says the person may enter the module's pages; this decides per class.
 *
 * Rules:
 *  - a Teacher, Assistant or Technician of the class is staff of it: view, post, comment, manage
 *    (customise, people, mute, edit or delete any post or comment);
 *  - a person with the _allClasses action has the same rights in every class;
 *  - a Student of the class: view; comment if the class allows it; post if the class allows it;
 *    neither if muted;
 *  - a person with the _departmentClassesView action who is staff of the department that owns the
 *    class's course (gibbonDepartmentStaff): view and comment, nothing else;
 *  - a Parent (the _myChildrensClasses action) of a Student of the class: view only, through the
 *    lens the school's Parent View setting chooses; never post or comment;
 *  - anyone else: no access.
 *
 * @version v0.2.00
 * @since   v0.1.00
 */
class StreamAccess
{
    const STAFF_ROLES = ['Teacher', 'Assistant', 'Technician'];

    protected $session;
    protected $settingGateway;
    protected $courseGateway;
    protected $studentGateway;
    protected $postGateway;
    protected $muteGateway;
    protected $classSettingsGateway;

    public function __construct(
        Session $session,
        SettingGateway $settingGateway,
        CourseGateway $courseGateway,
        StudentGateway $studentGateway,
        PostGateway $postGateway,
        MuteGateway $muteGateway,
        ClassSettingsGateway $classSettingsGateway
    ) {
        $this->session = $session;
        $this->settingGateway = $settingGateway;
        $this->courseGateway = $courseGateway;
        $this->studentGateway = $studentGateway;
        $this->postGateway = $postGateway;
        $this->muteGateway = $muteGateway;
        $this->classSettingsGateway = $classSettingsGateway;
    }

    /**
     * Resolve the current person's access to one class.
     *
     * @param string $scope 'all', 'department', 'my' or 'parent', from classStreamScope()
     * @param string $gibbonCourseClassID
     * @param string $gibbonPersonIDStudent  For the 'parent' scope: the child whose class this is.
     * @return array|null  null when the class does not exist or the person may not see it.
     *   Keys: class (CourseGateway::getCourseClassInfoByID row), role, viewingAs (Staff, Student
     *   or Parent, for the Planner's visibility flags), isStaff, canPost, canComment, canManage,
     *   muted, parentView (the school setting, parents only), childID (parents only),
     *   settings (colour, colourKey, headerImage, studentAccess, plannerDisplay, with defaults).
     */
    public function forClass(string $scope, $gibbonCourseClassID, $gibbonPersonIDStudent = null): ?array
    {
        if (empty($gibbonCourseClassID)) {
            return null;
        }

        // getCourseClassInfoByID, not getCourseClassDetails: the latter inner-joins gibbonDepartment
        // and so returns nothing for a course with no department.
        $class = $this->courseGateway->getCourseClassInfoByID($gibbonCourseClassID);
        if (empty($class)) {
            return null;
        }
        $class['gibbonCourseClassID'] = $gibbonCourseClassID;

        $settings = $this->settingsForClass($gibbonCourseClassID);

        if ($scope == 'parent') {
            return $this->forParent($class, $settings, $gibbonPersonIDStudent);
        }

        $gibbonPersonID = $this->session->get('gibbonPersonID');
        $role = $this->postGateway->getRoleInClass($gibbonCourseClassID, $gibbonPersonID);
        $role = is_string($role) ? $role : '';

        $isStaff = in_array($role, self::STAFF_ROLES) || $scope == 'all';
        $isStudent = $role == 'Student';

        if (!$isStaff && !$isStudent) {
            if ($scope == 'department' && $this->postGateway->isDepartmentStaffForClass($gibbonCourseClassID, $gibbonPersonID)) {
                return [
                    'class'      => $class,
                    'role'       => 'Department',
                    'viewingAs'  => 'Staff',
                    'isStaff'    => false,
                    'canManage'  => false,
                    'canPost'    => false,
                    'canComment' => true,
                    'muted'      => false,
                    'parentView' => '',
                    'childID'    => null,
                    'settings'   => $settings,
                ];
            }
            return null;
        }

        $muted = $isStudent && $this->muteGateway->isMuted($gibbonCourseClassID, $gibbonPersonID);

        return [
            'class'      => $class,
            'role'       => !empty($role) ? $role : 'Admin',
            'viewingAs'  => $isStaff ? 'Staff' : 'Student',
            'isStaff'    => $isStaff,
            'canManage'  => $isStaff,
            'canPost'    => $isStaff || (!$muted && $settings['studentAccess'] == 'Post'),
            'canComment' => $isStaff || (!$muted && in_array($settings['studentAccess'], ['Post', 'Comment'])),
            'muted'      => $muted,
            'parentView' => '',
            'childID'    => null,
            'settings'   => $settings,
        ];
    }

    /**
     * A parent sees a class only through a child who is a current Student of it, and only when
     * the family record grants them child data access (core's own rule, applied by
     * StudentGateway::selectActiveStudentsByFamilyAdult).
     */
    protected function forParent(array $class, array $settings, $gibbonPersonIDStudent): ?array
    {
        if (empty($gibbonPersonIDStudent)) {
            return null;
        }

        $children = $this->childrenOfCurrentParent();
        if (!isset($children[intval($gibbonPersonIDStudent)])) {
            return null;
        }

        $role = $this->postGateway->getRoleInClass($class['gibbonCourseClassID'], $gibbonPersonIDStudent);
        if ($role !== 'Student') {
            return null;
        }

        $parentView = $this->settingGateway->getSettingByScope('Class Stream', 'parentView');

        return [
            'class'      => $class,
            'role'       => 'Parent',
            'viewingAs'  => 'Parent',
            'isStaff'    => false,
            'canManage'  => false,
            'canPost'    => false,
            'canComment' => false,
            'muted'      => false,
            'parentView' => in_array($parentView, ['Own child', 'All redacted', 'None']) ? $parentView : 'Own child',
            'childID'    => $children[intval($gibbonPersonIDStudent)]['gibbonPersonID'],
            'settings'   => $settings,
        ];
    }

    /**
     * The current person's children this year, keyed by integer gibbonPersonID (the column is
     * zerofilled, so string keys would not match a value typed in a URL).
     */
    public function childrenOfCurrentParent(): array
    {
        $rows = $this->studentGateway->selectActiveStudentsByFamilyAdult($this->session->get('gibbonSchoolYearID'), $this->session->get('gibbonPersonID'))->fetchAll();

        $children = [];
        foreach ($rows as $row) {
            $children[intval($row['gibbonPersonID'])] = $row;
        }

        return $children;
    }

    /**
     * The class's stored settings with module defaults filled in.
     */
    public function settingsForClass($gibbonCourseClassID): array
    {
        $stored = $this->classSettingsGateway->getSettingsByClass($gibbonCourseClassID);

        $studentAccess = $stored['studentAccess'] ?? '';
        if (empty($studentAccess)) {
            $studentAccess = $this->settingGateway->getSettingByScope('Class Stream', 'defaultStudentAccess');
        }

        $colourKey = Theme::keyForClass($gibbonCourseClassID, $stored['colour'] ?? null);

        return [
            'colourKey'      => $colourKey,
            'colour'         => Theme::colour($colourKey),
            'colourStored'   => $stored['colour'] ?? '',
            'headerImage'    => $stored['headerImage'] ?? '',
            'studentAccess'  => in_array($studentAccess, ['Post', 'Comment', 'None']) ? $studentAccess : 'Comment',
            'studentAccessStored' => $stored['studentAccess'] ?? '',
            'plannerDisplay' => $stored['plannerDisplay'] ?? 'Condensed',
        ];
    }

    /**
     * May the person change or remove this post? Its author, or anyone who manages the class.
     */
    public function canEditPost(array $access, array $post): bool
    {
        return $access['canManage'] || (!empty($access['canPost']) && $post['gibbonPersonID'] == $this->session->get('gibbonPersonID'));
    }

    /**
     * May the person remove this comment? Its author, or anyone who manages the class.
     */
    public function canDeleteComment(array $access, array $comment): bool
    {
        return $access['canManage'] || (!empty($access['canComment']) && $comment['gibbonPersonID'] == $this->session->get('gibbonPersonID'));
    }
}

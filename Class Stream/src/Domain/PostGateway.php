<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Post Gateway
 *
 * Posts on a class stream, and the per-person class list the My Streams page is built from.
 *
 * @version v0.1.00
 * @since   v0.1.00
 */
class PostGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'classStreamPost';
    private static $primaryKey = 'classStreamPostID';
    private static $searchableColumns = [];

    /**
     * Every published post of one class (drafts and posts scheduled for later are left out), pinned
     * first then newest first, with the author joined and the comment count from gibbonDiscussion.
     * Not paginated here: the stream page merges these with the Planner's virtual rows into one
     * timeline and pages that in PHP.
     */
    public function selectPostsByClass($gibbonCourseClassID, $gibbonModuleID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonModuleID' => $gibbonModuleID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT classStreamPost.*, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240,
                    source.gibbonCourseClassID AS sourceClassID,
                    (SELECT COUNT(*) FROM gibbonDiscussion WHERE gibbonDiscussion.foreignTable='classStreamPost' AND gibbonDiscussion.gibbonModuleID=:gibbonModuleID AND gibbonDiscussion.foreignTableID=classStreamPost.classStreamPostID) AS commentCount
                FROM classStreamPost
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=classStreamPost.gibbonPersonID)
                LEFT JOIN classStreamPost AS source ON (source.classStreamPostID=classStreamPost.classStreamPostIDSource)
                WHERE classStreamPost.gibbonCourseClassID=:gibbonCourseClassID
                AND classStreamPost.timestampPublished IS NOT NULL AND classStreamPost.timestampPublished<=:now
                ORDER BY classStreamPost.pinned DESC, classStreamPost.timestampPublished DESC, classStreamPost.classStreamPostID DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * The copies of a post that live in other classes (made by a mirror or sync link, or by Reuse).
     */
    public function selectCopiesBySource($classStreamPostID)
    {
        $data = ['classStreamPostID' => $classStreamPostID];
        $sql = "SELECT * FROM classStreamPost WHERE classStreamPostIDSource=:classStreamPostID";

        return $this->db()->select($sql, $data);
    }

    /**
     * The copy of a post in one class, if there is one.
     */
    public function getCopyInClass($classStreamPostID, $gibbonCourseClassID)
    {
        $data = ['classStreamPostID' => $classStreamPostID, 'gibbonCourseClassID' => $gibbonCourseClassID];
        $sql = "SELECT * FROM classStreamPost WHERE classStreamPostIDSource=:classStreamPostID AND gibbonCourseClassID=:gibbonCourseClassID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * The classes a person is a Teacher of, in any school year, newest year first: the sources
     * the Reuse page offers, and the classes a teacher may link to.
     */
    public function selectTaughtClassesByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT gibbonCourseClass.gibbonCourseClassID, gibbonCourse.gibbonSchoolYearID, gibbonSchoolYear.name AS yearName, gibbonSchoolYear.sequenceNumber,
                    CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) AS name, gibbonCourse.name AS courseName,
                    (SELECT COUNT(*) FROM classStreamPost WHERE classStreamPost.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND classStreamPost.timestampPublished<=:now) AS postCount
                FROM gibbonCourseClassPerson
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                WHERE gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonCourseClassPerson.role='Teacher'
                ORDER BY gibbonSchoolYear.sequenceNumber DESC, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->db()->select($sql, $data);
    }

    /**
     * A class's drafts (no publish time) and scheduled posts (publish time still ahead), newest
     * first, for the Drafts page.
     */
    public function selectPendingPostsByClass($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT classStreamPost.*, gibbonPerson.preferredName, gibbonPerson.surname,
                    (SELECT COUNT(*) FROM classStreamPostAttachment WHERE classStreamPostAttachment.classStreamPostID=classStreamPost.classStreamPostID) AS attachmentCount
                FROM classStreamPost
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=classStreamPost.gibbonPersonID)
                WHERE classStreamPost.gibbonCourseClassID=:gibbonCourseClassID
                AND (classStreamPost.timestampPublished IS NULL OR classStreamPost.timestampPublished>:now)
                ORDER BY classStreamPost.timestampPublished IS NULL DESC, classStreamPost.timestampPublished, classStreamPost.timestampModified DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Every post of a class whatever its state, newest first, for the Reuse page.
     */
    public function selectAllPostsByClass($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID];
        $sql = "SELECT classStreamPost.* FROM classStreamPost WHERE gibbonCourseClassID=:gibbonCourseClassID ORDER BY COALESCE(timestampPublished, timestamp) DESC, classStreamPostID DESC";

        return $this->db()->select($sql, $data);
    }

    public function countPendingPostsByClass($gibbonCourseClassID): int
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT COUNT(*) FROM classStreamPost WHERE gibbonCourseClassID=:gibbonCourseClassID AND (timestampPublished IS NULL OR timestampPublished>:now)";

        return (int) $this->db()->selectOne($sql, $data);
    }

    /**
     * Posts of a class that are now on the stream but whose class has not been told yet: published
     * straight away, or scheduled and now due.
     */
    public function selectPostsDueForNotification($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT * FROM classStreamPost WHERE gibbonCourseClassID=:gibbonCourseClassID AND notified='N' AND timestampPublished IS NOT NULL AND timestampPublished<=:now";

        return $this->db()->select($sql, $data);
    }

    public function getPostByID($classStreamPostID)
    {
        $data = ['classStreamPostID' => $classStreamPostID];
        $sql = "SELECT classStreamPost.*, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM classStreamPost
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=classStreamPost.gibbonPersonID)
                WHERE classStreamPostID=:classStreamPostID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * The classes a person belongs to in a school year (any live role), with the class colour,
     * the teachers' names, the post count and the newest post time, for the My Streams cards.
     */
    public function selectClassesByPerson($gibbonSchoolYearID, $gibbonPersonID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT gibbonCourseClass.gibbonCourseClassID, gibbonCourse.name AS courseName, gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourseClassPerson.role,
                    classStreamClass.colour, classStreamClass.headerImage,
                    (SELECT GROUP_CONCAT(CONCAT_WS('|', teacher.title, teacher.preferredName, teacher.surname) ORDER BY teacher.surname SEPARATOR ';;')
                        FROM gibbonCourseClassPerson AS teacherRole
                        JOIN gibbonPerson AS teacher ON (teacher.gibbonPersonID=teacherRole.gibbonPersonID)
                        WHERE teacherRole.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND teacherRole.role='Teacher' AND teacher.status='Full') AS teachers,
                    (SELECT COUNT(*) FROM classStreamPost WHERE classStreamPost.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND classStreamPost.timestampPublished<=:now) AS postCount,
                    (SELECT MAX(timestampPublished) FROM classStreamPost WHERE classStreamPost.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND classStreamPost.timestampPublished<=:now) AS lastPost
                FROM gibbonCourseClassPerson
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                LEFT JOIN classStreamClass ON (classStreamClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonCourseClassPerson.role NOT LIKE '% - Left'
                ORDER BY gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->db()->select($sql, $data);
    }

    /**
     * The classes of the departments a person is staff of (any gibbonDepartmentStaff role), in a
     * school year, in the same shape as selectClassesByPerson() so the cards template serves both.
     * The person's own classes are left out; those come from selectClassesByPerson().
     */
    public function selectDepartmentClassesByPerson($gibbonSchoolYearID, $gibbonPersonID)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT gibbonCourseClass.gibbonCourseClassID, gibbonCourse.name AS courseName, gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, 'Department' AS role,
                    gibbonDepartment.name AS department,
                    classStreamClass.colour, classStreamClass.headerImage,
                    (SELECT GROUP_CONCAT(CONCAT_WS('|', teacher.title, teacher.preferredName, teacher.surname) ORDER BY teacher.surname SEPARATOR ';;')
                        FROM gibbonCourseClassPerson AS teacherRole
                        JOIN gibbonPerson AS teacher ON (teacher.gibbonPersonID=teacherRole.gibbonPersonID)
                        WHERE teacherRole.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND teacherRole.role='Teacher' AND teacher.status='Full') AS teachers,
                    (SELECT COUNT(*) FROM classStreamPost WHERE classStreamPost.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND classStreamPost.timestampPublished<=:now) AS postCount,
                    (SELECT MAX(timestampPublished) FROM classStreamPost WHERE classStreamPost.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND classStreamPost.timestampPublished<=:now) AS lastPost
                FROM gibbonDepartmentStaff
                JOIN gibbonDepartment ON (gibbonDepartment.gibbonDepartmentID=gibbonDepartmentStaff.gibbonDepartmentID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN classStreamClass ON (classStreamClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                WHERE gibbonDepartmentStaff.gibbonPersonID=:gibbonPersonID
                AND gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND NOT EXISTS (SELECT 1 FROM gibbonCourseClassPerson AS own
                    WHERE own.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND own.gibbonPersonID=:gibbonPersonID AND own.role NOT LIKE '% - Left')
                GROUP BY gibbonCourseClass.gibbonCourseClassID
                ORDER BY gibbonDepartment.name, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->db()->select($sql, $data);
    }

    /**
     * Is the person staff of the department that owns this class's course?
     */
    public function isDepartmentStaffForClass($gibbonCourseClassID, $gibbonPersonID): bool
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT COUNT(*)
                FROM gibbonCourseClass
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonDepartmentStaff ON (gibbonDepartmentStaff.gibbonDepartmentID=gibbonCourse.gibbonDepartmentID)
                WHERE gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID
                AND gibbonDepartmentStaff.gibbonPersonID=:gibbonPersonID";

        return (int) $this->db()->selectOne($sql, $data) > 0;
    }

    /**
     * The person's timetabled lessons on one date, in period order: the classes they teach or
     * attend, from the active timetable, honouring a student's period exceptions. The caller picks
     * the lesson happening now, or the next one, from the period times.
     */
    public function selectTimetabledClassesByPersonAndDate($gibbonSchoolYearID, $gibbonPersonID, $date)
    {
        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID, 'date' => $date];
        $sql = "SELECT gibbonCourseClass.gibbonCourseClassID, gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourse.name AS courseName,
                    gibbonTTColumnRow.name AS period, gibbonTTColumnRow.timeStart, gibbonTTColumnRow.timeEnd
                FROM gibbonTTDayDate
                JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayDate.gibbonTTDayID)
                JOIN gibbonTT ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID)
                JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonTTDayID=gibbonTTDay.gibbonTTDayID)
                JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonTTDayRowClass.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID AND gibbonCourseClassPerson.role NOT LIKE '% - Left')
                LEFT JOIN gibbonTTDayRowClassException ON (gibbonTTDayRowClassException.gibbonTTDayRowClassID=gibbonTTDayRowClass.gibbonTTDayRowClassID AND gibbonTTDayRowClassException.gibbonPersonID=:gibbonPersonID)
                WHERE gibbonTTDayDate.date=:date
                AND gibbonTT.active='Y'
                AND gibbonTT.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonTTDayRowClassException.gibbonTTDayRowClassExceptionID IS NULL
                GROUP BY gibbonTTDayRowClass.gibbonTTDayRowClassID
                ORDER BY gibbonTTColumnRow.timeStart, gibbonCourse.nameShort";

        return $this->db()->select($sql, $data);
    }

    /**
     * The current students of a class, one row each, straight from the class membership. No join
     * to gibbonStudentEnrolment: core's CourseClassPersonGateway::selectStudentsByClass() joins it
     * without a year filter and returns a student once per year they were ever enrolled.
     */
    public function selectStudentsByClass($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'today' => date('Y-m-d')];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.image_240
                FROM gibbonCourseClassPerson
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                WHERE gibbonCourseClassPerson.gibbonCourseClassID=:gibbonCourseClassID
                AND gibbonCourseClassPerson.role='Student'
                AND gibbonPerson.status='Full'
                AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=:today)
                AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=:today)
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * The person's live role in a class, Teacher-type roles first so a person who is somehow both
     * a teacher and a student of a class is treated as the teacher.
     */
    public function getRoleInClass($gibbonCourseClassID, $gibbonPersonID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT role FROM gibbonCourseClassPerson
                WHERE gibbonCourseClassID=:gibbonCourseClassID AND gibbonPersonID=:gibbonPersonID
                AND role NOT LIKE '% - Left'
                ORDER BY FIELD(role, 'Teacher', 'Assistant', 'Technician', 'Student', 'Parent')
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }
}

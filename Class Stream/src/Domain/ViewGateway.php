<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * View Gateway
 *
 * When a person last opened each class stream, and from that how many posts are new to them.
 *
 * @version v0.3.00
 * @since   v0.3.00
 */
class ViewGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'classStreamView';
    private static $primaryKey = 'classStreamViewID';

    /**
     * Record that the person has just opened this class's stream.
     */
    public function touch($gibbonPersonID, $gibbonCourseClassID)
    {
        $now = date('Y-m-d H:i:s');
        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonCourseClassID' => $gibbonCourseClassID, 'timestamp' => $now, 'visitCount' => 1];
        $sql = "INSERT INTO classStreamView SET gibbonPersonID=:gibbonPersonID, gibbonCourseClassID=:gibbonCourseClassID, timestamp=:timestamp, visitCount=:visitCount
                ON DUPLICATE KEY UPDATE timestamp=:timestamp, visitCount=visitCount+1";

        return $this->db()->insert($sql, $data);
    }

    /**
     * The current students of a class with their stream activity: last visit, visit count, and how
     * many comments and posts they have made in it. Students who have never visited come back with
     * NULLs, not missing rows.
     */
    public function selectInsightsByClass($gibbonCourseClassID, $gibbonModuleID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonModuleID' => $gibbonModuleID, 'today' => date('Y-m-d')];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.image_240,
                    classStreamView.timestamp AS lastVisit, classStreamView.visitCount,
                    (SELECT COUNT(*) FROM gibbonDiscussion
                        JOIN classStreamPost ON (gibbonDiscussion.foreignTable='classStreamPost' AND gibbonDiscussion.foreignTableID=classStreamPost.classStreamPostID)
                        WHERE gibbonDiscussion.gibbonModuleID=:gibbonModuleID AND gibbonDiscussion.gibbonPersonID=gibbonPerson.gibbonPersonID AND classStreamPost.gibbonCourseClassID=:gibbonCourseClassID)
                    + (SELECT COUNT(*) FROM gibbonDiscussion
                        JOIN gibbonPlannerEntry ON (gibbonDiscussion.foreignTable='gibbonPlannerEntry' AND gibbonDiscussion.foreignTableID=gibbonPlannerEntry.gibbonPlannerEntryID)
                        WHERE gibbonDiscussion.gibbonModuleID=:gibbonModuleID AND gibbonDiscussion.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonPlannerEntry.gibbonCourseClassID=:gibbonCourseClassID) AS commentCount,
                    (SELECT COUNT(*) FROM classStreamPost WHERE classStreamPost.gibbonCourseClassID=:gibbonCourseClassID AND classStreamPost.gibbonPersonID=gibbonPerson.gibbonPersonID) AS postCount
                FROM gibbonCourseClassPerson
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                LEFT JOIN classStreamView ON (classStreamView.gibbonPersonID=gibbonPerson.gibbonPersonID AND classStreamView.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
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
     * gibbonCourseClassID => number of posts by other people since the person's last visit, for
     * every class they have a row for. A class never opened is absent: the caller treats that as
     * "all posts are new".
     */
    public function selectNewCountsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT classStreamView.gibbonCourseClassID AS groupBy,
                    (SELECT COUNT(*) FROM classStreamPost
                        WHERE classStreamPost.gibbonCourseClassID=classStreamView.gibbonCourseClassID
                        AND classStreamPost.timestampPublished>classStreamView.timestamp
                        AND classStreamPost.timestampPublished<=:now
                        AND classStreamPost.gibbonPersonID<>classStreamView.gibbonPersonID) AS newCount
                FROM classStreamView
                WHERE classStreamView.gibbonPersonID=:gibbonPersonID";

        return $this->db()->select($sql, $data);
    }
}

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
     * "all posts are new". Parent callers supply the child and Parent View mode so hidden activity
     * never contributes to the badge.
     */
    public function selectNewCountsByPerson($gibbonPersonID, $gibbonPersonIDStudent = null, $parentView = null)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'now' => date('Y-m-d H:i:s')];
        $parentView = !empty($gibbonPersonIDStudent) && in_array($parentView, ['Own child', 'All redacted', 'None'], true) ? $parentView : null;
        $parentPostJoin = '';
        $parentPostFilter = '';

        if ($parentView !== null) {
            $data['gibbonPersonIDStudent'] = $gibbonPersonIDStudent;
            $data['today'] = date('Y-m-d');
            $parentPostJoin = " LEFT JOIN classStreamPost AS newSource ON (newSource.classStreamPostID=newPost.classStreamPostIDSource)";
            $studentAuthor = "EXISTS (SELECT 1 FROM gibbonCourseClassPerson AS studentRole
                JOIN gibbonPerson AS studentAuthor ON (studentAuthor.gibbonPersonID=studentRole.gibbonPersonID)
                WHERE studentRole.gibbonCourseClassID=COALESCE(newSource.gibbonCourseClassID, newPost.gibbonCourseClassID)
                AND studentRole.gibbonPersonID=newPost.gibbonPersonID
                AND studentRole.role='Student'
                AND studentAuthor.status='Full'
                AND (studentAuthor.dateStart IS NULL OR studentAuthor.dateStart<=:today)
                AND (studentAuthor.dateEnd IS NULL OR studentAuthor.dateEnd>=:today))";

            $parentPostFilter = " AND (newPost.parentsCanView IS NULL OR newPost.parentsCanView<>'N')";
            if ($parentView == 'Own child') {
                $parentPostFilter .= " AND (newPost.gibbonPersonID=:gibbonPersonIDStudent OR NOT ".$studentAuthor.")";
            } elseif ($parentView == 'None') {
                $parentPostFilter .= " AND NOT ".$studentAuthor;
            }
        }

        $sql = "SELECT classStreamView.gibbonCourseClassID AS groupBy,
                    (SELECT COUNT(*) FROM classStreamPost AS newPost".$parentPostJoin."
                        WHERE newPost.gibbonCourseClassID=classStreamView.gibbonCourseClassID
                        AND newPost.timestampPublished>classStreamView.timestamp
                        AND newPost.timestampPublished<=:now
                        AND newPost.gibbonPersonID<>classStreamView.gibbonPersonID".$parentPostFilter.") AS newCount
                FROM classStreamView
                ".($parentView !== null ? "LEFT JOIN classStreamClass ON (classStreamClass.gibbonCourseClassID=classStreamView.gibbonCourseClassID)" : '')."
                WHERE classStreamView.gibbonPersonID=:gibbonPersonID";

        if ($parentView !== null) {
            $sql .= " AND (classStreamClass.visibleToParents IS NULL OR classStreamClass.visibleToParents='Y')";
        }

        return $this->db()->select($sql, $data);
    }
}

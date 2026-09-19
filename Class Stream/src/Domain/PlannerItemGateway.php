<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Planner Item Gateway
 *
 * Read-only view of core's gibbonPlannerEntry for one class: the homework (and, when the school
 * allows it, the lessons) that appear on a stream as virtual rows. Nothing is copied or written;
 * the stream shows whatever the Planner holds at the moment the page renders, filtered by the
 * Planner's own viewableStudents / viewableParents flags for the role looking at it.
 *
 * @version v0.2.00
 * @since   v0.2.00
 */
class PlannerItemGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPlannerEntry';
    private static $primaryKey = 'gibbonPlannerEntryID';

    const COLS = "gibbonPlannerEntry.gibbonPlannerEntryID, gibbonPlannerEntry.gibbonCourseClassID, gibbonPlannerEntry.name, gibbonPlannerEntry.date, gibbonPlannerEntry.timeStart, gibbonPlannerEntry.timeEnd,
        gibbonPlannerEntry.description, gibbonPlannerEntry.homework, gibbonPlannerEntry.homeworkDueDateTime, gibbonPlannerEntry.homeworkDetails, gibbonPlannerEntry.homeworkSubmission,
        gibbonPlannerEntry.viewableStudents, gibbonPlannerEntry.viewableParents,
        creator.gibbonPersonID AS gibbonPersonIDCreator, creator.title, creator.preferredName, creator.surname, creator.image_240";

    /**
     * Lessons of a class up to today, newest first. $viewingAs is Staff, Student or Parent.
     * With $includeLessons every lesson is returned; otherwise only lessons that set homework.
     */
    public function selectItemsByClass($gibbonCourseClassID, $viewingAs, $includeLessons)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'today' => date('Y-m-d')];
        $sql = "SELECT ".self::COLS."
                FROM gibbonPlannerEntry
                JOIN gibbonPerson AS creator ON (creator.gibbonPersonID=gibbonPlannerEntry.gibbonPersonIDCreator)
                WHERE gibbonPlannerEntry.gibbonCourseClassID=:gibbonCourseClassID
                AND gibbonPlannerEntry.date<=:today"
                .($includeLessons ? '' : " AND gibbonPlannerEntry.homework='Y'")
                .$this->visibilityClause($viewingAs)."
                ORDER BY gibbonPlannerEntry.date DESC, gibbonPlannerEntry.timeStart DESC";

        return $this->db()->select($sql, $data);
    }

    /**
     * Homework of a class still to be handed in, soonest due first.
     */
    public function selectUpcomingHomeworkByClass($gibbonCourseClassID, $viewingAs, $limit = 5)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'now' => date('Y-m-d H:i:s')];
        $sql = "SELECT ".self::COLS."
                FROM gibbonPlannerEntry
                JOIN gibbonPerson AS creator ON (creator.gibbonPersonID=gibbonPlannerEntry.gibbonPersonIDCreator)
                WHERE gibbonPlannerEntry.gibbonCourseClassID=:gibbonCourseClassID
                AND gibbonPlannerEntry.homework='Y'
                AND gibbonPlannerEntry.homeworkDueDateTime>=:now"
                .$this->visibilityClause($viewingAs)."
                ORDER BY gibbonPlannerEntry.homeworkDueDateTime
                LIMIT ".intval($limit);

        return $this->db()->select($sql, $data);
    }

    public function getItemByID($gibbonPlannerEntryID)
    {
        $data = ['gibbonPlannerEntryID' => $gibbonPlannerEntryID];
        $sql = "SELECT ".self::COLS."
                FROM gibbonPlannerEntry
                JOIN gibbonPerson AS creator ON (creator.gibbonPersonID=gibbonPlannerEntry.gibbonPersonIDCreator)
                WHERE gibbonPlannerEntry.gibbonPlannerEntryID=:gibbonPlannerEntryID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Is this lesson visible to the role, by the Planner's own flags?
     */
    public static function isVisibleTo(array $item, $viewingAs): bool
    {
        if ($viewingAs == 'Student') return $item['viewableStudents'] == 'Y';
        if ($viewingAs == 'Parent') return $item['viewableParents'] == 'Y';

        return true;
    }

    protected function visibilityClause($viewingAs): string
    {
        if ($viewingAs == 'Student') return " AND gibbonPlannerEntry.viewableStudents='Y'";
        if ($viewingAs == 'Parent') return " AND gibbonPlannerEntry.viewableParents='Y'";

        return '';
    }
}

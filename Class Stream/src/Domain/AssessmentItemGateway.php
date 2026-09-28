<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Read-only view of the markbook columns that appear as assessment rows on a class stream.
 */
class AssessmentItemGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMarkbookColumn';
    private static $primaryKey = 'gibbonMarkbookColumnID';

    /**
     * The distinct assessment types configured in one class's Markbook Weightings or already used
     * by its Markbook columns. With no Weightings, the existing column types remain the fallback.
     */
    public function selectAssessmentTypesByClass($gibbonCourseClassID)
    {
        $data = [
            'gibbonCourseClassIDWeight' => $gibbonCourseClassID,
            'gibbonCourseClassIDColumn' => $gibbonCourseClassID,
        ];
        $sql = "SELECT type FROM (
                    SELECT type FROM gibbonMarkbookWeight
                    WHERE gibbonCourseClassID=:gibbonCourseClassIDWeight
                    AND type IS NOT NULL AND type<>''
                    UNION
                    SELECT type FROM gibbonMarkbookColumn
                    WHERE gibbonCourseClassID=:gibbonCourseClassIDColumn
                    AND type IS NOT NULL AND type<>''
                ) AS classAssessmentType
                ORDER BY type";

        return $this->db()->select($sql, $data);
    }

    public function selectAssessmentsByClass($gibbonCourseClassID, $viewingAs, ?array $types = null)
    {
        if (is_array($types) && empty($types)) {
            return $this->db()->select('SELECT NULL AS gibbonMarkbookColumnID WHERE 1=0');
        }

        $data = ['gibbonCourseClassID' => $gibbonCourseClassID];
        $typeFilter = '';
        if (is_array($types)) {
            $placeholders = [];
            foreach (array_values($types) as $index => $type) {
                $key = 'assessmentType'.$index;
                $data[$key] = $type;
                $placeholders[] = ':'.$key;
            }
            $typeFilter = ' AND mc.type IN ('.implode(',', $placeholders).')';
        }

        $sql = "SELECT mc.gibbonMarkbookColumnID, mc.gibbonCourseClassID, mc.gibbonPlannerEntryID, mc.name, mc.description,
                    mc.type, mc.date, mc.viewableStudents, mc.viewableParents,
                    plannerEntry.date AS plannerDate, mc.attachment AS attachmentPath,
                    creator.gibbonPersonID AS gibbonPersonIDCreator, creator.title, creator.preferredName, creator.surname
                FROM gibbonMarkbookColumn AS mc
                LEFT JOIN gibbonPlannerEntry AS plannerEntry ON (plannerEntry.gibbonPlannerEntryID=mc.gibbonPlannerEntryID)
                JOIN gibbonPerson AS creator ON (creator.gibbonPersonID=mc.gibbonPersonIDCreator)
                WHERE mc.gibbonCourseClassID=:gibbonCourseClassID
                AND mc.date IS NOT NULL".$typeFilter.$this->visibilityClause($viewingAs)."
                ORDER BY mc.date DESC, mc.sequenceNumber DESC, mc.gibbonMarkbookColumnID DESC";

        return $this->db()->select($sql, $data);
    }

    public function getAssessmentByID($gibbonMarkbookColumnID)
    {
        $data = ['gibbonMarkbookColumnID' => $gibbonMarkbookColumnID];
        $sql = "SELECT gibbonMarkbookColumnID, gibbonCourseClassID, gibbonPlannerEntryID, type, date, viewableStudents, viewableParents
                FROM gibbonMarkbookColumn
                WHERE gibbonMarkbookColumnID=:gibbonMarkbookColumnID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Assessments linked to one Planner entry. Used to tell whether an assessment row has replaced
     * that lesson row on the stream.
     */
    public function selectAssessmentsByPlannerEntry($gibbonPlannerEntryID)
    {
        $data = ['gibbonPlannerEntryID' => $gibbonPlannerEntryID];
        $sql = "SELECT gibbonMarkbookColumnID, gibbonCourseClassID, gibbonPlannerEntryID, type, date, viewableStudents, viewableParents
                FROM gibbonMarkbookColumn
                WHERE gibbonPlannerEntryID=:gibbonPlannerEntryID";

        return $this->db()->select($sql, $data);
    }

    public static function isVisibleTo(array $item, $viewingAs): bool
    {
        if ($viewingAs == 'Student') return ($item['viewableStudents'] ?? 'N') == 'Y';
        if ($viewingAs == 'Parent') return ($item['viewableParents'] ?? 'N') == 'Y';

        return true;
    }

    /**
     * Is this assessment enabled for the stream and visible to the current role?
     */
    public static function isVisibleOnStream(array $item, $viewingAs, $showAssessments, ?array $types = null): bool
    {
        if ($showAssessments != 'Y' || empty($item['date'])) return false;
        if (!self::isVisibleTo($item, $viewingAs)) return false;
        if (is_array($types) && !in_array($item['type'] ?? '', $types, true)) return false;

        return true;
    }

    protected function visibilityClause($viewingAs): string
    {
        if ($viewingAs == 'Student') return " AND mc.viewableStudents='Y'";
        if ($viewingAs == 'Parent') return " AND mc.viewableParents='Y'";

        return '';
    }
}

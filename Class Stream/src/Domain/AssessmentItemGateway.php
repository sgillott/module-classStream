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
                    plannerEntry.date AS plannerDate,
                    COALESCE(file.filePath, mc.attachment) AS attachmentPath, file.fileName AS attachmentName,
                    creator.gibbonPersonID AS gibbonPersonIDCreator, creator.title, creator.preferredName, creator.surname
                FROM gibbonMarkbookColumn AS mc
                LEFT JOIN gibbonPlannerEntry AS plannerEntry ON (plannerEntry.gibbonPlannerEntryID=mc.gibbonPlannerEntryID)
                LEFT JOIN gibbonFilePointer AS filePointer ON (filePointer.foreignTable='gibbonMarkbookColumn' AND filePointer.foreignTableID=mc.gibbonMarkbookColumnID AND filePointer.foreignColumn='attachment')
                LEFT JOIN gibbonFile AS file ON (file.gibbonFileID=filePointer.gibbonFileID)
                JOIN gibbonPerson AS creator ON (creator.gibbonPersonID=mc.gibbonPersonIDCreator)
                WHERE mc.gibbonCourseClassID=:gibbonCourseClassID
                AND mc.date IS NOT NULL".$typeFilter.$this->visibilityClause($viewingAs)."
                ORDER BY mc.date DESC, mc.sequenceNumber DESC, mc.gibbonMarkbookColumnID DESC";

        return $this->db()->select($sql, $data);
    }

    public function getAssessmentByID($gibbonMarkbookColumnID)
    {
        $data = ['gibbonMarkbookColumnID' => $gibbonMarkbookColumnID];
        $sql = "SELECT gibbonMarkbookColumnID, gibbonCourseClassID, viewableStudents, viewableParents
                FROM gibbonMarkbookColumn
                WHERE gibbonMarkbookColumnID=:gibbonMarkbookColumnID";

        return $this->db()->selectOne($sql, $data);
    }

    public static function isVisibleTo(array $item, $viewingAs): bool
    {
        if ($viewingAs == 'Student') return ($item['viewableStudents'] ?? 'N') == 'Y';
        if ($viewingAs == 'Parent') return ($item['viewableParents'] ?? 'N') == 'Y';

        return true;
    }

    protected function visibilityClause($viewingAs): string
    {
        if ($viewingAs == 'Student') return " AND mc.viewableStudents='Y'";
        if ($viewingAs == 'Parent') return " AND mc.viewableParents='Y'";

        return '';
    }
}

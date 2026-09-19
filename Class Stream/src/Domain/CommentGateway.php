<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Comment Gateway
 *
 * Class comments live in core's general-purpose gibbonDiscussion table. A comment hangs off one
 * of two targets: a stream post (foreignTable 'classStreamPost') or a Planner lesson shown on the
 * stream (foreignTable 'gibbonPlannerEntry'). Every row this module writes carries its own
 * gibbonModuleID, and every read filters on it, so rows another module may keep against the same
 * tables are never touched. Core's DiscussionGateway reads one context at a time; this adds the
 * bulk reads a stream page needs.
 *
 * @version v0.2.00
 * @since   v0.1.00
 */
class CommentGateway extends QueryableGateway
{
    use TableAware;

    const TARGET_POST = 'classStreamPost';
    const TARGET_PLANNER = 'gibbonPlannerEntry';

    private static $tableName = 'gibbonDiscussion';
    private static $primaryKey = 'gibbonDiscussionID';

    /**
     * Comments for a set of targets of one kind, oldest first, grouped by the target's numeric ID.
     * The group key is cast to a plain number on purpose: foreignTableID is a 14-digit zerofill,
     * classStreamPostID a 12-digit one, so their padded strings never match as array keys.
     */
    public function selectCommentsByTargets($foreignTable, array $foreignTableIDs, $gibbonModuleID)
    {
        if (empty($foreignTableIDs)) {
            return $this->db()->select("SELECT NULL AS groupBy WHERE 1=0");
        }

        $data = ['foreignTable' => $foreignTable, 'gibbonModuleID' => $gibbonModuleID];
        $placeholders = [];
        foreach (array_values($foreignTableIDs) as $index => $id) {
            $data['id'.$index] = $id;
            $placeholders[] = ':id'.$index;
        }

        $sql = "SELECT CAST(gibbonDiscussion.foreignTableID AS UNSIGNED) AS groupBy, gibbonDiscussion.gibbonDiscussionID, gibbonDiscussion.foreignTable, gibbonDiscussion.foreignTableID, gibbonDiscussion.gibbonPersonID, gibbonDiscussion.comment, gibbonDiscussion.timestamp,
                    gibbonPerson.title, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                FROM gibbonDiscussion
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonDiscussion.gibbonPersonID)
                WHERE gibbonDiscussion.foreignTable=:foreignTable
                AND gibbonDiscussion.gibbonModuleID=:gibbonModuleID
                AND gibbonDiscussion.foreignTableID IN (".implode(',', $placeholders).")
                ORDER BY gibbonDiscussion.timestamp, gibbonDiscussion.gibbonDiscussionID";

        return $this->db()->select($sql, $data);
    }

    public function getCommentByID($gibbonDiscussionID, $gibbonModuleID)
    {
        $data = ['gibbonDiscussionID' => $gibbonDiscussionID, 'gibbonModuleID' => $gibbonModuleID];
        $sql = "SELECT * FROM gibbonDiscussion WHERE gibbonDiscussionID=:gibbonDiscussionID AND gibbonModuleID=:gibbonModuleID";

        return $this->db()->selectOne($sql, $data);
    }

    public function insertComment($foreignTable, $foreignTableID, $gibbonModuleID, $gibbonPersonID, $comment)
    {
        $data = [
            'foreignTable'   => $foreignTable,
            'foreignTableID' => $foreignTableID,
            'gibbonModuleID' => $gibbonModuleID,
            'gibbonPersonID' => $gibbonPersonID,
            'type'           => 'Comment',
            'comment'        => $comment,
            'timestamp'      => date('Y-m-d H:i:s'),
        ];

        return $this->insert($data);
    }

    public function deleteByPost($classStreamPostID): bool
    {
        return $this->deleteWhere(['foreignTable' => self::TARGET_POST, 'foreignTableID' => $classStreamPostID]);
    }
}

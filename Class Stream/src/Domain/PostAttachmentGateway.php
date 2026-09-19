<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Post Attachment Gateway
 *
 * Links and uploaded files attached to a post.
 *
 * @version v0.1.00
 * @since   v0.1.00
 */
class PostAttachmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'classStreamPostAttachment';
    private static $primaryKey = 'classStreamPostAttachmentID';

    /**
     * Attachments for a set of posts in one query. IDs are bound one by one: the column is
     * zerofilled, so a FIND_IN_SET against a PHP-built list would not match.
     */
    public function selectAttachmentsByPosts(array $classStreamPostIDs)
    {
        if (empty($classStreamPostIDs)) {
            return $this->db()->select("SELECT NULL AS groupBy WHERE 1=0");
        }

        $data = [];
        $placeholders = [];
        foreach (array_values($classStreamPostIDs) as $index => $id) {
            $data['id'.$index] = $id;
            $placeholders[] = ':id'.$index;
        }

        $sql = "SELECT classStreamPostID AS groupBy, classStreamPostAttachment.*
                FROM classStreamPostAttachment
                WHERE classStreamPostID IN (".implode(',', $placeholders).")
                ORDER BY classStreamPostID, sequenceNumber, classStreamPostAttachmentID";

        return $this->db()->select($sql, $data);
    }

    public function selectAttachmentsByPost($classStreamPostID)
    {
        $data = ['classStreamPostID' => $classStreamPostID];
        $sql = "SELECT * FROM classStreamPostAttachment WHERE classStreamPostID=:classStreamPostID ORDER BY sequenceNumber, classStreamPostAttachmentID";

        return $this->db()->select($sql, $data);
    }

    public function getNextSequenceNumber($classStreamPostID): int
    {
        $data = ['classStreamPostID' => $classStreamPostID];
        $sql = "SELECT COALESCE(MAX(sequenceNumber), -1) + 1 FROM classStreamPostAttachment WHERE classStreamPostID=:classStreamPostID";

        return (int) $this->db()->selectOne($sql, $data);
    }

    /**
     * How many attachment rows point at one uploaded file. A reused post shares the file of the
     * post it was copied from, so a file is only removed from disk when this drops to zero.
     */
    public function countByLocation($location): int
    {
        $data = ['location' => $location];
        $sql = "SELECT COUNT(*) FROM classStreamPostAttachment WHERE type='File' AND location=:location";

        return (int) $this->db()->selectOne($sql, $data);
    }

    public function deleteByPost($classStreamPostID)
    {
        $data = ['classStreamPostID' => $classStreamPostID];
        $sql = "DELETE FROM classStreamPostAttachment WHERE classStreamPostID=:classStreamPostID";

        return $this->db()->delete($sql, $data);
    }
}

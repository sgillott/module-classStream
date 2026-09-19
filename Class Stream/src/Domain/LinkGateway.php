<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Link Gateway
 *
 * One-way links between classes. A post made in the source class is copied into each target
 * class. "Mirror" on the Customise page is one row (A -> B); "Sync" is two (A -> B and B -> A).
 *
 * @version v0.4.00
 * @since   v0.4.00
 */
class LinkGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'classStreamLink';
    private static $primaryKey = 'classStreamLinkID';

    /**
     * gibbonCourseClassIDTarget => class label for every class this one copies into.
     */
    public function selectTargetsByClass($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID];
        $sql = "SELECT classStreamLink.gibbonCourseClassIDTarget, CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) AS name
                FROM classStreamLink
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=classStreamLink.gibbonCourseClassIDTarget)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                WHERE classStreamLink.gibbonCourseClassID=:gibbonCourseClassID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    /**
     * gibbonCourseClassID => class label for every class that copies into this one.
     */
    public function selectSourcesByClass($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID];
        $sql = "SELECT classStreamLink.gibbonCourseClassID, CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) AS name
                FROM classStreamLink
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=classStreamLink.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                WHERE classStreamLink.gibbonCourseClassIDTarget=:gibbonCourseClassID
                ORDER BY name";

        return $this->db()->select($sql, $data);
    }

    public function link($gibbonCourseClassID, $gibbonCourseClassIDTarget, $gibbonPersonID)
    {
        $data = [
            'gibbonCourseClassID'       => $gibbonCourseClassID,
            'gibbonCourseClassIDTarget' => $gibbonCourseClassIDTarget,
            'gibbonPersonIDCreated'     => $gibbonPersonID,
            'timestamp'                 => date('Y-m-d H:i:s'),
        ];

        return $this->insertAndUpdate($data, ['gibbonPersonIDCreated' => $gibbonPersonID]);
    }

    public function unlink($gibbonCourseClassID, $gibbonCourseClassIDTarget): bool
    {
        return $this->deleteWhere(['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonCourseClassIDTarget' => $gibbonCourseClassIDTarget]);
    }
}

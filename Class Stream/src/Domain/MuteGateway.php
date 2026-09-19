<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Mute Gateway
 *
 * Students muted in one class. A muted student can read but not post or comment there.
 *
 * @version v0.1.00
 * @since   v0.1.00
 */
class MuteGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'classStreamMute';
    private static $primaryKey = 'classStreamMuteID';

    /**
     * gibbonPersonID => timestamp for every muted person in a class.
     */
    public function selectMutedByClass($gibbonCourseClassID)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID];
        $sql = "SELECT gibbonPersonID, timestamp FROM classStreamMute WHERE gibbonCourseClassID=:gibbonCourseClassID";

        return $this->db()->select($sql, $data);
    }

    public function isMuted($gibbonCourseClassID, $gibbonPersonID): bool
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT COUNT(*) FROM classStreamMute WHERE gibbonCourseClassID=:gibbonCourseClassID AND gibbonPersonID=:gibbonPersonID";

        return (int) $this->db()->selectOne($sql, $data) > 0;
    }

    public function mute($gibbonCourseClassID, $gibbonPersonID, $gibbonPersonIDMutedBy)
    {
        $data = [
            'gibbonCourseClassID'   => $gibbonCourseClassID,
            'gibbonPersonID'        => $gibbonPersonID,
            'gibbonPersonIDMutedBy' => $gibbonPersonIDMutedBy,
            'timestamp'             => date('Y-m-d H:i:s'),
        ];

        return $this->insertAndUpdate($data, ['gibbonPersonIDMutedBy' => $gibbonPersonIDMutedBy]);
    }

    public function unmute($gibbonCourseClassID, $gibbonPersonID): bool
    {
        return $this->deleteWhere(['gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $gibbonPersonID]);
    }
}

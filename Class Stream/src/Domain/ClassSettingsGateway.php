<?php
namespace Gibbon\Module\ClassStream\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Class Settings Gateway
 *
 * The per-class appearance and behaviour row. A class with no row uses the module defaults.
 *
 * @version v0.1.00
 * @since   v0.1.00
 */
class ClassSettingsGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'classStreamClass';
    private static $primaryKey = 'classStreamClassID';

    public function getSettingsByClass($gibbonCourseClassID): array
    {
        $row = $this->selectBy(['gibbonCourseClassID' => $gibbonCourseClassID])->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * Insert or update the one row for a class. gibbonCourseClassID is a unique key, so
     * insertAndUpdate's ON DUPLICATE KEY UPDATE lands on the existing row.
     */
    public function saveSettingsByClass($gibbonCourseClassID, array $settings)
    {
        $data = ['gibbonCourseClassID' => $gibbonCourseClassID] + $settings;

        return $this->insertAndUpdate($data, $settings);
    }
}

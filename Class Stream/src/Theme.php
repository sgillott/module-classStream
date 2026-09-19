<?php
namespace Gibbon\Module\ClassStream;

/**
 * Theme
 *
 * The fixed palette a teacher picks a class colour from, and the fallback colour for a class that
 * has never been customised. Colours are stored by key, not by hex, so the palette can change
 * later without touching stored rows. Sixteen entries, spread round the hue circle so neighbouring
 * swatches differ clearly, each dark enough for white text on the banner.
 *
 * @version v0.6.00
 * @since   v0.1.00
 */
class Theme
{
    const PALETTE = [
        'red'     => ['name' => 'Red',     'hex' => '#c5221f', 'light' => '#fad2cf'],
        'orange'  => ['name' => 'Orange',  'hex' => '#e8710a', 'light' => '#fedfc8'],
        'amber'   => ['name' => 'Amber',   'hex' => '#d99a00', 'light' => '#fdebc0'],
        'lime'    => ['name' => 'Lime',    'hex' => '#689f38', 'light' => '#dcedc8'],
        'green'   => ['name' => 'Green',   'hex' => '#1e8e3e', 'light' => '#ceead6'],
        'emerald' => ['name' => 'Emerald', 'hex' => '#2e9e6b', 'light' => '#cff0e0'],
        'teal'    => ['name' => 'Teal',    'hex' => '#129eaf', 'light' => '#cbf0f8'],
        'sky'     => ['name' => 'Sky',     'hex' => '#4285f4', 'light' => '#e8f0fe'],
        'blue'    => ['name' => 'Blue',    'hex' => '#1967d2', 'light' => '#d2e3fc'],
        'indigo'  => ['name' => 'Indigo',  'hex' => '#3949ab', 'light' => '#d6d9f2'],
        'purple'  => ['name' => 'Purple',  'hex' => '#8430ce', 'light' => '#e9d2fd'],
        'magenta' => ['name' => 'Magenta', 'hex' => '#a8329e', 'light' => '#f2d2ef'],
        'pink'    => ['name' => 'Pink',    'hex' => '#d01884', 'light' => '#fbd0e5'],
        'rose'    => ['name' => 'Rose',    'hex' => '#c2185b', 'light' => '#f8d0de'],
        'brown'   => ['name' => 'Brown',   'hex' => '#795548', 'light' => '#e4d6d1'],
        'gray'    => ['name' => 'Grey',    'hex' => '#5f6368', 'light' => '#e8eaed'],
    ];

    /**
     * The palette key for a class: the stored key when it is valid, otherwise a key derived from
     * the class ID so an uncustomised class always gets the same colour.
     */
    public static function keyForClass($gibbonCourseClassID, $storedKey = null): string
    {
        if (!empty($storedKey) && isset(self::PALETTE[$storedKey])) {
            return $storedKey;
        }

        $keys = array_keys(self::PALETTE);

        return $keys[intval($gibbonCourseClassID) % count($keys)];
    }

    public static function colour(string $key): array
    {
        return self::PALETTE[$key] ?? self::PALETTE['blue'];
    }
}

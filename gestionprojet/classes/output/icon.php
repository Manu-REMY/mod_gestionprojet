<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lucide SVG icon helper for mod_gestionprojet.
 *
 * Provides static methods to render inline SVG icons from the pix/lucide/ directory.
 * Icons are cached in memory for performance. Each icon is wrapped in a span with
 * CSS classes for sizing and colouring.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Lucide icon rendering helper.
 *
 * Usage:
 *   use mod_gestionprojet\output\icon;
 *   echo icon::render('save', 'md', 'purple');
 *   echo icon::render_step(4, 'lg');
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icon {

    /**
     * Map of step numbers to their Lucide icon names.
     */
    const STEP_ICONS = [
        1 => 'clipboard-list',
        2 => 'target',
        3 => 'calendar-range',
        4 => 'clipboard-list',
        5 => 'flask-conical',
        6 => 'file-text',
        7 => 'target',
        8 => 'book-open',
    ];

    /**
     * In-memory SVG content cache keyed by icon name.
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Render a named Lucide icon as an inline SVG wrapped in a span.
     *
     * @param string $name  Icon filename without extension (e.g. 'save', 'check-circle').
     * @param string $size  Size class suffix: xs|sm|md|lg|xl. Default 'md'.
     * @param string $color Colour class suffix: inherit|purple|green|blue|gray|red|orange|white. Default 'inherit'.
     * @return string HTML span containing the inline SVG, or empty string on failure.
     */
    public static function render(string $name, string $size = 'md', string $color = 'inherit'): string {
        $svg = self::load($name);
        if ($svg === '') {
            return '';
        }

        $size = clean_param($size, PARAM_ALPHA);
        $color = clean_param($color, PARAM_ALPHA);

        return '<span class="gp-icon gp-icon-' . $size . ' gp-icon-' . $color . '" aria-hidden="true">' . $svg . '</span>';
    }

    /**
     * Render the icon associated with a given step number.
     *
     * @param int    $stepnum Step number (1-8).
     * @param string $size    Size class suffix. Default 'md'.
     * @param string $color   Colour class suffix. Default 'purple'.
     * @return string HTML span containing the inline SVG, or empty string if step unknown.
     */
    public static function render_step(int $stepnum, string $size = 'md', string $color = 'purple'): string {
        if (!isset(self::STEP_ICONS[$stepnum])) {
            return '';
        }
        return self::render(self::STEP_ICONS[$stepnum], $size, $color);
    }

    /**
     * Load an SVG file from pix/lucide/ and cache the content.
     *
     * The icon name is sanitised with PARAM_ALPHANUMEXT (which does not allow
     * dashes), so dashes are temporarily replaced with underscores for validation,
     * then restored for the actual file path lookup.
     *
     * @param string $name Icon filename without extension.
     * @return string Raw SVG markup, or empty string if file not found.
     */
    private static function load(string $name): string {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        // Sanitise: PARAM_ALPHANUMEXT allows [a-zA-Z0-9_-] but NOT dashes,
        // so swap dashes to underscores for validation, then restore.
        $safename = str_replace('-', '_', $name);
        $safename = clean_param($safename, PARAM_ALPHANUMEXT);
        $safename = str_replace('_', '-', $safename);

        $filepath = dirname(__DIR__, 2) . '/pix/lucide/' . $safename . '.svg';

        if (!file_exists($filepath)) {
            self::$cache[$name] = '';
            return '';
        }

        $svg = file_get_contents($filepath);
        if ($svg === false) {
            self::$cache[$name] = '';
            return '';
        }

        self::$cache[$name] = $svg;
        return $svg;
    }
}

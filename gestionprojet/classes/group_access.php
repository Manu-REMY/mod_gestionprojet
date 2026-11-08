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
 * Which group a user may write in.
 *
 * The group id of a submission comes from the browser: it must never be trusted.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure rule of group access, without Moodle calls so that it can be checked alone.
 */
class group_access {

    /**
     * Whether a user may write in the requested group.
     *
     * @param int $requested Group id sent by the browser, 0 for individual work.
     * @param array $membergroupids Groups of the activity grouping the user belongs to.
     * @param array $activitygroupids All groups of the activity grouping in the course.
     * @param bool $accessallgroups Whether the user has moodle/site:accessallgroups.
     * @return bool
     */
    public static function is_allowed(int $requested, array $membergroupids, array $activitygroupids,
            bool $accessallgroups): bool {
        if ($requested === 0) {
            return true;
        }
        if (in_array($requested, array_map('intval', $membergroupids), true)) {
            return true;
        }
        return $accessallgroups && in_array($requested, array_map('intval', $activitygroupids), true);
    }
}

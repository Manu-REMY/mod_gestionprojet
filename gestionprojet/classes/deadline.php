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
 * Deadline of a copy when classes have their own date (request 40, v2.14.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure rule, without Moodle calls so that it can be checked alone.
 */
class deadline {

    /**
     * Effective deadline: the latest date among the groups of the copy, else the date of the step.
     *
     * As the group overrides of Moodle assignments, the most lenient date wins when a copy belongs to several groups.
     *
     * @param int $stepdeadline Deadline of the step (deadline_date of the teacher model), 0 when none.
     * @param int[] $groupdeadlines Dates of the groups of the copy (work group, classes of its members); 0 is ignored.
     * @return int Timestamp, 0 when there is no deadline.
     */
    public static function effective(int $stepdeadline, array $groupdeadlines): int {
        $dates = array_filter(array_map('intval', $groupdeadlines), function (int $date): bool {
            return $date > 0;
        });
        return $dates ? max($dates) : $stepdeadline;
    }
}

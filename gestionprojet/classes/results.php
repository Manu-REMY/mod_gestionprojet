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
 * Pure rules of the class results page (v2.13.0): state of a cell, rows by group, synthesis.
 *
 * No Moodle call: pure checks load this class.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

/**
 * Results of a class on every student step.
 */
class results {

    /**
     * One cell of the table: a copy of one step.
     *
     * @param array|null $copy ['status' => 0|1, 'grade' => ?number] or null when the copy does not exist.
     * @param bool $pending A grading AI evaluation is waiting or running.
     * @param array|null $levels Level mode of step 4: ['ingrid' => codes, 'validated' => codes], else null.
     * @param int $used Formative feedbacks used.
     * @param int $limit Formative feedbacks allowed.
     * @return array ['state' => 'none'|'draft'|'pending'|'submitted', 'grade' => ?float, 'levels' => ?array code => bool,
     *     'used' => int, 'limit' => int]
     */
    public static function cell(?array $copy, bool $pending, ?array $levels, int $used, int $limit): array {
        if ($copy === null) {
            $state = 'none';
        } else if ((int)$copy['status'] === 1) {
            $state = $pending ? 'pending' : 'submitted';
        } else {
            $state = 'draft';
        }
        $grade = $copy !== null && isset($copy['grade']) && $copy['grade'] !== null && $copy['grade'] !== ''
            ? (float)$copy['grade'] : null;
        $shown = null;
        if ($levels !== null) {
            $shown = [];
            foreach ($levels['ingrid'] as $code) {
                $shown[$code] = in_array($code, $levels['validated'], true);
            }
            $grade = null;
        }
        return ['state' => $state, 'grade' => $grade, 'levels' => $shown, 'used' => $used, 'limit' => $limit];
    }

    /**
     * Rows of the group mode: groups sorted by name, their students sorted by name, students in no group last.
     *
     * A group with none of the students of the page is left out; a student in two groups is listed in both.
     *
     * @param array $groups Group id => name.
     * @param array $memberships Group id => user ids.
     * @param array $users Students of the page: user id => ['lastname' => string, 'firstname' => string].
     * @return array List of ['groupid' => int (0: no group), 'name' => ?string, 'userids' => int[]].
     */
    public static function group_rows(array $groups, array $memberships, array $users): array {
        $byname = function (int $a, int $b) use ($users): int {
            $ka = $users[$a]['lastname'] . ' ' . $users[$a]['firstname'];
            $kb = $users[$b]['lastname'] . ' ' . $users[$b]['firstname'];
            return strnatcasecmp($ka, $kb) ?: $a <=> $b;
        };
        uasort($groups, function ($a, $b): int {
            return strnatcasecmp((string)$a, (string)$b);
        });
        $rows = [];
        $grouped = [];
        foreach ($groups as $groupid => $name) {
            $ids = array_values(array_filter(array_map('intval', $memberships[$groupid] ?? []), function (int $id) use ($users) {
                return isset($users[$id]);
            }));
            if (!$ids) {
                continue;
            }
            usort($ids, $byname);
            $rows[] = ['groupid' => (int)$groupid, 'name' => (string)$name, 'userids' => $ids];
            foreach ($ids as $id) {
                $grouped[$id] = true;
            }
        }
        $alone = array_values(array_filter(array_map('intval', array_keys($users)), function (int $id) use ($grouped) {
            return !isset($grouped[$id]);
        }));
        if ($alone) {
            usort($alone, $byname);
            $rows[] = ['groupid' => 0, 'name' => null, 'userids' => $alone];
        }
        return $rows;
    }

    /**
     * Synthesis of a step column: copies submitted (grading included) over all rows, average of the grades.
     *
     * @param array $cells Results of cell().
     * @return array ['submitted' => int, 'total' => int, 'average' => ?float]
     */
    public static function summary(array $cells): array {
        $submitted = 0;
        foreach ($cells as $cell) {
            if ($cell['state'] === 'submitted' || $cell['state'] === 'pending') {
                $submitted++;
            }
        }
        return ['submitted' => $submitted, 'total' => count($cells), 'average' => self::average($cells)];
    }

    /**
     * Average of the grades of some cells (cells without a grade are left out).
     *
     * @param array $cells Results of cell().
     * @return float|null
     */
    public static function average(array $cells): ?float {
        $grades = array_values(array_filter(array_column($cells, 'grade'), function ($grade) {
            return $grade !== null;
        }));
        return $grades ? round(array_sum($grades) / count($grades), 2) : null;
    }
}

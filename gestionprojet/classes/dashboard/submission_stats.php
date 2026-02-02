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
 * Submission statistics for teacher dashboard.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\dashboard;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for calculating submission statistics for a step.
 */
class submission_stats {

    /**
     * Get submission statistics for a specific step.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number (4-8)
     * @return object Statistics object with counts and averages
     */
    public static function get_step_stats($gestionprojetid, $step) {
        global $DB;

        $table = \gestionprojet_get_step_table($step);
        if (!$table) {
            return self::empty_stats();
        }

        // Get all submissions for this step.
        $submissions = $DB->get_records($table, ['gestionprojetid' => $gestionprojetid]);

        $total = count($submissions);
        if ($total === 0) {
            return self::empty_stats();
        }

        // Count by status.
        $submitted = 0;
        $draft = 0;
        $graded = 0;
        $grades = [];

        foreach ($submissions as $submission) {
            if (!empty($submission->status) && $submission->status == 1) {
                $submitted++;
                if (!empty($submission->grade) || $submission->grade === '0' || $submission->grade === 0) {
                    $graded++;
                    $grades[] = floatval($submission->grade);
                }
            } else {
                $draft++;
            }
        }

        // Calculate average.
        $avg = !empty($grades) ? round(array_sum($grades) / count($grades), 2) : null;

        // Calculate grade distribution (5 buckets: 0-4, 4-8, 8-12, 12-16, 16-20).
        $distribution = [0, 0, 0, 0, 0];
        foreach ($grades as $g) {
            $index = min(4, intval(floor($g / 4)));
            $distribution[$index]++;
        }

        // Calculate percentages.
        $completionPercent = round($submitted / $total * 100);
        $gradedPercent = round($graded / $total * 100);
        $pendingGrade = $submitted - $graded;
        $pendingPercent = round($pendingGrade / $total * 100);
        $draftPercent = round($draft / $total * 100);

        return (object)[
            'total' => $total,
            'submitted' => $submitted,
            'draft' => $draft,
            'graded_count' => $graded,
            'pending_grade' => $pendingGrade,
            'avg_grade' => $avg,
            'grade_distribution' => $distribution,
            'completion_percent' => $completionPercent,
            'graded_percent' => $gradedPercent,
            'pending_percent' => $pendingPercent,
            'draft_percent' => $draftPercent,
        ];
    }

    /**
     * Return empty statistics object.
     *
     * @return object Empty statistics
     */
    private static function empty_stats() {
        return (object)[
            'total' => 0,
            'submitted' => 0,
            'draft' => 0,
            'graded_count' => 0,
            'pending_grade' => 0,
            'avg_grade' => null,
            'grade_distribution' => [0, 0, 0, 0, 0],
            'completion_percent' => 0,
            'graded_percent' => 0,
            'pending_percent' => 0,
            'draft_percent' => 0,
        ];
    }

    /**
     * Get statistics for all steps at once.
     *
     * @param int $gestionprojetid Instance ID
     * @return array Array of statistics keyed by step number
     */
    public static function get_all_steps_stats($gestionprojetid) {
        $stats = [];
        for ($step = 4; $step <= 8; $step++) {
            $stats[$step] = self::get_step_stats($gestionprojetid, $step);
        }
        return $stats;
    }
}

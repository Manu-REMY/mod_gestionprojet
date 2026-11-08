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
 * Rules of the student progress panel (v2.12.0): status of the copy and which feedback to show.
 *
 * No Moodle call: pure checks load this class.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

/**
 * Rules of the student progress panel.
 */
class progress {

    /**
     * Status of a copy.
     *
     * @param int $status 0 draft, 1 submitted.
     * @param bool $pending An AI evaluation is running.
     * @return string draft, submitted, or pending (submitted and being graded).
     */
    public static function status(int $status, bool $pending): string {
        if ($status !== 1) {
            return 'draft';
        }
        return $pending ? 'pending' : 'submitted';
    }

    /**
     * Feedback to show: the last formative feedback when it is newer than the grade, else the feedback of the
     * applied AI evaluation, else the teacher's comment on a grade typed by hand, else nothing.
     *
     * @param object|null $applied Applied AI evaluation (applied_at, timemodified).
     * @param object|null $formative Last completed formative feedback (timemodified, parsed_feedback).
     * @param float|null $grade Grade of the copy.
     * @param string $teacherfeedback Feedback stored with the grade (submission feedback).
     * @param int $timegraded Date of a grade typed by hand (no applied evaluation).
     * @return array ['source' => formative|applied|teacher|none, 'evaluation' => ?object, 'text' => string, 'time' => int]
     */
    public static function pick_feedback(?object $applied, ?object $formative, ?float $grade, string $teacherfeedback,
            int $timegraded): array {
        $gradetime = $applied ? (int)($applied->applied_at ?? 0 ?: ($applied->timemodified ?? 0)) : $timegraded;
        $gradefeedback = $applied !== null || ($grade !== null && trim($teacherfeedback) !== '');
        if ($formative && (!$gradefeedback || (int)$formative->timemodified > $gradetime)) {
            return ['source' => 'formative', 'evaluation' => $formative, 'text' => (string)($formative->parsed_feedback ?? ''),
                'time' => (int)$formative->timemodified];
        }
        if ($applied) {
            return ['source' => 'applied', 'evaluation' => $applied, 'text' => $teacherfeedback, 'time' => $gradetime];
        }
        if ($grade !== null && trim($teacherfeedback) !== '') {
            return ['source' => 'teacher', 'evaluation' => null, 'text' => $teacherfeedback, 'time' => $timegraded];
        }
        return ['source' => 'none', 'evaluation' => null, 'text' => '', 'time' => 0];
    }
}

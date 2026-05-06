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
 * Reset helper: rebuilds a student submission from the latest teacher-provided
 * consigne. Extracted for testability.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

class reset_helper {

    /**
     * Mapping of supported steps to the provided table and the student table + payload fields.
     *
     * @var array<int, array{provided_table: string, student_table: string, table_key: string, fields: array<string>}>
     */
    private const STEP_MAP = [
        4 => [
            'provided_table' => 'gestionprojet_cdcf_provided',
            'student_table'  => 'gestionprojet_cdcf',
            'table_key'      => 'cdcf',
            'fields'         => ['interacteurs_data'],
        ],
    ];

    /**
     * Reset a student record to the latest teacher-provided consigne.
     *
     * @param object $gestionprojet Plugin instance record.
     * @param int    $step          Step number (4 currently; 5/7/9 future).
     * @param int    $groupid       Group ID (0 for individual mode).
     * @param int    $userid        User ID.
     * @return array{success: bool, error?: string}
     */
    public static function reset_step_to_provided(object $gestionprojet, int $step, int $groupid, int $userid): array {
        global $DB;

        if (!isset(self::STEP_MAP[$step])) {
            return ['success' => false, 'error' => 'unsupported_step'];
        }

        $map = self::STEP_MAP[$step];

        $provided = $DB->get_record($map['provided_table'], ['gestionprojetid' => $gestionprojet->id]);
        if (!$provided) {
            return ['success' => false, 'error' => 'no_provided'];
        }

        require_once(__DIR__ . '/../lib.php');
        $record = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, $map['table_key']);

        if ((int)$record->status === 1) {
            return ['success' => false, 'error' => 'locked'];
        }

        foreach ($map['fields'] as $field) {
            if (property_exists($provided, $field)) {
                $record->$field = $provided->$field;
            }
        }
        $record->timemodified = time();
        $DB->update_record($map['student_table'], $record);

        // Audit log. The signature of gestionprojet_log_change is:
        // ($gestionprojetid, $tablename, $recordid, $fieldname, $oldvalue, $newvalue, $userid, $groupid = null).
        if (function_exists('gestionprojet_log_change')) {
            gestionprojet_log_change(
                $gestionprojet->id,
                $map['table_key'],
                $record->id,
                'reset_to_provided',
                '',
                'reset',
                $userid,
                $groupid
            );
        }

        return ['success' => true];
    }
}

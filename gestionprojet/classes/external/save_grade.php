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
 * External function for saving manual grades.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External function to save a manual grade for a step submission.
 */
class save_grade extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number (4-8)'),
            'groupid' => new external_value(PARAM_INT, 'Group ID'),
            'grade' => new external_value(PARAM_FLOAT, 'Grade value (0-20)', VALUE_DEFAULT, null),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save a manual grade for a step submission.
     *
     * @param int $cmid Course module ID
     * @param int $step Step number
     * @param int $groupid Group ID
     * @param float|null $grade Grade value
     * @param string $feedback Feedback text
     * @return array Result with success status and message
     */
    public static function execute($cmid, $step, $groupid, $grade = null, $feedback = ''): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
            'groupid' => $groupid,
            'grade' => $grade,
            'feedback' => $feedback,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Check capability.
        require_capability('mod/gestionprojet:grade', $context);

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Ensure lib.php is loaded.
        require_once(__DIR__ . '/../../lib.php');

        try {
            // Map step to table type.
            $steptypes = [
                4 => 'cdcf',
                5 => 'essai',
                6 => 'rapport',
                7 => 'besoin_eleve',
                8 => 'carnet',
            ];

            if (!isset($steptypes[$params['step']])) {
                throw new \moodle_exception('invalidstep');
            }

            $type = $steptypes[$params['step']];
            $tablename = 'gestionprojet_' . $type;

            // Get submission record.
            $conditions = ['gestionprojetid' => $gestionprojet->id, 'groupid' => $params['groupid']];
            $record = $DB->get_record($tablename, $conditions);

            if (!$record) {
                // Create empty record if grading before submission exists.
                $record = new \stdClass();
                $record->gestionprojetid = $gestionprojet->id;
                $record->groupid = $params['groupid'];
                $record->userid = 0;
                $record->status = 0;
                $record->timecreated = time();
                $record->timemodified = time();
                $record->id = $DB->insert_record($tablename, $record);
            }

            $record->grade = $params['grade'];
            $record->feedback = $params['feedback'];
            $record->timemodified = time();

            $DB->update_record($tablename, $record);

            return [
                'success' => true,
                'message' => get_string('gradesaved', 'gestionprojet'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Status or error message'),
        ]);
    }
}

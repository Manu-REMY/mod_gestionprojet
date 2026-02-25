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
 * External function for bulk re-evaluation of all submissions for a step.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Bulk re-evaluate all submissions for a given step using AI.
 */
class bulk_reevaluate extends external_api {

    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number (4-8)'),
        ]);
    }

    /**
     * Trigger bulk re-evaluation of all submissions for a step.
     *
     * @param int $cmid Course module ID.
     * @param int $step Step number.
     * @return array Result with success status and message.
     */
    public static function execute(int $cmid, int $step): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
        ]);
        $cmid = $params['cmid'];
        $step = $params['step'];

        // Get course module and context.
        $cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Security checks.
        require_login($course, true, $cm);
        $context = \context_module::instance($cm->id);
        require_capability('mod/gestionprojet:grade', $context);

        // Check if AI is enabled.
        if (empty($gestionprojet->ai_enabled)) {
            return [
                'success' => false,
                'message' => get_string('ai_not_enabled', 'gestionprojet'),
                'deleted' => 0,
                'queued' => 0,
            ];
        }

        // Validate step number.
        if ($step < 4 || $step > 8) {
            return [
                'success' => false,
                'message' => 'Invalid step number',
                'deleted' => 0,
                'queued' => 0,
            ];
        }

        // Check if step is enabled.
        $stepfield = 'enable_step' . $step;
        if (isset($gestionprojet->$stepfield) && !$gestionprojet->$stepfield) {
            return [
                'success' => false,
                'message' => get_string('step_not_enabled', 'gestionprojet'),
                'deleted' => 0,
                'queued' => 0,
            ];
        }

        // Load the AI evaluator.
        require_once(__DIR__ . '/../ai_evaluator.php');

        // Perform bulk re-evaluation.
        $result = \mod_gestionprojet\ai_evaluator::bulk_reevaluate_step($gestionprojet->id, $step);

        if ($result['queued'] > 0) {
            return [
                'success' => true,
                'message' => get_string('bulk_reevaluate_success', 'gestionprojet', $result),
                'deleted' => $result['deleted'],
                'queued' => $result['queued'],
            ];
        }

        return [
            'success' => true,
            'message' => get_string('bulk_reevaluate_no_submissions', 'gestionprojet'),
            'deleted' => 0,
            'queued' => 0,
        ];
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'message' => new external_value(PARAM_RAW, 'Result message'),
            'deleted' => new external_value(PARAM_INT, 'Number of old evaluations deleted'),
            'queued' => new external_value(PARAM_INT, 'Number of submissions queued for re-evaluation'),
        ]);
    }
}

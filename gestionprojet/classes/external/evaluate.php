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
 * External function for triggering AI evaluation.
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
 * External function to trigger AI evaluation for a submission.
 */
class evaluate extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number (4-8)'),
            'submissionid' => new external_value(PARAM_INT, 'Submission ID'),
        ]);
    }

    /**
     * Trigger AI evaluation for a submission.
     *
     * @param int $cmid Course module ID
     * @param int $step Step number
     * @param int $submissionid Submission ID
     * @return array Result with success status, evaluation ID, and message
     */
    public static function execute($cmid, $step, $submissionid): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
            'submissionid' => $submissionid,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Only teachers can manually trigger evaluation.
        require_capability('mod/gestionprojet:grade', $context);

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Ensure required files are loaded.
        require_once(__DIR__ . '/../../lib.php');
        require_once(__DIR__ . '/../ai_evaluator.php');

        try {
            // Check if AI is enabled.
            if (empty($gestionprojet->ai_enabled)) {
                return [
                    'success' => false,
                    'evaluationid' => 0,
                    'message' => get_string('ai_not_enabled', 'gestionprojet'),
                ];
            }

            // Validate step number.
            if ($params['step'] < 4 || $params['step'] > 8) {
                return [
                    'success' => false,
                    'evaluationid' => 0,
                    'message' => 'Invalid step number',
                ];
            }

            // Get submission to retrieve group/user info.
            $tables = [
                4 => 'gestionprojet_cdcf',
                5 => 'gestionprojet_essai',
                6 => 'gestionprojet_rapport',
                7 => 'gestionprojet_besoin_eleve',
                8 => 'gestionprojet_carnet',
            ];

            $submission = $DB->get_record($tables[$params['step']], ['id' => $params['submissionid']],
                '*', MUST_EXIST);

            // Queue evaluation.
            $evaluationid = \mod_gestionprojet\ai_evaluator::queue_evaluation(
                $gestionprojet->id,
                $params['step'],
                $params['submissionid'],
                $submission->groupid ?? 0,
                $submission->userid ?? 0
            );

            return [
                'success' => true,
                'evaluationid' => (int) $evaluationid,
                'message' => get_string('ai_evaluation_queued', 'gestionprojet'),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'evaluationid' => 0,
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
            'evaluationid' => new external_value(PARAM_INT, 'AI evaluation ID (0 if failed)'),
            'message' => new external_value(PARAM_TEXT, 'Status or error message'),
        ]);
    }
}

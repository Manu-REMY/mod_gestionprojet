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
 * External function for polling AI evaluation status.
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
use core_external\external_multiple_structure;

/**
 * External function to get AI evaluation status and results.
 */
class get_evaluation_status extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'evaluationid' => new external_value(PARAM_INT, 'AI evaluation ID (0 to use step+submissionid)',
                VALUE_DEFAULT, 0),
            'step' => new external_value(PARAM_INT, 'Step number (used with submissionid)', VALUE_DEFAULT, 0),
            'submissionid' => new external_value(PARAM_INT, 'Submission ID (used with step)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get AI evaluation status and results.
     *
     * @param int $cmid Course module ID
     * @param int $evaluationid AI evaluation ID
     * @param int $step Step number
     * @param int $submissionid Submission ID
     * @return array Complex structure with evaluation data
     */
    public static function execute($cmid, $evaluationid = 0, $step = 0, $submissionid = 0): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'evaluationid' => $evaluationid,
            'step' => $step,
            'submissionid' => $submissionid,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Check basic view capability.
        require_capability('mod/gestionprojet:view', $context);

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Ensure required files are loaded.
        require_once(__DIR__ . '/../../lib.php');
        require_once(__DIR__ . '/../ai_evaluator.php');
        require_once(__DIR__ . '/../ai_response_parser.php');

        try {
            $evaluation = null;

            if ($params['evaluationid']) {
                // Get specific evaluation.
                $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $params['evaluationid']]);
            } else if ($params['step'] && $params['submissionid']) {
                // Get latest evaluation for submission.
                $evaluation = \mod_gestionprojet\ai_evaluator::get_evaluation(
                    $gestionprojet->id,
                    $params['step'],
                    $params['submissionid']
                );
            }

            if (!$evaluation) {
                return [
                    'success' => true,
                    'has_evaluation' => false,
                    'evaluation_id' => 0,
                    'status' => '',
                    'status_label' => '',
                    'status_class' => '',
                    'timecreated' => 0,
                    'timemodified' => 0,
                    'grade' => -1.0,
                    'feedback' => '',
                    'criteria' => '[]',
                    'keywords_found' => '[]',
                    'keywords_missing' => '[]',
                    'suggestions' => '[]',
                    'tokens_used' => 0,
                    'html' => '',
                    'error_message' => '',
                    'applied_by' => 0,
                    'applied_at' => 0,
                ];
            }

            // Verify it belongs to this instance.
            if ($evaluation->gestionprojetid != $gestionprojet->id) {
                throw new \Exception('Invalid evaluation');
            }

            // Get status display info.
            $statusinfo = \mod_gestionprojet\ai_evaluator::get_status_display($evaluation);

            $response = [
                'success' => true,
                'has_evaluation' => true,
                'evaluation_id' => (int) $evaluation->id,
                'status' => $evaluation->status,
                'status_label' => $statusinfo['label'] ?? $evaluation->status,
                'status_class' => $statusinfo['class'] ?? '',
                'timecreated' => (int) $evaluation->timecreated,
                'timemodified' => (int) $evaluation->timemodified,
                'grade' => -1.0,
                'feedback' => '',
                'criteria' => '[]',
                'keywords_found' => '[]',
                'keywords_missing' => '[]',
                'suggestions' => '[]',
                'tokens_used' => 0,
                'html' => '',
                'error_message' => '',
                'applied_by' => 0,
                'applied_at' => 0,
            ];

            // Include results if completed.
            if ($evaluation->status === 'completed' || $evaluation->status === 'applied') {
                $response['grade'] = (float) ($evaluation->parsed_grade ?? -1.0);
                $response['feedback'] = $evaluation->parsed_feedback ?? '';
                $response['criteria'] = $evaluation->criteria_json ?? '[]';
                $response['keywords_found'] = $evaluation->keywords_found ?? '[]';
                $response['keywords_missing'] = $evaluation->keywords_missing ?? '[]';
                $response['suggestions'] = $evaluation->suggestions ?? '[]';
                $response['tokens_used'] = (int) (($evaluation->prompt_tokens ?? 0)
                    + ($evaluation->completion_tokens ?? 0));

                // Include formatted HTML for teacher view.
                if (has_capability('mod/gestionprojet:grade', $context)) {
                    $parser = new \mod_gestionprojet\ai_response_parser();
                    $result = new \stdClass();
                    $result->grade = $evaluation->parsed_grade;
                    $result->max_grade = 20;
                    $result->feedback = $evaluation->parsed_feedback;
                    $result->criteria = json_decode($evaluation->criteria_json, true) ?? [];
                    $result->keywords_found = json_decode($evaluation->keywords_found, true) ?? [];
                    $result->keywords_missing = json_decode($evaluation->keywords_missing, true) ?? [];
                    $result->suggestions = json_decode($evaluation->suggestions, true) ?? [];
                    $response['html'] = $parser->format_for_display($result);
                }
            }

            // Include error if failed.
            if ($evaluation->status === 'failed') {
                $response['error_message'] = $evaluation->error_message ?? '';
            }

            // Include applied info if applied.
            if ($evaluation->status === 'applied') {
                $response['applied_by'] = (int) ($evaluation->applied_by ?? 0);
                $response['applied_at'] = (int) ($evaluation->applied_at ?? 0);
            }

            return $response;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'has_evaluation' => false,
                'evaluation_id' => 0,
                'status' => 'error',
                'status_label' => $e->getMessage(),
                'status_class' => '',
                'timecreated' => 0,
                'timemodified' => 0,
                'grade' => -1.0,
                'feedback' => '',
                'criteria' => '[]',
                'keywords_found' => '[]',
                'keywords_missing' => '[]',
                'suggestions' => '[]',
                'tokens_used' => 0,
                'html' => '',
                'error_message' => $e->getMessage(),
                'applied_by' => 0,
                'applied_at' => 0,
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
            'has_evaluation' => new external_value(PARAM_BOOL, 'Whether an evaluation was found'),
            'evaluation_id' => new external_value(PARAM_INT, 'Evaluation ID'),
            'status' => new external_value(PARAM_TEXT, 'Evaluation status: pending, processing, completed, applied, failed'),
            'status_label' => new external_value(PARAM_TEXT, 'Human-readable status label'),
            'status_class' => new external_value(PARAM_TEXT, 'CSS class for status display'),
            'timecreated' => new external_value(PARAM_INT, 'Time evaluation was created'),
            'timemodified' => new external_value(PARAM_INT, 'Time evaluation was last modified'),
            'grade' => new external_value(PARAM_FLOAT, 'Parsed grade (-1 if not available)'),
            'feedback' => new external_value(PARAM_RAW, 'Parsed feedback text'),
            'criteria' => new external_value(PARAM_RAW, 'JSON-encoded criteria array'),
            'keywords_found' => new external_value(PARAM_RAW, 'JSON-encoded keywords found array'),
            'keywords_missing' => new external_value(PARAM_RAW, 'JSON-encoded keywords missing array'),
            'suggestions' => new external_value(PARAM_RAW, 'JSON-encoded suggestions array'),
            'tokens_used' => new external_value(PARAM_INT, 'Total tokens used'),
            'html' => new external_value(PARAM_RAW, 'Formatted HTML for teacher display'),
            'error_message' => new external_value(PARAM_RAW, 'Error message if failed'),
            'applied_by' => new external_value(PARAM_INT, 'User ID who applied the grade'),
            'applied_at' => new external_value(PARAM_INT, 'Timestamp when grade was applied'),
        ]);
    }
}

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
 * External function for applying AI evaluation grades.
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
 * External function to apply an AI evaluation grade to a submission.
 */
class apply_ai_grade extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'evaluationid' => new external_value(PARAM_INT, 'AI evaluation ID'),
            'action' => new external_value(PARAM_TEXT, 'Action: apply or apply_modified', VALUE_DEFAULT, 'apply'),
            'grade' => new external_value(PARAM_FLOAT, 'Override grade value (for apply_modified)', VALUE_DEFAULT, null),
            'feedback' => new external_value(PARAM_RAW, 'Override feedback (for apply_modified)', VALUE_DEFAULT, ''),
            'show_feedback' => new external_value(PARAM_INT, 'Show feedback to students (1/0)', VALUE_DEFAULT, 1),
            'show_criteria' => new external_value(PARAM_INT, 'Show criteria to students (1/0)', VALUE_DEFAULT, 1),
            'show_keywords_found' => new external_value(PARAM_INT, 'Show keywords found to students (1/0)',
                VALUE_DEFAULT, 1),
            'show_keywords_missing' => new external_value(PARAM_INT, 'Show keywords missing to students (1/0)',
                VALUE_DEFAULT, 1),
            'show_suggestions' => new external_value(PARAM_INT, 'Show suggestions to students (1/0)',
                VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Apply an AI evaluation grade to a submission.
     *
     * @param int $cmid Course module ID
     * @param int $evaluationid AI evaluation ID
     * @param string $action Action (apply or apply_modified)
     * @param float|null $grade Override grade
     * @param string $feedback Override feedback
     * @param int $showfeedback Show feedback to students
     * @param int $showcriteria Show criteria to students
     * @param int $showkeywordsfound Show keywords found
     * @param int $showkeywordsmissing Show keywords missing
     * @param int $showsuggestions Show suggestions
     * @return array Result with success status, message, grade, and feedback
     */
    public static function execute($cmid, $evaluationid, $action = 'apply', $grade = null,
            $feedback = '', $show_feedback = 1, $show_criteria = 1, $show_keywords_found = 1,
            $show_keywords_missing = 1, $show_suggestions = 1): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'evaluationid' => $evaluationid,
            'action' => $action,
            'grade' => $grade,
            'feedback' => $feedback,
            'show_feedback' => $show_feedback,
            'show_criteria' => $show_criteria,
            'show_keywords_found' => $show_keywords_found,
            'show_keywords_missing' => $show_keywords_missing,
            'show_suggestions' => $show_suggestions,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Only teachers can apply grades.
        require_capability('mod/gestionprojet:grade', $context);

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Ensure required files are loaded.
        require_once(__DIR__ . '/../../lib.php');
        require_once(__DIR__ . '/../ai_evaluator.php');

        try {
            // Get evaluation.
            $evaluation = $DB->get_record('gestionprojet_ai_evaluations',
                ['id' => $params['evaluationid']], '*', MUST_EXIST);

            // Verify it belongs to this instance.
            if ($evaluation->gestionprojetid != $gestionprojet->id) {
                throw new \Exception('Invalid evaluation');
            }

            // Check evaluation is completed.
            if ($evaluation->status !== 'completed') {
                throw new \Exception(get_string('ai_evaluation_not_ready', 'gestionprojet'));
            }

            // Update visibility settings on the evaluation record.
            $evaluation->show_feedback = $params['show_feedback'] ? 1 : 0;
            $evaluation->show_criteria = $params['show_criteria'] ? 1 : 0;
            $evaluation->show_keywords_found = $params['show_keywords_found'] ? 1 : 0;
            $evaluation->show_keywords_missing = $params['show_keywords_missing'] ? 1 : 0;
            $evaluation->show_suggestions = $params['show_suggestions'] ? 1 : 0;
            $evaluation->timemodified = time();
            $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

            // Apply the grade.
            $overridegrade = $params['grade'];
            $overridefeedback = !empty($params['feedback']) ? $params['feedback'] : null;

            if ($params['action'] === 'apply_modified' && ($overridegrade !== null || $overridefeedback !== null)) {
                $success = \mod_gestionprojet\ai_evaluator::apply_evaluation(
                    $params['evaluationid'],
                    $USER->id,
                    $overridegrade,
                    $overridefeedback
                );
            } else {
                $success = \mod_gestionprojet\ai_evaluator::apply_evaluation(
                    $params['evaluationid'],
                    $USER->id
                );
            }

            if ($success) {
                // Get updated submission for response.
                $tables = [
                    4 => 'gestionprojet_cdcf',
                    5 => 'gestionprojet_essai',
                    6 => 'gestionprojet_rapport',
                    7 => 'gestionprojet_besoin_eleve',
                    8 => 'gestionprojet_carnet',
                ];
                $submission = $DB->get_record($tables[$evaluation->step],
                    ['id' => $evaluation->submissionid]);

                return [
                    'success' => true,
                    'message' => get_string('ai_grade_applied', 'gestionprojet'),
                    'grade' => (float) ($submission->grade ?? 0),
                    'feedback' => $submission->feedback ?? '',
                ];
            } else {
                throw new \Exception(get_string('ai_grade_apply_failed', 'gestionprojet'));
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'grade' => 0.0,
                'feedback' => '',
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
            'grade' => new external_value(PARAM_FLOAT, 'Applied grade value'),
            'feedback' => new external_value(PARAM_RAW, 'Applied feedback text'),
        ]);
    }
}

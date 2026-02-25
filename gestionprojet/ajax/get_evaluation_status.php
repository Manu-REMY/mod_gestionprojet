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
 * AJAX handler for polling AI evaluation status.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/ai_evaluator.php');
require_once(__DIR__ . '/../classes/ai_response_parser.php');

global $CFG, $DB, $USER;

$id = required_param('id', PARAM_INT); // CM ID.
$evaluationid = optional_param('evaluationid', 0, PARAM_INT); // AI Evaluation ID.
$step = optional_param('step', 0, PARAM_INT); // Step number.
$submissionid = optional_param('submissionid', 0, PARAM_INT); // Submission ID.

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

header('Content-Type: application/json');

try {
    $evaluation = null;

    if ($evaluationid) {
        // Get specific evaluation.
        $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid]);
    } elseif ($step && $submissionid) {
        // Get latest evaluation for submission.
        $evaluation = \mod_gestionprojet\ai_evaluator::get_evaluation(
            $gestionprojet->id,
            $step,
            $submissionid
        );
    }

    if (!$evaluation) {
        echo json_encode([
            'success' => true,
            'has_evaluation' => false,
        ]);
        exit;
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
        'evaluation_id' => $evaluation->id,
        'status' => $evaluation->status,
        'status_display' => $statusinfo,
        'timecreated' => $evaluation->timecreated,
        'timemodified' => $evaluation->timemodified,
    ];

    // Include results if completed.
    if ($evaluation->status === 'completed' || $evaluation->status === 'applied') {
        $response['grade'] = $evaluation->parsed_grade;
        $response['feedback'] = $evaluation->parsed_feedback;
        $response['criteria'] = json_decode($evaluation->criteria_json, true) ?? [];
        $response['keywords_found'] = json_decode($evaluation->keywords_found, true) ?? [];
        $response['keywords_missing'] = json_decode($evaluation->keywords_missing, true) ?? [];
        $response['suggestions'] = json_decode($evaluation->suggestions, true) ?? [];
        $response['tokens_used'] = ($evaluation->prompt_tokens ?? 0) + ($evaluation->completion_tokens ?? 0);

        // Include formatted HTML for teacher view.
        if (has_capability('mod/gestionprojet:grade', $context)) {
            $parser = new \mod_gestionprojet\ai_response_parser();
            $result = new \stdClass();
            $result->grade = $evaluation->parsed_grade;
            $result->max_grade = 20;
            $result->feedback = $evaluation->parsed_feedback;
            $result->criteria = $response['criteria'];
            $result->keywords_found = $response['keywords_found'];
            $result->keywords_missing = $response['keywords_missing'];
            $result->suggestions = $response['suggestions'];
            $response['html'] = $parser->format_for_display($result);
        }
    }

    // Include error if failed.
    if ($evaluation->status === 'failed') {
        $response['error_message'] = $evaluation->error_message;
    }

    // Include applied info if applied.
    if ($evaluation->status === 'applied') {
        $response['applied_by'] = $evaluation->applied_by;
        $response['applied_at'] = $evaluation->applied_at;
    }

    echo json_encode($response);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

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
 * AJAX handler for deleting AI evaluations.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/ai_evaluator.php');

global $CFG, $DB, $USER;

$id = required_param('id', PARAM_INT); // CM ID.
$evaluationid = optional_param('evaluationid', 0, PARAM_INT); // Single evaluation ID.
$step = optional_param('step', 0, PARAM_INT); // Step number for bulk delete.
$submissionid = optional_param('submissionid', 0, PARAM_INT); // Submission ID for bulk delete.

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Only teachers can delete evaluations.
require_capability('mod/gestionprojet:grade', $context);

header('Content-Type: application/json');

try {
    if ($evaluationid > 0) {
        // Delete single evaluation.
        $success = \mod_gestionprojet\ai_evaluator::delete_evaluation($evaluationid);

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => get_string('ai_evaluation_deleted', 'gestionprojet'),
            ]);
        } else {
            throw new \Exception(get_string('ai_evaluation_delete_failed', 'gestionprojet'));
        }

    } else if ($step > 0 && $submissionid > 0) {
        // Delete all evaluations for a submission.
        $deleted = \mod_gestionprojet\ai_evaluator::delete_evaluations_for_submission(
            $gestionprojet->id,
            $step,
            $submissionid
        );

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => get_string('ai_evaluations_deleted', 'gestionprojet', $deleted),
        ]);

    } else {
        throw new \Exception(get_string('error_invaliddata', 'gestionprojet'));
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

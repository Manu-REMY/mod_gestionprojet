<?php
/**
 * AJAX handler for applying AI evaluation grades.
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
$evaluationid = required_param('evaluationid', PARAM_INT); // AI Evaluation ID.
$action = optional_param('action', 'apply', PARAM_ALPHA); // 'apply' or 'apply_modified'.

// Optional overrides for apply_modified.
$overridegrade = optional_param('grade', null, PARAM_FLOAT);
$overridefeedback = optional_param('feedback', null, PARAM_RAW);

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Only teachers can apply grades.
require_capability('mod/gestionprojet:grade', $context);

header('Content-Type: application/json');

try {
    // Get evaluation.
    $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid], '*', MUST_EXIST);

    // Verify it belongs to this instance.
    if ($evaluation->gestionprojetid != $gestionprojet->id) {
        throw new \Exception('Invalid evaluation');
    }

    // Check evaluation is completed.
    if ($evaluation->status !== 'completed') {
        throw new \Exception(get_string('ai_evaluation_not_ready', 'gestionprojet'));
    }

    // Apply the grade.
    if ($action === 'apply_modified' && ($overridegrade !== null || $overridefeedback !== null)) {
        $success = \mod_gestionprojet\ai_evaluator::apply_evaluation(
            $evaluationid,
            $USER->id,
            $overridegrade,
            $overridefeedback
        );
    } else {
        $success = \mod_gestionprojet\ai_evaluator::apply_evaluation($evaluationid, $USER->id);
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
        $submission = $DB->get_record($tables[$evaluation->step], ['id' => $evaluation->submissionid]);

        echo json_encode([
            'success' => true,
            'message' => get_string('ai_grade_applied', 'gestionprojet'),
            'grade' => $submission->grade ?? null,
            'feedback' => $submission->feedback ?? '',
        ]);
    } else {
        throw new \Exception(get_string('ai_grade_apply_failed', 'gestionprojet'));
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

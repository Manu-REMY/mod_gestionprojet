<?php
/**
 * AJAX handler for manual AI evaluation trigger.
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
$step = required_param('step', PARAM_INT); // Step number (4-8).
$submissionid = required_param('submissionid', PARAM_INT); // Submission ID.

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Only teachers can manually trigger evaluation.
require_capability('mod/gestionprojet:grade', $context);

header('Content-Type: application/json');

try {
    // Check if AI is enabled.
    if (empty($gestionprojet->ai_enabled)) {
        throw new \Exception(get_string('ai_not_enabled', 'gestionprojet'));
    }

    // Validate step number.
    if ($step < 4 || $step > 8) {
        throw new \Exception('Invalid step number');
    }

    // Get submission to retrieve group/user info.
    $tables = [
        4 => 'gestionprojet_cdcf',
        5 => 'gestionprojet_essai',
        6 => 'gestionprojet_rapport',
        7 => 'gestionprojet_besoin_eleve',
        8 => 'gestionprojet_carnet',
    ];

    $submission = $DB->get_record($tables[$step], ['id' => $submissionid], '*', MUST_EXIST);

    // Queue evaluation.
    $evaluationid = \mod_gestionprojet\ai_evaluator::queue_evaluation(
        $gestionprojet->id,
        $step,
        $submissionid,
        $submission->groupid ?? 0,
        $submission->userid ?? 0
    );

    echo json_encode([
        'success' => true,
        'evaluationid' => $evaluationid,
        'message' => get_string('ai_evaluation_queued', 'gestionprojet'),
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

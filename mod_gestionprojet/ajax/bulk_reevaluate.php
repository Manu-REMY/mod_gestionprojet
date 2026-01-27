<?php
/**
 * AJAX handler for bulk re-evaluation of all submissions for a step.
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

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Only teachers can trigger bulk re-evaluation.
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

    // Check if step is enabled.
    $stepfield = 'enable_step' . $step;
    if (isset($gestionprojet->$stepfield) && !$gestionprojet->$stepfield) {
        throw new \Exception(get_string('step_not_enabled', 'gestionprojet'));
    }

    // Perform bulk re-evaluation.
    $result = \mod_gestionprojet\ai_evaluator::bulk_reevaluate_step($gestionprojet->id, $step);

    if ($result['queued'] > 0) {
        echo json_encode([
            'success' => true,
            'deleted' => $result['deleted'],
            'queued' => $result['queued'],
            'errors' => $result['errors'],
            'message' => get_string('bulk_reevaluate_success', 'gestionprojet', $result),
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'deleted' => 0,
            'queued' => 0,
            'errors' => [],
            'message' => get_string('bulk_reevaluate_no_submissions', 'gestionprojet'),
        ]);
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

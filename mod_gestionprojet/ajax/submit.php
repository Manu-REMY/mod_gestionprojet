<?php
/**
 * AJAX handler for submission actions
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

$id = required_param('id', PARAM_INT); // CM ID
$step = required_param('step', PARAM_INT); // Step number (4, 5, or 6)
$action = required_param('action', PARAM_ALPHA); // 'submit' or 'revert'

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Map step to table type
// Step 4: Cahier des Charges Fonctionnel -> table gestionprojet_cdcf
// Step 5: Fiche Essai -> table gestionprojet_essai
// Step 6: Rapport -> table gestionprojet_rapport

$type = '';
if ($step == 4) {
    $type = 'cdcf';
} elseif ($step == 5) {
    $type = 'essai';
} elseif ($step == 6) {
    $type = 'rapport';
} elseif ($step == 7) {
    $type = 'besoin_eleve';
} elseif ($step == 8) {
    $type = 'carnet';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid step']);
    die();
}

// Get group
$groupid = optional_param('groupid', 0, PARAM_INT);

if ($gestionprojet->group_submission) {
    // If teacher, respect the passed groupid (0 or specific group)
    if (has_capability('mod/gestionprojet:grade', $context)) {
        // Keep $groupid as passed
    } else {
        // Student: Force their assigned group
        $groupid = gestionprojet_get_user_group($cm, $USER->id);
        if (!$groupid) {
            echo json_encode(['success' => false, 'message' => 'Not in group']);
            die();
        }
    }
}

// Get submission
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, $type);

if ($action === 'submit') {
    require_capability('mod/gestionprojet:submit', $context);

    // Check if duplicate submission (optional, but good practice)
    if ($submission->status == 1) {
        echo json_encode(['success' => false, 'message' => 'Already submitted']);
        die();
    }

    $submission->status = 1; // Submitted
    $submission->timesubmitted = time();
    $submission->timemodified = time();

    // Determine table name
    $table = 'gestionprojet_' . $type;
    $DB->update_record($table, $submission);

    // Trigger event
    // \mod_gestionprojet\event\assessable_submitted::create(...) (If we had events)

    echo json_encode(['success' => true, 'status' => 'submitted']);

} elseif ($action === 'revert') {
    // Only teacher can revert? Or student if enabled?
    // Requirement says: "Teacher can revert to draft". 
    // cap: mod/gestionprojet:grade usually implies teacher.
    require_capability('mod/gestionprojet:grade', $context);

    $submission->status = 0; // Draft
    $submission->grade = null; // Clear grade? Maybe keep it but allow resubmit? Usually revert to draft keeps history but for simplicity reset status.
    // If we revert to draft, we probably shouldn't clear the grade immediately unless desired.
    // But typically if you revert to draft, it means you want them to change it.
    // Let's just set status to 0.

    $submission->timemodified = time();

    $table = 'gestionprojet_' . $type;
    $DB->update_record($table, $submission);

    echo json_encode(['success' => true, 'status' => 'draft']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

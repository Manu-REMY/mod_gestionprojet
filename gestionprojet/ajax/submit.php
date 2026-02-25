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
 * AJAX handler for submission actions
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

global $CFG;

$id = required_param('id', PARAM_INT); // CM ID
$step = required_param('step', PARAM_INT); // Step number (4, 5, or 6)
$action = required_param('action', PARAM_ALPHA); // 'submit' or 'revert'

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Map step to table type.
// Step 4: Requirements specification -> table gestionprojet_cdcf
// Step 5: Trial sheet -> table gestionprojet_essai
// Step 6: Project report -> table gestionprojet_rapport

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

    // Trigger submission created event.
    $event = \mod_gestionprojet\event\submission_created::create([
        'objectid' => $gestionprojet->id,
        'context' => $context,
        'other' => ['step' => $step],
    ]);
    $event->trigger();

    // Trigger AI evaluation if enabled.
    $aievaluationid = null;
    if (!empty($gestionprojet->ai_enabled)) {
        try {
            require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_evaluator.php');
            $aievaluationid = \mod_gestionprojet\ai_evaluator::queue_evaluation(
                $gestionprojet->id,
                $step,
                $submission->id,
                $groupid,
                $gestionprojet->group_submission ? 0 : $USER->id
            );
        } catch (\Exception $e) {
            // Log error but don't fail submission.
            debugging('AI evaluation queue failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    $response = ['success' => true, 'status' => 'submitted'];
    if ($aievaluationid) {
        $response['ai_evaluation_id'] = $aievaluationid;
        $response['ai_evaluation_status'] = 'pending';
    }
    echo json_encode($response);

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

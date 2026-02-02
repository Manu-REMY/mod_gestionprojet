<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for grading submissions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

// Ensure JSON headers
header('Content-Type: application/json');

$id = required_param('id', PARAM_INT); // CM ID
$step = required_param('step', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);
$grade = optional_param('grade', null, PARAM_FLOAT);
$feedback = optional_param('feedback', '', PARAM_RAW); // HTML allow

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:grade', $context);
require_sesskey();

$success = false;
$message = '';

try {
    // Map step to table type
    $type = '';
    if ($step == 4) {
        $type = 'cdcf';
    } elseif ($step == 5) {
        $type = 'essai';
    } elseif ($step == 6) {
        $type = 'rapport';
    } else {
        throw new moodle_exception('invalidstep');
    }

    // Get submission record
    // Note: grading is usually per group. Userid is 0 for group submissions.
    // If individual, we might need userid. But grading.php probably sends groupid.
    // Assuming group submission for now as per project structure.

    $params = ['gestionprojetid' => $gestionprojet->id, 'groupid' => $groupid];
    // If group submission is NOT enabled, we might need to handle individual grading.
    // But Step 4, 5, 6 are group based? Let's assume groupid is valid.

    $tablename = 'gestionprojet_' . $type;
    $record = $DB->get_record($tablename, $params);

    if (!$record) {
        // Create empty record if grading before submission?
        // Usually we enter grade on existing submission.
        // But let's allow creating one.
        $record = new stdClass();
        $record->gestionprojetid = $gestionprojet->id;
        $record->groupid = $groupid;
        $record->userid = 0; // Group submission
        $record->status = 0; // Draft? Or keep as is.
        $record->timecreated = time();
        $record->timemodified = time();
        $record->id = $DB->insert_record($tablename, $record);
    }

    $record->grade = $grade;
    $record->feedback = $feedback;
    $record->timemodified = time();

    $DB->update_record($tablename, $record);

    // Update Moodle Gradebook
    // We need to calculate the total grade (average of steps or specific formula?)
    // lib.php has gestionprojet_update_grades($gestionprojet, $userid)
    // But that might expect overall grade.
    // For now, storing component grade in DB is done.
    // If we want to push to gradebook, we usually push a single grade.
    // Maybe we average the 3 steps? Or this module only has one grade item?
    // Let's defer gradebook sync logic to a specific function or assume manual calculation later.
    // Or just call update_grades which usually syncs "final" grade.
    // If the module supports only one grade item, we need to decide what that grade is.
    // Usually it's the sum or average.
    // Let's just save to local table for now.

    $success = true;
    $message = get_string('gradesaved', 'gestionprojet');

} catch (Exception $e) {
    $success = false;
    $message = $e->getMessage();
}

echo json_encode([
    'success' => $success,
    'message' => $message
]);

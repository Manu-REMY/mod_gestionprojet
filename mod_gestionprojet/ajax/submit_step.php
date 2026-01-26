<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for step submission.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

header('Content-Type: application/json');

$cmid = required_param('cmid', PARAM_INT);
$step = required_param('step', PARAM_INT);
$action = required_param('action', PARAM_ALPHA); // 'submit' or 'unlock'
$groupid = optional_param('groupid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

require_sesskey();

// Map step to table name.
$steptables = [
    4 => 'gestionprojet_cdcf',
    5 => 'gestionprojet_essai',
    6 => 'gestionprojet_rapport',
    7 => 'gestionprojet_besoin_eleve',
    8 => 'gestionprojet_carnet',
];

if (!isset($steptables[$step])) {
    echo json_encode(['success' => false, 'message' => get_string('invalidstep', 'gestionprojet')]);
    exit;
}

$tablename = $steptables[$step];
$steptype = str_replace('gestionprojet_', '', $tablename);

$success = false;
$message = '';

try {
    // Check if submission is enabled.
    if (empty($gestionprojet->enable_submission)) {
        throw new moodle_exception('submissiondisabled', 'gestionprojet');
    }

    if ($action === 'submit') {
        // Student submitting their work.
        if (!has_capability('mod/gestionprojet:submit', $context)) {
            throw new moodle_exception('nopermission');
        }

        // Get the submission record.
        $record = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, $steptype);

        // Check if already submitted.
        if (!empty($record->status) && $record->status == 1) {
            throw new moodle_exception('alreadysubmitted', 'gestionprojet');
        }

        // Update status to submitted.
        $record->status = 1;
        $record->timesubmitted = time();
        $record->timemodified = time();

        $DB->update_record($tablename, $record);

        // Log the submission.
        gestionprojet_log_change(
            $gestionprojet->id,
            $steptype,
            $record->id,
            'status',
            0,
            1,
            $USER->id,
            $groupid
        );

        $success = true;
        $message = get_string('submissionsuccess', 'gestionprojet');

    } else if ($action === 'unlock') {
        // Teacher unlocking a submission.
        if (!has_capability('mod/gestionprojet:lock', $context)) {
            throw new moodle_exception('nopermission');
        }

        $userid = optional_param('userid', 0, PARAM_INT);

        // Find the submission to unlock.
        $conditions = ['gestionprojetid' => $gestionprojet->id];
        if ($groupid) {
            $conditions['groupid'] = $groupid;
            $conditions['userid'] = 0;
        } else if ($userid) {
            $conditions['userid'] = $userid;
            $conditions['groupid'] = 0;
        } else {
            throw new moodle_exception('invalidparams');
        }

        $record = $DB->get_record($tablename, $conditions);

        if (!$record) {
            throw new moodle_exception('submissionnotfound', 'gestionprojet');
        }

        // Unlock the submission.
        $record->status = 0;
        $record->timemodified = time();

        $DB->update_record($tablename, $record);

        // Log the unlock.
        gestionprojet_log_change(
            $gestionprojet->id,
            $steptype,
            $record->id,
            'status',
            1,
            0,
            $USER->id,
            $groupid
        );

        $success = true;
        $message = get_string('submissionunlocked', 'gestionprojet');

    } else {
        throw new moodle_exception('invalidaction');
    }

} catch (Exception $e) {
    $success = false;
    $message = $e->getMessage();
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'timestamp' => time()
]);

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for autosave functionality.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

$cmid = required_param('cmid', PARAM_INT);
$step = required_param('step', PARAM_INT);
$data = required_param('data', PARAM_RAW);
$groupid = optional_param('groupid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

// Decode JSON data
$formdata = json_decode($data, true);

if (!$formdata) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$success = false;
$message = '';

try {
    $time = time();

    // Determine which table to update based on step
    switch ($step) {
        case 1: // Description
            if (!has_capability('mod/gestionprojet:configureteacherpages', $context)) {
                throw new moodle_exception('nopermission');
            }

            $record = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
            if (!$record) {
                $record = new stdClass();
                $record->gestionprojetid = $gestionprojet->id;
                $record->timecreated = $time;
            }

            // Update fields
            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && property_exists($record, $key)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = $value;

                    // Log change
                    if ($record->id ?? 0) {
                        gestionprojet_log_change($gestionprojet->id, 'description', $record->id, $key, $oldvalue, $value, $USER->id);
                    }
                }
            }

            $record->timemodified = $time;

            if (isset($record->id)) {
                $DB->update_record('gestionprojet_description', $record);
            } else {
                $record->id = $DB->insert_record('gestionprojet_description', $record);
            }

            $success = true;
            break;

        case 2: // Besoin
            if (!has_capability('mod/gestionprojet:configureteacherpages', $context)) {
                throw new moodle_exception('nopermission');
            }

            $record = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
            if (!$record) {
                $record = new stdClass();
                $record->gestionprojetid = $gestionprojet->id;
                $record->timecreated = $time;
            }

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && property_exists($record, $key)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = $value;

                    if ($record->id ?? 0) {
                        gestionprojet_log_change($gestionprojet->id, 'besoin', $record->id, $key, $oldvalue, $value, $USER->id);
                    }
                }
            }

            $record->timemodified = $time;

            if (isset($record->id)) {
                $DB->update_record('gestionprojet_besoin', $record);
            } else {
                $record->id = $DB->insert_record('gestionprojet_besoin', $record);
            }

            $success = true;
            break;

        case 3: // Planning
            if (!has_capability('mod/gestionprojet:configureteacherpages', $context)) {
                throw new moodle_exception('nopermission');
            }

            $record = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);
            if (!$record) {
                $record = new stdClass();
                $record->gestionprojetid = $gestionprojet->id;
                $record->timecreated = $time;
            }

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && property_exists($record, $key)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = $value;

                    if ($record->id ?? 0) {
                        gestionprojet_log_change($gestionprojet->id, 'planning', $record->id, $key, $oldvalue, $value, $USER->id);
                    }
                }
            }

            $record->timemodified = $time;

            if (isset($record->id)) {
                $DB->update_record('gestionprojet_planning', $record);
            } else {
                $record->id = $DB->insert_record('gestionprojet_planning', $record);
            }

            $success = true;
            break;

        case 4: // CDCF
            if (!has_capability('mod/gestionprojet:submit', $context)) {
                throw new moodle_exception('nopermission');
            }

            $record = gestionprojet_get_or_create_submission($gestionprojet->id, $groupid, 'cdcf');

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && property_exists($record, $key)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = is_array($value) ? json_encode($value) : $value;

                    gestionprojet_log_change($gestionprojet->id, 'cdcf', $record->id, $key, $oldvalue, $record->$key, $USER->id, $groupid);
                }
            }

            $record->timemodified = $time;
            $DB->update_record('gestionprojet_cdcf', $record);

            $success = true;
            break;

        case 5: // Essai
            if (!has_capability('mod/gestionprojet:submit', $context)) {
                throw new moodle_exception('nopermission');
            }

            $record = gestionprojet_get_or_create_submission($gestionprojet->id, $groupid, 'essai');

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && property_exists($record, $key)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = $value;

                    gestionprojet_log_change($gestionprojet->id, 'essai', $record->id, $key, $oldvalue, $value, $USER->id, $groupid);
                }
            }

            $record->timemodified = $time;
            $DB->update_record('gestionprojet_essai', $record);

            $success = true;
            break;

        case 6: // Rapport
            if (!has_capability('mod/gestionprojet:submit', $context)) {
                throw new moodle_exception('nopermission');
            }

            $record = gestionprojet_get_or_create_submission($gestionprojet->id, $groupid, 'rapport');

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && property_exists($record, $key)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = $value;

                    gestionprojet_log_change($gestionprojet->id, 'rapport', $record->id, $key, $oldvalue, $value, $USER->id, $groupid);
                }
            }

            $record->timemodified = $time;
            $DB->update_record('gestionprojet_rapport', $record);

            $success = true;
            break;

        default:
            throw new moodle_exception('invalidstep');
    }

    $message = get_string('autosave_success', 'gestionprojet');

} catch (Exception $e) {
    $success = false;
    $message = $e->getMessage();
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'timestamp' => time()
]);

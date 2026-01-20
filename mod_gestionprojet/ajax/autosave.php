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

// Ensure JSON headers
header('Content-Type: application/json');

// Debug logging
$debug_log = __DIR__ . '/../../../moodledata/temp/autosave_debug.log';
@file_put_contents($debug_log, "\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
@file_put_contents($debug_log, "POST: " . print_r($_POST, true), FILE_APPEND);

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
@file_put_contents($debug_log, "Data received: " . $data . "\n", FILE_APPEND);
@file_put_contents($debug_log, "Decoded: " . print_r($formdata, true) . "\n", FILE_APPEND);

if (!$formdata) {
    @file_put_contents($debug_log, "ERROR: Invalid JSON\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
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

            // List of valid fields for description table
            $validfields = ['intitule', 'niveau', 'support', 'duree', 'besoin', 'production', 'outils', 'evaluation', 'competences', 'imageid', 'locked'];

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && in_array($key, $validfields)) {
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

            // List of valid fields for besoin table
            $validfields = ['aqui', 'surquoi', 'dansquelbut', 'locked'];

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && in_array($key, $validfields)) {
                    $oldvalue = isset($record->$key) ? $record->$key : null;
                    $record->$key = $value;

                    if ($record->id ?? 0) {
                        gestionprojet_log_change($gestionprojet->id, 'besoin', $record->id, $key, $oldvalue, $value, $USER->id);
                    }
                }
            }

            $record->timemodified = $time;

            if (isset($record->id)) {
                @file_put_contents($debug_log, "STEP 2 UPDATE: " . print_r($record, true) . "\n", FILE_APPEND);
                $DB->update_record('gestionprojet_besoin', $record);
            } else {
                @file_put_contents($debug_log, "STEP 2 INSERT: " . print_r($record, true) . "\n", FILE_APPEND);
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

            // List of valid fields for planning table
            $validfields = ['projectname', 'startdate', 'enddate', 'vacationzone', 'task1_hours', 'task2_hours', 'task3_hours', 'task4_hours', 'task5_hours', 'locked'];

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && in_array($key, $validfields)) {
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

            // List of valid fields for cdcf table
            $validfields = ['produit', 'milieu', 'fp', 'interacteurs_data', 'grade', 'feedback'];

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && in_array($key, $validfields)) {
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

            // List of valid fields for essai table
            $validfields = ['nom_essai', 'date_essai', 'groupe_eleves', 'fonction_service', 'niveaux_reussite', 'etapes_protocole', 'materiel_outils', 'precautions', 'resultats_obtenus', 'observations_remarques', 'conclusion', 'grade', 'feedback'];

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && in_array($key, $validfields)) {
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

            // List of valid fields for rapport table
            $validfields = ['titre_projet', 'auteurs', 'besoin_projet', 'imperatifs', 'solutions', 'justification', 'realisation', 'difficultes', 'validation', 'ameliorations', 'bilan', 'perspectives', 'grade', 'feedback'];

            foreach ($formdata as $key => $value) {
                if ($key !== 'id' && in_array($key, $validfields)) {
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

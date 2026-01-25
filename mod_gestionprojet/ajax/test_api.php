<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint for testing AI API connection.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

use mod_gestionprojet\ai_config;

// Check session.
require_sesskey();

// Get parameters.
$cmid = required_param('cmid', PARAM_INT);
$provider = required_param('provider', PARAM_ALPHA);
$apikey = required_param('apikey', PARAM_RAW);

// Set up context - handle both new activity (cmid=0) and existing activity.
if ($cmid > 0) {
    // Existing activity.
    $cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

    // Require login and capability.
    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);
    require_capability('mod/gestionprojet:configureteacherpages', $context);

    // Log the access attempt.
    ai_config::log_access($gestionprojet->id, $USER->id, 'test_connection');
} else {
    // New activity - just require login and check user can create activities.
    require_login();
    // User is creating a new activity, so they must have permission somewhere.
    // The actual capability check happens when they save the form.
}

// Test the connection.
$result = ai_config::test_connection($provider, $apikey);

// Return JSON response.
header('Content-Type: application/json');
echo json_encode($result);

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Main view page for gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID
$a = optional_param('a', 0, PARAM_INT);  // Gestionprojet instance ID
$step = optional_param('step', 0, PARAM_INT); // Step number (1-6)
$groupid = optional_param('groupid', 0, PARAM_INT); // For grading navigation

$cm = false;
$gestionprojet = false;
$course = false;

if ($id) {
    $cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, IGNORE_MISSING);
    if ($cm) {
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);
    } else {
        // If CM lookup failed, maybe id was actually the instance id?
        // This handles cases where links are malformed using id=INSTANCEID
        $a = $id;
        $id = 0;
    }
}

if ($a) {
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $a], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $gestionprojet->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('gestionprojet', $gestionprojet->id, $course->id, false, MUST_EXIST);
}

if (!$cm) {
    throw new \moodle_exception('missingidandcmid', 'gestionprojet');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/gestionprojet:view', $context);

// Trigger module viewed event.
$event = \mod_gestionprojet\event\course_module_viewed::create([
    'objectid' => $gestionprojet->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('gestionprojet', $gestionprojet);
$event->trigger();

// Completion tracking
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Page setup
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($gestionprojet->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Check user role
$isteacher = has_capability('mod/gestionprojet:configureteacherpages', $context);
$cangrade = has_capability('mod/gestionprojet:grade', $context);
$cansubmit = has_capability('mod/gestionprojet:submit', $context);

// Get user's group if student
$usergroup = 0;
if ($cansubmit && !$isteacher) {
    $usergroup = gestionprojet_get_user_group($cm, $USER->id);
}

// Determine which view to show
if ($step > 0) {
    // Check if step is enabled
    $stepfield = 'enable_step' . $step;
    $enabled = isset($gestionprojet->$stepfield) ? $gestionprojet->$stepfield : 1;

    // Check availability
    if (!$isteacher && !$enabled) {
        throw new \moodle_exception('stepdisabled', 'gestionprojet');
    }

    // Show specific step
    require_once(__DIR__ . '/pages/step' . $step . '.php');
    exit;
}

// Show home page
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($gestionprojet->name));

// Display intro
// if ($gestionprojet->intro) {
//     echo $OUTPUT->box(format_module_intro('gestionprojet', $gestionprojet, $cm->id), 'generalbox', 'intro');
// }

// Include home page template
require_once(__DIR__ . '/pages/home.php');

echo $OUTPUT->footer();

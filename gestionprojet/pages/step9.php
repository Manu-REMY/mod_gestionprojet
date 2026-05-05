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
 * Step 9: FAST diagram (student production).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_capability('mod/gestionprojet:submit', $context);

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9]);
$PAGE->set_title(get_string('step9', 'gestionprojet'));

// Resolve groupid / userid.
$groupid = groups_get_activity_group($cm, true);
$isgroup = ($gestionprojet->group_submission && $groupid != 0);
$effectivegroupid = $isgroup ? $groupid : 0;
$effectiveuserid = $isgroup ? 0 : $USER->id;

// get_or_create_submission already handles step9_provided seeding from teacher.
$submission = gestionprojet_get_or_create_submission(
    $gestionprojet,
    $effectivegroupid,
    $effectiveuserid,
    'fast'
);

$tplcontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'isteacher' => false,
    'datajson' => $submission->data_json ?? '',
    'aiinstructions' => '',
    'canpopulatecdcf' => false,
    'isprovided' => (int)$gestionprojet->step9_provided === 1,
    'submitted' => (int)$submission->status === 1,
    'mode' => 'student',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [[
    'cmid' => (int)$cm->id,
    'mode' => 'student',
    'sesskey' => sesskey(),
    'groupid' => (int)$effectivegroupid,
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 9, 'student')
);
echo $OUTPUT->heading(get_string('step9', 'gestionprojet'));
echo html_writer::start_div('description');
echo html_writer::tag('h3', get_string('step9_desc_title', 'gestionprojet'));
echo html_writer::tag('p', get_string('step9_desc_text', 'gestionprojet'));
echo html_writer::end_div();
echo html_writer::start_div('gp-student');
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
echo html_writer::end_div();
echo $OUTPUT->footer();

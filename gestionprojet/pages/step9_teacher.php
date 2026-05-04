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
 * Step 9 Teacher Correction Model: FAST diagram.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_capability('mod/gestionprojet:configureteacherpages', $context);

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step9', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Get or create teacher record.
$teacher = $DB->get_record('gestionprojet_fast_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$teacher) {
    $teacher = new stdClass();
    $teacher->gestionprojetid = $gestionprojet->id;
    $teacher->data_json = '';
    $teacher->ai_instructions = '';
    $teacher->submission_date = null;
    $teacher->deadline_date = null;
    $teacher->timecreated = time();
    $teacher->timemodified = time();
    $teacher->id = $DB->insert_record('gestionprojet_fast_teacher', $teacher);
}

// Determine if a CDCF teacher record exists with FS data (to enable populate-from-CDCF button).
$cdcfteacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
$hascdcffs = $cdcfteacher && !empty($cdcfteacher->interacteurs_data);

$tplcontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'isteacher' => true,
    'datajson' => $teacher->data_json ?? '',
    'aiinstructions' => $teacher->ai_instructions ?? '',
    'canpopulatecdcf' => $hascdcffs,
    'isprovided' => (int)$gestionprojet->step9_provided === 1,
    'mode' => 'teacher',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [[
    'cmid' => (int)$cm->id,
    'mode' => 'teacher',
    'sesskey' => sesskey(),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 9, 'correction')
);
echo $OUTPUT->heading(
    get_string('step9', 'gestionprojet')
        . ' <span class="gp-correction-badge">' . get_string('correction_model_badge', 'gestionprojet') . '</span>',
    2
);

// Render the per-step submissions dashboard.
if (function_exists('gestionprojet_render_step_dashboard')) {
    echo gestionprojet_render_step_dashboard($gestionprojet, 9, $context, $cm->id);
}

echo '<div class="gp-correction-page">';
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
echo '</div>';
echo $OUTPUT->footer();

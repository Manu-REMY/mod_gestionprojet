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
 * Step 9 Teacher consigne (FAST diagram provided to students).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Read-only when the user lacks teacher-edit capability — students see the brief but cannot edit it.
$canedit = has_capability('mod/gestionprojet:configureteacherpages', $context);
$readonly = !$canedit;

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'provided']);
$PAGE->set_title(get_string('step9', 'gestionprojet') . ' - ' . get_string('consigne', 'gestionprojet'));

// Get or create the provided record.
$provided = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);
if (!$provided) {
    $provided = new stdClass();
    $provided->gestionprojetid = $gestionprojet->id;
    $provided->data_json = '';
    $provided->timecreated = time();
    $provided->timemodified = time();
    $provided->id = $DB->insert_record('gestionprojet_fast_provided', $provided);
}

// CDCF teacher source for the populate-from-CDCF button (uses cdcf_provided, falls back to cdcf_teacher).
$cdcfprovided = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
$cdcfteacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
$hascdcffs = ($cdcfprovided && !empty($cdcfprovided->interacteurs_data))
    || ($cdcfteacher && !empty($cdcfteacher->interacteurs_data));

$tplcontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'isteacher' => false, // Hides the AI instructions textarea from the form template.
    'datajson' => $provided->data_json ?? '',
    'aiinstructions' => '',
    // Students cannot trigger the populate-from-CDCF action; only teachers can.
    'canpopulatecdcf' => $canedit && $hascdcffs,
    'isprovided' => true,
    'mode' => 'provided',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [[
    'cmid' => (int)$cm->id,
    'mode' => 'provided',
    'sesskey' => sesskey(),
]]);

// fast_editor only serializes data_json + ai_instructions, so the intro_text
// textarea needs its own autosave loop (teacher-only).
if ($canedit) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/intro_text_autosave', 'init', [[
        'cmid'       => (int)$cm->id,
        'step'       => 9,
        'autosaveMs' => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    ]]);
}

echo $OUTPUT->header();
// Tabs: teacher gets consignes navigation; student gets work navigation.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 9, $canedit ? 'consignes' : 'student')
);
echo $OUTPUT->heading(get_string('step9', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

// Intro text editor (teacher-only — students see the read-only banner via step9.php).
if ($canedit) {
    echo '<div class="model-form-section gp-intro-section">';
    echo '<h3>' . \mod_gestionprojet\output\icon::render('file-text', 'sm', 'blue') . ' '
        . get_string('intro_text_label', 'gestionprojet') . '</h3>';
    echo '<p class="text-muted small">' . get_string('intro_text_help', 'gestionprojet') . '</p>';
    echo '<textarea name="intro_text" id="intro_text" rows="8" class="form-control gp-intro-textarea">'
        . s($provided->intro_text ?? '') . '</textarea>';
    echo '</div>';

    $editor = editors_get_preferred_editor(FORMAT_HTML);
    $editor->set_text($provided->intro_text ?? '');
    $editor->use_editor('intro_text', [
        'context'  => $context,
        'autosave' => false,
    ]);
}

echo '<div class="alert alert-info">';
echo '<h4>' . get_string('step9_desc_title', 'gestionprojet') . '</h4>';
echo '<p>' . get_string('step9_desc_text', 'gestionprojet') . '</p>';
echo '</div>';

if ($readonly) {
    // Wrap the form in a read-only container; CSS disables pointer events on descendants.
    echo '<div class="gp-fast-readonly">';
}
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
if ($readonly) {
    echo '</div>';
}
echo $OUTPUT->footer();

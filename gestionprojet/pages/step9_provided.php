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

require_capability('mod/gestionprojet:configureteacherpages', $context);

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
    'canpopulatecdcf' => $hascdcffs,
    'isprovided' => true,
    'mode' => 'provided',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [[
    'cmid' => (int)$cm->id,
    'mode' => 'provided',
    'sesskey' => sesskey(),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 9, 'consignes')
);
echo $OUTPUT->heading(get_string('step9', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
echo $OUTPUT->footer();

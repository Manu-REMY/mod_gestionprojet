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

// AI instructions buttons (Generate from model + Default template).
$cmid = (int)$cm->id;
$aienabledjs = $gestionprojet->ai_enabled ? 'true' : 'false';
$defaulttextjs = json_encode(get_string('ai_instructions_default_step9', 'gestionprojet'));
$PAGE->requires->js_init_code("
require(['jquery', 'mod_gestionprojet/generate_ai_instructions'], function(\$, GenerateAi) {
    GenerateAi.init({
        cmid: {$cmid},
        step: 9,
        aiEnabled: {$aienabledjs},
        defaultText: {$defaulttextjs},
        containerSelector: '#aiInstructionsActions-{$cmid}',
        textareaSelector: '#fast-ai-{$cmid}',
        getModelData: function() {
            var input = document.getElementById('fast-data-{$cmid}');
            return { data_json: input ? input.value : '' };
        },
        isModelEmpty: function() {
            var d = this.getModelData();
            if (!d.data_json) { return true; }
            try {
                var obj = JSON.parse(d.data_json);
                if (!obj || typeof obj !== 'object') { return true; }
                if (Array.isArray(obj)) { return obj.length === 0; }
                // FAST data shape: an object with arrays/keys representing the diagram.
                // Empty if no nodes (heuristic: no own enumerable properties with non-empty values).
                for (var k in obj) {
                    if (Object.prototype.hasOwnProperty.call(obj, k)) {
                        var v = obj[k];
                        if (v && (Array.isArray(v) ? v.length > 0 : Object.keys(v).length > 0)) {
                            return false;
                        }
                    }
                }
                return true;
            } catch (e) {
                return false;
            }
        }
    });
});
");

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

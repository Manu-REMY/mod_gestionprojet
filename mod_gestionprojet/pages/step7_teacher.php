<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 7 Teacher Correction Model: Expression du Besoin (Eleve)
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 7, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step7', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Get or create teacher model.
$model = $DB->get_record('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_besoin_eleve_teacher', $model);
    $model = $DB->get_record('gestionprojet_besoin_eleve_teacher', ['id' => $model->id]);
}

echo $OUTPUT->header();
require_once(__DIR__ . '/teacher_model_styles.php');
?>

<div class="teacher-model-container">

    <div class="back-nav">
        <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>">
            &#8592; <?php echo get_string('correction_models', 'gestionprojet'); ?>
        </a>
    </div>

    <div class="teacher-model-header">
        <h2>&#129423; <?php echo get_string('step7', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <form id="teacherModelForm">
        <input type="hidden" name="step" value="7">

        <div class="model-form-section">
            <h3>&#129423; <?php echo get_string('bete_a_corne_diagram', 'gestionprojet'); ?></h3>

            <div class="form-group">
                <label for="aqui"><?php echo get_string('aqui', 'gestionprojet'); ?></label>
                <textarea id="aqui" name="aqui" placeholder="<?php echo get_string('aqui_help', 'gestionprojet'); ?>"><?php echo s($model->aqui ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="surquoi"><?php echo get_string('surquoi', 'gestionprojet'); ?></label>
                <textarea id="surquoi" name="surquoi" placeholder="<?php echo get_string('surquoi_help', 'gestionprojet'); ?>"><?php echo s($model->surquoi ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="dansquelbut"><?php echo get_string('dansquelbut', 'gestionprojet'); ?></label>
                <textarea id="dansquelbut" name="dansquelbut" placeholder="<?php echo get_string('dansquelbut_help', 'gestionprojet'); ?>"><?php echo s($model->dansquelbut ?? ''); ?></textarea>
            </div>
        </div>

        <div class="ai-instructions-section">
            <h3>&#129302; <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
            <textarea id="ai_instructions" name="ai_instructions"
                      placeholder="<?php echo get_string('ai_instructions_placeholder', 'gestionprojet'); ?>"><?php echo s($model->ai_instructions ?? ''); ?></textarea>
            <p class="ai-instructions-help"><?php echo get_string('ai_instructions_help', 'gestionprojet'); ?></p>
        </div>

        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">&#128190; <?php echo get_string('save', 'gestionprojet'); ?></button>
            <div id="saveStatus" class="save-status"></div>
        </div>
    </form>
</div>

<script>
(function waitRequire() {
    if (typeof require === 'undefined') {
        setTimeout(waitRequire, 50);
        return;
    }

    require(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
        $(document).ready(function() {
            var cmid = <?php echo $cm->id; ?>;
            var autosaveInterval = <?php echo ($gestionprojet->autosave_interval ?? 30) * 1000; ?>;

            // Custom serialization for step 7 teacher model
            var serializeData = function() {
                return {
                    aqui: document.getElementById('aqui').value,
                    surquoi: document.getElementById('surquoi').value,
                    dansquelbut: document.getElementById('dansquelbut').value,
                    ai_instructions: document.getElementById('ai_instructions').value
                };
            };

            // Initialize Autosave for teacher mode
            Autosave.init({
                cmid: cmid,
                step: 7,
                groupid: 0,
                mode: 'teacher',
                interval: autosaveInterval,
                formSelector: '#teacherModelForm',
                serialize: serializeData
            });

            // Manual save button
            document.getElementById('saveButton').addEventListener('click', function() {
                Autosave.save();
            });
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();

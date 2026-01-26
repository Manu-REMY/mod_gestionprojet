<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 6 Teacher Correction Model: Rapport de Projet
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 6, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step6', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Get or create teacher model.
$model = $DB->get_record('gestionprojet_rapport_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_rapport_teacher', $model);
    $model = $DB->get_record('gestionprojet_rapport_teacher', ['id' => $model->id]);
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
        <h2>&#128221; <?php echo get_string('step6', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <form id="teacherModelForm">
        <input type="hidden" name="step" value="6">

        <div class="model-form-section">
            <h3>&#128221; <?php echo get_string('step6', 'gestionprojet'); ?></h3>

            <div class="form-group">
                <label for="titre_projet"><?php echo get_string('titre_projet', 'gestionprojet'); ?></label>
                <input type="text" id="titre_projet" name="titre_projet" value="<?php echo s($model->titre_projet ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="besoins"><?php echo get_string('besoins', 'gestionprojet'); ?></label>
                <textarea id="besoins" name="besoins"><?php echo s($model->besoins ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="imperatifs"><?php echo get_string('imperatifs', 'gestionprojet'); ?></label>
                <textarea id="imperatifs" name="imperatifs"><?php echo s($model->imperatifs ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="solutions"><?php echo get_string('solutions', 'gestionprojet'); ?></label>
                <textarea id="solutions" name="solutions"><?php echo s($model->solutions ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="justification"><?php echo get_string('justification', 'gestionprojet'); ?></label>
                <textarea id="justification" name="justification"><?php echo s($model->justification ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="realisation"><?php echo get_string('realisation', 'gestionprojet'); ?></label>
                <textarea id="realisation" name="realisation"><?php echo s($model->realisation ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="difficultes"><?php echo get_string('difficultes', 'gestionprojet'); ?></label>
                <textarea id="difficultes" name="difficultes"><?php echo s($model->difficultes ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="validation"><?php echo get_string('validation', 'gestionprojet'); ?></label>
                <textarea id="validation" name="validation"><?php echo s($model->validation ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="ameliorations"><?php echo get_string('ameliorations', 'gestionprojet'); ?></label>
                <textarea id="ameliorations" name="ameliorations"><?php echo s($model->ameliorations ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="bilan"><?php echo get_string('bilan', 'gestionprojet'); ?></label>
                <textarea id="bilan" name="bilan"><?php echo s($model->bilan ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="perspectives"><?php echo get_string('perspectives', 'gestionprojet'); ?></label>
                <textarea id="perspectives" name="perspectives"><?php echo s($model->perspectives ?? ''); ?></textarea>
            </div>
        </div>

        <?php
        $step = 6;
        require_once(__DIR__ . '/teacher_dates_section.php');
        ?>

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

            // Custom serialization for step 6 teacher model
            var serializeData = function() {
                var dates = getDateValues();
                return {
                    titre_projet: document.getElementById('titre_projet').value,
                    besoins: document.getElementById('besoins').value,
                    imperatifs: document.getElementById('imperatifs').value,
                    solutions: document.getElementById('solutions').value,
                    justification: document.getElementById('justification').value,
                    realisation: document.getElementById('realisation').value,
                    difficultes: document.getElementById('difficultes').value,
                    validation: document.getElementById('validation').value,
                    ameliorations: document.getElementById('ameliorations').value,
                    bilan: document.getElementById('bilan').value,
                    perspectives: document.getElementById('perspectives').value,
                    ai_instructions: document.getElementById('ai_instructions').value,
                    submission_date: dates.submission_date,
                    deadline_date: dates.deadline_date
                };
            };

            // Initialize Autosave for teacher mode
            Autosave.init({
                cmid: cmid,
                step: 6,
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

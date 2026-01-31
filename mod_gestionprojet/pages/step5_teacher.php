<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 5 Teacher Correction Model: Fiche Essai
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 5, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step5', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Get or create teacher model.
$model = $DB->get_record('gestionprojet_essai_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_essai_teacher', $model);
    $model = $DB->get_record('gestionprojet_essai_teacher', ['id' => $model->id]);
}

echo $OUTPUT->header();

// Include shared teacher model styles.
require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher steps.
$stepnav = gestionprojet_get_teacher_step_navigation($gestionprojet, 5);
?>

<!-- Navigation en haut (avant le dashboard) -->
<div class="step-navigation step-navigation-top" style="max-width: 1200px; margin: 0 auto 20px auto; padding: 0 20px;">
    <?php if ($stepnav['prev']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
        &#8592; <?php echo get_string('previous', 'gestionprojet'); ?>
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>

    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
        <?php echo get_string('correction_models', 'gestionprojet'); ?>
    </a>

    <?php if ($stepnav['next']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
        <?php echo get_string('next', 'gestionprojet'); ?> &#8594;
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>
</div>

<?php
// Render teacher dashboard for this step.
echo gestionprojet_render_step_dashboard($gestionprojet, 5, $context, $cm->id);
?>

<div class="teacher-model-container">

    <div class="teacher-model-header">
        <h2>&#128300; <?php echo get_string('step5', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <form id="teacherModelForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="5">

        <div class="model-form-section">
            <h3>&#128300; <?php echo get_string('step5', 'gestionprojet'); ?></h3>

            <div class="form-group">
                <label for="nom_essai"><?php echo get_string('nom_essai', 'gestionprojet'); ?></label>
                <input type="text" id="nom_essai" name="nom_essai" value="<?php echo s($model->nom_essai ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="objectif"><?php echo get_string('objectif', 'gestionprojet'); ?></label>
                <textarea id="objectif" name="objectif"><?php echo s($model->objectif ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="fonction_service"><?php echo get_string('fonction_service', 'gestionprojet'); ?></label>
                <textarea id="fonction_service" name="fonction_service"><?php echo s($model->fonction_service ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="niveaux_reussite"><?php echo get_string('niveaux_reussite', 'gestionprojet'); ?></label>
                <textarea id="niveaux_reussite" name="niveaux_reussite"><?php echo s($model->niveaux_reussite ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="etapes_protocole"><?php echo get_string('etapes_protocole', 'gestionprojet'); ?></label>
                <textarea id="etapes_protocole" name="etapes_protocole"><?php echo s($model->etapes_protocole ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="materiel_outils"><?php echo get_string('materiel_outils', 'gestionprojet'); ?></label>
                <textarea id="materiel_outils" name="materiel_outils"><?php echo s($model->materiel_outils ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="precautions"><?php echo get_string('precautions', 'gestionprojet'); ?></label>
                <textarea id="precautions" name="precautions"><?php echo s($model->precautions ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="resultats_obtenus"><?php echo get_string('resultats_obtenus', 'gestionprojet'); ?></label>
                <textarea id="resultats_obtenus" name="resultats_obtenus"><?php echo s($model->resultats_obtenus ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="observations_remarques"><?php echo get_string('observations_remarques', 'gestionprojet'); ?></label>
                <textarea id="observations_remarques" name="observations_remarques"><?php echo s($model->observations_remarques ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="conclusion"><?php echo get_string('conclusion', 'gestionprojet'); ?></label>
                <textarea id="conclusion" name="conclusion"><?php echo s($model->conclusion ?? ''); ?></textarea>
            </div>
        </div>

        <?php
        $step = 5;
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

        <!-- Navigation entre étapes -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
                &#8592; <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
                <?php echo get_string('correction_models', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> &#8594;
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
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

            // Custom serialization for step 5 teacher model
            var serializeData = function() {
                var dates = getDateValues();
                return {
                    nom_essai: document.getElementById('nom_essai').value,
                    objectif: document.getElementById('objectif').value,
                    fonction_service: document.getElementById('fonction_service').value,
                    niveaux_reussite: document.getElementById('niveaux_reussite').value,
                    etapes_protocole: document.getElementById('etapes_protocole').value,
                    materiel_outils: document.getElementById('materiel_outils').value,
                    precautions: document.getElementById('precautions').value,
                    resultats_obtenus: document.getElementById('resultats_obtenus').value,
                    observations_remarques: document.getElementById('observations_remarques').value,
                    conclusion: document.getElementById('conclusion').value,
                    ai_instructions: document.getElementById('ai_instructions').value,
                    submission_date: dates.submission_date,
                    deadline_date: dates.deadline_date
                };
            };

            // Initialize Autosave for teacher mode
            Autosave.init({
                cmid: cmid,
                step: 5,
                groupid: 0,
                mode: 'teacher',
                interval: autosaveInterval,
                formSelector: '#teacherModelForm',
                serialize: serializeData
            });

            // Manual save button with redirect to hub
            document.getElementById('saveButton').addEventListener('click', function() {
                var originalOnSave = Autosave.onSave;
                Autosave.onSave = function(response) {
                    if (originalOnSave) originalOnSave(response);
                    setTimeout(function() {
                        window.location.href = M.cfg.wwwroot + '/mod/gestionprojet/view.php?id=' + cmid + '&page=correctionmodels';
                    }, 800);
                };
                Autosave.save();
            });
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();

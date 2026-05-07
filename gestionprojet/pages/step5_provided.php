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
 * Step 5 Teacher Consigne (Provided Document): Essai (Test Sheet).
 *
 * This page lets the teacher fill in the Essai consigne (objective, protocol,
 * results, conclusion). The same content is shown read-only to students, AND
 * is seeded into the student's editable essai on first access (see
 * gestionprojet_get_or_create_submission in lib.php).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

// Read-only when the user lacks teacher-edit capability — students see the brief but cannot edit it.
$canedit = has_capability('mod/gestionprojet:configureteacherpages', $context);
$readonly = !$canedit;

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 5, 'mode' => 'provided']);
$PAGE->set_title(get_string('step5', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

// Get or create the provided consigne record.
$model = $DB->get_record('gestionprojet_essai_provided', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_essai_provided', $model);
    $model = $DB->get_record('gestionprojet_essai_provided', ['id' => $model->id]);
}

echo $OUTPUT->header();

// Tabs: teacher gets consignes navigation; student gets work navigation.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 5, $canedit ? 'consignes' : 'student')
);
echo $OUTPUT->heading(get_string('step5', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

echo '<div class="alert alert-info">';
echo '<h4>' . get_string('step5_desc_title', 'gestionprojet') . '</h4>';
echo '<p>' . get_string('step5_desc_text', 'gestionprojet') . '</p>';
echo '</div>';

require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher consigne steps (only meaningful for editors).
$stepnav = $canedit ? gestionprojet_get_teacher_step_navigation($gestionprojet, 5) : ['prev' => null, 'next' => null];
?>

<div class="teacher-model-container gp-consigne">

    <?php if ($readonly): ?>
    <div class="gp-fast-readonly">
    <?php endif; ?>

    <form id="essaiProvidedForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="5">
        <input type="hidden" name="mode" value="provided">

        <!-- Intro text displayed read-only to students at the top of step 5. -->
        <div class="model-form-section gp-intro-section">
            <h3><?php echo icon::render('file-text', 'sm', 'blue'); ?> <?php echo get_string('intro_text_label', 'gestionprojet'); ?></h3>
            <p class="text-muted small"><?php echo get_string('intro_text_help', 'gestionprojet'); ?></p>
            <textarea name="intro_text" id="gp_intro_text_step5" rows="8" class="form-control gp-intro-textarea"><?php echo s($model->intro_text ?? ''); ?></textarea>
        </div>
        <?php
        // Activate the Moodle preferred rich-text editor (Atto/TinyMCE) on the textarea.
        // Use a step-specific id so TinyMCE drafts don't collide between consigne pages
        // sharing the same module context.
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->set_text($model->intro_text ?? '');
        $editor->use_editor('gp_intro_text_step5', [
            'context'  => $context,
            'autosave' => false,
        ]);
        ?>

        <div class="model-form-section">
            <h3><?php echo icon::render('flask-conical', 'sm', 'purple'); ?> <?php echo get_string('step5', 'gestionprojet'); ?></h3>

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
                <textarea id="precautions" name="precautions" placeholder="<?php echo s(get_string('step5_provided_precautions_placeholder', 'gestionprojet')); ?>"><?php echo s($model->precautions ?? ''); ?></textarea>
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

        <?php if (!$readonly): ?>
        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">
                <?php echo icon::render('save', 'sm', 'inherit'); ?> <?php echo get_string('save', 'gestionprojet'); ?>
            </button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation (editor only). -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'provided']); ?>" class="btn-nav btn-prev">
                <?php echo icon::render('chevron-left', 'sm', 'inherit'); ?> <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>" class="btn-nav btn-hub">
                <?php echo get_string('consigne', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'provided']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> <?php echo icon::render('chevron-right', 'sm', 'inherit'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($readonly): ?>
    </div>
    <?php endif; ?>

</div>

<?php
// Wire autosave (editor only — readonly mode posts nothing).
if (!$readonly) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/essai_provided', 'init', [[
        'cmid' => (int)$cm->id,
        'autosaveInterval' => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    ]]);
}

echo $OUTPUT->footer();

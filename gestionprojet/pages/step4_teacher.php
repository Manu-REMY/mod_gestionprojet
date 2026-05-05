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
 * Step 4 Teacher Correction Model: Functional Specifications
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step4', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Default AI instructions for CdCF (Functional Specifications).
$defaultaiinstructions = get_string('ai_instructions_default_step4', 'gestionprojet');

// Get or create teacher model. The legacy produit/milieu/fp columns were
// dropped; the model now stores the unified CDCF JSON in interacteurs_data.
$model = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->interacteurs_data = '';
    $model->ai_instructions = $defaultaiinstructions;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_cdcf_teacher', $model);
}

// Decode the unified CDCF data structure (interactors / fonctionsService / contraintes).
require_once($CFG->dirroot . '/mod/gestionprojet/classes/cdcf_helper.php');
$cdcfdata = \mod_gestionprojet\cdcf_helper::decode($model->interacteurs_data ?? null);
$projetnom = format_string($gestionprojet->name);

// Mode detection: combined state of step4_provided and enable_step4.
$step4provided = isset($gestionprojet->step4_provided) ? (int)$gestionprojet->step4_provided : 0;
$step4studentenabled = isset($gestionprojet->enable_step4) ? (int)$gestionprojet->enable_step4 : 1;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 4, 'correction')
);

// Contextual notice based on the combined state of step4_provided and enable_step4.
$noticekey = null;
if ($step4provided === 1 && $step4studentenabled === 0) {
    $noticekey = 'step4_provided_notice_teacher';
} else if ($step4provided === 1 && $step4studentenabled === 1) {
    $noticekey = 'step4_hybrid_notice_teacher';
}
if ($noticekey !== null) {
    echo html_writer::div(
        get_string($noticekey, 'gestionprojet'),
        'gp-provided-notice'
    );
}

echo $OUTPUT->heading(
    get_string('step4', 'gestionprojet')
    . ' <span class="gp-correction-badge">' . get_string('correction_model_badge', 'gestionprojet') . '</span>',
    2
);

require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher steps.
$stepnav = gestionprojet_get_teacher_step_navigation($gestionprojet, 4);
?>

<?php
// Render teacher dashboard for this step.
echo gestionprojet_render_step_dashboard($gestionprojet, 4, $context, $cm->id);
?>

<div class="teacher-model-container gp-correction">

    <div class="teacher-model-header">
        <h2><?php echo icon::render('clipboard-list', 'sm', 'purple'); ?> <?php echo get_string('step4', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <?php if ($gestionprojet->ai_enabled): ?>
        <div class="ai-status-indicator enabled">
            <?php echo icon::render('check-circle', 'sm', 'green'); ?> <?php echo get_string('ai_evaluation_enabled', 'gestionprojet'); ?>
            (<?php echo ucfirst($gestionprojet->ai_provider); ?>)
        </div>
    <?php else: ?>
        <div class="ai-status-indicator disabled">
            <?php echo icon::render('alert-triangle', 'sm', 'orange'); ?> <?php echo get_string('ai_evaluation_disabled_hint', 'gestionprojet'); ?>
        </div>
    <?php endif; ?>

    <form id="cdcfForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="interacteurs_data" id="cdcfDataField"
            value="<?php echo s(json_encode($cdcfdata, JSON_UNESCAPED_UNICODE)); ?>">

        <div class="model-form-section">
            <h3><?php echo icon::render('clipboard-list', 'sm', 'purple'); ?> <?php echo get_string('step4', 'gestionprojet'); ?></h3>
            <div class="gp-cdcf-norm-block">
                <strong>NF EN 16271 :</strong>
                <?php echo get_string('step4_norm_intro', 'gestionprojet'); ?>
            </div>
            <div id="cdcfRoot" class="gp-cdcf-root"></div>
        </div>

        <?php
        $step = 4;
        require_once(__DIR__ . '/teacher_dates_section.php');
        ?>

        <div class="ai-instructions-section">
            <h3><?php echo icon::render('bot', 'sm', 'purple'); ?> <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
            <div class="ai-instructions-actions" id="aiInstructionsActions"></div>
            <textarea id="ai_instructions" name="ai_instructions"
                      placeholder="<?php echo get_string('ai_instructions_placeholder', 'gestionprojet'); ?>"><?php echo s($model->ai_instructions ?? ''); ?></textarea>
            <p class="ai-instructions-help">
                <?php echo get_string('ai_instructions_help', 'gestionprojet'); ?>
            </p>
        </div>

        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">
                <?php echo icon::render('save', 'sm', 'inherit'); ?> <?php echo get_string('save', 'gestionprojet'); ?>
            </button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
                <?php echo icon::render('chevron-left', 'sm', 'inherit'); ?> <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>" class="btn-nav btn-hub">
                <?php echo get_string('correction_models', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> <?php echo icon::render('chevron-right', 'sm', 'inherit'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
    </form>

</div>

<?php
$langstrings = [
    'interactorsTitle'         => get_string('step4_interactors_title', 'gestionprojet'),
    'interactorsNorm'          => get_string('step4_interactors_norm', 'gestionprojet'),
    'interactorPlaceholder'    => get_string('step4_interactor_placeholder', 'gestionprojet'),
    'addInteractor'            => get_string('step4_add_interactor', 'gestionprojet'),
    'diagramTitle'             => get_string('step4_diagram_title', 'gestionprojet'),
    'fsTitle'                  => get_string('step4_fs_title', 'gestionprojet'),
    'fsNorm'                   => get_string('step4_fs_norm', 'gestionprojet'),
    'fsDescPlaceholder'        => get_string('step4_fs_desc_placeholder', 'gestionprojet'),
    'fsDescLabel'              => get_string('step4_fs_desc_label', 'gestionprojet'),
    'fsInteractorsLabel'       => get_string('step4_fs_interactors_label', 'gestionprojet'),
    'addFs'                    => get_string('step4_add_fs', 'gestionprojet'),
    'criterePlaceholder'       => get_string('step4_critere_placeholder', 'gestionprojet'),
    'niveauPlaceholder'        => get_string('step4_niveau_placeholder', 'gestionprojet'),
    'flexNone'                 => get_string('step4_flex_none', 'gestionprojet'),
    'flexF0'                   => get_string('step4_flex_f0', 'gestionprojet'),
    'flexF1'                   => get_string('step4_flex_f1', 'gestionprojet'),
    'flexF2'                   => get_string('step4_flex_f2', 'gestionprojet'),
    'flexF3'                   => get_string('step4_flex_f3', 'gestionprojet'),
    'addCritere'               => get_string('step4_add_critere', 'gestionprojet'),
    'noneOption'               => get_string('step4_none_option', 'gestionprojet'),
    'contraintesTitle'         => get_string('step4_contraintes_title', 'gestionprojet'),
    'contraintesNorm'          => get_string('step4_contraintes_norm', 'gestionprojet'),
    'contraintePlaceholder'    => get_string('step4_contrainte_placeholder', 'gestionprojet'),
    'justificationPlaceholder' => get_string('step4_justification_placeholder', 'gestionprojet'),
    'noFsLink'                 => get_string('step4_no_fs_link', 'gestionprojet'),
    'addContrainte'            => get_string('step4_add_contrainte', 'gestionprojet'),
];

$PAGE->requires->js_call_amd('mod_gestionprojet/cdcf_bootstrap', 'init', [[
    'cmid'              => (int)$cm->id,
    'step'              => 4,
    'groupid'           => 0,
    'mode'              => 'teacher',
    'autosaveMs'        => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    'isLocked'          => false,
    'canSubmit'         => false,
    'canRevert'         => false,
    'projetNom'         => $projetnom,
    'initial'           => $cdcfdata,
    'lang'              => $langstrings,
    'redirectAfterSave' => (new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]))->out(false),
]]);

// AI instructions Generate buttons. The new modelDataField API reads JSON
// directly from the hidden #cdcfDataField input, replacing the legacy
// produit/milieu/fp callback that no longer matches the data shape.
$PAGE->requires->js_call_amd('mod_gestionprojet/generate_ai_instructions', 'init', [[
    'cmid'              => (int)$cm->id,
    'step'              => 4,
    'aiEnabled'         => (bool)$gestionprojet->ai_enabled,
    'defaultText'       => get_string('ai_instructions_default_step4', 'gestionprojet'),
    'containerSelector' => '#aiInstructionsActions',
    'textareaSelector'  => '#ai_instructions',
    'modelDataField'    => 'cdcfDataField',
]]);

echo $OUTPUT->footer();

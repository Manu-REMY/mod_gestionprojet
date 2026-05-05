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
 * Step 4 Teacher Consigne (Provided Document): Functional Specifications
 *
 * This page lets the teacher fill in the CDCF consigne (interactors, FS,
 * constraints) that will be displayed read-only to students. It does NOT
 * contain AI instructions, submission dates, deadline dates, or a student
 * dashboard — those belong to the correction model page (step4_teacher.php).
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
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'provided']);
$PAGE->set_title(get_string('step4', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

// Get or create the provided consigne record.
$model = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->interacteurs_data = '';
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_cdcf_provided', $model);
}

require_once($CFG->dirroot . '/mod/gestionprojet/classes/cdcf_helper.php');
$cdcfdata = \mod_gestionprojet\cdcf_helper::decode($model->interacteurs_data ?? null);
$projetnom = format_string($gestionprojet->name);

echo $OUTPUT->header();

// Tabs: teacher gets consignes navigation (1, 3, 2, 4, 9); student gets work navigation (7, 4, 9, 5, 8, 6).
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 4, $canedit ? 'consignes' : 'student')
);
echo $OUTPUT->heading(get_string('step4', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

echo '<div class="alert alert-info">';
echo '<h4>' . get_string('step4_desc_title', 'gestionprojet') . '</h4>';
echo '<p>' . get_string('step4_desc_text', 'gestionprojet') . '</p>';
echo '</div>';

require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher consigne steps (reuse teacher step navigation order, only for editors).
$stepnav = $canedit ? gestionprojet_get_teacher_step_navigation($gestionprojet, 4) : ['prev' => null, 'next' => null];
?>

<div class="teacher-model-container gp-consigne">

    <form id="cdcfForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="mode" value="provided">
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

        <?php if (!$readonly): ?>
        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">
                <?php echo icon::render('save', 'sm', 'inherit'); ?> <?php echo get_string('save', 'gestionprojet'); ?>
            </button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation (editor only) -->
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
    'cmid'              => $cm->id,
    'step'              => 4,
    'groupid'           => 0,
    'mode'              => 'provided',
    'autosaveMs'        => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    'isLocked'          => $readonly,
    'canSubmit'         => false,
    'canRevert'         => false,
    'projetNom'         => $projetnom,
    'initial'           => $cdcfdata,
    'lang'              => $langstrings,
    'redirectAfterSave' => $readonly ? null : (new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]))->out(false),
]]);

echo $OUTPUT->footer();

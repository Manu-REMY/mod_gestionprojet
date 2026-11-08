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

// A student who must complete the step works on the work page, which already holds the consigne (preco 18).
if ($readonly && !gestionprojet_student_consult_only($gestionprojet, 4)) {
    redirect(new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4]));
}

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

// Draft copies follow each save of the consigne (request 23, v2.12.3): say so when there are some.
if ($canedit && (int)($gestionprojet->step4_provided ?? 0) === 1) {
    $copiesfollowing = $DB->count_records('gestionprojet_cdcf', ['gestionprojetid' => $gestionprojet->id, 'status' => 0]);
    if ($copiesfollowing > 0) {
        echo html_writer::div('ℹ ' . get_string('consigne_copies_follow', 'gestionprojet', $copiesfollowing),
            'alert alert-warning gp-consigne-copies-follow');
    }
}

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

        <?php if ($readonly): ?>
        <?php if (!empty(trim(strip_tags($model->intro_text ?? '')))): ?>
        <!-- Readers (students) see the intro text, never its editor. -->
        <div class="alert alert-info gp-consigne-intro">
            <?php echo format_text($model->intro_text, FORMAT_HTML, ['context' => $context]); ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (!$readonly): ?>
        <!-- Intro text displayed read-only to students. -->
        <div class="model-form-section gp-intro-section">
            <h3><?php echo icon::render('file-text', 'sm', 'blue'); ?> <?php echo get_string('intro_text_label', 'gestionprojet'); ?></h3>
            <p class="text-muted small"><?php echo get_string('intro_text_help', 'gestionprojet'); ?></p>
            <textarea name="intro_text" id="gp_intro_text_step4" rows="8" class="form-control gp-intro-textarea"><?php echo s($model->intro_text ?? ''); ?></textarea>
        </div>
        <?php
        // Activate the Moodle preferred rich-text editor (Atto/TinyMCE) on the textarea.
        // Use a step-specific id so TinyMCE drafts don't collide between consigne pages
        // sharing the same module context.
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->set_text($model->intro_text ?? '');
        $editor->use_editor('gp_intro_text_step4', [
            'context' => $context,
            'autosave' => false,
        ]);
        ?>
        <?php endif; ?>

        <div class="model-form-section">
            <h3><?php echo icon::render('clipboard-list', 'sm', 'purple'); ?> <?php echo get_string('step4', 'gestionprojet'); ?></h3>
            <div class="gp-cdcf-norm-block">
                <strong>NF EN 16271 :</strong>
                <?php echo get_string('step4_norm_intro', 'gestionprojet'); ?>
            </div>
            <div id="cdcfRoot" class="gp-cdcf-root"></div>
        </div>

        <?php if (!$readonly && (int)($gestionprojet->step4_provided ?? 0) === 1): ?>
        <!-- Give the current starting copy to the copies nobody worked on (preco 22). -->
        <div class="gp-reseed">
            <button type="button" class="btn btn-outline-secondary" id="gpReseedButton"
                title="<?php echo s(get_string('reseed_copies_help', 'gestionprojet')); ?>">
                <?php echo get_string('reseed_copies', 'gestionprojet'); ?>
            </button>
        </div>
        <?php endif; ?>

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
    'flagLocked'               => get_string('step4_flag_locked', 'gestionprojet'),
    'flagLockedHelp'           => get_string('step4_flag_locked_help', 'gestionprojet'),
    'flagGap'                  => get_string('step4_flag_gap', 'gestionprojet'),
    'flagGapHelp'              => get_string('step4_flag_gap_help', 'gestionprojet'),
    'gapPlaceholder'           => get_string('step4_gap_placeholder', 'gestionprojet'),
    'flagPrefill'              => get_string('step4_flag_prefill', 'gestionprojet'),
    'flagPrefillHelp'          => get_string('step4_flag_prefill_help', 'gestionprojet'),
    'flagLinks'                => get_string('step4_flag_links', 'gestionprojet'),
    'flagLinksHelp'            => get_string('step4_flag_links_help', 'gestionprojet'),
    'prefillBadge'             => get_string('step4_prefill_badge', 'gestionprojet'),
    'automark'                 => get_string('step4_automark', 'gestionprojet'),
    'automarkHelp'             => get_string('step4_automark_help', 'gestionprojet'),
    'automarkConfirm'          => get_string('step4_automark_confirm', 'gestionprojet'),
    'noLockWarning'            => get_string('step4_nolock_warning', 'gestionprojet'),
];

if (!$readonly && (int)($gestionprojet->step4_provided ?? 0) === 1) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/reseed_copies', 'init', [[
        'cmid'     => (int)$cm->id,
        'buttonId' => 'gpReseedButton',
        'dataFieldId' => 'cdcfDataField',
        'title'    => get_string('reseed_copies', 'gestionprojet'),
        'confirm'  => get_string('reseed_copies_confirm', 'gestionprojet'),
        'done'     => get_string('reseed_copies_done', 'gestionprojet'),
    ]]);
}

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
    'showFlexibility' => (bool)($gestionprojet->step4_flexibility ?? 1),
    // Lock / gap toggles on each field of the consigne (v2.11.1).
    'editFlags'         => !$readonly,
    'initial'           => $cdcfdata,
    'lang'              => $langstrings,
    'introTextSelector' => '#gp_intro_text_step4',
    'redirectAfterSave' => $readonly ? null : (new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]))->out(false),
]]);

echo $OUTPUT->footer();

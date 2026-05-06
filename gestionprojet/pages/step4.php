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
 * Step 4: Functional Specifications (Student group page)
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Check if this file is included by view.php or accessed directly
if (!defined('MOODLE_INTERNAL')) {
    // Standalone mode - requires config
    require_once(__DIR__ . '/../../../config.php');
    require_once(__DIR__ . '/../lib.php');

    $id = required_param('id', PARAM_INT); // Course module ID

    $cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);

    $context = context_module::instance($cm->id);

    // Page setup
    $PAGE->set_url('/mod/gestionprojet/pages/step4.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('step4', 'gestionprojet'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);
}

// Variables are set - continue with page logic
require_capability('mod/gestionprojet:submit', $context);

// Get user's group or requested group (for teachers)
$groupid = optional_param('groupid', 0, PARAM_INT);
if ($groupid) {
    // Only teachers can view other groups
    require_capability('mod/gestionprojet:grade', $context);
} else {
    // If not showing a specific group...
    // If teacher, they start with groupid=0 (Teacher Workspace)
    if (has_capability('mod/gestionprojet:grade', $context)) {
        $groupid = 0;
    } else {
        // Students get their assigned group
        $groupid = gestionprojet_get_user_group($cm, $USER->id);
    }
}

// If group submission is enabled, user must be in a group (unless teacher)
// Teachers with groupid=0 are allowed (handled by lib.php as individual submission)
if ($gestionprojet->group_submission && !$groupid && !has_capability('mod/gestionprojet:grade', $context)) {
    throw new \moodle_exception('not_in_group', 'gestionprojet');
}

// Determine display mode based on 2-flag model.
$step4provided = isset($gestionprojet->step4_provided) ? (int)$gestionprojet->step4_provided : 0;
$step4studentenabled = isset($gestionprojet->enable_step4) ? (int)$gestionprojet->enable_step4 : 1;
$showteacherref = ($step4provided === 1);
$showstudentform = ($step4studentenabled === 1);

// Both flags off: page should not be accessible (view.php normally handles this,
// but guard here as well for direct include scenarios).
if (!$showteacherref && !$showstudentform) {
    throw new \moodle_exception('stepdisabled', 'gestionprojet');
}

// Get or create submission (only needed when student form is shown)
$submission = null;
if ($showstudentform) {
    $submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'cdcf');
}

// Check if submitted (only relevant when student form is shown)
$isSubmitted = $showstudentform && $submission && ($submission->status == 1);
$isLocked = $isSubmitted; // Lock if submitted
$canSubmit = $showstudentform && $gestionprojet->enable_submission && !$isSubmitted;
$canRevert = $showstudentform && has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;

// Autosave handled inline at bottom of file
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [[
//     'cmid' => $cm->id,
//     'step' => 4,
//     'interval' => $gestionprojet->autosaveinterval * 1000
// ]]);

echo $OUTPUT->header();

// Render student step navigation tabs.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 4, 'student')
);

// Status display
if ($isSubmitted) {
    echo $OUTPUT->notification(get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted)), 'success');
}

// Display submission dates
$step = 4;
require_once(__DIR__ . '/student_dates_display.php');

// When step4_provided=1, the consigne is seeded into the student's record on first
// open (see gestionprojet_get_or_create_submission in lib.php). No separate read-only
// block: the editable form below is pre-populated with the teacher's content.

// --- Student form block (enable_step4 = 1) ---
if ($showstudentform):

// Get group info.
$group = groups_get_group($groupid);
if (!$group) {
    $group = new stdClass(); // Fallback to avoid crash on name access.
    $group->name = get_string('defaultgroup', 'group'); // Generic fallback.
}
?>



<div class="step4-container gp-student">
    <?php
    // Display teacher's pedagogical intro text (read-only, live-read from cdcf_provided).
    if ((int)($gestionprojet->step4_provided ?? 0) === 1) {
        $providedforintro = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
        if ($providedforintro && !empty(trim(strip_tags($providedforintro->intro_text ?? '')))) {
            echo html_writer::start_div('alert alert-info gp-consigne-intro');
            echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
            echo format_text($providedforintro->intro_text, FORMAT_HTML, ['context' => $context]);
            echo html_writer::end_div();
        }
    }
    ?>
    <?php
    // Moodle-native heading + subtitle (replaces legacy colored banner).
    echo $OUTPUT->heading(get_string('step4_page_title', 'gestionprojet'), 2);
    ?>
    <p class="text-muted small"><?php echo get_string('step4_page_subtitle', 'gestionprojet'); ?></p>

    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- Description -->
    <div class="description">
        <h3><?php echo get_string('step4_desc_title', 'gestionprojet'); ?></h3>
        <p><?php echo get_string('step4_desc_text', 'gestionprojet'); ?></p>
    </div>

    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

    <?php
    require_once($CFG->dirroot . '/mod/gestionprojet/classes/cdcf_helper.php');
    $cdcfdata = \mod_gestionprojet\cdcf_helper::decode($submission->interacteurs_data ?? null);
    $projetnom = format_string($gestionprojet->name);
    ?>

    <form id="cdcfForm" method="post" action="" class="gp-cdcf-form">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="interacteurs_data" id="cdcfDataField"
            value="<?php echo s(json_encode($cdcfdata, JSON_UNESCAPED_UNICODE)); ?>">

        <div class="gp-cdcf-norm-block">
            <strong>NF EN 16271 :</strong>
            <?php echo get_string('step4_norm_intro', 'gestionprojet'); ?>
        </div>

        <div id="cdcfRoot" class="gp-cdcf-root"></div>

        <!-- Actions section -->
        <div class="export-section">
            <?php if ($canSubmit): ?>
                <button type="button" class="btn btn-primary btn-lg btn-submit-large" id="submitButton">
                    📤 <?php echo get_string('submit', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <?php if ($canRevert): ?>
                <button type="button" class="btn btn-warning" id="revertButton">
                    ↩️ <?php echo get_string('revert_to_draft', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <?php if ($showstudentform && (int)($gestionprojet->step4_provided ?? 0) === 1): ?>
                <button type="button"
                        class="btn btn-warning"
                        id="resetButton"
                        <?php echo $isLocked ? 'disabled title="' . s(get_string('reset_disabled_tooltip', 'gestionprojet')) . '"' : ''; ?>>
                    <?php echo get_string('reset_button_label', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php
// Wire the new modal-based submit flow + AI progress banner.
$isSubmitted = ($submission && (int)$submission->status === 1);
require __DIR__ . '/student_submit_helper.php';

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
    'cmid'          => (int)$cm->id,
    'step'          => 4,
    'groupid'       => (int)$groupid,
    'autosaveMs'    => (int)$gestionprojet->autosave_interval * 1000,
    'isLocked'      => (bool)$isLocked,
    'canSubmit'     => (bool)$canSubmit,
    'canRevert'     => (bool)$canRevert,
    'projetNom'     => $projetnom,
    'initial'       => $cdcfdata,
    'lang'          => $langstrings,
    'confirmSubmit' => get_string('confirm_submission', 'gestionprojet'),
    'confirmRevert' => get_string('confirm_revert', 'gestionprojet'),
    'resetEnabled'  => (bool)((int)($gestionprojet->step4_provided ?? 0) === 1) && !$isLocked,
    'resetUrl'      => (new moodle_url('/mod/gestionprojet/ajax/reset_to_provided.php'))->out(false),
    'sesskey'       => sesskey(),
    'resetLang'     => [
        'modalTitle'   => get_string('reset_modal_title', 'gestionprojet'),
        'modalBody'    => get_string('reset_modal_body', 'gestionprojet'),
        'modalConfirm' => get_string('reset_modal_confirm', 'gestionprojet'),
        'modalCancel'  => get_string('reset_modal_cancel', 'gestionprojet'),
        'success'      => get_string('reset_success', 'gestionprojet'),
        'genericError' => get_string('error', 'core'),
    ],
]]);
?>


<?php
endif; // $showstudentform

echo $OUTPUT->footer();
?>
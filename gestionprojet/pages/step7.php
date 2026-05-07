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
 * Step 7: Needs Expression (Horn Chart) - Student page
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
    $PAGE->set_url('/mod/gestionprojet/pages/step7.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('step7', 'gestionprojet'));
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

// Get or create submission
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'besoin_eleve');

// Check if submitted
$isSubmitted = $submission->status == 1;
$isLocked = $isSubmitted; // Lock if submitted
$canSubmit = $gestionprojet->enable_submission && !$isSubmitted; // Note: enable_submission might not be in $gestionprojet, usually it's just implicit or a setting. Assuming always enabled unless closed.
// Checking db/install.xml, there is no enable_submission field. So assuming always available.
$canSubmit = !$isSubmitted;

$canRevert = has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;

$readonly = $isLocked;

// Resolve group info (used by group-info banner below).
$group = null;
if ($gestionprojet->group_submission && $groupid) {
    $group = groups_get_group($groupid);
}

echo $OUTPUT->header();

// Render student step navigation tabs.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 7, 'student')
);

// Status display
if ($isSubmitted) {
    echo $OUTPUT->notification(get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted)), 'success');
}

// Display submission dates
$step = 7;
require_once(__DIR__ . '/student_dates_display.php');

// Open student wrapper for full-width + blue accent.
echo '<div class="gp-student">';

// Group info banner (only when group submission is enabled).
if ($gestionprojet->group_submission && $group) {
    echo '<div class="group-info">';
    echo '👥 <strong>' . get_string('your_group', 'gestionprojet') . ':</strong> ';
    echo format_string($group->name);
    echo '</div>';
}

// AI Evaluation Feedback Display.
require_once(__DIR__ . '/student_ai_feedback_display.php');

// Moodle-native heading (replaces legacy emoji-prefixed h2).
echo $OUTPUT->heading(get_string('step7', 'gestionprojet'), 2);

// Description
echo '<div class="alert alert-info">';
echo '<h4>' . get_string('bete_a_corne_title', 'gestionprojet') . '</h4>';
echo '<p>' . get_string('bete_a_corne_description', 'gestionprojet') . '</p>';
echo '<ul>';
echo '<li><strong>' . get_string('aqui', 'gestionprojet') . '</strong> - ' . get_string('aqui_help', 'gestionprojet') . '</li>';
echo '<li><strong>' . get_string('surquoi', 'gestionprojet') . '</strong> - ' . get_string('surquoi_help', 'gestionprojet') . '</li>';
echo '<li><strong>' . get_string('dansquelbut', 'gestionprojet') . '</strong> - ' . get_string('dansquelbut_help', 'gestionprojet') . '</li>';
echo '</ul>';
echo '</div>';

?>

<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0;">
                <?php echo get_string('bete_a_corne_diagram', 'gestionprojet'); ?>
            </h4>
        </div>

        <div id="diagramContainer" class="diagram-box">
            <svg id="beteACorneCanvas" class="diagram-svg"></svg>
        </div>

        <form id="besoinEleveForm">
            <div class="form-group mb-3">
                <label for="aqui" class="label-highlight">
                    <?php echo get_string('aqui', 'gestionprojet'); ?>
                </label>
                <small class="form-text text-muted">
                    <?php echo get_string('aqui_help', 'gestionprojet'); ?>
                </small>
                <textarea class="form-control" id="aqui" name="aqui" rows="3" <?php echo ($readonly) ? 'readonly' : ''; ?>><?php echo $submission ? s($submission->aqui) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="surquoi" class="label-highlight">
                    <?php echo get_string('surquoi', 'gestionprojet'); ?>
                </label>
                <small class="form-text text-muted">
                    <?php echo get_string('surquoi_help', 'gestionprojet'); ?>
                </small>
                <textarea class="form-control" id="surquoi" name="surquoi" rows="3" <?php echo ($readonly) ? 'readonly' : ''; ?>><?php echo $submission ? s($submission->surquoi) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="dansquelbut" class="label-highlight">
                    <?php echo get_string('dansquelbut', 'gestionprojet'); ?>
                </label>
                <small class="form-text text-muted">
                    <?php echo get_string('dansquelbut_help', 'gestionprojet'); ?>
                </small>
                <textarea class="form-control" id="dansquelbut" name="dansquelbut" rows="3" <?php echo ($readonly) ? 'readonly' : ''; ?>><?php echo $submission ? s($submission->dansquelbut) : ''; ?></textarea>
            </div>

            <div class="export-section">
                <?php if ($canSubmit): ?>
                    <button type="button" class="btn btn-primary btn-lg btn-submit-large" id="submitButton">
                        📤
                        <?php echo get_string('submit', 'gestionprojet'); ?>
                    </button>
                <?php endif; ?>

                <?php if ($canRevert): ?>
                    <button type="button" class="btn btn-warning" id="revertButton">
                        ↩️
                        <?php echo get_string('revert_to_draft', 'gestionprojet'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>
</div><?php // /.gp-student ?>

<?php
$PAGE->requires->js_call_amd('mod_gestionprojet/step7', 'init', [[
    'cmid' => (int)$cm->id,
    'autosaveInterval' => (int)$gestionprojet->autosave_interval * 1000,
    'groupid' => (int)$groupid,
    'isLocked' => (bool)$isLocked,
    'strings' => [
        'confirm_submission' => get_string('confirm_submission', 'gestionprojet'),
        'confirm_revert' => get_string('confirm_revert', 'gestionprojet'),
        'aquiTitle' => get_string('diagram_aqui_title', 'gestionprojet'),
        'aquiSubtitle' => get_string('diagram_aqui_subtitle', 'gestionprojet'),
        'surquoiTitle' => get_string('diagram_surquoi_title', 'gestionprojet'),
        'surquoiSubtitle' => get_string('diagram_surquoi_subtitle', 'gestionprojet'),
        'productLabel' => get_string('diagram_product_label', 'gestionprojet'),
        'productSubtitle' => get_string('diagram_product_subtitle', 'gestionprojet'),
        'dansquelbutTitle' => get_string('diagram_dansquelbut_title', 'gestionprojet'),
        'dansquelbutSubtitle' => get_string('diagram_dansquelbut_subtitle', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->footer();

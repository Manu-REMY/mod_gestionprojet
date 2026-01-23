<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 7: Expression du Besoin (Bête à Corne) - Student page
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
    $groupid = gestionprojet_get_user_group($cm, $USER->id);
}

// If group submission is enabled, user must be in a group
if ($gestionprojet->group_submission && !$groupid) {
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

echo $OUTPUT->header();

// Status display
if ($isSubmitted) {
    echo $OUTPUT->notification(get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted)), 'success');
}

// Navigation buttons
echo '<div class="navigation-container" style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">';
echo '<div>';
echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="btn btn-secondary">🏠 ' . get_string('home', 'gestionprojet') . '</a>';
echo '</div>';
echo '</div>';


echo '<h2>🦏 ' . get_string('step7', 'gestionprojet') . '</h2>';

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

if (isset($submission->grade) && $submission->grade !== null) {
    echo '<div class="alert alert-success">';
    echo '<h4>' . get_string('grade', 'gestionprojet') . ': ' . number_format($submission->grade, 2) . '/20</h4>';
    if (!empty($submission->feedback)) {
        echo '<p><strong>' . get_string('feedback', 'gestionprojet') . ':</strong><br>' . format_text($submission->feedback, FORMAT_HTML) . '</p>';
    }
    echo '</div>';
}

?>

<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0;">
                <?php echo get_string('bete_a_corne_diagram', 'gestionprojet'); ?>
            </h4>
        </div>

        <div id="diagramContainer"
            style="background: #fafbfc; padding: 20px; border-radius: 10px; border: 2px solid #e9ecef; margin-bottom: 30px;">
            <svg id="beteACorneCanvas" style="width: 100%; height: 500px;"></svg>
        </div>

        <form id="besoinEleveForm">
            <div class="form-group mb-3">
                <label for="aqui" style="font-weight: 600; color: #667eea;">
                    <?php echo get_string('aqui', 'gestionprojet'); ?>
                </label>
                <small class="form-text text-muted">
                    <?php echo get_string('aqui_help', 'gestionprojet'); ?>
                </small>
                <textarea class="form-control" id="aqui" name="aqui" rows="3" <?php echo ($readonly) ? 'readonly' : ''; ?>><?php echo $submission ? s($submission->aqui) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="surquoi" style="font-weight: 600; color: #667eea;">
                    <?php echo get_string('surquoi', 'gestionprojet'); ?>
                </label>
                <small class="form-text text-muted">
                    <?php echo get_string('surquoi_help', 'gestionprojet'); ?>
                </small>
                <textarea class="form-control" id="surquoi" name="surquoi" rows="3" <?php echo ($readonly) ? 'readonly' : ''; ?>><?php echo $submission ? s($submission->surquoi) : ''; ?></textarea>
            </div>

            <div class="form-group mb-3">
                <label for="dansquelbut" style="font-weight: 600; color: #667eea;">
                    <?php echo get_string('dansquelbut', 'gestionprojet'); ?>
                </label>
                <small class="form-text text-muted">
                    <?php echo get_string('dansquelbut_help', 'gestionprojet'); ?>
                </small>
                <textarea class="form-control" id="dansquelbut" name="dansquelbut" rows="3" <?php echo ($readonly) ? 'readonly' : ''; ?>><?php echo $submission ? s($submission->dansquelbut) : ''; ?></textarea>
            </div>

            <div class="export-section" style="margin-top: 40px; text-align: center;">
                <?php if ($canSubmit): ?>
                    <button type="button" class="btn btn-primary btn-lg" id="submitButton"
                        style="padding: 15px 40px; font-size: 18px; border-radius: 50px;">
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


// Call AMD module
$PAGE->requires->js_call_amd('mod_gestionprojet/step7', 'init', [[
'cmid' => $cm->id,
'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
'groupid' => $groupid,
'isLocked' => $isLocked,
'strings' => [
'confirm_submission' => get_string('confirm_submission', 'gestionprojet'),
'confirm_revert' => get_string('confirm_revert', 'gestionprojet')
]
]]);
?>
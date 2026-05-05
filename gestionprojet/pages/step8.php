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
 * Step 8: Logbook (Student logbook)
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
    $PAGE->set_url('/mod/gestionprojet/pages/step8.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('step8', 'gestionprojet'));
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
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'carnet');

// Check if submitted
$isSubmitted = $submission->status == 1;
$isLocked = $isSubmitted; // Lock if submitted
$canSubmit = $gestionprojet->enable_submission && !$isSubmitted;
$canRevert = has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;

echo $OUTPUT->header();

// Render student step navigation tabs.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 8, 'student')
);

// Status display
if ($isSubmitted) {
    echo $OUTPUT->notification(get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted)), 'success');
}

// Display submission dates
$step = 8;
require_once(__DIR__ . '/student_dates_display.php');

$disabled = $isLocked ? 'disabled readonly' : '';

// Get group info
$group = groups_get_group($groupid);
if (!$group) {
    $group = new stdClass(); // Fallback to avoid crash on name access
    $group->name = get_string('defaultgroup', 'group'); // Generic fallback
}

// Parse tasks data (stored as JSON array)
$tasks_data = [];
if ($submission->tasks_data) {
    $tasks_data = json_decode($submission->tasks_data, true) ?? [];
}

// Default tasks if empty
if (empty($tasks_data)) {
    $tasks_data = [
        [
            'date' => date('Y-m-d'),
            'tasks_today' => '',
            'tasks_future' => '',
            'status' => 'ontime' // ahead, ontime, late
        ]
    ];
}
?>

<div class="step8-container gp-student">
    <?php
    // Moodle-native heading + subtitle (replaces legacy colored banner).
    echo $OUTPUT->heading(get_string('step8', 'gestionprojet'), 2);
    ?>
    <p class="text-muted small"><?php echo get_string('step8_desc', 'gestionprojet'); ?></p>

    <!-- Group info -->
    <div class="group-info">
        👥 <strong>
            <?php echo get_string('your_group', 'gestionprojet'); ?>:
        </strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

    <form id="carnetForm" method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Logbook entries -->
        <div class="logbook-section">
            <table class="logbook-table">
                <thead>
                    <tr>
                        <th style="width: 140px;"><?php echo get_string('logbook_date', 'gestionprojet'); ?></th>
                        <th><?php echo get_string('logbook_tasks_today', 'gestionprojet'); ?></th>
                        <th><?php echo get_string('logbook_tasks_future', 'gestionprojet'); ?></th>
                        <th style="width: 180px;"><?php echo get_string('logbook_status', 'gestionprojet'); ?></th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody id="logbookTableBody"></tbody>
            </table>

            <?php if (!$isLocked): ?>
                <button type="button" id="addLogEntryButton" class="btn-add-line">
                    ➕
                    <?php echo get_string('logbook_add_line', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Actions section -->
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

            <button type="button" id="exportPdfButton" class="btn-export btn-export-margin">
                📄
                <?php echo get_string('export_pdf', 'gestionprojet'); ?>
            </button>
        </div>
    </form>
</div>

<?php
$PAGE->requires->js_call_amd('mod_gestionprojet/step8', 'init', [[
    'cmid' => (int)$cm->id,
    'autosaveInterval' => (int)$gestionprojet->autosave_interval * 1000,
    'groupid' => (int)$groupid,
    'isLocked' => (bool)$isLocked,
    'tasksData' => $tasks_data,
    'strings' => [
        'confirm_submission' => get_string('confirm_submission', 'gestionprojet'),
        'confirm_revert' => get_string('confirm_revert', 'gestionprojet'),
        'export_pdf_coming_soon' => get_string('export_pdf_coming_soon', 'gestionprojet'),
        'status_ahead' => get_string('logbook_status_ahead', 'gestionprojet'),
        'status_ontime' => get_string('logbook_status_ontime', 'gestionprojet'),
        'status_late' => get_string('logbook_status_late', 'gestionprojet'),
        'remove_line' => get_string('logbook_remove_line', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->footer();
?>
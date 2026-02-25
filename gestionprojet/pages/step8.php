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
 * Step 8: Student logbook page
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

// Load AMD module.
$PAGE->requires->js_call_amd('mod_gestionprojet/step8', 'init', [[
    'cmid' => $cm->id,
    'step' => 8,
    'groupid' => $groupid,
    'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
    'isLocked' => $isLocked,
    'tasksData' => $tasks_data,
    'strings' => [
        'confirm_submission' => get_string('confirm_submission', 'gestionprojet'),
        'confirm_revert' => get_string('confirm_revert', 'gestionprojet'),
        'status_ahead' => get_string('logbook_status_ahead', 'gestionprojet'),
        'status_ontime' => get_string('logbook_status_ontime', 'gestionprojet'),
        'status_late' => get_string('logbook_status_late', 'gestionprojet'),
        'remove_line' => get_string('logbook_remove_line', 'gestionprojet'),
        'confirm_remove_line' => get_string('logbook_remove_line', 'gestionprojet') . '?',
    ],
]]);
?>

<div class="step8-container">
    <!-- Navigation -->
    <?php
    $nav_links = gestionprojet_get_navigation_links($gestionprojet, $cm->id, 'step8');
    ?>
    <div class="navigation-top">
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>🏠</span>
                <span>
                    <?php echo get_string('home', 'gestionprojet'); ?>
                </span>
            </a>
            <?php if ($nav_links['prev']): ?>
                <a href="<?php echo $nav_links['prev']; ?>" class="nav-button nav-button-prev">
                    <span>←</span>
                    <span>
                        <?php echo get_string('previous', 'gestionprojet'); ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
        <?php if ($nav_links['next']): ?>
            <a href="<?php echo $nav_links['next']; ?>" class="nav-button">
                <span>
                    <?php echo get_string('next', 'gestionprojet'); ?>
                </span>
                <span>→</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Header -->
    <div class="header-section">
        <h2>📓
            <?php echo get_string('step8', 'gestionprojet'); ?>
        </h2>
        <p>
            <?php echo get_string('step8_desc', 'gestionprojet'); ?>
        </p>
    </div>

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
                <button type="button" class="btn-add-line" id="addLogEntryButton">
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

            <button type="button" class="btn-export btn-export-margin" id="exportPdfButton">
                📄
                <?php echo get_string('export_pdf', 'gestionprojet'); ?>
            </button>
        </div>
    </form>
</div>

<?php
echo $OUTPUT->footer();
?>
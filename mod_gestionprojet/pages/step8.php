<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 8: Carnet de bord (Student logbook)
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
                <button type="button" class="btn-add-line" onclick="addLogEntry()">
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

            <button type="button" class="btn-export btn-export-margin" onclick="exportPDF()">
                📄
                <?php echo get_string('export_pdf', 'gestionprojet'); ?>
            </button>
        </div>
    </form>
</div>

<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>

<script>
    // Wait for RequireJS and jQuery
    (function waitRequire() {
        if (typeof require === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(waitRequire, 50);
            return;
        }

        require(['jquery', 'mod_gestionprojet/autosave'], function ($, Autosave) {
            var cmid = <?php echo $cm->id; ?>;
            var step = 8;
            var autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
            var groupid = <?php echo $groupid; ?>;

            // Custom serialization for step 8
            var serializeData = function () {
                var formData = {};
                formData['tasks_data'] = JSON.stringify(tasksData);
                return formData;
            };

            var onSave = function (response) {
                // console.log('Step 8 saved', response);
            };

            var isLocked = <?php echo $isLocked ? 'true' : 'false'; ?>;

            // Handle Submission
            $('#submitButton').on('click', function () {
                if (confirm('<?php echo get_string('confirm_submission', 'gestionprojet'); ?>')) {
                    $.ajax({
                        url: '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            groupid: groupid,
                            action: 'submit',
                            sesskey: M.cfg.sesskey
                        },
                        success: function (response) {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert('Error submitting');
                            }
                        }
                    });
                }
            });

            // Handle Revert
            $('#revertButton').on('click', function () {
                if (confirm('<?php echo get_string('confirm_revert', 'gestionprojet'); ?>')) {
                    $.ajax({
                        url: '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            groupid: groupid,
                            action: 'revert',
                            sesskey: M.cfg.sesskey
                        },
                        success: function (response) {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert('Error reverting');
                            }
                        }
                    });
                }
            });

            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#carnetForm',
                    serialize: serializeData,
                    onSave: onSave
                });
            }
        });
    })();

    // Tasks data
    let tasksData = <?php echo json_encode($tasks_data); ?>;
    const isLocked = <?php echo $isLocked ? 'true' : 'false'; ?>;

    function renderLogEntries() {
        const tbody = document.getElementById('logbookTableBody');
        tbody.innerHTML = '';

        tasksData.forEach((entry, index) => {
            const tr = document.createElement('tr');

            // Date Cell
            const tdDate = document.createElement('td');
            const dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.className = 'form-control';
            dateInput.value = entry.date;
            dateInput.disabled = isLocked;
            dateInput.onchange = (e) => {
                tasksData[index].date = e.target.value;
            };
            tdDate.appendChild(dateInput);
            tr.appendChild(tdDate);

            // Today Tasks Cell
            const tdToday = document.createElement('td');
            const todayInput = document.createElement('textarea');
            todayInput.className = 'form-control';
            todayInput.rows = 3;
            todayInput.value = entry.tasks_today;
            todayInput.disabled = isLocked;
            todayInput.onchange = (e) => {
                tasksData[index].tasks_today = e.target.value;
            };
            tdToday.appendChild(todayInput);
            tr.appendChild(tdToday);

            // Future Tasks Cell
            const tdFuture = document.createElement('td');
            const futureInput = document.createElement('textarea');
            futureInput.className = 'form-control';
            futureInput.rows = 3;
            futureInput.value = entry.tasks_future;
            futureInput.disabled = isLocked;
            futureInput.onchange = (e) => {
                tasksData[index].tasks_future = e.target.value;
            };
            tdFuture.appendChild(futureInput);
            tr.appendChild(tdFuture);

            // Status Cell
            const tdStatus = document.createElement('td');
            const statusDiv = document.createElement('div');
            statusDiv.className = 'status-radios';

            const statuses = [
                { id: 'ahead', label: <?php echo json_encode(get_string('logbook_status_ahead', 'gestionprojet')); ?> },
                { id: 'ontime', label: <?php echo json_encode(get_string('logbook_status_ontime', 'gestionprojet')); ?> },
                { id: 'late', label: <?php echo json_encode(get_string('logbook_status_late', 'gestionprojet')); ?> }
            ];

            statuses.forEach(status => {
                const label = document.createElement('label');
                label.className = 'status-radio-label';

                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'status_' + index;
                input.value = status.id;
                input.checked = entry.status === status.id;
                input.disabled = isLocked;
                input.onchange = () => {
                    tasksData[index].status = status.id;
                };

                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + status.label));
                statusDiv.appendChild(label);
            });
            tdStatus.appendChild(statusDiv);
            tr.appendChild(tdStatus);

            // Actions Cell
            const tdAction = document.createElement('td');
            if (!isLocked && tasksData.length > 1) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-remove-line';
                removeBtn.innerHTML = '🗑️';
                removeBtn.title = '<?php echo get_string('logbook_remove_line', 'gestionprojet'); ?>';
                removeBtn.onclick = () => {
                    if (confirm('<?php echo get_string('logbook_remove_line', 'gestionprojet'); ?>?')) {
                        tasksData.splice(index, 1);
                        renderLogEntries();
                    }
                };
                tdAction.appendChild(removeBtn);
            }
            tr.appendChild(tdAction);

            tbody.appendChild(tr);
        });
    }

    function addLogEntry() {
        tasksData.push({
            date: new Date().toISOString().split('T')[0],
            tasks_today: '',
            tasks_future: '',
            status: 'ontime'
        });
        renderLogEntries();
    }

    function exportPDF() {
        window.location.href = '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/export_pdf.php?id=<?php echo $cm->id; ?>&groupid=<?php echo $groupid; ?>';
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        renderLogEntries();
    });
</script>

<?php
echo $OUTPUT->footer();
?>
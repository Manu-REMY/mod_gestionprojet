<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared submission section for student pages.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Map step to teacher table.
$teacherTables = [
    4 => 'gestionprojet_cdcf_teacher',
    5 => 'gestionprojet_essai_teacher',
    6 => 'gestionprojet_rapport_teacher',
    7 => 'gestionprojet_besoin_eleve_teacher',
    8 => 'gestionprojet_carnet_teacher',
];

// Get teacher model for this step to retrieve dates.
$teacherModel = null;
if (isset($teacherTables[$step])) {
    $teacherModel = $DB->get_record($teacherTables[$step], ['gestionprojetid' => $gestionprojet->id]);
}

// Check submission status.
$submissionEnabled = !empty($gestionprojet->enable_submission);
$isSubmitted = !empty($submission->status) && $submission->status == 1;
$timeSubmitted = $submission->timesubmitted ?? 0;

// Get dates from teacher model.
$submissionDate = $teacherModel->submission_date ?? 0;
$deadlineDate = $teacherModel->deadline_date ?? 0;

// Calculate status.
$now = time();
$isOverdue = ($deadlineDate > 0 && $now > $deadlineDate && !$isSubmitted);
$isDueSoon = ($submissionDate > 0 && $now < $submissionDate && ($submissionDate - $now) < (3 * 24 * 60 * 60)); // 3 days
?>

<style>
    .submission-section {
        background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
        border-radius: 12px;
        padding: 20px;
        margin-top: 25px;
        border-left: 4px solid #4caf50;
    }
    .submission-section.submitted {
        border-left-color: #2196f3;
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    }
    .submission-section.overdue {
        border-left-color: #f44336;
        background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
    }
    .submission-section.due-soon {
        border-left-color: #ff9800;
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    }
    .submission-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .submission-info h3 {
        margin: 0 0 10px 0;
        font-size: 16px;
        color: #333;
    }
    .submission-dates {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .date-item {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .date-item .label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }
    .date-item .value {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }
    .date-item .value.overdue {
        color: #f44336;
    }
    .date-item .value.due-soon {
        color: #ff9800;
    }
    .submission-status {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    .submission-status.draft {
        background: #e0e0e0;
        color: #616161;
    }
    .submission-status.submitted {
        background: #c8e6c9;
        color: #2e7d32;
    }
    .submission-status.overdue {
        background: #ffcdd2;
        color: #c62828;
    }
    .btn-submit-step {
        background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .btn-submit-step:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }
    .btn-submit-step:disabled {
        background: #bdbdbd;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .submission-submitted-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #2e7d32;
        font-weight: 500;
    }
    .submission-submitted-info .icon {
        font-size: 24px;
    }
    @media (max-width: 768px) {
        .submission-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<?php if ($submissionEnabled): ?>
<div class="submission-section <?php echo $isSubmitted ? 'submitted' : ($isOverdue ? 'overdue' : ($isDueSoon ? 'due-soon' : '')); ?>">
    <div class="submission-header">
        <div class="submission-info">
            <h3><?php echo get_string('submission_section_title', 'gestionprojet'); ?></h3>
            <div class="submission-dates">
                <?php if ($submissionDate > 0): ?>
                <div class="date-item">
                    <span class="label"><?php echo get_string('submission_date', 'gestionprojet'); ?></span>
                    <span class="value <?php echo $isDueSoon ? 'due-soon' : ''; ?>">
                        <?php echo userdate($submissionDate, get_string('strftimedatefullshort', 'langconfig')); ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($deadlineDate > 0): ?>
                <div class="date-item">
                    <span class="label"><?php echo get_string('deadline_date', 'gestionprojet'); ?></span>
                    <span class="value <?php echo $isOverdue ? 'overdue' : ''; ?>">
                        <?php echo userdate($deadlineDate, get_string('strftimedatefullshort', 'langconfig')); ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($isSubmitted && $timeSubmitted > 0): ?>
                <div class="date-item">
                    <span class="label"><?php echo get_string('submitted_at', 'gestionprojet'); ?></span>
                    <span class="value"><?php echo userdate($timeSubmitted, get_string('strftimedatefullshort', 'langconfig')); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="submission-actions">
            <?php if ($isSubmitted): ?>
                <div class="submission-submitted-info">
                    <span class="icon">&#9989;</span>
                    <span><?php echo get_string('already_submitted', 'gestionprojet'); ?></span>
                </div>
            <?php else: ?>
                <button type="button" class="btn-submit-step" id="submitStepBtn" onclick="submitStep()">
                    <span>&#128228;</span>
                    <?php echo get_string('submit_step', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function submitStep() {
    if (!confirm('<?php echo get_string('confirm_submit', 'gestionprojet'); ?>')) {
        return;
    }

    var btn = document.getElementById('submitStepBtn');
    btn.disabled = true;
    btn.innerHTML = '<span>&#8987;</span> <?php echo get_string('submitting', 'gestionprojet'); ?>';

    var formData = new FormData();
    formData.append('cmid', <?php echo $cm->id; ?>);
    formData.append('step', <?php echo $step; ?>);
    formData.append('action', 'submit');
    formData.append('groupid', <?php echo $groupid ?? 0; ?>);
    formData.append('sesskey', M.cfg.sesskey);

    fetch('<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit_step.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success and reload page
            alert('<?php echo get_string('submissionsuccess', 'gestionprojet'); ?>');
            window.location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<span>&#128228;</span> <?php echo get_string('submit_step', 'gestionprojet'); ?>';
            alert(data.message || '<?php echo get_string('submissionerror', 'gestionprojet'); ?>');
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = '<span>&#128228;</span> <?php echo get_string('submit_step', 'gestionprojet'); ?>';
        alert('<?php echo get_string('submissionerror', 'gestionprojet'); ?>');
        console.error('Error:', error);
    });
}
</script>
<?php endif; ?>

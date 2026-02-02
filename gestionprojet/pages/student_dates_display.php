<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared dates display for student pages.
 * This shows submission dates to students from the teacher model.
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

// Get dates from teacher model.
$submissionDate = $teacherModel->submission_date ?? 0;
$deadlineDate = $teacherModel->deadline_date ?? 0;

// Calculate status.
$now = time();
$isOverdue = ($deadlineDate > 0 && $now > $deadlineDate && !$isSubmitted);
$isDueSoon = ($submissionDate > 0 && $now < $submissionDate && ($submissionDate - $now) < (3 * 24 * 60 * 60)); // 3 days

// Only display if we have at least one date set.
if ($submissionDate > 0 || $deadlineDate > 0):
?>
<style>
    .student-dates-display {
        background: linear-gradient(135deg, #e8f4fd 0%, #f0f8ff 100%);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        border-left: 4px solid #2196f3;
    }
    .student-dates-display.overdue {
        border-left-color: #f44336;
        background: linear-gradient(135deg, #ffebee 0%, #fff5f5 100%);
    }
    .student-dates-display.due-soon {
        border-left-color: #ff9800;
        background: linear-gradient(135deg, #fff3e0 0%, #fffaf5 100%);
    }
    .student-dates-display .date-icon {
        font-size: 24px;
    }
    .student-dates-display .dates-info {
        display: flex;
        gap: 25px;
        flex-wrap: wrap;
    }
    .student-dates-display .date-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .student-dates-display .date-label {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .student-dates-display .date-value {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }
    .student-dates-display .date-value.overdue {
        color: #c62828;
    }
    .student-dates-display .date-value.due-soon {
        color: #ef6c00;
    }
    .student-dates-display .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .student-dates-display .status-badge.overdue {
        background: #ffcdd2;
        color: #c62828;
    }
    .student-dates-display .status-badge.due-soon {
        background: #ffe0b2;
        color: #ef6c00;
    }
</style>

<div class="student-dates-display <?php echo $isOverdue ? 'overdue' : ($isDueSoon ? 'due-soon' : ''); ?>">
    <span class="date-icon"><?php echo $isOverdue ? '&#9888;' : ($isDueSoon ? '&#8987;' : '&#128197;'); ?></span>
    <div class="dates-info">
        <?php if ($submissionDate > 0): ?>
        <div class="date-item">
            <span class="date-label"><?php echo get_string('expected_submission', 'gestionprojet'); ?></span>
            <span class="date-value <?php echo $isDueSoon ? 'due-soon' : ''; ?>">
                <?php echo userdate($submissionDate, get_string('strftimedatefullshort', 'langconfig')); ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($deadlineDate > 0): ?>
        <div class="date-item">
            <span class="date-label"><?php echo get_string('deadline', 'gestionprojet'); ?></span>
            <span class="date-value <?php echo $isOverdue ? 'overdue' : ''; ?>">
                <?php echo userdate($deadlineDate, get_string('strftimedatefullshort', 'langconfig')); ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($isOverdue): ?>
        <span class="status-badge overdue"><?php echo get_string('overdue', 'gestionprojet'); ?></span>
    <?php elseif ($isDueSoon): ?>
        <span class="status-badge due-soon"><?php echo get_string('due_soon', 'gestionprojet'); ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

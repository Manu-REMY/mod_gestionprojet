<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared dates section for teacher correction models.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Get planning data to auto-fill submission dates from milestones.
$planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

// Calculate milestone dates based on task hours if planning exists.
$milestones = [];
if ($planning && $planning->startdate && $planning->enddate) {
    $totalHours = ($planning->task1_hours ?? 0) + ($planning->task2_hours ?? 0) +
                  ($planning->task3_hours ?? 0) + ($planning->task4_hours ?? 0) +
                  ($planning->task5_hours ?? 0);

    if ($totalHours > 0) {
        $projectDuration = $planning->enddate - $planning->startdate;
        $currentTime = $planning->startdate;

        // Step 7 (Besoin Eleve) = after task 1 (Step 7)
        $currentTime += ($planning->task1_hours / $totalHours) * $projectDuration;
        $milestones[7] = (int) $currentTime;

        // Step 4 (CDCF) = after task 2 (Step 4)
        $currentTime += ($planning->task2_hours / $totalHours) * $projectDuration;
        $milestones[4] = (int) $currentTime;

        // Step 5 (Essai) = after task 3 (Step 5)
        $currentTime += ($planning->task3_hours / $totalHours) * $projectDuration;
        $milestones[5] = (int) $currentTime;

        // Step 8 (Carnet) = after task 4 (Step 8)
        $currentTime += ($planning->task4_hours / $totalHours) * $projectDuration;
        $milestones[8] = (int) $currentTime;

        // Step 6 (Rapport) = end of project (after task 5)
        $milestones[6] = (int) $planning->enddate;
    }
}

// Get default submission date from milestones for current step.
$defaultSubmissionDate = isset($milestones[$step]) ? $milestones[$step] : 0;

// Format dates for input fields.
$submissionDateValue = '';
$deadlineDateValue = '';

if (!empty($model->submission_date)) {
    $submissionDateValue = date('Y-m-d', $model->submission_date);
} else if ($defaultSubmissionDate > 0) {
    $submissionDateValue = date('Y-m-d', $defaultSubmissionDate);
}

if (!empty($model->deadline_date)) {
    $deadlineDateValue = date('Y-m-d', $model->deadline_date);
}
?>

<style>
    .dates-section {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        border-left: 4px solid #2196f3;
    }
    .dates-section h3 {
        margin: 0 0 15px 0;
        color: #1565c0;
        font-size: 16px;
    }
    .dates-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .date-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .date-field label {
        font-weight: 500;
        color: #424242;
        font-size: 14px;
    }
    .date-field input[type="date"] {
        padding: 10px 12px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    .date-field input[type="date"]:focus {
        border-color: #2196f3;
        outline: none;
    }
    .date-field .date-help {
        font-size: 12px;
        color: #757575;
        margin-top: 3px;
    }
    .date-auto-filled {
        background-color: #fff9c4 !important;
        border-color: #fbc02d !important;
    }
    .auto-fill-notice {
        font-size: 11px;
        color: #f57c00;
        margin-top: 3px;
    }
    @media (max-width: 768px) {
        .dates-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dates-section">
    <h3><?php echo get_string('submission_dates', 'gestionprojet'); ?></h3>
    <div class="dates-grid">
        <div class="date-field">
            <label for="submission_date"><?php echo get_string('submission_date', 'gestionprojet'); ?></label>
            <input type="date" id="submission_date" name="submission_date"
                   value="<?php echo $submissionDateValue; ?>"
                   class="<?php echo (empty($model->submission_date) && $defaultSubmissionDate > 0) ? 'date-auto-filled' : ''; ?>">
            <span class="date-help"><?php echo get_string('submission_date_help', 'gestionprojet'); ?></span>
            <?php if (empty($model->submission_date) && $defaultSubmissionDate > 0): ?>
                <span class="auto-fill-notice"><?php echo get_string('date_from_planning', 'gestionprojet'); ?></span>
            <?php endif; ?>
        </div>
        <div class="date-field">
            <label for="deadline_date"><?php echo get_string('deadline_date', 'gestionprojet'); ?></label>
            <input type="date" id="deadline_date" name="deadline_date"
                   value="<?php echo $deadlineDateValue; ?>">
            <span class="date-help"><?php echo get_string('deadline_date_help', 'gestionprojet'); ?></span>
        </div>
    </div>
</div>

<script>
// Helper function to convert date input to timestamp.
function dateToTimestamp(dateStr) {
    if (!dateStr) return null;
    var date = new Date(dateStr);
    return Math.floor(date.getTime() / 1000);
}

// Helper function to get date values for serialization.
function getDateValues() {
    return {
        submission_date: dateToTimestamp(document.getElementById('submission_date').value),
        deadline_date: dateToTimestamp(document.getElementById('deadline_date').value)
    };
}
</script>

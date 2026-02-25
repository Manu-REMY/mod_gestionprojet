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
 * Shared dates section for teacher correction models.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

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

        // Step 7 (Student Needs Expression) = after task 1 (Step 7).
        $currentTime += ($planning->task1_hours / $totalHours) * $projectDuration;
        $milestones[7] = (int) $currentTime;

        // Step 4 (CDCF) = after task 2 (Step 4).
        $currentTime += ($planning->task2_hours / $totalHours) * $projectDuration;
        $milestones[4] = (int) $currentTime;

        // Step 5 (Test Sheet) = after task 3 (Step 5).
        $currentTime += ($planning->task3_hours / $totalHours) * $projectDuration;
        $milestones[5] = (int) $currentTime;

        // Step 8 (Logbook) = after task 4 (Step 8).
        $currentTime += ($planning->task4_hours / $totalHours) * $projectDuration;
        $milestones[8] = (int) $currentTime;

        // Step 6 (Report) = end of project (after task 5).
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

$dateFromPlanningClass = (empty($model->submission_date) && $defaultSubmissionDate > 0)
    ? 'date-from-planning' : '';
?>

<div class="section-header-gradient teacher-dates-header">
    <span><?php echo icon::render('calendar-range', 'sm', 'white'); ?> <?php echo get_string('submission_dates', 'gestionprojet'); ?></span>
</div>

<div class="dates-container teacher-dates-grid">
    <div class="form-group teacher-dates-field">
        <label for="submission_date"><?php echo get_string('submission_date', 'gestionprojet'); ?></label>
        <input type="date" id="submission_date" name="submission_date"
               value="<?php echo $submissionDateValue; ?>"
               class="form-control teacher-date-input <?php echo $dateFromPlanningClass; ?>">
        <div class="help-text teacher-dates-help"><?php echo get_string('submission_date_help', 'gestionprojet'); ?></div>
        <?php if (empty($model->submission_date) && $defaultSubmissionDate > 0): ?>
            <div class="teacher-dates-planning-hint"><?php echo get_string('date_from_planning', 'gestionprojet'); ?></div>
        <?php endif; ?>
    </div>
    <div class="form-group teacher-dates-field">
        <label for="deadline_date"><?php echo get_string('deadline_date', 'gestionprojet'); ?></label>
        <input type="date" id="deadline_date" name="deadline_date"
               value="<?php echo $deadlineDateValue; ?>"
               class="form-control teacher-date-input">
        <div class="help-text teacher-dates-help"><?php echo get_string('deadline_date_help', 'gestionprojet'); ?></div>
    </div>
</div>

<?php
// Load date helper AMD module (exposes getDateValues() for teacher page serializers).
$PAGE->requires->js_call_amd('mod_gestionprojet/teacher_dates', 'init');
?>

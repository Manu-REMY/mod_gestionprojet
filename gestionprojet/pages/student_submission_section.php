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
 * Shared submission section for student pages.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

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

<?php if ($submissionEnabled): ?>
<?php
// Load the submission AMD module.
$PAGE->requires->js_call_amd('mod_gestionprojet/submission', 'init', [[
    'cmid' => $cm->id,
    'step' => $step,
    'strings' => [
        'confirm_submit' => get_string('confirm_submit', 'gestionprojet'),
        'submitting' => get_string('submitting', 'gestionprojet'),
        'submission_error' => get_string('submissionerror', 'gestionprojet'),
    ],
]]);
?>
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
                    <?php echo icon::render('check-circle', 'sm', 'green'); ?>
                    <span><?php echo get_string('already_submitted', 'gestionprojet'); ?></span>
                </div>
            <?php else: ?>
                <button type="button" class="btn-submit-step" id="submitStepBtn">
                    <?php echo icon::render('zap', 'sm', 'inherit'); ?>
                    <?php echo get_string('submit_step', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

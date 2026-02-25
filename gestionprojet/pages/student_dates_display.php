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

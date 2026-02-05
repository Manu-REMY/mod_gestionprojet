<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task for auto-submitting drafts when deadline passes.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to auto-submit draft submissions when deadline passes.
 */
class auto_submit_deadline extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_auto_submit_deadline', 'gestionprojet');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

        mtrace('Starting auto-submit deadline task...');

        $now = time();
        $processedcount = 0;
        $errorcount = 0;

        // Define the steps that have deadlines (steps 4-8).
        $steps = [
            4 => ['table' => 'gestionprojet_cdcf', 'teacher_table' => 'gestionprojet_cdcf_teacher', 'name' => 'cdcf'],
            5 => ['table' => 'gestionprojet_essai', 'teacher_table' => 'gestionprojet_essai_teacher', 'name' => 'essai'],
            6 => ['table' => 'gestionprojet_rapport', 'teacher_table' => 'gestionprojet_rapport_teacher', 'name' => 'rapport'],
            7 => ['table' => 'gestionprojet_besoin_eleve', 'teacher_table' => 'gestionprojet_besoin_eleve_teacher', 'name' => 'besoin_eleve'],
            8 => ['table' => 'gestionprojet_carnet', 'teacher_table' => 'gestionprojet_carnet_teacher', 'name' => 'carnet'],
        ];

        foreach ($steps as $stepnum => $stepconfig) {
            mtrace("  Processing step {$stepnum} ({$stepconfig['name']})...");

            // Get all teacher models with a deadline_date that has passed and is not 0.
            $sql = "SELECT t.*, g.id as gestionprojetid, g.course, g.group_submission, g.enable_step{$stepnum}
                    FROM {{$stepconfig['teacher_table']}} t
                    JOIN {gestionprojet} g ON g.id = t.gestionprojetid
                    WHERE t.deadline_date > 0
                      AND t.deadline_date <= :now";

            $teachermodels = $DB->get_records_sql($sql, ['now' => $now]);

            if (empty($teachermodels)) {
                mtrace("    No expired deadlines found for step {$stepnum}.");
                continue;
            }

            foreach ($teachermodels as $model) {
                // Check if step is enabled.
                $enableprop = 'enable_step' . $stepnum;
                if (isset($model->$enableprop) && !$model->$enableprop) {
                    mtrace("    Step {$stepnum} is disabled for instance {$model->gestionprojetid}, skipping.");
                    continue;
                }

                // Use date() instead of userdate() to avoid IntlTimeZone dependency in CLI context.
                $deadlinestr = date('Y-m-d H:i:s', $model->deadline_date);
                mtrace("    Processing instance {$model->gestionprojetid} (deadline: {$deadlinestr})...");

                // Get all draft submissions (status = 0) for this step and instance.
                $drafts = $DB->get_records($stepconfig['table'], [
                    'gestionprojetid' => $model->gestionprojetid,
                    'status' => 0, // Draft status
                ]);

                if (empty($drafts)) {
                    mtrace("      No draft submissions found.");
                    continue;
                }

                foreach ($drafts as $draft) {
                    try {
                        // Update to submitted status.
                        $draft->status = 1; // Submitted
                        $draft->timesubmitted = $now;
                        $draft->timemodified = $now;

                        $DB->update_record($stepconfig['table'], $draft);

                        // Log the auto-submission to history.
                        $identifier = $draft->groupid ? "group {$draft->groupid}" : "user {$draft->userid}";
                        mtrace("      Auto-submitted {$identifier} for step {$stepnum}.");

                        // Log to history table.
                        gestionprojet_log_change(
                            $model->gestionprojetid,
                            $stepconfig['table'],
                            $draft->id,
                            'status',
                            0,
                            1,
                            0, // System user (cron)
                            $draft->groupid
                        );

                        // Trigger AI evaluation if enabled (wrapped in try-catch to not block submission).
                        try {
                            $this->trigger_ai_evaluation($model->gestionprojetid, $stepnum, $draft);
                        } catch (\Throwable $aierror) {
                            mtrace("        WARNING: AI evaluation trigger failed: " . $aierror->getMessage());
                        }

                        $processedcount++;
                    } catch (\Exception $e) {
                        mtrace("      ERROR: Failed to auto-submit for draft ID {$draft->id}: " . $e->getMessage());
                        $errorcount++;
                    }
                }
            }
        }

        mtrace("Auto-submit deadline task completed. Processed: {$processedcount}, Errors: {$errorcount}");
    }

    /**
     * Trigger AI evaluation for an auto-submitted draft.
     *
     * @param int $gestionprojetid Activity instance ID
     * @param int $step Step number
     * @param object $submission Submission record
     */
    private function trigger_ai_evaluation($gestionprojetid, $step, $submission) {
        global $DB, $CFG;

        // Get the activity instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $gestionprojetid]);

        if (!$gestionprojet || empty($gestionprojet->ai_enabled)) {
            return;
        }

        // Check if AI evaluator class exists.
        $evaluatorfile = $CFG->dirroot . '/mod/gestionprojet/classes/ai_evaluator.php';
        if (!file_exists($evaluatorfile)) {
            return;
        }

        require_once($evaluatorfile);

        // Get the teacher model for this step.
        $teachertables = [
            4 => 'gestionprojet_cdcf_teacher',
            5 => 'gestionprojet_essai_teacher',
            6 => 'gestionprojet_rapport_teacher',
            7 => 'gestionprojet_besoin_eleve_teacher',
            8 => 'gestionprojet_carnet_teacher',
        ];

        $teachertable = $teachertables[$step] ?? null;
        if (!$teachertable) {
            return;
        }

        $teachermodel = $DB->get_record($teachertable, ['gestionprojetid' => $gestionprojetid]);

        // Check if AI instructions are defined.
        if (!$teachermodel || empty($teachermodel->ai_instructions)) {
            return;
        }

        try {
            // Queue the AI evaluation.
            \mod_gestionprojet\ai_evaluator::queue_evaluation(
                $gestionprojet,
                $step,
                $submission->groupid,
                $submission->userid ?: 0
            );
            mtrace("        AI evaluation queued for auto-submitted draft.");
        } catch (\Exception $e) {
            mtrace("        WARNING: Could not queue AI evaluation: " . $e->getMessage());
        }
    }
}

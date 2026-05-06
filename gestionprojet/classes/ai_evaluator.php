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
 * AI Evaluator for Project Management.
 *
 * Main orchestrator for AI-powered evaluation of student submissions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/ai_config.php');
require_once(__DIR__ . '/ai_prompt_builder.php');
require_once(__DIR__ . '/ai_response_parser.php');

/**
 * Orchestrates AI evaluation of student submissions.
 */
class ai_evaluator {

    /** @var array Step to table name mapping */
    const STEP_TABLES = [
        4 => 'gestionprojet_cdcf',
        5 => 'gestionprojet_essai',
        6 => 'gestionprojet_rapport',
        7 => 'gestionprojet_besoin_eleve',
        8 => 'gestionprojet_carnet',
        9 => 'gestionprojet_fast',
    ];

    /** @var array Step to teacher table name mapping */
    const TEACHER_TABLES = [
        4 => 'gestionprojet_cdcf_teacher',
        5 => 'gestionprojet_essai_teacher',
        6 => 'gestionprojet_rapport_teacher',
        7 => 'gestionprojet_besoin_eleve_teacher',
        8 => 'gestionprojet_carnet_teacher',
        9 => 'gestionprojet_fast_teacher',
    ];

    /**
     * Queue an evaluation for asynchronous processing.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number (4-9)
     * @param int $submissionid Submission record ID
     * @param int $groupid Group ID (0 for individual)
     * @param int $userid User ID (0 for group submission)
     * @return int Evaluation record ID
     */
    public static function queue_evaluation(int $gestionprojetid, int $step, int $submissionid, int $groupid = 0, int $userid = 0): int {
        global $DB;

        // Get instance configuration.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $gestionprojetid], '*', MUST_EXIST);

        // Check if AI is enabled.
        if (empty($gestionprojet->ai_enabled)) {
            throw new \Exception(get_string('ai_not_enabled', 'gestionprojet'));
        }

        // Get AI config.
        $aiconfig = ai_config::get_config($gestionprojetid);
        if (!$aiconfig) {
            throw new \Exception(get_string('ai_not_enabled', 'gestionprojet'));
        }

        // Check for effective API key (built-in or user-provided).
        $apikey = ai_config::get_effective_api_key($aiconfig->provider, $aiconfig->api_key);
        if (empty($apikey)) {
            throw new \Exception(get_string('ai_api_key_required', 'gestionprojet'));
        }

        // Check for existing pending/processing evaluation.
        $existing = $DB->get_record('gestionprojet_ai_evaluations', [
            'gestionprojetid' => $gestionprojetid,
            'step' => $step,
            'submissionid' => $submissionid,
            'status' => 'pending',
        ]);

        if ($existing) {
            // Return existing evaluation ID if already queued.
            return $existing->id;
        }

        // Create evaluation record.
        $evaluation = new \stdClass();
        $evaluation->gestionprojetid = $gestionprojetid;
        $evaluation->step = $step;
        $evaluation->submissionid = $submissionid;
        $evaluation->groupid = $groupid;
        $evaluation->userid = $userid;
        $evaluation->provider = $aiconfig->provider;
        $evaluation->model = self::get_model_for_provider($aiconfig->provider);
        $evaluation->status = 'pending';
        $evaluation->timecreated = time();
        $evaluation->timemodified = time();

        $evaluationid = $DB->insert_record('gestionprojet_ai_evaluations', $evaluation);

        // Create adhoc task for processing.
        $task = new \mod_gestionprojet\task\evaluate_submission();
        $task->set_custom_data((object) ['evaluationid' => $evaluationid]);
        $task->set_component('mod_gestionprojet');

        \core\task\manager::queue_adhoc_task($task);

        return $evaluationid;
    }

    /**
     * Process a queued evaluation.
     *
     * @param int $evaluationid Evaluation record ID
     * @return bool Success
     */
    public static function process_evaluation(int $evaluationid): bool {
        global $DB;

        // Get evaluation record.
        $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid]);
        if (!$evaluation) {
            return false;
        }

        // Check if already processed.
        if (!in_array($evaluation->status, ['pending', 'processing'])) {
            return false;
        }

        // Mark as processing.
        $evaluation->status = 'processing';
        $evaluation->timemodified = time();
        $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

        try {
            // Get instance configuration.
            $gestionprojet = $DB->get_record('gestionprojet', ['id' => $evaluation->gestionprojetid], '*', MUST_EXIST);

            // Get AI config.
            $aiconfig = ai_config::get_config($evaluation->gestionprojetid);
            if (!$aiconfig) {
                throw new \Exception(get_string('ai_not_enabled', 'gestionprojet'));
            }

            // Get effective API key (built-in or user-provided).
            $apikey = ai_config::get_effective_api_key($aiconfig->provider, $aiconfig->api_key);
            if (empty($apikey)) {
                throw new \Exception(get_string('ai_api_key_required', 'gestionprojet'));
            }

            // Get submission data.
            $submissiontable = self::STEP_TABLES[$evaluation->step] ?? null;
            if (!$submissiontable) {
                throw new \Exception('Invalid step number');
            }
            $submission = $DB->get_record($submissiontable, ['id' => $evaluation->submissionid], '*', MUST_EXIST);

            // Get teacher model.
            $teachertable = self::TEACHER_TABLES[$evaluation->step] ?? null;
            $teachermodel = $DB->get_record($teachertable, ['gestionprojetid' => $evaluation->gestionprojetid]);

            if (!$teachermodel) {
                // Create empty model if not exists.
                $teachermodel = new \stdClass();
                $teachermodel->ai_instructions = '';
            }

            // Mapping per step: provided table + comparator strategy for "no modifications" detection.
            $providedmap = [
                4 => [
                    'table'      => 'gestionprojet_cdcf_provided',
                    'comparator' => 'json_normalized',
                    'field'      => 'interacteurs_data',
                ],
                5 => [
                    'table'      => 'gestionprojet_essai_provided',
                    'comparator' => 'fields_strict',
                    'fields'     => [
                        'nom_essai', 'date_essai', 'groupe_eleves', 'objectif',
                        'fonction_service', 'niveaux_reussite', 'etapes_protocole',
                        'materiel_outils', 'precautions', 'resultats_obtenus',
                        'observations_remarques', 'conclusion',
                    ],
                ],
                9 => [
                    'table'      => 'gestionprojet_fast_provided',
                    'comparator' => 'string_strict',
                    'field'      => 'data_json',
                ],
            ];

            $teacherintro = null;
            $providedrec_for_prompt = null;
            $nomodifications = false;

            if (isset($providedmap[$evaluation->step])) {
                $cfg = $providedmap[$evaluation->step];
                $providedrec = $DB->get_record($cfg['table'], ['gestionprojetid' => $evaluation->gestionprojetid]);
                if ($providedrec) {
                    if (!empty(trim(strip_tags($providedrec->intro_text ?? '')))) {
                        $teacherintro = $providedrec->intro_text;
                    }
                    if (self::provided_has_content($cfg, $providedrec)) {
                        $providedrec_for_prompt = $providedrec;
                        $nomodifications = self::detect_no_modifications($cfg, $submission, $providedrec);
                    }
                }
            }

            // Build prompts.
            $promptbuilder = new ai_prompt_builder();
            $prompts = $promptbuilder->build_prompt(
                $evaluation->step, $submission, $teachermodel, $teacherintro, $providedrec_for_prompt, $nomodifications
            );

            // Get AI provider.
            $provider = self::get_provider($aiconfig->provider, $apikey);

            // Call AI.
            $response = $provider->evaluate(
                $prompts['system'],
                $prompts['user'],
                $evaluation->model,
                2500
            );

            // Parse response.
            $parser = new ai_response_parser();
            $result = $parser->parse($response['content']);

            // Update evaluation record with prompts and response.
            $evaluation->prompt_system = $prompts['system'];
            $evaluation->prompt_user = $prompts['user'];
            $evaluation->raw_response = $response['content'];
            $evaluation->prompt_tokens = $response['prompt_tokens'] ?? 0;
            $evaluation->completion_tokens = $response['completion_tokens'] ?? 0;
            $evaluation->parsed_grade = $result->grade;
            $evaluation->parsed_feedback = $result->feedback;
            $evaluation->criteria_json = json_encode($result->criteria);
            $evaluation->keywords_found = json_encode($result->keywords_found);
            $evaluation->keywords_missing = json_encode($result->keywords_missing);
            $evaluation->suggestions = json_encode($result->suggestions);
            $evaluation->status = 'completed';
            $evaluation->timemodified = time();

            $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

            // Trigger AI evaluation completed event.
            $cm = get_coursemodule_from_instance('gestionprojet', $gestionprojet->id, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $event = \mod_gestionprojet\event\ai_evaluation_completed::create([
                'objectid' => $evaluation->id,
                'context' => $context,
                'other' => [
                    'step' => $evaluation->step,
                    'status' => 'completed',
                ],
            ]);
            $event->trigger();

            // Auto-apply if configured.
            if (!empty($gestionprojet->ai_auto_apply)) {
                self::apply_evaluation($evaluationid, 0); // 0 = system applied.
            }

            return true;

        } catch (\Exception $e) {
            // Mark as failed.
            $evaluation->status = 'failed';
            $evaluation->error_message = $e->getMessage();
            $evaluation->timemodified = time();
            $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

            // Log the error.
            debugging('AI evaluation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);

            // Notify teachers (best-effort, errors swallowed inside).
            self::notify_teachers_of_failure($evaluation, $e->getMessage());

            return false;
        }
    }

    /**
     * Get evaluation for a submission.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @param int $submissionid Submission ID
     * @return object|null Latest evaluation or null
     */
    public static function get_evaluation(int $gestionprojetid, int $step, int $submissionid): ?object {
        global $DB;

        // Return null if submissionid is 0 or invalid (no submission exists yet).
        if (empty($submissionid)) {
            return null;
        }

        $result = $DB->get_record_sql(
            'SELECT * FROM {gestionprojet_ai_evaluations}
             WHERE gestionprojetid = ? AND step = ? AND submissionid = ?
             ORDER BY timecreated DESC LIMIT 1',
            [$gestionprojetid, $step, $submissionid]
        );

        // Convert false to null to match return type.
        return $result ?: null;
    }

    /**
     * Get all evaluations for an instance.
     *
     * @param int $gestionprojetid Instance ID
     * @param string|null $status Filter by status
     * @return array Evaluation records
     */
    public static function get_all_evaluations(int $gestionprojetid, ?string $status = null): array {
        global $DB;

        $params = ['gestionprojetid' => $gestionprojetid];
        $sql = 'SELECT * FROM {gestionprojet_ai_evaluations} WHERE gestionprojetid = :gestionprojetid';

        if ($status) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY timecreated DESC';

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Apply AI evaluation result to submission.
     *
     * @param int $evaluationid Evaluation ID
     * @param int $userid Teacher ID applying (0 for auto)
     * @param float|null $overridegrade Optional grade override
     * @param string|null $overridefeedback Optional feedback override
     * @return bool Success
     */
    public static function apply_evaluation(int $evaluationid, int $userid, ?float $overridegrade = null, ?string $overridefeedback = null): bool {
        global $DB;

        $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid], '*', MUST_EXIST);

        if ($evaluation->status !== 'completed') {
            return false;
        }

        // Determine grade and feedback to apply.
        $grade = $overridegrade ?? $evaluation->parsed_grade;
        $feedback = $overridefeedback ?? $evaluation->parsed_feedback;

        // Update submission record.
        $submissiontable = self::STEP_TABLES[$evaluation->step] ?? null;
        if (!$submissiontable) {
            return false;
        }

        $submission = $DB->get_record($submissiontable, ['id' => $evaluation->submissionid]);
        if (!$submission) {
            return false;
        }

        $submission->grade = $grade;
        $submission->feedback = $feedback;
        $submission->timemodified = time();
        $DB->update_record($submissiontable, $submission);

        // Update evaluation record.
        $evaluation->status = 'applied';
        $evaluation->applied_by = $userid;
        $evaluation->applied_at = time();
        $evaluation->timemodified = time();
        $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

        // Update Moodle gradebook.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $evaluation->gestionprojetid]);
        if ($gestionprojet) {
            // Determine userid for gradebook update.
            // For group submissions, we need to get the group members.
            $targetuserid = 0;
            if ($evaluation->userid) {
                $targetuserid = $evaluation->userid;
            } else if ($evaluation->groupid && $gestionprojet->group_submission) {
                // Group submission - update all group members.
                $targetuserid = 0; // 0 means update all users.
            }

            // Check grade mode and pass step for per-step mode.
            $grademode = isset($gestionprojet->grade_mode) ? (int)$gestionprojet->grade_mode : 0;
            if ($grademode == 1) {
                // Per-step mode: update only this step's grade item.
                gestionprojet_update_grades($gestionprojet, $targetuserid, true, $evaluation->step);
            } else {
                // Combined mode: recalculate the combined average.
                gestionprojet_update_grades($gestionprojet, $targetuserid);
            }
        }

        return true;
    }

    /**
     * Retry a failed evaluation.
     *
     * @param int $evaluationid Evaluation ID
     * @return int New evaluation ID
     */
    public static function retry_evaluation(int $evaluationid): int {
        global $DB;

        $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid], '*', MUST_EXIST);

        // Reset status.
        $evaluation->status = 'pending';
        $evaluation->error_message = null;
        $evaluation->timemodified = time();
        $DB->update_record('gestionprojet_ai_evaluations', $evaluation);

        // Queue new task.
        $task = new \mod_gestionprojet\task\evaluate_submission();
        $task->set_custom_data((object) ['evaluationid' => $evaluationid]);
        $task->set_component('mod_gestionprojet');

        \core\task\manager::queue_adhoc_task($task);

        return $evaluationid;
    }

    /**
     * Get AI provider instance.
     *
     * @param string $provider Provider name
     * @param string $apikey API key
     * @return ai_provider\provider_interface
     */
    public static function get_provider(string $provider, string $apikey): ai_provider\provider_interface {
        switch ($provider) {
            case 'openai':
                return new ai_provider\openai_provider($apikey);
            case 'anthropic':
                return new ai_provider\anthropic_provider($apikey);
            case 'mistral':
                return new ai_provider\mistral_provider($apikey);
            case 'albert':
                return new ai_provider\albert_provider($apikey);
            default:
                throw new \Exception(get_string('ai_provider_invalid', 'gestionprojet'));
        }
    }

    /**
     * Get default model for provider.
     *
     * @param string $provider Provider name
     * @return string Model name
     */
    public static function get_model_for_provider(string $provider): string {
        switch ($provider) {
            case 'openai':
                return ai_provider\openai_provider::DEFAULT_MODEL;
            case 'anthropic':
                return ai_provider\anthropic_provider::DEFAULT_MODEL;
            case 'mistral':
                return ai_provider\mistral_provider::DEFAULT_MODEL;
            case 'albert':
                return ai_provider\albert_provider::DEFAULT_MODEL;
            default:
                return 'gpt-4o-mini';
        }
    }

    /**
     * Delete an evaluation record.
     *
     * @param int $evaluationid Evaluation ID
     * @return bool Success
     */
    public static function delete_evaluation(int $evaluationid): bool {
        global $DB;

        $evaluation = $DB->get_record('gestionprojet_ai_evaluations', ['id' => $evaluationid]);
        if (!$evaluation) {
            return false;
        }

        // Don't delete if evaluation is currently processing.
        if ($evaluation->status === 'processing') {
            return false;
        }

        return $DB->delete_records('gestionprojet_ai_evaluations', ['id' => $evaluationid]);
    }

    /**
     * Delete all evaluations for a submission.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @param int $submissionid Submission ID
     * @return int Number of deleted records
     */
    public static function delete_evaluations_for_submission(int $gestionprojetid, int $step, int $submissionid): int {
        global $DB;

        // Get all evaluations (excluding processing ones).
        $evaluations = $DB->get_records_sql(
            'SELECT id FROM {gestionprojet_ai_evaluations}
             WHERE gestionprojetid = ? AND step = ? AND submissionid = ?
             AND status != ?',
            [$gestionprojetid, $step, $submissionid, 'processing']
        );

        $deleted = 0;
        foreach ($evaluations as $eval) {
            if ($DB->delete_records('gestionprojet_ai_evaluations', ['id' => $eval->id])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get all submitted submissions for a step.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @return array Submission records
     */
    public static function get_submitted_submissions(int $gestionprojetid, int $step): array {
        global $DB;

        $table = self::STEP_TABLES[$step] ?? null;
        if (!$table) {
            return [];
        }

        return $DB->get_records_sql(
            'SELECT * FROM {' . $table . '}
             WHERE gestionprojetid = ? AND status = 1',
            [$gestionprojetid]
        );
    }

    /**
     * Bulk re-evaluate all submissions for a step.
     * Deletes existing evaluations and queues new ones.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @return array ['deleted' => int, 'queued' => int, 'errors' => array]
     */
    public static function bulk_reevaluate_step(int $gestionprojetid, int $step): array {
        global $DB;

        $result = [
            'deleted' => 0,
            'queued' => 0,
            'errors' => [],
        ];

        // Get all submitted submissions for this step.
        $submissions = self::get_submitted_submissions($gestionprojetid, $step);

        if (empty($submissions)) {
            return $result;
        }

        foreach ($submissions as $submission) {
            try {
                // Delete existing evaluations for this submission.
                $deleted = self::delete_evaluations_for_submission($gestionprojetid, $step, $submission->id);
                $result['deleted'] += $deleted;

                // Queue new evaluation.
                self::queue_evaluation(
                    $gestionprojetid,
                    $step,
                    $submission->id,
                    $submission->groupid ?? 0,
                    $submission->userid ?? 0
                );
                $result['queued']++;

            } catch (\Exception $e) {
                $result['errors'][] = [
                    'submissionid' => $submission->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Check if a submission has a pending evaluation.
     *
     * @param int $gestionprojetid Instance ID
     * @param int $step Step number
     * @param int $submissionid Submission ID
     * @return bool True if pending/processing
     */
    public static function has_pending_evaluation(int $gestionprojetid, int $step, int $submissionid): bool {
        global $DB;

        return $DB->record_exists_sql(
            'SELECT 1 FROM {gestionprojet_ai_evaluations}
             WHERE gestionprojetid = ? AND step = ? AND submissionid = ?
             AND status IN (?, ?)',
            [$gestionprojetid, $step, $submissionid, 'pending', 'processing']
        );
    }

    /**
     * Get evaluation status for display.
     *
     * @param object $evaluation Evaluation record
     * @return array ['status' => string, 'badge_class' => string, 'message' => string]
     */
    public static function get_status_display(object $evaluation): array {
        $statuses = [
            'pending' => [
                'badge_class' => 'badge-secondary',
                'message' => get_string('ai_evaluation_pending', 'gestionprojet'),
            ],
            'processing' => [
                'badge_class' => 'badge-info',
                'message' => get_string('ai_evaluating', 'gestionprojet'),
            ],
            'completed' => [
                'badge_class' => 'badge-success',
                'message' => get_string('ai_evaluation_complete', 'gestionprojet'),
            ],
            'failed' => [
                'badge_class' => 'badge-danger',
                'message' => get_string('ai_evaluation_failed', 'gestionprojet'),
            ],
            'applied' => [
                'badge_class' => 'badge-primary',
                'message' => get_string('ai_evaluation_applied', 'gestionprojet'),
            ],
        ];

        $info = $statuses[$evaluation->status] ?? $statuses['pending'];
        $info['status'] = $evaluation->status;

        return $info;
    }

    /**
     * Notify all teachers of the course about a failed AI evaluation.
     *
     * Sends a Moodle message (popup + email per teacher prefs) to every user
     * holding the mod/gestionprojet:grade capability in this module's context.
     * Failure to send notifications is logged via debugging() and does not
     * propagate (this method is best-effort).
     *
     * @param object $evaluation Evaluation record (after being flagged 'failed')
     * @param string $errorMessage The exception message
     * @return void
     */
    private static function notify_teachers_of_failure(object $evaluation, string $errorMessage): void {
        $cm = get_coursemodule_from_instance('gestionprojet', $evaluation->gestionprojetid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);

        $teachers = get_users_by_capability(
            $context,
            'mod/gestionprojet:grade',
            'u.id, u.firstname, u.lastname, u.email, u.lang, u.maildisplay, u.mailformat, u.deleted, u.suspended, u.confirmed, u.auth, u.username'
        );
        if (empty($teachers)) {
            return;
        }

        $url = (new \moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id]))->out(false);
        $activityname = format_string($cm->name);

        foreach ($teachers as $teacher) {
            try {
                $message = new \core\message\message();
                $message->component = 'mod_gestionprojet';
                $message->name = 'ai_evaluation_failed';
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = $teacher;
                $message->subject = get_string(
                    'ai_failure_notif_subject',
                    'gestionprojet',
                    (object)['activityname' => $activityname]
                );
                $message->fullmessage = get_string(
                    'ai_failure_notif_body',
                    'gestionprojet',
                    (object)[
                        'step' => $evaluation->step,
                        'error' => $errorMessage,
                        'url' => $url,
                    ]
                );
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = '';
                $message->smallmessage = get_string('ai_failure_notif_small', 'gestionprojet');
                $message->notification = 1;
                $message->contexturl = $url;
                $message->contexturlname = get_string('grading', 'gestionprojet');

                \message_send($message);
            } catch (\Exception $e) {
                debugging('Failed to notify teacher of AI failure: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Check whether the provided record has any non-empty content in the comparable fields.
     *
     * @param array $cfg     Provided map entry for the current step.
     * @param object $rec    Provided record.
     * @return bool          True when at least one field is non-empty.
     */
    private static function provided_has_content(array $cfg, object $rec): bool {
        $fields = $cfg['fields'] ?? [$cfg['field']];
        foreach ($fields as $f) {
            if (!empty(trim((string)($rec->$f ?? '')))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare the student submission to the provided record using the per-step comparator.
     *
     * @param array $cfg            Provided map entry.
     * @param object $submission    Student submission record.
     * @param object $providedrec   Teacher provided record.
     * @return bool                 True when the student record matches the provided record exactly.
     */
    private static function detect_no_modifications(array $cfg, object $submission, object $providedrec): bool {
        switch ($cfg['comparator']) {
            case 'json_normalized':
                $f = $cfg['field'];
                $studentjson  = json_decode($submission->$f ?? '', true);
                $providedjson = json_decode($providedrec->$f ?? '', true);
                if (is_array($studentjson) && is_array($providedjson)) {
                    return json_encode($studentjson) === json_encode($providedjson);
                }
                return false;

            case 'string_strict':
                $f = $cfg['field'];
                return (string)($submission->$f ?? '') === (string)($providedrec->$f ?? '');

            case 'fields_strict':
                foreach ($cfg['fields'] as $f) {
                    if ((string)($submission->$f ?? '') !== (string)($providedrec->$f ?? '')) {
                        return false;
                    }
                }
                return true;

            default:
                return false;
        }
    }
}

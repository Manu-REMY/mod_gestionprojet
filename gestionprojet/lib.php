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
 * Library of interface functions and constants for module gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supported features
 */
function gestionprojet_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Add gestionprojet instance.
 *
 * @param stdClass $data
 * @param mod_gestionprojet_mod_form $mform
 * @return int new gestionprojet instance id
 */
function gestionprojet_add_instance($data, $mform = null)
{
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    // Set default autosave interval if not specified
    if (!isset($data->autosave_interval)) {
        $data->autosave_interval = 30;
    }

    // Encrypt API key if provided.
    if (!empty($data->ai_api_key)) {
        $data->ai_api_key = \mod_gestionprojet\ai_config::encrypt_api_key($data->ai_api_key);
    }

    $data->id = $DB->insert_record('gestionprojet', $data);

    // Create empty teacher pages.
    gestionprojet_create_teacher_pages($data->id);

    // Initialize gradebook items.
    // Need to fetch the complete record to have all fields including course.
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $data->id]);
    gestionprojet_grade_item_update($gestionprojet);

    return $data->id;
}

/**
 * Update gestionprojet instance.
 *
 * @param stdClass $data
 * @param mod_gestionprojet_mod_form $mform
 * @return bool true
 */
function gestionprojet_update_instance($data, $mform = null)
{
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    // Encrypt API key if provided and changed.
    if (!empty($data->ai_api_key)) {
        // Only encrypt if the key has changed (not already encrypted).
        $existing = $DB->get_field('gestionprojet', 'ai_api_key', ['id' => $data->id]);
        if ($data->ai_api_key !== $existing) {
            $data->ai_api_key = \mod_gestionprojet\ai_config::encrypt_api_key($data->ai_api_key);
        }
    }

    $result = $DB->update_record('gestionprojet', $data);

    // Update gradebook items (may need to create/remove items if grade_mode changed).
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $data->id]);
    gestionprojet_update_grades($gestionprojet);

    return $result;
}

/**
 * Delete gestionprojet instance.
 *
 * @param int $id
 * @return bool true
 */
function gestionprojet_delete_instance($id)
{
    global $DB;

    if (!$gestionprojet = $DB->get_record('gestionprojet', ['id' => $id])) {
        return false;
    }

    // Delete all related data.
    // Teacher-authored content (configuration steps 1-3).
    $DB->delete_records('gestionprojet_description', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_besoin', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_planning', ['gestionprojetid' => $id]);
    // Student submissions (steps 4-9).
    $DB->delete_records('gestionprojet_cdcf', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_essai', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_rapport', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_besoin_eleve', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_carnet', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_fast', ['gestionprojetid' => $id]);
    // Teacher correction models (steps 4-9).
    $DB->delete_records('gestionprojet_cdcf_teacher', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_essai_teacher', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_rapport_teacher', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_carnet_teacher', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_fast_teacher', ['gestionprojetid' => $id]);
    // Teacher-provided ready-to-use content.
    $DB->delete_records('gestionprojet_cdcf_provided', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_fast_provided', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_essai_provided', ['gestionprojetid' => $id]);
    // AI evaluations and history.
    $DB->delete_records('gestionprojet_ai_evaluations', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_ai_summaries', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_history', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_palier', ['gestionprojetid' => $id]);

    // Delete the instance.
    $DB->delete_records('gestionprojet', ['id' => $id]);

    return true;
}

/**
 * Create empty teacher pages for a new instance.
 *
 * @param int $gestionprojetid
 */
function gestionprojet_create_teacher_pages($gestionprojetid)
{
    global $DB;

    $time = time();

    // Create description
    $description = new stdClass();
    $description->gestionprojetid = $gestionprojetid;
    $description->locked = 0;
    $description->timecreated = $time;
    $description->timemodified = $time;
    $DB->insert_record('gestionprojet_description', $description);

    // Create besoin
    $besoin = new stdClass();
    $besoin->gestionprojetid = $gestionprojetid;
    $besoin->locked = 0;
    $besoin->timecreated = $time;
    $besoin->timemodified = $time;
    $DB->insert_record('gestionprojet_besoin', $besoin);

    // Create planning
    $planning = new stdClass();
    $planning->gestionprojetid = $gestionprojetid;
    $planning->locked = 0;
    $planning->task1_hours = 0;
    $planning->task2_hours = 0;
    $planning->task3_hours = 0;
    $planning->task4_hours = 0;
    $planning->task5_hours = 0;
    $planning->timecreated = $time;
    $planning->timemodified = $time;
    $DB->insert_record('gestionprojet_planning', $planning);
}

/**
 * Get user's group for this activity.
 *
 * @param stdClass $cm Course module object
 * @param int $userid User ID
 * @return int|false Group ID or false if no group
 */
function gestionprojet_get_user_group($cm, $userid)
{
    // Only the groups the user belongs to: groups_get_activity_allowed_groups() returns every group
    // in visible groups mode or with accessallgroups, which put all students in the first group.
    $groups = groups_get_all_groups($cm->course, $userid, $cm->groupingid, 'g.id');

    return $groups ? (int) array_key_first($groups) : 0;
}

/**
 * Words of the correction model a student must not read in the feedback: those they did not write
 * and that are not in the instructions given to them.
 *
 * @param stdClass $gestionprojet Activity instance
 * @param int $step Step number (4-9)
 * @param stdClass $submission Student submission record
 * @return string[] Forbidden roots, see \mod_gestionprojet\answer_leak_filter
 */
function gestionprojet_answer_leak_roots($gestionprojet, int $step, $submission): array
{
    global $DB;

    $teachertable = \mod_gestionprojet\ai_evaluator::TEACHER_TABLES[$step] ?? null;
    if (!$teachertable) {
        return [];
    }
    $model = $DB->get_record($teachertable, ['gestionprojetid' => $gestionprojet->id]);
    if (!$model) {
        return [];
    }
    // The AI instructions describe how to grade, not the expected answer: they would forbid harmless words.
    unset($model->ai_instructions);

    $permitted = [\mod_gestionprojet\answer_leak_filter::content_text($submission)];
    $providedtables = [4 => 'gestionprojet_cdcf_provided', 5 => 'gestionprojet_essai_provided',
        9 => 'gestionprojet_fast_provided'];
    if (isset($providedtables[$step])) {
        $provided = $DB->get_record($providedtables[$step], ['gestionprojetid' => $gestionprojet->id]);
        if ($provided) {
            $permitted[] = \mod_gestionprojet\answer_leak_filter::content_text($provided);
        }
    }

    return \mod_gestionprojet\answer_leak_filter::forbidden_roots(
        \mod_gestionprojet\answer_leak_filter::content_text($model),
        implode(' ', $permitted)
    );
}

/**
 * Check that the user may write in the group sent by the browser, and return it.
 *
 * @param stdClass|cm_info $cm Course module
 * @param int $groupid Group id sent by the browser, 0 for individual work
 * @param int $userid User id
 * @param context_module $context Module context
 * @return int The checked group id
 * @throws moodle_exception When the user is not a member of that group
 */
function gestionprojet_require_group_access($cm, int $groupid, int $userid, $context): int
{
    if ($groupid === 0) {
        return 0;
    }
    $members = array_keys(groups_get_all_groups($cm->course, $userid, $cm->groupingid, 'g.id'));
    $activity = array_keys(groups_get_all_groups($cm->course, 0, $cm->groupingid, 'g.id'));
    $accessall = has_capability('moodle/site:accessallgroups', $context, $userid);
    if (!\mod_gestionprojet\group_access::is_allowed($groupid, $members, $activity, $accessall)) {
        throw new moodle_exception('error:notgroupmember', 'gestionprojet');
    }
    return $groupid;
}

/**
 * Zones of the step 4 grid entirely provided by the consigne shown to students (v2.11.1).
 *
 * @param object $gestionprojet Instance record.
 * @return string[] See consigne_lock::locked_zones(); empty when the consigne is not shown to students.
 */
function gestionprojet_cdcf_locked_zones(object $gestionprojet): array {
    global $DB;

    if ((int)($gestionprojet->step4_provided ?? 0) !== 1) {
        return [];
    }
    $provided = $DB->get_field('gestionprojet_cdcf_provided', 'interacteurs_data', ['gestionprojetid' => $gestionprojet->id]);
    return \mod_gestionprojet\consigne_lock::locked_zones(\mod_gestionprojet\cdcf_helper::decode($provided ?: null));
}

/**
 * Step 4 student copy as the server stores it: what the consigne locks is restored (v2.11.1).
 *
 * When the consigne is not shown to students or locks nothing, the JSON is returned unchanged except that
 * flags sent by the browser are removed: activities without locks store exactly what they did before.
 *
 * @param object $gestionprojet Instance record.
 * @param string $studentjson CDCF JSON sent by the browser.
 * @param object|null $submission The copy being saved: in the level mode its frozen zones are restored.
 * @return string CDCF JSON to store.
 */
function gestionprojet_cdcf_enforce_consigne(object $gestionprojet, string $studentjson, ?object $submission = null): string {
    global $DB;

    $json = \mod_gestionprojet\consigne_lock::sanitize_student_json($studentjson);
    if ((int)($gestionprojet->step4_provided ?? 0) === 1) {
        $provided = $DB->get_field('gestionprojet_cdcf_provided', 'interacteurs_data', ['gestionprojetid' => $gestionprojet->id]);
        $consigne = \mod_gestionprojet\cdcf_helper::decode($provided ?: null);
        if (\mod_gestionprojet\consigne_lock::has_locks($consigne)) {
            $json = json_encode(\mod_gestionprojet\consigne_lock::enforce(\mod_gestionprojet\cdcf_helper::decode($studentjson),
                $consigne), JSON_UNESCAPED_UNICODE);
        }
    }

    // Level mode (v2.11.2): zones of validated levels, and of the level being judged, are frozen as a whole.
    if ((int)($gestionprojet->step4_paliers ?? 0) !== 1 || !$submission) {
        return $json;
    }
    $frozen = gestionprojet_palier_frozen_zones($gestionprojet, $submission);
    if (!$frozen) {
        return $json;
    }
    $student = \mod_gestionprojet\cdcf_helper::decode($json);
    foreach ($frozen as $evaluationid => $zones) {
        $contenu = $DB->get_field('gestionprojet_ai_evaluations', 'contenu_json',
            ['id' => $evaluationid, 'gestionprojetid' => $gestionprojet->id]);
        if ($contenu === false) {
            continue;
        }
        $student = \mod_gestionprojet\palier::freeze($student, \mod_gestionprojet\cdcf_helper::decode($contenu ?: null), $zones);
    }
    return json_encode($student, JSON_UNESCAPED_UNICODE);
}

/**
 * Graded parts of the step 4 grid, as the AI grades them (v2.11.2).
 *
 * @param object $gestionprojet Instance record.
 * @return array See grille::effective_parts().
 */
function gestionprojet_palier_parts(object $gestionprojet): array {
    global $DB;

    $grille = $DB->get_field('gestionprojet_cdcf_teacher', 'grille_json', ['gestionprojetid' => $gestionprojet->id]);
    $flexibility = !isset($gestionprojet->step4_flexibility) || (bool)$gestionprojet->step4_flexibility;
    return \mod_gestionprojet\grille::effective_parts(\mod_gestionprojet\grille::decode($grille ?: null, $flexibility),
        gestionprojet_cdcf_locked_zones($gestionprojet));
}

/**
 * Zones of a copy frozen by the level mode: evaluation id => zones (its frozen copy is the reference).
 *
 * Validated zones (zones_validees) come first; the zones of a level being judged are added when not validated yet.
 *
 * @param object $gestionprojet Instance record.
 * @param object $submission Step 4 copy (gestionprojet_cdcf).
 * @return array<int, string[]>
 */
function gestionprojet_palier_frozen_zones(object $gestionprojet, object $submission): array {
    $byevaluation = [];
    $done = [];
    foreach ((array)json_decode((string)($submission->zones_validees ?? ''), true) as $zone => $evaluationid) {
        if ((int)$evaluationid > 0) {
            $byevaluation[(int)$evaluationid][] = (string)$zone;
            $done[(string)$zone] = true;
        }
    }
    $pending = gestionprojet_palier_pending($gestionprojet, (int)$submission->id);
    if ($pending) {
        foreach (\mod_gestionprojet\palier::zones_for(gestionprojet_palier_parts($gestionprojet), $pending->palier) as $zone) {
            if (empty($done[$zone])) {
                $byevaluation[(int)$pending->id][] = $zone;
            }
        }
    }
    return $byevaluation;
}

/**
 * Level evaluation waiting or running for a copy.
 *
 * @param object $gestionprojet Instance record.
 * @param int $submissionid
 * @return object|null
 */
function gestionprojet_palier_pending(object $gestionprojet, int $submissionid): ?object {
    global $DB;

    $records = $DB->get_records_select('gestionprojet_ai_evaluations',
        "gestionprojetid = :gpid AND step = 4 AND submissionid = :sid AND palier IS NOT NULL AND palier <> ''
         AND status IN ('pending', 'processing')",
        ['gpid' => $gestionprojet->id, 'sid' => $submissionid], 'id DESC', '*', 0, 1);
    return $records ? reset($records) : null;
}

/**
 * Owner of the levels of a copy, as stored in gestionprojet_palier: the group (userid 0) for group work,
 * the student alone (groupid 0) for individual work, even when the student belongs to a course group.
 *
 * @param object $submission Step 4 copy (gestionprojet_cdcf).
 * @return array ['groupid' => int, 'userid' => int]
 */
function gestionprojet_palier_owner(object $submission): array {
    if ((int)$submission->userid > 0) {
        return ['groupid' => 0, 'userid' => (int)$submission->userid];
    }
    return ['groupid' => (int)$submission->groupid, 'userid' => 0];
}

/**
 * State of the level mode for a step 4 copy (v2.11.2).
 *
 * @param object $gestionprojet Instance record.
 * @param object $submission Step 4 copy (gestionprojet_cdcf).
 * @return array enabled, ingrid, validated (levels), rows (validated rows), requestable, pending, last, attempts
 */
function gestionprojet_palier_state(object $gestionprojet, object $submission): array {
    global $DB;

    $ingrid = \mod_gestionprojet\palier::paliers_in_grid(gestionprojet_palier_parts($gestionprojet));
    $rows = $DB->get_records('gestionprojet_palier', ['gestionprojetid' => $gestionprojet->id, 'retire' => 0]
        + gestionprojet_palier_owner($submission), 'timecreated ASC');
    $validated = array_values(array_unique(array_column($rows, 'palier')));
    $attempts = $DB->get_records_select('gestionprojet_ai_evaluations',
        "gestionprojetid = :gpid AND step = 4 AND submissionid = :sid AND palier IS NOT NULL AND palier <> ''",
        ['gpid' => $gestionprojet->id, 'sid' => $submission->id], 'id DESC');
    $last = null;
    foreach ($attempts as $attempt) {
        if (in_array($attempt->status, ['completed', 'failed'], true)) {
            $last = $attempt;
            break;
        }
    }
    return [
        'enabled' => (int)($gestionprojet->step4_paliers ?? 0) === 1,
        'ingrid' => $ingrid,
        'validated' => $validated,
        'rows' => $rows,
        'requestable' => \mod_gestionprojet\palier::requestable($ingrid, $validated),
        'pending' => gestionprojet_palier_pending($gestionprojet, (int)$submission->id),
        'last' => $last,
        'attempts' => $attempts,
    ];
}

/**
 * Ask for the validation of a level: freeze the copy and queue its AI evaluation (v2.11.2).
 *
 * @param object $gestionprojet Instance record.
 * @param object $cm Course module.
 * @param int $groupid Group checked by the caller (gestionprojet_require_group_access).
 * @param int $userid
 * @param string $palier F, S or TS.
 * @return int Evaluation id.
 * @throws moodle_exception When the mode is off, the level cannot be asked now, or an evaluation is running.
 */
function gestionprojet_palier_request(object $gestionprojet, object $cm, int $groupid, int $userid, string $palier): int {
    global $DB;

    if ((int)($gestionprojet->step4_paliers ?? 0) !== 1 || empty($gestionprojet->ai_enabled)) {
        throw new moodle_exception('palier_disabled', 'gestionprojet');
    }
    // Same gates as the submission: step 4 open, submissions allowed, copy not locked, deadline not passed.
    if (!gestionprojet_is_step_enabled($gestionprojet, 4) || empty($gestionprojet->enable_submission)) {
        throw new moodle_exception('palier_disabled', 'gestionprojet');
    }
    $submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, 'cdcf');
    if ((int)$submission->status === 1) {
        throw new moodle_exception('submissionlocked', 'gestionprojet');
    }
    $deadline = (int)$DB->get_field('gestionprojet_cdcf_teacher', 'deadline_date', ['gestionprojetid' => $gestionprojet->id]);
    if ($deadline > 0 && time() > $deadline) {
        throw new moodle_exception('submissionlocked', 'gestionprojet');
    }
    // Check and queue under a lock per copy: two members clicking at once get one evaluation.
    $lock = gestionprojet_palier_lock((int)$submission->id);
    try {
        $state = gestionprojet_palier_state($gestionprojet, $submission);
        if ($state['pending']) {
            // One evaluation at a time per copy ('pending' or 'processing').
            throw new moodle_exception('palier_pending_error', 'gestionprojet');
        }
        if (!in_array($palier, $state['requestable'], true)) {
            throw new moodle_exception('palier_not_requestable', 'gestionprojet');
        }
        // The copy judged, and the reference of the zones if the level is validated.
        $submission = $DB->get_record('gestionprojet_cdcf', ['id' => $submission->id], '*', MUST_EXIST);
        $contenu = (string)($submission->interacteurs_data ?? '');
        return \mod_gestionprojet\ai_evaluator::queue_evaluation((int)$gestionprojet->id, 4, (int)$submission->id,
            (int)$submission->groupid, (int)$submission->userid, $palier, $contenu);
    } finally {
        $lock->release();
    }
}

/**
 * Lock of a copy for the checks done before queueing an AI evaluation (level, verdict, formative feedback).
 *
 * @param int $submissionid
 * @param string $table Submission table without prefix (cdcf, essai, ...).
 * @return \core\lock\lock
 * @throws moodle_exception When the copy stays locked (another request is being handled).
 */
function gestionprojet_palier_lock(int $submissionid, string $table = 'cdcf'): \core\lock\lock {
    $factory = \core\lock\lock_config::get_lock_factory('mod_gestionprojet_palier');
    $lock = $factory->get_lock($table . '_' . $submissionid, 10);
    if (!$lock) {
        throw new moodle_exception('palier_pending_error', 'gestionprojet');
    }
    return $lock;
}

/**
 * Record the verdict of a level evaluation; a validated level is added once and its zones are frozen (v2.11.2).
 *
 * A later failed attempt never removes a validated level.
 *
 * @param object $evaluation Level evaluation (gestionprojet_ai_evaluations).
 * @param array $verdict palier::verdict() result.
 */
function gestionprojet_palier_record(object $evaluation, array $verdict): void {
    global $DB;

    $DB->set_field('gestionprojet_ai_evaluations', 'palier_valide', $verdict['valide'] ? 1 : 0, ['id' => $evaluation->id]);
    if (!$verdict['valide']) {
        return;
    }
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $evaluation->gestionprojetid], '*', MUST_EXIST);
    $lock = gestionprojet_palier_lock((int)$evaluation->submissionid);
    try {
        gestionprojet_palier_record_locked($gestionprojet, $evaluation);
    } finally {
        $lock->release();
    }
}

/**
 * Insert a validated level and freeze its zones; the caller holds the lock of the copy.
 *
 * @param object $gestionprojet Instance record.
 * @param object $evaluation Validated level evaluation.
 */
function gestionprojet_palier_record_locked(object $gestionprojet, object $evaluation): void {
    global $DB;

    $submission = $DB->get_record('gestionprojet_cdcf', ['id' => $evaluation->submissionid,
        'gestionprojetid' => $gestionprojet->id], '*', MUST_EXIST);
    $owner = ['gestionprojetid' => $gestionprojet->id, 'palier' => $evaluation->palier, 'retire' => 0]
        + gestionprojet_palier_owner($submission);
    if ($DB->record_exists('gestionprojet_palier', $owner)) {
        return;
    }

    // Members at validation time, as plan-classe reads them: [[userid, username], ...].
    $membres = [];
    if ((int)$submission->userid === 0 && (int)$submission->groupid > 0) {
        // Group work: every member of the group; individual work: the student alone, even inside a course group.
        foreach (groups_get_members($submission->groupid, 'u.id, u.username') as $member) {
            $membres[] = [(int)$member->id, $member->username];
        }
    } else if ((int)$submission->userid > 0) {
        $user = $DB->get_record('user', ['id' => $submission->userid], 'id, username');
        if ($user) {
            $membres[] = [(int)$user->id, $user->username];
        }
    }
    $now = time();
    $DB->insert_record('gestionprojet_palier', (object)($owner + [
        'step' => 4,
        'evaluationid' => $evaluation->id,
        'membres' => json_encode($membres),
        'timecreated' => $now,
        'timemodified' => $now,
    ]));

    // Freeze the zones of the level, with the copy judged as reference (zones already frozen keep theirs).
    $zones = (array)json_decode((string)($submission->zones_validees ?? ''), true);
    foreach (\mod_gestionprojet\palier::zones_for(gestionprojet_palier_parts($gestionprojet), $evaluation->palier) as $zone) {
        $zones[$zone] = $zones[$zone] ?? (int)$evaluation->id;
    }
    $DB->set_field('gestionprojet_cdcf', 'zones_validees', json_encode($zones), ['id' => $submission->id]);
}

/**
 * Remove a validated level, and the higher ones, for its copy; their zones open again (v2.11.2).
 *
 * The rows are kept with retire = 1 for the history; the class life app never lowers a level by itself.
 *
 * @param object $gestionprojet Instance record.
 * @param int $palierid Row of gestionprojet_palier.
 * @return string[] Levels removed.
 */
function gestionprojet_palier_retire(object $gestionprojet, int $palierid): array {
    global $DB;

    $row = $DB->get_record('gestionprojet_palier', ['id' => $palierid, 'gestionprojetid' => $gestionprojet->id, 'retire' => 0],
        '*', MUST_EXIST);
    // The copy: through the evaluation that validated the level, else through the owner.
    $submissionid = $row->evaluationid ? (int)$DB->get_field('gestionprojet_ai_evaluations', 'submissionid',
        ['id' => $row->evaluationid]) : 0;
    $submission = $submissionid ? $DB->get_record('gestionprojet_cdcf', ['id' => $submissionid]) : null;
    if (!$submission) {
        $candidates = $DB->get_records('gestionprojet_cdcf', ['gestionprojetid' => $gestionprojet->id, 'userid' => $row->userid]
            + ($row->userid ? [] : ['groupid' => $row->groupid]), 'id ASC', '*', 0, 1);
        $submission = $candidates ? reset($candidates) : null;
    }
    $lock = $submission ? gestionprojet_palier_lock((int)$submission->id) : null;
    try {
        $rows = $DB->get_records('gestionprojet_palier', ['gestionprojetid' => $gestionprojet->id, 'groupid' => $row->groupid,
            'userid' => $row->userid, 'retire' => 0]);
        $retired = \mod_gestionprojet\palier::retire_cascade($row->palier, array_column($rows, 'palier'));
        $evaluationids = [];
        foreach ($rows as $r) {
            if (in_array($r->palier, $retired, true)) {
                $DB->update_record('gestionprojet_palier', (object)['id' => $r->id, 'retire' => 1, 'timemodified' => time()]);
                $evaluationids[] = (int)$r->evaluationid;
            }
        }
        if ($submission) {
            $current = $DB->get_field('gestionprojet_cdcf', 'zones_validees', ['id' => $submission->id]);
            $zones = array_filter((array)json_decode((string)$current, true),
                function ($evaluationid) use ($evaluationids): bool {
                    return !in_array((int)$evaluationid, $evaluationids, true);
                });
            $DB->set_field('gestionprojet_cdcf', 'zones_validees', $zones ? json_encode($zones) : null, ['id' => $submission->id]);
        }
        return $retired;
    } finally {
        if ($lock) {
            $lock->release();
        }
    }
}

/**
 * Get or create student submission record.
 *
 * @param stdClass $gestionprojet The activity record
 * @param int $groupid
 * @param int $userid
 * @param string $table Table name (cdcf, essai, or rapport)
 * @return stdClass
 */
function gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, $table)
{
    global $DB;

    $tablename = 'gestionprojet_' . $table;
    $isGroupSubmission = $gestionprojet->group_submission;

    $params = ['gestionprojetid' => $gestionprojet->id];

    // Logic: 
    // If group mode enabled AND groupid is not 0: submission is linked to groupid, userid is ignored (or set to 0)
    // If individual mode OR groupid is 0 (Teacher/Solo): submission is linked to userid.

    if ($isGroupSubmission && $groupid != 0) {
        $params['groupid'] = $groupid;
        $params['userid'] = 0; // Use 0 for group submissions to ensure uniqueness
    } else {
        $params['userid'] = $userid;
        $params['groupid'] = $groupid; // Likely 0
    }

    $record = $DB->get_record($tablename, $params);

    if (!$record) {
        $record = new stdClass();
        $record->gestionprojetid = $gestionprojet->id;
        $record->groupid = $params['groupid'];
        $record->userid = $params['userid'];
        $record->status = 0; // Draft
        $record->timecreated = time();
        $record->timemodified = time();

        $record->id = $DB->insert_record($tablename, $record);
    }

    // For FAST phase: when teacher provides a consigne, seed student submission with it.
    if ($table === 'fast' && (int)$gestionprojet->step9_provided === 1 && empty($record->data_json)) {
        $provided = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);
        if ($provided && !empty($provided->data_json)) {
            $record->data_json = $provided->data_json;
            $record->timemodified = time();
            $DB->update_record('gestionprojet_fast', $record);
        }
    }

    // For CDCF phase: when teacher provides a consigne, seed student submission with it.
    // "Empty" means no fonctions de service AND no contraintes — the default skeleton
    // (2 blank interactors) produced by the migration counts as empty.
    if ($table === 'cdcf' && (int)$gestionprojet->step4_provided === 1) {
        require_once(__DIR__ . '/classes/cdcf_helper.php');
        $current = \mod_gestionprojet\cdcf_helper::decode($record->interacteurs_data ?? null);
        $isempty = empty($current['fonctionsService']) && empty($current['contraintes']);
        if ($isempty) {
            $provided = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
            if ($provided && !empty($provided->interacteurs_data)) {
                // Starting copy: no lock/gap flag, fields "to fill in" emptied (v2.11.1).
                $record->interacteurs_data = json_encode(\mod_gestionprojet\consigne_lock::seed(
                    \mod_gestionprojet\cdcf_helper::decode($provided->interacteurs_data)), JSON_UNESCAPED_UNICODE);
                $record->timemodified = time();
                $DB->update_record('gestionprojet_cdcf', $record);
            }
        }
    }

    // For Essai phase: when teacher provides a consigne, seed student submission with it.
    // "Empty" means all main text fields are blank — initial creation or never touched.
    // Once the student saves any of these fields, no more seeding (predictable behavior).
    if ($table === 'essai' && (int)($gestionprojet->step5_provided ?? 0) === 1) {
        $checkfields = [
            'fonction_service', 'niveaux_reussite', 'etapes_protocole',
            'materiel_outils', 'precautions', 'resultats_obtenus',
            'observations_remarques', 'conclusion', 'objectif',
        ];
        $isempty = true;
        foreach ($checkfields as $f) {
            if (!empty(trim((string)($record->{$f} ?? '')))) {
                $isempty = false;
                break;
            }
        }
        if ($isempty) {
            $provided = $DB->get_record('gestionprojet_essai_provided',
                ['gestionprojetid' => $gestionprojet->id]);
            if ($provided) {
                // Copy all consigne fields (text + meta) into the student record.
                $copyfields = array_merge($checkfields, ['nom_essai', 'date_essai', 'groupe_eleves']);
                $changed = false;
                foreach ($copyfields as $f) {
                    if (!empty($provided->{$f} ?? '')) {
                        $record->{$f} = $provided->{$f};
                        $changed = true;
                    }
                }
                if ($changed) {
                    $record->timemodified = time();
                    $DB->update_record('gestionprojet_essai', $record);
                }
            }
        }
    }

    return $record;
}

/**
 * Submit a specific step.
 *
 * @param stdClass $gestionprojet
 * @param int $groupid
 * @param int $userid
 * @param string $stepname (cdcf, essai, or rapport)
 * @return bool
 */
function gestionprojet_submit_step($gestionprojet, $groupid, $userid, $stepname)
{
    global $DB;

    $submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, $stepname);

    if (!$submission) {
        return false;
    }

    $tablename = 'gestionprojet_' . $stepname;

    $submission->status = 1; // Submitted
    $submission->timesubmitted = time();
    $submission->timemodified = time();

    return $DB->update_record($tablename, $submission);
}

/**
 * Revert a submission to draft.
 *
 * @param stdClass $gestionprojet
 * @param int $groupid
 * @param int $userid
 * @param string $stepname
 * @return bool
 */
function gestionprojet_revert_to_draft($gestionprojet, $groupid, $userid, $stepname)
{
    global $DB;

    $submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, $stepname);

    if (!$submission) {
        return false;
    }

    $tablename = 'gestionprojet_' . $stepname;

    $submission->status = 0; // Draft
    $submission->timemodified = time();

    return $DB->update_record($tablename, $submission);
}

/**
 * Log a modification to history table.
 *
 * @param int $gestionprojetid
 * @param string $tablename
 * @param int $recordid
 * @param string $fieldname
 * @param mixed $oldvalue
 * @param mixed $newvalue
 * @param int $userid
 * @param int $groupid
 */
function gestionprojet_log_change($gestionprojetid, $tablename, $recordid, $fieldname, $oldvalue, $newvalue, $userid, $groupid = null)
{
    global $DB;

    // Don't log if values are the same
    if ($oldvalue === $newvalue) {
        return;
    }

    $history = new stdClass();
    $history->gestionprojetid = $gestionprojetid;
    $history->tablename = $tablename;
    $history->recordid = $recordid;
    $history->fieldname = $fieldname;
    $history->oldvalue = is_string($oldvalue) ? $oldvalue : json_encode($oldvalue);
    $history->newvalue = is_string($newvalue) ? $newvalue : json_encode($newvalue);
    $history->userid = $userid;
    $history->groupid = $groupid;
    $history->timecreated = time();

    $DB->insert_record('gestionprojet_history', $history);
}

/**
 * Check if teacher pages are locked.
 *
 * @param int $gestionprojetid
 * @return bool
 */
function gestionprojet_teacher_pages_locked($gestionprojetid)
{
    global $DB;

    $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojetid]);
    $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojetid]);
    $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojetid]);

    return ($description && $description->locked) ||
        ($besoin && $besoin->locked) ||
        ($planning && $planning->locked);
}

/**
 * State of the formative feedback of a copy (v2.11.4): feedbacks asked before submitting, never graded.
 *
 * @param object $gestionprojet Instance record.
 * @param int $step Step 4 to 9.
 * @param object $submission The copy.
 * @return array enabled, limit, used, remaining, pending (evaluation or null), last (last completed feedback or null)
 */
function gestionprojet_formative_state(object $gestionprojet, int $step, object $submission): array {
    global $DB;

    $limit = max(0, (int)($gestionprojet->formative_limit ?? 3));
    $where = 'gestionprojetid = :gpid AND step = :step AND submissionid = :sid AND formatif = 1';
    $params = ['gpid' => $gestionprojet->id, 'step' => $step, 'sid' => $submission->id];
    // A feedback that failed for a technical reason does not count, but the total (failures included) is bounded
    // to twice the limit: a copy crafted to make the AI fail cannot ask again and again.
    $used = $DB->count_records_select('gestionprojet_ai_evaluations', $where . " AND status <> 'failed'", $params);
    $total = $DB->count_records_select('gestionprojet_ai_evaluations', $where, $params);
    $pending = $DB->get_records_select('gestionprojet_ai_evaluations', $where . " AND status IN ('pending', 'processing')",
        $params, 'id DESC', '*', 0, 1);
    $last = $DB->get_records_select('gestionprojet_ai_evaluations', $where . " AND status = 'completed'",
        $params, 'id DESC', '*', 0, 1);
    return [
        'enabled' => $limit > 0 && !empty($gestionprojet->ai_enabled)
            && !($step === 4 && (int)($gestionprojet->step4_paliers ?? 0) === 1),
        'limit' => $limit,
        'used' => $used,
        'remaining' => max(0, min($limit - $used, 2 * $limit - $total)),
        'pending' => $pending ? reset($pending) : null,
        'last' => $last ? reset($last) : null,
    ];
}

/**
 * Ask the AI for a formative feedback on a copy without submitting it (v2.11.4).
 *
 * The copy stays open; the step fields are frozen into the evaluation; no grade is ever applied.
 *
 * @param object $gestionprojet Instance record.
 * @param object $cm Course module.
 * @param int $step Step 4 to 9.
 * @param int $groupid Group checked by the caller (gestionprojet_require_group_access).
 * @param int $userid
 * @return int Evaluation id.
 * @throws moodle_exception When feedback is off, used up, running, or the copy is submitted or past the deadline.
 */
function gestionprojet_formative_request(object $gestionprojet, object $cm, int $step, int $groupid, int $userid): int {
    global $DB;

    $tables = [4 => 'cdcf', 5 => 'essai', 6 => 'rapport', 7 => 'besoin_eleve', 8 => 'carnet', 9 => 'fast'];
    if (!isset($tables[$step]) || empty($gestionprojet->ai_enabled) || !gestionprojet_is_step_enabled($gestionprojet, $step)
            || ($step === 4 && (int)($gestionprojet->step4_paliers ?? 0) === 1)) {
        throw new moodle_exception('formative_disabled', 'gestionprojet');
    }
    $submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $userid, $tables[$step]);
    if ((int)$submission->status === 1) {
        throw new moodle_exception('submissionlocked', 'gestionprojet');
    }
    $deadline = (int)$DB->get_field('gestionprojet_' . $tables[$step] . '_teacher', 'deadline_date',
        ['gestionprojetid' => $gestionprojet->id]);
    if ($deadline > 0 && time() > $deadline) {
        throw new moodle_exception('submissionlocked', 'gestionprojet');
    }

    // Check and queue under the lock of the copy: a double click never exceeds the limit.
    $lock = gestionprojet_palier_lock((int)$submission->id, $tables[$step]);
    try {
        $state = gestionprojet_formative_state($gestionprojet, $step, $submission);
        if (!$state['enabled']) {
            throw new moodle_exception('formative_disabled', 'gestionprojet');
        }
        if ($state['pending']) {
            throw new moodle_exception('formative_pending_error', 'gestionprojet');
        }
        if ($state['remaining'] <= 0) {
            throw new moodle_exception('formative_none_left', 'gestionprojet');
        }
        // The copy judged: the step fields as saved now.
        $submission = $DB->get_record('gestionprojet_' . $tables[$step], ['id' => $submission->id], '*', MUST_EXIST);
        $contenu = [];
        foreach (\mod_gestionprojet\ai_prompt_builder::STEP_FIELDS[$step] ?? [] as $field) {
            $contenu[$field] = $submission->$field ?? null;
        }
        return \mod_gestionprojet\ai_evaluator::queue_evaluation((int)$gestionprojet->id, $step, (int)$submission->id,
            (int)$submission->groupid, (int)$submission->userid, '', json_encode($contenu, JSON_UNESCAPED_UNICODE), true);
    } finally {
        $lock->release();
    }
}

/**
 * Opening of the two-column layout of a student step page: the form, then the progress panel (v2.12.0).
 *
 * pages/student_progress_panel.php closes the main column and the layout.
 *
 * @return string
 */
function gestionprojet_progress_layout_open(): string {
    return '<div class="gp-progress-layout"><div class="gp-progress-main" id="gpProgressMain">';
}

/**
 * State shown by the progress panel of a student step page (v2.12.0).
 *
 * @param object $gestionprojet Instance record.
 * @param int $step Step 4 to 9.
 * @param object $submission The copy.
 * @param bool $readonly A teacher looking at a group's or a student's copy: no submit, no feedback request.
 * @return array status, grade, previousgrade, palier, formative, feedback (null when AI is off), cansubmit, canask
 */
function gestionprojet_progress_state(object $gestionprojet, int $step, object $submission, bool $readonly): array {
    global $DB;

    $aion = !empty($gestionprojet->ai_enabled);
    $submitted = (int)$submission->status === 1;
    $pending = $aion && $submitted
        && \mod_gestionprojet\ai_evaluator::has_pending_evaluation((int)$gestionprojet->id, $step, (int)$submission->id);
    $paliermode = $step === 4 && (int)($gestionprojet->step4_paliers ?? 0) === 1;
    $palier = $paliermode ? gestionprojet_palier_state($gestionprojet, $submission) : null;
    $formative = $aion ? gestionprojet_formative_state($gestionprojet, $step, $submission) : null;
    $grade = $submission->grade !== null ? (float)$submission->grade : null;

    $feedback = null;
    if ($aion) {
        $applied = $DB->get_records('gestionprojet_ai_evaluations', ['gestionprojetid' => $gestionprojet->id, 'step' => $step,
            'submissionid' => $submission->id, 'status' => 'applied', 'formatif' => 0], 'timemodified DESC, id DESC', '*', 0, 1);
        $applied = $applied ? reset($applied) : null;
        $pick = \mod_gestionprojet\progress::pick_feedback($applied, $formative ? $formative['last'] : null, $grade,
            (string)($submission->feedback ?? ''), (int)($submission->timemodified ?? 0));
        $text = $pick['text'];
        $criteria = [];
        $suggestions = [];
        if ($pick['evaluation']) {
            if ($pick['source'] === 'applied' && isset($pick['evaluation']->show_feedback) && !$pick['evaluation']->show_feedback) {
                $text = '';
            }
            $criteria = json_decode((string)$pick['evaluation']->criteria_json, true) ?: [];
            if (!isset($pick['evaluation']->show_suggestions) || $pick['evaluation']->show_suggestions) {
                $suggestions = json_decode((string)$pick['evaluation']->suggestions, true) ?: [];
            }
        }
        // Never a word of the correction model the student did not write (always for a formative feedback).
        if ($pick['source'] === 'formative' || !isset($gestionprojet->ai_hide_answers) || $gestionprojet->ai_hide_answers) {
            $view = \mod_gestionprojet\answer_leak_filter::student_view($text, $criteria, $suggestions,
                gestionprojet_answer_leak_roots($gestionprojet, $step, $submission));
            $text = $view['feedback'];
            $suggestions = $view['suggestions'];
        }
        $feedback = ['source' => $pick['source'], 'text' => $text, 'suggestions' => $suggestions, 'time' => $pick['time']];
    }

    return [
        'status' => \mod_gestionprojet\progress::status((int)$submission->status, $pending),
        'grade' => $grade,
        'previousgrade' => $grade !== null && !$submitted,
        'palier' => $palier,
        'formative' => $formative,
        'feedback' => $feedback,
        'cansubmit' => !$readonly && !$submitted && !empty($gestionprojet->enable_submission) && !$paliermode,
        'canask' => !$readonly && !$submitted && $formative !== null && $formative['enabled'],
    ];
}

/**
 * Whether a student only consults the teacher's consigne of a step (4, 5 or 9) without completing it.
 *
 * True when the consigne is shown (stepN_provided = 1) and the student step is disabled. When students must
 * complete the step, the consigne is already copied into their work page: no separate consultation page.
 *
 * @param object $gestionprojet Instance record.
 * @param int $step 4, 5 or 9.
 * @return bool
 */
function gestionprojet_student_consult_only(object $gestionprojet, int $step): bool {
    return (int)($gestionprojet->{'step' . $step . '_provided'} ?? 0) === 1
        && (int)($gestionprojet->{'enable_step' . $step} ?? 1) !== 1;
}

/**
 * Whether a student step (4, 5 or 9) is enabled with a consigne shown: students can start it whatever the
 * teacher pages (preco 17 bis, v2.11.3).
 *
 * @param object $gestionprojet Instance record.
 * @return bool
 */
function gestionprojet_has_provided_student_step(object $gestionprojet): bool {
    foreach ([4, 5, 9] as $step) {
        if ((int)($gestionprojet->{'step' . $step . '_provided'} ?? 0) === 1
                && (int)($gestionprojet->{'enable_step' . $step} ?? 1) === 1) {
            return true;
        }
    }
    return false;
}

/**
 * Give the current starting copy to the step 4 copies nobody worked on (preco 22, v2.11.3).
 *
 * A copy is reseeded when it is a draft, has no zone frozen by a level, no AI evaluation, and is still a
 * starting copy (consigne_lock::is_untouched): the teacher can set the flags after the copies were created.
 *
 * @param object $gestionprojet Instance record.
 * @return int Number of copies reseeded.
 */
function gestionprojet_cdcf_reseed_untouched(object $gestionprojet): int {
    global $DB;

    if ((int)($gestionprojet->step4_provided ?? 0) !== 1) {
        return 0;
    }
    $provided = $DB->get_field('gestionprojet_cdcf_provided', 'interacteurs_data', ['gestionprojetid' => $gestionprojet->id]);
    if (empty($provided)) {
        return 0;
    }
    $consigne = \mod_gestionprojet\cdcf_helper::decode($provided);
    $seedjson = json_encode(\mod_gestionprojet\consigne_lock::seed($consigne), JSON_UNESCAPED_UNICODE);
    $count = 0;
    foreach ($DB->get_records('gestionprojet_cdcf', ['gestionprojetid' => $gestionprojet->id, 'status' => 0]) as $copy) {
        if (!empty($copy->zones_validees) || $copy->interacteurs_data === $seedjson) {
            continue;
        }
        if ($DB->record_exists('gestionprojet_ai_evaluations',
                ['gestionprojetid' => $gestionprojet->id, 'step' => 4, 'submissionid' => $copy->id])) {
            continue;
        }
        if (!\mod_gestionprojet\consigne_lock::is_untouched(
                \mod_gestionprojet\cdcf_helper::decode($copy->interacteurs_data ?: null), $consigne)) {
            continue;
        }
        $DB->update_record('gestionprojet_cdcf', (object)['id' => $copy->id, 'interacteurs_data' => $seedjson,
            'timemodified' => time()]);
        $count++;
    }
    return $count;
}

/**
 * Check if teacher pages are complete.
 *
 * Only the enabled teacher steps (1 description, 2 besoin, 3 planning) are checked: a disabled step
 * never blocks the students.
 *
 * @param int $gestionprojetid
 * @return bool
 */
function gestionprojet_teacher_pages_complete($gestionprojetid)
{
    global $DB;

    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $gestionprojetid],
        'id, enable_step1, enable_step2, enable_step3');

    // Step => [table, required field].
    $required = [
        1 => ['gestionprojet_description', 'intitule'],
        2 => ['gestionprojet_besoin', 'aqui'],
        3 => ['gestionprojet_planning', 'projectname'],
    ];
    foreach ($required as $step => [$table, $field]) {
        $enabled = !$gestionprojet || !isset($gestionprojet->{'enable_step' . $step})
            || (int)$gestionprojet->{'enable_step' . $step} === 1;
        if (!$enabled) {
            continue;
        }
        $record = $DB->get_record($table, ['gestionprojetid' => $gestionprojetid]);
        if (!$record || empty($record->$field)) {
            return false;
        }
    }
    return true;
}

/**
 * Get all groups with submissions for grading.
 *
 * @param int $gestionprojetid
 * @param int $courseid
 * @return array Array of groups with submission status
 */
function gestionprojet_get_groups_for_grading($gestionprojetid, $courseid)
{
    global $DB;

    $groups = groups_get_all_groups($courseid);
    $result = [];

    foreach ($groups as $group) {
        $cdcf = $DB->get_record('gestionprojet_cdcf', [
            'gestionprojetid' => $gestionprojetid,
            'groupid' => $group->id
        ]);
        $essai = $DB->get_record('gestionprojet_essai', [
            'gestionprojetid' => $gestionprojetid,
            'groupid' => $group->id
        ]);
        $rapport = $DB->get_record('gestionprojet_rapport', [
            'gestionprojetid' => $gestionprojetid,
            'groupid' => $group->id
        ]);
        $besoin_eleve = $DB->get_record('gestionprojet_besoin_eleve', [
            'gestionprojetid' => $gestionprojetid,
            'groupid' => $group->id
        ]);

        $result[] = [
            'group' => $group,
            'cdcf' => $cdcf,
            'essai' => $essai,
            'rapport' => $rapport,
            'besoin_eleve' => $besoin_eleve,
            'has_submission' => ($cdcf || $essai || $rapport || $besoin_eleve)
        ];
    }

    return $result;
}

/**
 * Extend navigation settings.
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 */
function gestionprojet_extend_settings_navigation($settings, $navref)
{
    global $PAGE, $USER;

    $context = $PAGE->cm->context;

    // Add link to AI usage report for teachers (if AI is enabled)
    if (has_capability('mod/gestionprojet:grade', $context)) {
        global $DB;
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $PAGE->cm->instance]);
        if (!empty($gestionprojet->ai_enabled)) {
            $url = new moodle_url('/mod/gestionprojet/report.php', ['id' => $PAGE->cm->id]);
            $navref->add(get_string('ai_log_report', 'gestionprojet'), $url, navigation_node::TYPE_SETTING);
        }
    }
}

/**
 * Check if a specific step is enabled for an instance.
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $step Step number (4-8)
 * @return bool True if step is enabled
 */
function gestionprojet_is_step_enabled($gestionprojet, $step)
{
    $enableprop = 'enable_step' . $step;
    // Check if property exists.
    if (!isset($gestionprojet->$enableprop)) {
        return true; // Default to enabled if not set.
    }
    // Handle various value formats (string "1", int 1, bool true).
    $value = $gestionprojet->$enableprop;
    return !empty($value) && $value !== '0';
}

/**
 * Get the table name for a graded step.
 *
 * @param int $step Step number (4-9)
 * @return string|null Table name or null if invalid step
 */
function gestionprojet_get_step_table($step)
{
    $tables = [
        4 => 'gestionprojet_cdcf',
        5 => 'gestionprojet_essai',
        6 => 'gestionprojet_rapport',
        7 => 'gestionprojet_besoin_eleve',
        8 => 'gestionprojet_carnet',
        9 => 'gestionprojet_fast',
    ];
    return $tables[$step] ?? null;
}

/**
 * Map step number to itemnumber for gradebook.
 * Steps 4-9 map to itemnumbers 1-6 (0 is reserved for combined mode).
 *
 * @param int $step Step number (4-9)
 * @return int Itemnumber for gradebook (1-6)
 */
function gestionprojet_step_to_itemnumber($step)
{
    // Map: step 4 => 1, step 5 => 2, step 6 => 3, step 7 => 4, step 8 => 5, step 9 => 6
    return $step - 3;
}

/**
 * Map itemnumber back to step number.
 *
 * @param int $itemnumber Itemnumber (1-6)
 * @return int Step number (4-9)
 */
function gestionprojet_itemnumber_to_step($itemnumber)
{
    return $itemnumber + 3;
}

/**
 * Get grades for a specific step.
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $step Step number (4-9)
 * @param int $userid Optional user ID (0 for all users)
 * @return array Array of grade objects keyed by userid
 */
function gestionprojet_get_step_grades($gestionprojet, $step, $userid = 0)
{
    global $DB;

    $grades = [];
    $tablename = gestionprojet_get_step_table($step);

    if (!$tablename) {
        return $grades;
    }

    // Check if step is enabled.
    if (!gestionprojet_is_step_enabled($gestionprojet, $step)) {
        return $grades;
    }

    // Get all groups.
    $groups = groups_get_all_groups($gestionprojet->course);

    // If no groups, create a virtual group for "All participants".
    if (empty($groups)) {
        $groups = [0 => (object) ['id' => 0]];
    }

    $isGroupSubmission = $gestionprojet->group_submission;

    foreach ($groups as $group) {
        // Get group members.
        if ($group->id == 0) {
            $context = context_course::instance($gestionprojet->course);
            $members = get_enrolled_users($context);
        } else {
            $members = groups_get_members($group->id, 'u.id');
        }

        if (empty($members)) {
            continue;
        }

        if (!$isGroupSubmission) {
            // Individual mode: each user has their own submission.
            foreach ($members as $member) {
                if ($userid != 0 && $userid != $member->id) {
                    continue;
                }

                // Individual submissions are stored with the user's id.
                // Try to find submission with groupid=0 first (standard individual).
                $submission = $DB->get_record($tablename, [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id,
                    'groupid' => 0
                ]);

                // If not found, try with the group's id (individual in group context).
                if (!$submission && $group->id != 0) {
                    $submission = $DB->get_record($tablename, [
                        'gestionprojetid' => $gestionprojet->id,
                        'userid' => $member->id,
                        'groupid' => $group->id
                    ]);
                }

                if ($submission && $submission->grade !== null) {
                    $grades[$member->id] = new stdClass();
                    $grades[$member->id]->userid = $member->id;
                    $grades[$member->id]->rawgrade = $submission->grade;
                }
            }
        } else {
            // Group mode: all group members share the same grade.
            // Group submissions are stored with userid=0.
            $submission = $DB->get_record($tablename, [
                'gestionprojetid' => $gestionprojet->id,
                'groupid' => $group->id,
                'userid' => 0
            ]);

            if ($submission && $submission->grade !== null) {
                foreach ($members as $member) {
                    if ($userid != 0 && $userid != $member->id) {
                        continue;
                    }
                    $grades[$member->id] = new stdClass();
                    $grades[$member->id]->userid = $member->id;
                    $grades[$member->id]->rawgrade = $submission->grade;
                }
            }
        }
    }

    return $grades;
}

/**
 * Get or create the grade category for this activity.
 * Each activity has its own unique category.
 * We use the grade_item associated with the category to store our identifier.
 *
 * @param stdClass $gestionprojet The activity instance
 * @return grade_category|null The grade category or null on failure
 */
function gestionprojet_get_grade_category($gestionprojet)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/grade/grade_category.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    // Unique identifier for this activity's category.
    // We store it in the category's grade_item idnumber field.
    $catidnumber = 'gestionprojet_cat_' . $gestionprojet->id;

    // First, try to find the category via its associated grade_item.
    // Each category in Moodle has an associated grade_item of type 'category'.
    $catitem = $DB->get_record('grade_items', [
        'itemtype' => 'category',
        'courseid' => $gestionprojet->course,
        'idnumber' => $catidnumber
    ]);

    if ($catitem && $catitem->iteminstance) {
        // Found the category's grade_item, fetch the category.
        $category = grade_category::fetch(['id' => $catitem->iteminstance]);

        if ($category) {
            // Update category name if activity name changed.
            if ($category->fullname !== $gestionprojet->name) {
                $category->fullname = $gestionprojet->name;
                $category->update();
            }
            // Unhide if previously hidden.
            if ($category->hidden) {
                $category->set_hidden(0);
            }
            return $category;
        }
    }

    // Category doesn't exist, create a new one.
    $category = new grade_category([
        'courseid' => $gestionprojet->course,
        'fullname' => $gestionprojet->name,
    ], false);
    $category->insert();

    // Now set the idnumber on the category's grade_item for future lookups.
    if ($category->id) {
        $categoryitem = $category->get_grade_item();
        if ($categoryitem) {
            $categoryitem->idnumber = $catidnumber;
            $categoryitem->update();
        }
    }

    return $category;
}

/**
 * Get the display order for graded steps (matching project workflow).
 * Order: 7 (Student Needs Expression), 4 (Functional Specifications), 9 (FAST Diagram), 5 (Test Sheet), 8 (Logbook), 6 (Report)
 *
 * @return array Step numbers in display order
 */
function gestionprojet_get_graded_steps_order()
{
    return [7, 4, 9, 5, 8, 6];
}

/**
 * Update activity grades.
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $userid Optional user ID (0 for all users)
 * @param bool $nullifnone If true, return null grade for users with no submission
 * @param int|null $step Optional step number for per_step mode (4-9)
 */
function gestionprojet_update_grades($gestionprojet, $userid = 0, $nullifnone = true, $step = null)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    // Check grade mode.
    $grademode = isset($gestionprojet->grade_mode) ? (int)$gestionprojet->grade_mode : 0;

    if ($grademode == 1) {
        // Per-step mode: create/update grade items for each enabled step.
        // Use the correct display order for steps.
        $gradedsteps = gestionprojet_get_graded_steps_order();

        // Get or create the grade category for this activity.
        $category = gestionprojet_get_grade_category($gestionprojet);
        $categoryid = $category ? $category->id : null;

        if ((int)$step === 4 && (int)($gestionprojet->step4_paliers ?? 0) === 1) {
            // Level mode (v2.11.2): no grade for step 4.
            return;
        }
        if ($step !== null) {
            // Update only the specified step.
            if (in_array($step, $gradedsteps) && gestionprojet_is_step_enabled($gestionprojet, $step)) {
                $grades = gestionprojet_get_step_grades($gestionprojet, $step, $userid);
                $sortorder = array_search($step, $gradedsteps);
                gestionprojet_grade_item_update($gestionprojet, $grades, $step, $categoryid, $sortorder);
            }
        } else {
            // Update all steps - create for enabled, hide for disabled.
            $sortorder = 0;
            foreach ($gradedsteps as $s) {
                $isenabled = gestionprojet_is_step_enabled($gestionprojet, $s);
                $idnumber = 'gestionprojet_' . $gestionprojet->id . '_step' . $s;

                if ($isenabled) {
                    // Create or update grade item for enabled step.
                    $grades = gestionprojet_get_step_grades($gestionprojet, $s, $userid);
                    gestionprojet_grade_item_update($gestionprojet, $grades, $s, $categoryid, $sortorder);
                    $sortorder++;
                } else {
                    // Hide grade item for disabled step if it exists.
                    $itemrecord = $DB->get_record('grade_items', [
                        'itemtype' => 'manual',
                        'courseid' => $gestionprojet->course,
                        'idnumber' => $idnumber
                    ]);
                    if ($itemrecord) {
                        $gradeitem = grade_item::fetch(['id' => $itemrecord->id]);
                        if ($gradeitem) {
                            $gradeitem->set_hidden(1);
                        }
                    }
                }
            }
        }
    } else {
        // Combined mode (legacy): single average grade.
        $grades = gestionprojet_get_user_grades($gestionprojet, $userid);
        gestionprojet_grade_item_update($gestionprojet, $grades);

        // Hide any per-step grade items that might exist from mode change.
        $gradedsteps = gestionprojet_get_graded_steps_order();
        foreach ($gradedsteps as $s) {
            $idnumber = 'gestionprojet_' . $gestionprojet->id . '_step' . $s;
            $itemrecord = $DB->get_record('grade_items', [
                'itemtype' => 'manual',
                'courseid' => $gestionprojet->course,
                'idnumber' => $idnumber
            ]);
            if ($itemrecord) {
                $gradeitem = grade_item::fetch(['id' => $itemrecord->id]);
                if ($gradeitem) {
                    $gradeitem->set_hidden(1);
                }
            }
        }

        // Also hide the category if it exists (from a previous per-step mode).
        $catidnumber = 'gestionprojet_cat_' . $gestionprojet->id;
        // Find category via its grade_item.
        $catitem = $DB->get_record('grade_items', [
            'itemtype' => 'category',
            'courseid' => $gestionprojet->course,
            'idnumber' => $catidnumber
        ]);
        if ($catitem && $catitem->iteminstance) {
            $category = grade_category::fetch(['id' => $catitem->iteminstance]);
            if ($category) {
                $category->set_hidden(1);
            }
        }
    }
}

/**
 * Create or update grade item.
 *
 * @param stdClass $gestionprojet The activity instance
 * @param mixed $grades Array of grades or 'reset'
 * @param int|null $step Step number for per_step mode (4-9), null for combined mode
 * @param int|null $categoryid Grade category ID to assign the item to
 * @param int|null $sortorder Sort order within the category
 * @return int 0 if ok, error code otherwise
 */
function gestionprojet_grade_item_update($gestionprojet, $grades = null, $step = null, $categoryid = null, $sortorder = null)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

    // Level mode (v2.11.2): step 4 gives levels, not grades; its grade item is no longer updated.
    if ((int)$step === 4 && (int)($gestionprojet->step4_paliers ?? 0) === 1) {
        return 0;
    }

    if ($step !== null) {
        // Per-step mode: create/update a manual grade item for this step.
        $stepname = get_string('step' . $step, 'gestionprojet');
        // Use only the step name (not activity name prefix) since items are in a category.
        $itemname = $stepname;
        // Unique idnumber per activity and step.
        $idnumber = 'gestionprojet_' . $gestionprojet->id . '_step' . $step;

        // Check if grade item already exists - use direct DB query to avoid cache issues.
        $itemrecord = $DB->get_record('grade_items', [
            'itemtype' => 'manual',
            'courseid' => $gestionprojet->course,
            'idnumber' => $idnumber
        ]);

        $gradeitem = null;
        if ($itemrecord) {
            $gradeitem = grade_item::fetch(['id' => $itemrecord->id]);
        }

        if (!$gradeitem) {
            // Create new grade item.
            $itemdata = [
                'itemtype' => 'manual',
                'itemname' => $itemname,
                'idnumber' => $idnumber,
                'courseid' => $gestionprojet->course,
                'gradetype' => GRADE_TYPE_VALUE,
                'grademax' => 20,
                'grademin' => 0,
            ];
            // Assign to category if provided.
            if ($categoryid !== null) {
                $itemdata['categoryid'] = $categoryid;
            }
            // Set sort order if provided.
            if ($sortorder !== null) {
                $itemdata['sortorder'] = $sortorder;
            }
            $gradeitem = new grade_item($itemdata, false);
            $gradeitem->insert();
        } else {
            // Update item name if changed and ensure it's visible.
            $needsupdate = false;
            if ($gradeitem->itemname !== $itemname) {
                $gradeitem->itemname = $itemname;
                $needsupdate = true;
            }
            // Update category if provided and different.
            if ($categoryid !== null && (int)$gradeitem->categoryid !== (int)$categoryid) {
                $gradeitem->categoryid = $categoryid;
                $needsupdate = true;
            }
            // Update sort order if provided.
            if ($sortorder !== null && (int)$gradeitem->sortorder !== (int)$sortorder) {
                $gradeitem->sortorder = $sortorder;
                $needsupdate = true;
            }
            // Unhide if previously hidden (e.g., step was re-enabled).
            if ($gradeitem->hidden) {
                $gradeitem->set_hidden(0);
            }
            if ($needsupdate) {
                $gradeitem->update();
            }
        }

        // Update grades if provided.
        // For manual grade items, we need to use grade_grade directly.
        if ($grades !== null && $grades !== 'reset' && !empty($grades)) {
            require_once($CFG->libdir . '/grade/grade_grade.php');

            foreach ($grades as $uid => $grade) {
                // Fetch or create grade_grade record.
                $gradegrade = grade_grade::fetch([
                    'itemid' => $gradeitem->id,
                    'userid' => $uid
                ]);

                if (!$gradegrade) {
                    // Create new grade record.
                    $gradegrade = new grade_grade([
                        'itemid' => $gradeitem->id,
                        'userid' => $uid,
                    ], false);
                }

                // Set the grade value.
                $gradegrade->rawgrade = $grade->rawgrade;
                $gradegrade->rawgrademax = $gradeitem->grademax;
                $gradegrade->rawgrademin = $gradeitem->grademin;
                $gradegrade->rawscaleid = $gradeitem->scaleid;
                $gradegrade->finalgrade = $grade->rawgrade; // For manual items, final = raw.
                $gradegrade->timemodified = time();

                if ($gradegrade->id) {
                    $gradegrade->update();
                } else {
                    $gradegrade->timecreated = time();
                    $gradegrade->insert();
                }
            }

            // Force regrading to update final grades.
            $gradeitem->force_regrading();
        }

        if ($grades === 'reset') {
            $gradeitem->delete_all_grades();
        }

        return GRADE_UPDATE_OK;
    }

    // Combined mode: use standard grade_update with itemnumber = 0.
    $params = [
        'itemname' => $gestionprojet->name,
        'idnumber' => $gestionprojet->id
    ];

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax'] = 20;
    $params['grademin'] = 0;

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/gestionprojet',
        $gestionprojet->course,
        'mod',
        'gestionprojet',
        $gestionprojet->id,
        0,
        $grades,
        $params
    );
}

/**
 * Get user grades.
 *
 * @param stdClass $gestionprojet
 * @param int $userid
 * @return array
 */
function gestionprojet_get_user_grades($gestionprojet, $userid = 0)
{
    global $DB;

    $grades = [];

    // Get all groups
    $groups = groups_get_all_groups($gestionprojet->course);

    // If no groups, create a virtual group for "All participants"
    if (empty($groups)) {
        $groups = [0 => (object) ['id' => 0]];
    }

    foreach ($groups as $group) {
        // Get group members
        if ($group->id == 0) {
            // All participants
            $context = context_course::instance($gestionprojet->course);
            $members = get_enrolled_users($context);
        } else {
            $members = groups_get_members($group->id, 'u.id');
        }

        if (empty($members)) {
            continue;
        }

        // Check if we are in individual submission mode
        $isGroupSubmission = $gestionprojet->group_submission;

        if (!$isGroupSubmission) {
            // Individual grading logic
            foreach ($members as $member) {
                // Get submissions for this user
                $cdcf = $DB->get_record('gestionprojet_cdcf', [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id
                ]);
                $essai = $DB->get_record('gestionprojet_essai', [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id
                ]);
                $rapport = $DB->get_record('gestionprojet_rapport', [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id
                ]);
                $besoin_eleve = $DB->get_record('gestionprojet_besoin_eleve', [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id
                ]);

                // Calculate grade for this user
                $totalgrade = 0;
                $count = 0;

                if ($cdcf && $cdcf->grade !== null && (!isset($gestionprojet->enable_step4) || $gestionprojet->enable_step4)
                && empty($gestionprojet->step4_paliers)) {
                    $totalgrade += $cdcf->grade;
                    $count++;
                }
                if ($essai && $essai->grade !== null && (!isset($gestionprojet->enable_step5) || $gestionprojet->enable_step5)) {
                    $totalgrade += $essai->grade;
                    $count++;
                }
                if ($rapport && $rapport->grade !== null && (!isset($gestionprojet->enable_step6) || $gestionprojet->enable_step6)) {
                    $totalgrade += $rapport->grade;
                    $count++;
                }
                if ($besoin_eleve && $besoin_eleve->grade !== null && (!isset($gestionprojet->enable_step7) || $gestionprojet->enable_step7)) {
                    $totalgrade += $besoin_eleve->grade;
                    $count++;
                }
                $carnet = $DB->get_record('gestionprojet_carnet', [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id
                ]);
                if ($carnet && $carnet->grade !== null && (!isset($gestionprojet->enable_step8) || $gestionprojet->enable_step8)) {
                    $totalgrade += $carnet->grade;
                    $count++;
                }
                $fast = $DB->get_record('gestionprojet_fast', [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $member->id
                ]);
                if ($fast && $fast->grade !== null && (!isset($gestionprojet->enable_step9) || $gestionprojet->enable_step9)) {
                    $totalgrade += $fast->grade;
                    $count++;
                }

                if ($count > 0 && ($userid == 0 || $userid == $member->id)) {
                    $avggrade = $totalgrade / $count;
                    $grades[$member->id] = new stdClass();
                    $grades[$member->id]->userid = $member->id;
                    $grades[$member->id]->rawgrade = $avggrade;
                }
            }
            continue; // Skip the group grading logic below
        }

        // Group grading logic (existing)
        // Get grades for this group - group submissions are stored with userid=0
        $cdcf = $DB->get_record('gestionprojet_cdcf', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id,
            'userid' => 0
        ]);
        $essai = $DB->get_record('gestionprojet_essai', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id,
            'userid' => 0
        ]);
        $rapport = $DB->get_record('gestionprojet_rapport', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id,
            'userid' => 0
        ]);
        $besoin_eleve = $DB->get_record('gestionprojet_besoin_eleve', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id,
            'userid' => 0
        ]);

        // Calculate average grade
        $totalgrade = 0;
        $count = 0;

        if ($cdcf && $cdcf->grade !== null && (!isset($gestionprojet->enable_step4) || $gestionprojet->enable_step4)
                && empty($gestionprojet->step4_paliers)) {
            $totalgrade += $cdcf->grade;
            $count++;
        }
        if ($essai && $essai->grade !== null && (!isset($gestionprojet->enable_step5) || $gestionprojet->enable_step5)) {
            $totalgrade += $essai->grade;
            $count++;
        }
        if ($rapport && $rapport->grade !== null && (!isset($gestionprojet->enable_step6) || $gestionprojet->enable_step6)) {
            $totalgrade += $rapport->grade;
            $count++;
        }
        if ($besoin_eleve && $besoin_eleve->grade !== null && (!isset($gestionprojet->enable_step7) || $gestionprojet->enable_step7)) {
            $totalgrade += $besoin_eleve->grade;
            $count++;
        }
        $carnet = $DB->get_record('gestionprojet_carnet', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id,
            'userid' => 0
        ]);
        if ($carnet && $carnet->grade !== null && (!isset($gestionprojet->enable_step8) || $gestionprojet->enable_step8)) {
            $totalgrade += $carnet->grade;
            $count++;
        }
        $fast = $DB->get_record('gestionprojet_fast', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id,
            'userid' => 0
        ]);
        if ($fast && $fast->grade !== null && (!isset($gestionprojet->enable_step9) || $gestionprojet->enable_step9)) {
            $totalgrade += $fast->grade;
            $count++;
        }

        if ($count > 0) {
            $avggrade = $totalgrade / $count;

            // Assign same grade to all group members
            foreach ($members as $member) {
                if ($userid == 0 || $userid == $member->id) {
                    $grades[$member->id] = new stdClass();
                    $grades[$member->id]->userid = $member->id;
                    $grades[$member->id]->rawgrade = $avggrade;
                }
            }
        }
    }

    return $grades;
}

/**
 * Get navigation links for student pages based on enabled steps.
 *
 * @param stdClass $gestionprojet
 * @param int $cmid
 * @param string $current_step Name of the current step (e.g., 'step4')
 * @return array Array with 'prev' and 'next' moodle_url objects or null
 */
function gestionprojet_get_navigation_links($gestionprojet, $cmid, $current_step)
{
    // Define steps in order: Teacher steps (1, 3, 2) -> Student steps (7, 4, 5, 8, 6)
    $steps = ['step1', 'step3', 'step2', 'step7', 'step4', 'step5', 'step8', 'step6'];
    $current_index = array_search($current_step, $steps);

    if ($current_index === false) {
        return ['prev' => null, 'next' => null];
    }

    $prev_url = null;
    $next_url = null;

    // Find previous enabled step
    for ($i = $current_index - 1; $i >= 0; $i--) {
        $step = $steps[$i];
        $enable_prop = 'enable_' . $step;
        if (!isset($gestionprojet->$enable_prop) || $gestionprojet->$enable_prop) {
            $prev_url = new moodle_url('/mod/gestionprojet/view.php', ['id' => $cmid, 'step' => (int) substr($step, 4)]);
            break;
        }
    }

    // Find next enabled step
    for ($i = $current_index + 1; $i < count($steps); $i++) {
        $step = $steps[$i];
        $enable_prop = 'enable_' . $step;
        if (!isset($gestionprojet->$enable_prop) || $gestionprojet->$enable_prop) {
            $next_url = new moodle_url('/mod/gestionprojet/view.php', ['id' => $cmid, 'step' => (int) substr($step, 4)]);
            break;
        }
    }

    // Special case for step pages (stepX.php) vs view.php usage
    // The view.php handles redirection, but specific pages might be accessed directly.
    // Ideally we should use view.php for all links to ensure proper context setup if needed, 
    // but the current pages use view.php?step=X.

    // Note: The previous implementation returned full URLs to pages/stepX.php for students.
    // Teacher pages use view.php?step=X usually.
    // To be consistent and robust, let's check where the user is.
    // But sticking to view.php?step=X is safer as it handles both teacher and student routing if view.php is set up correctly.
    // However, existing student pages used direct links.
    // Let's change strictly to view.php?step=X for consistency across the module.

    return ['prev' => $prev_url, 'next' => $next_url];
}

/**
 * Render the teacher dashboard for a specific step.
 *
 * This function generates the HTML for the dashboard that shows submission
 * statistics, grade averages, AI summaries, and token usage.
 *
 * @param object $gestionprojet Instance record
 * @param int $step Step number (4-8)
 * @param context_module $context Module context
 * @param int $cmid Course module ID
 * @return string Rendered HTML, empty if user doesn't have permission
 */
function gestionprojet_render_step_dashboard($gestionprojet, $step, $context, $cmid) {
    global $OUTPUT, $PAGE;

    // Only show dashboard for student submission steps (4-9).
    if ($step < 4 || $step > 9) {
        return '';
    }

    // Check permission.
    if (!has_capability('mod/gestionprojet:grade', $context)) {
        return '';
    }

    // Check if step is enabled.
    if (!gestionprojet_is_step_enabled($gestionprojet, $step)) {
        return '';
    }

    // Load dashboard classes.
    require_once(__DIR__ . '/classes/dashboard/submission_stats.php');
    require_once(__DIR__ . '/classes/dashboard/token_stats.php');
    require_once(__DIR__ . '/classes/dashboard/ai_summary_generator.php');

    // Get statistics.
    $submissionStats = \mod_gestionprojet\dashboard\submission_stats::get_step_stats($gestionprojet->id, $step);
    $tokenStats = \mod_gestionprojet\dashboard\token_stats::get_step_token_stats($gestionprojet->id, $step);

    // Try to get existing summary, or auto-generate if enough evaluations exist.
    $aiSummary = \mod_gestionprojet\dashboard\ai_summary_generator::get_summary($gestionprojet->id, $step);
    if (!$aiSummary->has_summary && !empty($gestionprojet->ai_enabled)) {
        // Check if there are enough evaluations to generate a summary.
        $minRequired = \mod_gestionprojet\dashboard\ai_summary_generator::MIN_EVALUATIONS;
        if (isset($aiSummary->current_count) && $aiSummary->current_count >= $minRequired) {
            // Auto-generate the summary.
            $generateResult = \mod_gestionprojet\dashboard\ai_summary_generator::generate_summary($gestionprojet, $step, false);
            if ($generateResult->success && isset($generateResult->summary)) {
                $aiSummary = $generateResult->summary;
            }
        }
    }

    // Step name.
    $stepname = get_string('step' . $step, 'gestionprojet');

    // Prepare template context.
    $dashboardContext = [
        'step' => $step,
        'stepname' => $stepname,
        'cmid' => $cmid,
        'submissionstats' => (array)$submissionStats,
        'tokenstats' => (array)$tokenStats,
        'aisummary' => (array)$aiSummary,
        'aienabled' => !empty($gestionprojet->ai_enabled),
        'canedit' => has_capability('mod/gestionprojet:configureteacherpages', $context),
        'gradeDistributionJson' => json_encode($submissionStats->grade_distribution),
    ];

    // Convert nested objects to arrays for Mustache.
    if (!empty($dashboardContext['tokenstats']['by_provider'])) {
        $providers = [];
        foreach ($dashboardContext['tokenstats']['by_provider'] as $p) {
            $providers[] = (array)$p;
        }
        $dashboardContext['tokenstats']['by_provider'] = $providers;
    }

    // Ensure arrays are properly formatted.
    $dashboardContext['aisummary']['difficulties'] = $aiSummary->difficulties ?? [];
    $dashboardContext['aisummary']['strengths'] = $aiSummary->strengths ?? [];
    $dashboardContext['aisummary']['recommendations'] = $aiSummary->recommendations ?? [];

    // Add JS module.
    $PAGE->requires->js_call_amd('mod_gestionprojet/dashboard', 'init', [$cmid, $step, $submissionStats->grade_distribution]);

    return $OUTPUT->render_from_template('mod_gestionprojet/dashboard_teacher', $dashboardContext);
}

/**
 * Get navigation info for teacher correction model pages.
 *
 * Returns previous/next step numbers based on enabled steps and fixed order.
 * Order: Step 7 (Needs Expression) → Step 4 (Functional Specifications) → Step 5 (Test Sheet)
 *        → Step 8 (Logbook) → Step 6 (Report)
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $currentstep The current step number
 * @return array ['prev' => int|null, 'next' => int|null, 'order' => array]
 */
function gestionprojet_get_teacher_step_navigation($gestionprojet, $currentstep) {
    // Fixed order for correction models.
    $steporder = [7, 4, 5, 8, 6];

    // Filter to only enabled steps.
    $enabledsteps = [];
    foreach ($steporder as $step) {
        $field = 'enable_step' . $step;
        if (!isset($gestionprojet->$field) || $gestionprojet->$field) {
            $enabledsteps[] = $step;
        }
    }

    // Find current position.
    $currentindex = array_search($currentstep, $enabledsteps);
    if ($currentindex === false) {
        return ['prev' => null, 'next' => null, 'order' => $enabledsteps];
    }

    return [
        'prev' => ($currentindex > 0) ? $enabledsteps[$currentindex - 1] : null,
        'next' => ($currentindex < count($enabledsteps) - 1) ? $enabledsteps[$currentindex + 1] : null,
        'order' => $enabledsteps
    ];
}

/**
 * Check and auto-submit drafts that have passed their deadline.
 *
 * This function is called on page view to ensure deadlines are enforced
 * even without a working cron task.
 *
 * @param stdClass $gestionprojet The activity instance
 * @return array ['processed' => int, 'errors' => int] Count of processed and errored submissions
 */
function gestionprojet_check_deadline_submissions($gestionprojet) {
    global $DB;

    $result = ['processed' => 0, 'errors' => 0];
    $now = time();

    // Define the steps that have deadlines (steps 4-8).
    $steps = [
        4 => ['table' => 'gestionprojet_cdcf', 'teacher_table' => 'gestionprojet_cdcf_teacher'],
        5 => ['table' => 'gestionprojet_essai', 'teacher_table' => 'gestionprojet_essai_teacher'],
        6 => ['table' => 'gestionprojet_rapport', 'teacher_table' => 'gestionprojet_rapport_teacher'],
        7 => ['table' => 'gestionprojet_besoin_eleve', 'teacher_table' => 'gestionprojet_besoin_eleve_teacher'],
        8 => ['table' => 'gestionprojet_carnet', 'teacher_table' => 'gestionprojet_carnet_teacher'],
    ];

    foreach ($steps as $stepnum => $stepconfig) {
        // Check if step is enabled.
        $enableprop = 'enable_step' . $stepnum;
        if (isset($gestionprojet->$enableprop) && !$gestionprojet->$enableprop) {
            continue;
        }

        // Get teacher model with deadline.
        $teachermodel = $DB->get_record($stepconfig['teacher_table'], [
            'gestionprojetid' => $gestionprojet->id
        ]);

        // Skip if no deadline or deadline not passed.
        if (!$teachermodel || empty($teachermodel->deadline_date) || $teachermodel->deadline_date > $now) {
            continue;
        }

        // Get all draft submissions for this step and instance.
        $drafts = $DB->get_records($stepconfig['table'], [
            'gestionprojetid' => $gestionprojet->id,
            'status' => 0, // Draft status
        ]);

        if (empty($drafts)) {
            continue;
        }

        foreach ($drafts as $draft) {
            try {
                // Update to submitted status.
                $draft->status = 1; // Submitted
                $draft->timesubmitted = $now;
                $draft->timemodified = $now;

                $DB->update_record($stepconfig['table'], $draft);

                // Log to history table.
                gestionprojet_log_change(
                    $gestionprojet->id,
                    $stepconfig['table'],
                    $draft->id,
                    'status',
                    0,
                    1,
                    0, // System user
                    $draft->groupid
                );

                // Trigger AI evaluation if enabled.
                gestionprojet_trigger_ai_evaluation_safe($gestionprojet, $stepnum, $draft);

                $result['processed']++;
            } catch (\Exception $e) {
                $result['errors']++;
                debugging('Auto-submit failed for draft ' . $draft->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    return $result;
}

/**
 * Safely trigger AI evaluation (catches all errors to not break page load).
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $step Step number
 * @param stdClass $submission Submission record
 */
function gestionprojet_trigger_ai_evaluation_safe($gestionprojet, $step, $submission) {
    global $CFG;

    // Skip if AI not enabled.
    if (empty($gestionprojet->ai_enabled)) {
        return;
    }

    // Check if AI evaluator class exists.
    $evaluatorfile = $CFG->dirroot . '/mod/gestionprojet/classes/ai_evaluator.php';
    if (!file_exists($evaluatorfile)) {
        return;
    }

    try {
        require_once($evaluatorfile);

        // Check if AI instructions are defined.
        $teachertables = [
            4 => 'gestionprojet_cdcf_teacher',
            5 => 'gestionprojet_essai_teacher',
            6 => 'gestionprojet_rapport_teacher',
            7 => 'gestionprojet_besoin_eleve_teacher',
            8 => 'gestionprojet_carnet_teacher',
        ];

        global $DB;
        $teachertable = $teachertables[$step] ?? null;
        if (!$teachertable) {
            return;
        }

        $teachermodel = $DB->get_record($teachertable, ['gestionprojetid' => $gestionprojet->id]);
        if (!$teachermodel || empty($teachermodel->ai_instructions)) {
            return;
        }

        // Queue the AI evaluation.
        \mod_gestionprojet\ai_evaluator::queue_evaluation(
            $gestionprojet->id,
            $step,
            $submission->id,
            $submission->groupid ?? 0,
            $submission->userid ?? 0
        );
    } catch (\Throwable $e) {
        // Silently fail - don't break page load for AI errors.
        debugging('AI evaluation trigger failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

/**
 * Build the context for the step_tabs Mustache partial.
 *
 * @param stdClass $gestionprojet The activity instance (for enable_stepN flags)
 * @param int $cmid Course module ID
 * @param int $currentstep The currently active step (1-8)
 * @param string $context Tab context: 'teacher' (steps 1,2,3 view), 'model' (steps 4-8 teacher mode), 'grading' (grading.php)
 * @return array Context array with key 'tabs' suitable for the step_tabs partial
 */
function gestionprojet_build_step_tabs($gestionprojet, $cmid, $currentstep, $context) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/gestionprojet/classes/output/icon.php');

    // Tabs ordering depends on the context:
    //   - 'consignes': teacher-provided documents — phases [1, 3, 2, 4, 9].
    //   - 'correction': teacher correction models — phases [7, 4, 9, 5, 8, 6]
    //                   (matches gestionprojet_get_graded_steps_order pedagogical sequence).
    //   - 'grading': student submissions to grade — same order as 'correction'.
    //   - 'student': student work pages — phases [7, 4, 9, 5, 8, 6] with bare URLs.
    //   - 'model' (legacy) and other: full ordering with all 9 steps.
    if ($context === 'consignes') {
        $order = [1, 3, 2, 4, 9, 5];
    } else if ($context === 'correction' || $context === 'grading' || $context === 'student') {
        $order = [7, 4, 9, 5, 8, 6];
    } else {
        $order = [1, 3, 2, 7, 4, 9, 5, 8, 6];
    }
    $tabs = [];

    foreach ($order as $stepnum) {
        $field = 'enable_step' . $stepnum;
        $enableval = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
        $isenabled = ($enableval !== 0);

        // Build the URL according to the destination context.
        $params = ['id' => $cmid, 'step' => $stepnum];
        $isconsigneonlystep = in_array($stepnum, [1, 2, 3], true);
        $iscorrectiononlystep = in_array($stepnum, [5, 6, 7, 8], true);
        $isdualstep = in_array($stepnum, [4, 5, 9], true);
        $isstudentstep = in_array($stepnum, [4, 5, 6, 7, 8, 9], true);

        if ($context === 'grading' && $isstudentstep) {
            $url = (new \moodle_url('/mod/gestionprojet/grading.php', $params))->out(false);
        } else if ($context === 'consignes') {
            // Consigne tabs: 1/2/3 use bare URL, 4/9 add mode=provided.
            if ($isdualstep) {
                $params['mode'] = 'provided';
            }
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        } else if ($context === 'correction') {
            // Correction tabs always add mode=teacher.
            $params['mode'] = 'teacher';
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        } else if ($context === 'student') {
            // Student work tabs: bare URLs to step pages 4..9 (and 7 via merged column).
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        } else if ($isconsigneonlystep) {
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        } else {
            // Legacy 'model' context for student/dual steps.
            $params['mode'] = 'teacher';
            $url = (new \moodle_url('/mod/gestionprojet/view.php', $params))->out(false);
        }

        $tabs[] = [
            'stepnum' => $stepnum,
            'icon' => \mod_gestionprojet\output\icon::render_step($stepnum, 'sm', 'inherit'),
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'isactive' => ($stepnum === (int)$currentstep),
            'isenabled' => $isenabled,
            'url' => $url,
        ];
    }

    $homeurl = (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cmid]))->out(false);
    return [
        'tabs' => $tabs,
        'homeurl' => $homeurl,
        'icon_home' => \mod_gestionprojet\output\icon::render('home', 'sm', 'inherit'),
        'homelabel' => get_string('home', 'gestionprojet'),
    ];
}

/**
 * Serialize a FAST data_json structure into a human/LLM-readable hierarchical text.
 *
 * @param string|null $datajson JSON string from gestionprojet_fast(_teacher).data_json
 * @return string Multiline text representation, or empty string if no data
 */
function gestionprojet_fast_to_text($datajson) {
    if (empty($datajson)) {
        return '';
    }
    $data = json_decode($datajson, true);
    if (!is_array($data) || empty($data['fonctions'])) {
        return '';
    }

    $lines = [];
    if (!empty($data['fonctionsPrincipales'])) {
        $fps = array_map(function($fp) {
            return $fp['description'] ?? '';
        }, $data['fonctionsPrincipales']);
        $fps = array_filter($fps);
        if (!empty($fps)) {
            $lines[] = 'Fonction principale : ' . implode(' / ', $fps);
            $lines[] = '';
        }
    }

    foreach ($data['fonctions'] as $idx => $ft) {
        $ftnum = $idx + 1;
        $ftdesc = $ft['description'] ?? '';
        $lines[] = "FT{$ftnum} — {$ftdesc}";
        $sous = $ft['sousFonctions'] ?? [];
        if (!empty($sous)) {
            $count = count($sous);
            foreach ($sous as $sfidx => $sf) {
                $branch = ($sfidx === $count - 1) ? '└─' : '├─';
                $sfdesc = $sf['description'] ?? '';
                $sfsol = $sf['solution'] ?? '';
                $lines[] = "  {$branch} FT{$ftnum}.".($sfidx + 1)." {$sfdesc}";
                if (!empty($sfsol)) {
                    $vert = ($sfidx === $count - 1) ? '   ' : '  │';
                    $lines[] = "  {$vert}  Solution : {$sfsol}";
                }
            }
        } else if (!empty($ft['solution'])) {
            $lines[] = '  Solution : ' . $ft['solution'];
        }
    }

    return implode("\n", $lines);
}

/**
 * Returns the column definitions for the Gantt-style home view.
 *
 * Each entry: ['stepnum' => N, 'mergedwith' => M|null].
 * 'mergedwith' indicates that the column also represents another step's row 2/3 cells.
 *
 * Order matches the visual layout: 1 · 3 · 2(+7) · 4 · 9 · 5 · 8 · 6.
 *
 * @return array
 */
function gestionprojet_get_gantt_column_defs(): array {
    return [
        ['stepnum' => 1, 'mergedwith' => null],
        ['stepnum' => 3, 'mergedwith' => null],
        ['stepnum' => 2, 'mergedwith' => 7],
        ['stepnum' => 4, 'mergedwith' => null],
        ['stepnum' => 9, 'mergedwith' => null],
        ['stepnum' => 5, 'mergedwith' => null],
        ['stepnum' => 8, 'mergedwith' => null],
        ['stepnum' => 6, 'mergedwith' => null],
    ];
}

/**
 * Builds a single cell for the student Gantt view.
 *
 * Pure function — accepts pre-resolved data, returns the array consumed by the
 * home_gantt_student Mustache template.
 *
 * Expected $input keys when isfilled is true:
 * - role: 'consult' or 'work'
 * - isenabled: bool
 * - iscomplete: bool
 * - name: string
 * - url: string
 * - isprovided: bool (only for role='consult', columns 4 and 9)
 * - grade: float|null (only for role='work')
 *
 * @param array $input
 * @return array
 */
function gestionprojet_build_student_gantt_cell(array $input): array {
    if (empty($input['isfilled'])) {
        return ['isfilled' => false];
    }

    $cell = [
        'isfilled' => true,
        'isenabled' => (bool)($input['isenabled'] ?? false),
        'iscomplete' => (bool)($input['iscomplete'] ?? false),
        'name' => $input['name'] ?? '',
        'url' => $input['url'] ?? '#',
    ];

    if (($input['role'] ?? '') === 'consult') {
        $cell['isprovided'] = (bool)($input['isprovided'] ?? false);
    }

    if (($input['role'] ?? '') === 'work') {
        $grade = $input['grade'] ?? null;
        if ($grade !== null) {
            $cell['hasgrade'] = true;
            $cell['gradeformatted'] = number_format((float)$grade, 1) . ' / 20';
        } else {
            $cell['hasgrade'] = false;
        }
    }

    return $cell;
}

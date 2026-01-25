<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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

    // Create empty teacher pages
    gestionprojet_create_teacher_pages($data->id);

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

    return $DB->update_record('gestionprojet', $data);
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

    // Delete all related data
    $DB->delete_records('gestionprojet_description', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_besoin', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_planning', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_cdcf', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_essai', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_rapport', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_besoin_eleve', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_carnet', ['gestionprojetid' => $id]);
    $DB->delete_records('gestionprojet_history', ['gestionprojetid' => $id]);

    // Delete the instance
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
    $groups = groups_get_activity_allowed_groups($cm, $userid);

    if (empty($groups)) {
        return 0; // No groups mode or user not in any group
    }

    return $groups ? array_key_first($groups) : 0;
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
 * Check if teacher pages are complete.
 *
 * @param int $gestionprojetid
 * @return bool
 */
function gestionprojet_teacher_pages_complete($gestionprojetid)
{
    global $DB;

    $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojetid]);
    $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojetid]);
    $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojetid]);

    // Check if basic required fields are filled
    return $description && !empty($description->intitule) &&
        $besoin && !empty($besoin->aqui) &&
        $planning && !empty($planning->projectname);
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

    // Add link to view history for teachers
    if (has_capability('mod/gestionprojet:viewhistory', $context)) {
        $url = new moodle_url('/mod/gestionprojet/history.php', ['id' => $PAGE->cm->id]);
        $navref->add(get_string('view_history', 'gestionprojet'), $url, navigation_node::TYPE_SETTING);
    }

    // Add link to export all for teachers
    if (has_capability('mod/gestionprojet:exportall', $context)) {
        $url = new moodle_url('/mod/gestionprojet/export.php', ['id' => $PAGE->cm->id]);
        $navref->add(get_string('export_all', 'gestionprojet'), $url, navigation_node::TYPE_SETTING);
    }
}

/**
 * Update activity grades.
 *
 * @param stdClass $gestionprojet
 * @param int $userid
 * @param bool $nullifnone
 */
function gestionprojet_update_grades($gestionprojet, $userid = 0, $nullifnone = true)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($gestionprojet->grade == 0) {
        gestionprojet_grade_item_update($gestionprojet);
    } else {
        $grades = gestionprojet_get_user_grades($gestionprojet, $userid);
        gestionprojet_grade_item_update($gestionprojet, $grades);
    }
}

/**
 * Create or update grade item.
 *
 * @param stdClass $gestionprojet
 * @param mixed $grades
 * @return int 0 if ok, error code otherwise
 */
function gestionprojet_grade_item_update($gestionprojet, $grades = null)
{
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

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

                if ($cdcf && $cdcf->grade !== null && (!isset($gestionprojet->enable_step4) || $gestionprojet->enable_step4)) {
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
        // Get grades for this group
        $cdcf = $DB->get_record('gestionprojet_cdcf', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id
        ]);
        $essai = $DB->get_record('gestionprojet_essai', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id
        ]);
        $rapport = $DB->get_record('gestionprojet_rapport', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id
        ]);
        $besoin_eleve = $DB->get_record('gestionprojet_besoin_eleve', [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $group->id
        ]);

        // Calculate average grade
        $totalgrade = 0;
        $count = 0;

        if ($cdcf && $cdcf->grade !== null && (!isset($gestionprojet->enable_step4) || $gestionprojet->enable_step4)) {
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
            'groupid' => $group->id
        ]);
        if ($carnet && $carnet->grade !== null && (!isset($gestionprojet->enable_step8) || $gestionprojet->enable_step8)) {
            $totalgrade += $carnet->grade;
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

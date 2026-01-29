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
 * @param int $step Step number (4-8)
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
    ];
    return $tables[$step] ?? null;
}

/**
 * Map step number to itemnumber for gradebook.
 * Steps 4-8 map to itemnumbers 1-5 (0 is reserved for combined mode).
 *
 * @param int $step Step number (4-8)
 * @return int Itemnumber for gradebook (1-5)
 */
function gestionprojet_step_to_itemnumber($step)
{
    // Map: step 4 => 1, step 5 => 2, step 6 => 3, step 7 => 4, step 8 => 5
    return $step - 3;
}

/**
 * Map itemnumber back to step number.
 *
 * @param int $itemnumber Itemnumber (1-5)
 * @return int Step number (4-8)
 */
function gestionprojet_itemnumber_to_step($itemnumber)
{
    return $itemnumber + 3;
}

/**
 * Get grades for a specific step.
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $step Step number (4-8)
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
 * Order: 7 (Expression du Besoin Élève), 4 (CdCF), 5 (Essai), 8 (Carnet de bord), 6 (Rapport)
 *
 * @return array Step numbers in display order
 */
function gestionprojet_get_graded_steps_order()
{
    return [7, 4, 5, 8, 6];
}

/**
 * Update activity grades.
 *
 * @param stdClass $gestionprojet The activity instance
 * @param int $userid Optional user ID (0 for all users)
 * @param bool $nullifnone If true, return null grade for users with no submission
 * @param int|null $step Optional step number for per_step mode (4-8)
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
 * @param int|null $step Step number for per_step mode (4-8), null for combined mode
 * @param int|null $categoryid Grade category ID to assign the item to
 * @param int|null $sortorder Sort order within the category
 * @return int 0 if ok, error code otherwise
 */
function gestionprojet_grade_item_update($gestionprojet, $grades = null, $step = null, $categoryid = null, $sortorder = null)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/grade/grade_item.php');

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
            'groupid' => $group->id,
            'userid' => 0
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

    // Only show dashboard for student submission steps (4-8).
    if ($step < 4 || $step > 8) {
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

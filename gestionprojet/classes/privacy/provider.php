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
 * Privacy Subsystem implementation for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_approved;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the privacy subsystem plugin provider for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about this plugin's privacy policy.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // Student submission tables - Step 4: CDCF (Functional Specifications).
        $collection->add_database_table(
            'gestionprojet_cdcf',
            [
                'userid' => 'privacy:metadata:gestionprojet_cdcf:userid',
                'groupid' => 'privacy:metadata:groupid',
                'interacteurs_data' => 'privacy:metadata:gestionprojet_cdcf:interacteurs_data',
                'grade' => 'privacy:metadata:grade',
                'feedback' => 'privacy:metadata:feedback',
                'status' => 'privacy:metadata:gestionprojet_cdcf:status',
                'timesubmitted' => 'privacy:metadata:gestionprojet_cdcf:timesubmitted',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_cdcf'
        );

        // Student submission tables - Step 5: Test Sheet.
        $collection->add_database_table(
            'gestionprojet_essai',
            [
                'userid' => 'privacy:metadata:gestionprojet_essai:userid',
                'groupid' => 'privacy:metadata:groupid',
                'nom_essai' => 'privacy:metadata:gestionprojet_essai:nom_essai',
                'date_essai' => 'privacy:metadata:gestionprojet_essai:date_essai',
                'groupe_eleves' => 'privacy:metadata:gestionprojet_essai:groupe_eleves',
                'fonction_service' => 'privacy:metadata:gestionprojet_essai:fonction_service',
                'etapes_protocole' => 'privacy:metadata:gestionprojet_essai:etapes_protocole',
                'resultats_obtenus' => 'privacy:metadata:gestionprojet_essai:resultats_obtenus',
                'conclusion' => 'privacy:metadata:gestionprojet_essai:conclusion',
                'grade' => 'privacy:metadata:grade',
                'feedback' => 'privacy:metadata:feedback',
                'status' => 'privacy:metadata:gestionprojet_essai:status',
                'timesubmitted' => 'privacy:metadata:gestionprojet_essai:timesubmitted',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_essai'
        );

        // Student submission tables - Step 6: Project Report.
        $collection->add_database_table(
            'gestionprojet_rapport',
            [
                'userid' => 'privacy:metadata:gestionprojet_rapport:userid',
                'groupid' => 'privacy:metadata:groupid',
                'titre_projet' => 'privacy:metadata:gestionprojet_rapport:titre_projet',
                'auteurs' => 'privacy:metadata:gestionprojet_rapport:auteurs',
                'besoin_projet' => 'privacy:metadata:gestionprojet_rapport:besoin_projet',
                'solutions' => 'privacy:metadata:gestionprojet_rapport:solutions',
                'realisation' => 'privacy:metadata:gestionprojet_rapport:realisation',
                'difficultes' => 'privacy:metadata:gestionprojet_rapport:difficultes',
                'bilan' => 'privacy:metadata:gestionprojet_rapport:bilan',
                'grade' => 'privacy:metadata:grade',
                'feedback' => 'privacy:metadata:feedback',
                'status' => 'privacy:metadata:gestionprojet_rapport:status',
                'timesubmitted' => 'privacy:metadata:gestionprojet_rapport:timesubmitted',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_rapport'
        );

        // Student submission tables - Step 7: Student Needs Expression.
        $collection->add_database_table(
            'gestionprojet_besoin_eleve',
            [
                'userid' => 'privacy:metadata:gestionprojet_besoin_eleve:userid',
                'groupid' => 'privacy:metadata:groupid',
                'aqui' => 'privacy:metadata:gestionprojet_besoin_eleve:aqui',
                'surquoi' => 'privacy:metadata:gestionprojet_besoin_eleve:surquoi',
                'dansquelbut' => 'privacy:metadata:gestionprojet_besoin_eleve:dansquelbut',
                'grade' => 'privacy:metadata:grade',
                'feedback' => 'privacy:metadata:feedback',
                'status' => 'privacy:metadata:gestionprojet_besoin_eleve:status',
                'timesubmitted' => 'privacy:metadata:gestionprojet_besoin_eleve:timesubmitted',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_besoin_eleve'
        );

        // Student submission tables - Step 8: Logbook.
        $collection->add_database_table(
            'gestionprojet_carnet',
            [
                'userid' => 'privacy:metadata:gestionprojet_carnet:userid',
                'groupid' => 'privacy:metadata:groupid',
                'tasks_data' => 'privacy:metadata:gestionprojet_carnet:tasks_data',
                'grade' => 'privacy:metadata:grade',
                'feedback' => 'privacy:metadata:feedback',
                'status' => 'privacy:metadata:gestionprojet_carnet:status',
                'timesubmitted' => 'privacy:metadata:gestionprojet_carnet:timesubmitted',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_carnet'
        );

        // Student submission tables - Step 9: FAST diagram.
        $collection->add_database_table(
            'gestionprojet_fast',
            [
                'userid' => 'privacy:metadata:gestionprojet_fast:userid',
                'groupid' => 'privacy:metadata:groupid',
                'data_json' => 'privacy:metadata:gestionprojet_fast:data_json',
                'grade' => 'privacy:metadata:grade',
                'feedback' => 'privacy:metadata:feedback',
                'status' => 'privacy:metadata:gestionprojet_fast:status',
                'timesubmitted' => 'privacy:metadata:gestionprojet_fast:timesubmitted',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_fast'
        );

        // AI Evaluations table.
        $collection->add_database_table(
            'gestionprojet_ai_evaluations',
            [
                'userid' => 'privacy:metadata:gestionprojet_ai_evaluations:userid',
                'groupid' => 'privacy:metadata:groupid',
                'step' => 'privacy:metadata:gestionprojet_ai_evaluations:step',
                'provider' => 'privacy:metadata:gestionprojet_ai_evaluations:provider',
                'parsed_grade' => 'privacy:metadata:gestionprojet_ai_evaluations:parsed_grade',
                'parsed_feedback' => 'privacy:metadata:gestionprojet_ai_evaluations:parsed_feedback',
                'status' => 'privacy:metadata:gestionprojet_ai_evaluations:status',
                'applied_by' => 'privacy:metadata:gestionprojet_ai_evaluations:applied_by',
                'applied_at' => 'privacy:metadata:gestionprojet_ai_evaluations:applied_at',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'privacy:metadata:timemodified',
            ],
            'privacy:metadata:gestionprojet_ai_evaluations'
        );

        // Levels (F, S, TS) validated in step 4 (v2.11.2).
        $collection->add_database_table(
            'gestionprojet_palier',
            [
                'userid' => 'privacy:metadata:gestionprojet_ai_evaluations:userid',
                'groupid' => 'privacy:metadata:groupid',
                'palier' => 'privacy:metadata:palier',
                'membres' => 'privacy:metadata:palier',
                'timecreated' => 'privacy:metadata:timecreated',
            ],
            'privacy:metadata:palier'
        );

        // History/Audit trail table.
        $collection->add_database_table(
            'gestionprojet_history',
            [
                'userid' => 'privacy:metadata:gestionprojet_history:userid',
                'groupid' => 'privacy:metadata:groupid',
                'tablename' => 'privacy:metadata:gestionprojet_history:tablename',
                'fieldname' => 'privacy:metadata:gestionprojet_history:fieldname',
                'oldvalue' => 'privacy:metadata:gestionprojet_history:oldvalue',
                'newvalue' => 'privacy:metadata:gestionprojet_history:newvalue',
                'timecreated' => 'privacy:metadata:gestionprojet_history:timecreated',
            ],
            'privacy:metadata:gestionprojet_history'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        // Submission tables.
        $submissiontables = [
            'gestionprojet_cdcf',
            'gestionprojet_essai',
            'gestionprojet_rapport',
            'gestionprojet_besoin_eleve',
            'gestionprojet_carnet',
            'gestionprojet_fast',
            'gestionprojet_palier',
        ];

        foreach ($submissiontables as $table) {
            $sql = "SELECT c.id
                      FROM {context} c
                      JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {gestionprojet} gp ON gp.id = cm.instance
                      JOIN {{$table}} t ON t.gestionprojetid = gp.id
                     WHERE t.userid = :userid
                        OR (t.userid = 0 AND EXISTS (
                            SELECT 1 FROM {groups_members} gm WHERE gm.groupid = t.groupid AND gm.userid = :memberid))";

            // Group work is stored with userid 0: it belongs to each member of the group.
            $params = [
                'contextlevel' => CONTEXT_MODULE,
                'modname' => 'gestionprojet',
                'userid' => $userid,
                'memberid' => $userid,
            ];

            $contextlist->add_from_sql($sql, $params);
        }

        // AI evaluations.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {gestionprojet} gp ON gp.id = cm.instance
                  JOIN {gestionprojet_ai_evaluations} ae ON ae.gestionprojetid = gp.id
                 WHERE ae.userid = :userid OR ae.applied_by = :appliedby";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'gestionprojet',
            'userid' => $userid,
            'appliedby' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        // Levels whose members (at validation time) include the user, even after they left the group.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {gestionprojet} gp ON gp.id = cm.instance
                  JOIN {gestionprojet_palier} p ON p.gestionprojetid = gp.id
                 WHERE " . $DB->sql_like('p.membres', ':pattern');
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'gestionprojet',
            'pattern' => '%[' . $userid . ',%',
        ]);

        // History table.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {gestionprojet} gp ON gp.id = cm.instance
                  JOIN {gestionprojet_history} h ON h.gestionprojetid = gp.id
                 WHERE h.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'gestionprojet',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
            'modname' => 'gestionprojet',
        ];

        // Submission tables.
        $submissiontables = [
            'gestionprojet_cdcf',
            'gestionprojet_essai',
            'gestionprojet_rapport',
            'gestionprojet_besoin_eleve',
            'gestionprojet_carnet',
            'gestionprojet_fast',
            'gestionprojet_palier',
        ];

        foreach ($submissiontables as $table) {
            $sql = "SELECT t.userid
                      FROM {{$table}} t
                      JOIN {gestionprojet} gp ON gp.id = t.gestionprojetid
                      JOIN {course_modules} cm ON cm.instance = gp.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                     WHERE cm.id = :instanceid AND t.userid > 0";

            $userlist->add_from_sql('userid', $sql, $params);

            // Members of the groups whose work is stored with userid 0.
            $sql = "SELECT gm.userid
                      FROM {{$table}} t
                      JOIN {gestionprojet} gp ON gp.id = t.gestionprojetid
                      JOIN {course_modules} cm ON cm.instance = gp.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {groups_members} gm ON gm.groupid = t.groupid
                     WHERE cm.id = :instanceid AND t.userid = 0";

            $userlist->add_from_sql('userid', $sql, $params);
        }

        // AI evaluations - users and teachers who applied grades.
        $sql = "SELECT ae.userid
                  FROM {gestionprojet_ai_evaluations} ae
                  JOIN {gestionprojet} gp ON gp.id = ae.gestionprojetid
                  JOIN {course_modules} cm ON cm.instance = gp.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :instanceid AND ae.userid > 0";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT ae.applied_by
                  FROM {gestionprojet_ai_evaluations} ae
                  JOIN {gestionprojet} gp ON gp.id = ae.gestionprojetid
                  JOIN {course_modules} cm ON cm.instance = gp.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :instanceid AND ae.applied_by IS NOT NULL";

        $userlist->add_from_sql('applied_by', $sql, $params);

        // Members of the validated levels (at validation time).
        $cm = get_coursemodule_from_id('gestionprojet', $context->instanceid);
        if ($cm) {
            $memberids = [];
            foreach ($DB->get_records('gestionprojet_palier', ['gestionprojetid' => $cm->instance], '', 'id, membres') as $row) {
                foreach ((array)json_decode((string)$row->membres, true) as $member) {
                    if (is_array($member) && (int)($member[0] ?? 0) > 0) {
                        $memberids[(int)$member[0]] = (int)$member[0];
                    }
                }
            }
            $userlist->add_users(array_values($memberids));
        }

        // History table.
        $sql = "SELECT h.userid
                  FROM {gestionprojet_history} h
                  JOIN {gestionprojet} gp ON gp.id = h.gestionprojetid
                  JOIN {course_modules} cm ON cm.instance = gp.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :instanceid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('gestionprojet', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance]);
            if (!$gestionprojet) {
                continue;
            }

            // Export submissions.
            $submissiontables = [
                'gestionprojet_cdcf' => get_string('step4', 'gestionprojet'),
                'gestionprojet_essai' => get_string('step5', 'gestionprojet'),
                'gestionprojet_rapport' => get_string('step6', 'gestionprojet'),
                'gestionprojet_besoin_eleve' => get_string('step7', 'gestionprojet'),
                'gestionprojet_carnet' => get_string('step8', 'gestionprojet'),
                'gestionprojet_fast' => get_string('step9', 'gestionprojet'),
            ];

            // Group work is stored with userid 0: it is exported to each member of the group.
            $membergroups = $DB->get_fieldset_select('groups_members', 'groupid', 'userid = ?', [$user->id]);

            // Levels validated (v2.11.2), by the user alone or by one of their groups.
            $select = 'gestionprojetid = :gpid AND userid = :userid';
            $params = ['gpid' => $gestionprojet->id, 'userid' => $user->id];
            if ($membergroups) {
                [$insql, $inparams] = $DB->get_in_or_equal($membergroups, SQL_PARAMS_NAMED, 'pgrp');
                $select = "gestionprojetid = :gpid AND (userid = :userid OR (userid = 0 AND groupid $insql))";
                $params += $inparams;
            }
            $paliers = $DB->get_records_select('gestionprojet_palier', $select, $params);
            // Also the levels whose members (at validation time) include the user.
            foreach ($DB->get_records('gestionprojet_palier', ['gestionprojetid' => $gestionprojet->id]) as $row) {
                if (!isset($paliers[$row->id]) && \mod_gestionprojet\palier::has_member($row->membres, (int)$user->id)) {
                    $paliers[$row->id] = $row;
                }
            }
            if ($paliers) {
                $data = [];
                foreach ($paliers as $row) {
                    $data[] = [
                        'palier' => $row->palier,
                        'groupwork' => transform::yesno((int)$row->userid === 0),
                        'removed' => transform::yesno((bool)$row->retire),
                        'member' => transform::yesno(\mod_gestionprojet\palier::has_member($row->membres, (int)$user->id)),
                        'timecreated' => transform::datetime($row->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'gestionprojet'), get_string('privacy:metadata:palier', 'gestionprojet')],
                    (object) ['paliers' => $data]
                );
            }

            foreach ($submissiontables as $table => $stepname) {
                $select = 'gestionprojetid = :gpid AND userid = :userid';
                $params = ['gpid' => $gestionprojet->id, 'userid' => $user->id];
                if ($membergroups) {
                    [$insql, $inparams] = $DB->get_in_or_equal($membergroups, SQL_PARAMS_NAMED, 'grp');
                    $select = "gestionprojetid = :gpid AND (userid = :userid OR (userid = 0 AND groupid $insql))";
                    $params += $inparams;
                }
                $submissions = $DB->get_records_select($table, $select, $params);

                if (!empty($submissions)) {
                    $data = [];
                    foreach ($submissions as $submission) {
                        $data[] = [
                            'step' => $stepname,
                            'groupwork' => transform::yesno((int)$submission->userid === 0),
                            'grade' => $submission->grade,
                            'feedback' => $submission->feedback,
                            'status' => $submission->status ? 'submitted' : 'draft',
                            'timesubmitted' => $submission->timesubmitted ?
                                transform::datetime($submission->timesubmitted) : null,
                            'timecreated' => transform::datetime($submission->timecreated),
                            'timemodified' => transform::datetime($submission->timemodified),
                        ];
                    }
                    writer::with_context($context)->export_data(
                        [get_string('pluginname', 'gestionprojet'), $stepname],
                        (object) ['submissions' => $data]
                    );
                }
            }

            // Export AI evaluations.
            $evaluations = $DB->get_records_select(
                'gestionprojet_ai_evaluations',
                'gestionprojetid = :gpid AND (userid = :userid OR applied_by = :appliedby)',
                [
                    'gpid' => $gestionprojet->id,
                    'userid' => $user->id,
                    'appliedby' => $user->id,
                ]
            );

            if (!empty($evaluations)) {
                $data = [];
                foreach ($evaluations as $eval) {
                    $data[] = [
                        'step' => $eval->step,
                        'provider' => $eval->provider,
                        'grade' => $eval->parsed_grade,
                        'feedback' => $eval->parsed_feedback,
                        'status' => $eval->status,
                        'timecreated' => transform::datetime($eval->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'gestionprojet'), get_string('ai_evaluation', 'gestionprojet')],
                    (object) ['evaluations' => $data]
                );
            }

            // Export history.
            $history = $DB->get_records('gestionprojet_history', [
                'gestionprojetid' => $gestionprojet->id,
                'userid' => $user->id,
            ]);

            if (!empty($history)) {
                $data = [];
                foreach ($history as $h) {
                    $data[] = [
                        'table' => $h->tablename,
                        'field' => $h->fieldname,
                        'oldvalue' => $h->oldvalue,
                        'newvalue' => $h->newvalue,
                        'timecreated' => transform::datetime($h->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'gestionprojet'), get_string('view_history', 'gestionprojet')],
                    (object) ['history' => $data]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('gestionprojet', $context->instanceid);
        if (!$cm) {
            return;
        }

        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance]);
        if (!$gestionprojet) {
            return;
        }

        // Delete all submission data.
        $submissiontables = [
            'gestionprojet_cdcf',
            'gestionprojet_essai',
            'gestionprojet_rapport',
            'gestionprojet_besoin_eleve',
            'gestionprojet_carnet',
            'gestionprojet_fast',
            'gestionprojet_palier',
        ];

        foreach ($submissiontables as $table) {
            $DB->delete_records($table, ['gestionprojetid' => $gestionprojet->id]);
        }

        // Delete AI evaluations.
        $DB->delete_records('gestionprojet_ai_evaluations', ['gestionprojetid' => $gestionprojet->id]);

        // Delete history.
        $DB->delete_records('gestionprojet_history', ['gestionprojetid' => $gestionprojet->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * Group work (userid 0) is shared by the other members: it is kept, as mod_assign does for team submissions.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('gestionprojet', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance]);
            if (!$gestionprojet) {
                continue;
            }

            // Delete user submissions.
            $submissiontables = [
                'gestionprojet_cdcf',
                'gestionprojet_essai',
                'gestionprojet_rapport',
                'gestionprojet_besoin_eleve',
                'gestionprojet_carnet',
                'gestionprojet_fast',
                'gestionprojet_palier',
            ];

            foreach ($submissiontables as $table) {
                $DB->delete_records($table, [
                    'gestionprojetid' => $gestionprojet->id,
                    'userid' => $userid,
                ]);
            }

            // The user's name and id leave the members of every level (group rows are kept).
            self::remove_from_palier_members((int)$gestionprojet->id, [(int)$userid]);

            // Delete user AI evaluations.
            $DB->delete_records('gestionprojet_ai_evaluations', [
                'gestionprojetid' => $gestionprojet->id,
                'userid' => $userid,
            ]);

            // Anonymize teacher applied_by references.
            $DB->set_field_select(
                'gestionprojet_ai_evaluations',
                'applied_by',
                0,
                'gestionprojetid = :gpid AND applied_by = :userid',
                ['gpid' => $gestionprojet->id, 'userid' => $userid]
            );

            // Delete history.
            $DB->delete_records('gestionprojet_history', [
                'gestionprojetid' => $gestionprojet->id,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('gestionprojet', $context->instanceid);
        if (!$cm) {
            return;
        }

        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance]);
        if (!$gestionprojet) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete submissions.
        $submissiontables = [
            'gestionprojet_cdcf',
            'gestionprojet_essai',
            'gestionprojet_rapport',
            'gestionprojet_besoin_eleve',
            'gestionprojet_carnet',
            'gestionprojet_fast',
            'gestionprojet_palier',
        ];

        foreach ($submissiontables as $table) {
            $DB->delete_records_select(
                $table,
                "gestionprojetid = :gpid AND userid $usersql",
                array_merge(['gpid' => $gestionprojet->id], $userparams)
            );
        }

        // The users' names and ids leave the members of every level (group rows are kept).
        self::remove_from_palier_members((int)$gestionprojet->id, array_map('intval', $userids));

        // Delete AI evaluations.
        $DB->delete_records_select(
            'gestionprojet_ai_evaluations',
            "gestionprojetid = :gpid AND userid $usersql",
            array_merge(['gpid' => $gestionprojet->id], $userparams)
        );

        // Anonymize applied_by.
        $DB->set_field_select(
            'gestionprojet_ai_evaluations',
            'applied_by',
            0,
            "gestionprojetid = :gpid AND applied_by $usersql",
            array_merge(['gpid' => $gestionprojet->id], $userparams)
        );

        // Delete history.
        $DB->delete_records_select(
            'gestionprojet_history',
            "gestionprojetid = :gpid AND userid $usersql",
            array_merge(['gpid' => $gestionprojet->id], $userparams)
        );
    }

    /**
     * Remove users from the members stored with the levels of an instance (v2.11.2).
     *
     * @param int $gestionprojetid
     * @param int[] $userids
     */
    private static function remove_from_palier_members(int $gestionprojetid, array $userids): void {
        global $DB;

        foreach ($DB->get_records('gestionprojet_palier', ['gestionprojetid' => $gestionprojetid], '', 'id, membres') as $row) {
            $membres = $row->membres;
            foreach ($userids as $userid) {
                if (\mod_gestionprojet\palier::has_member($membres, $userid)) {
                    $membres = \mod_gestionprojet\palier::remove_member($membres, $userid);
                }
            }
            if ($membres !== $row->membres) {
                $DB->set_field('gestionprojet_palier', 'membres', $membres, ['id' => $row->id]);
            }
        }
    }
}

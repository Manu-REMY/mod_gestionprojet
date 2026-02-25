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
 * External function for submitting/unlocking steps.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External function to submit or unlock a step.
 */
class submit_step extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number (4-8)'),
            'action' => new external_value(PARAM_TEXT, 'Action: submit or unlock'),
            'groupid' => new external_value(PARAM_INT, 'Group ID (0 for individual)', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'User ID (for unlock, 0 for group)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Submit or unlock a step.
     *
     * @param int $cmid Course module ID
     * @param int $step Step number
     * @param string $action Action (submit or unlock)
     * @param int $groupid Group ID
     * @param int $userid User ID
     * @return array Result with success status, message, and timestamp
     */
    public static function execute($cmid, $step, $action, $groupid = 0, $userid = 0): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
            'action' => $action,
            'groupid' => $groupid,
            'userid' => $userid,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Ensure lib.php is loaded.
        require_once(__DIR__ . '/../../lib.php');

        // Map step to table name.
        $steptables = [
            4 => 'gestionprojet_cdcf',
            5 => 'gestionprojet_essai',
            6 => 'gestionprojet_rapport',
            7 => 'gestionprojet_besoin_eleve',
            8 => 'gestionprojet_carnet',
        ];

        if (!isset($steptables[$params['step']])) {
            return [
                'success' => false,
                'message' => get_string('invalidstep', 'gestionprojet'),
                'timestamp' => time(),
            ];
        }

        $tablename = $steptables[$params['step']];
        $steptype = str_replace('gestionprojet_', '', $tablename);

        try {
            // Check if submission is enabled.
            if (empty($gestionprojet->enable_submission)) {
                throw new \moodle_exception('submissiondisabled', 'gestionprojet');
            }

            if ($params['action'] === 'submit') {
                // Student submitting their work.
                require_capability('mod/gestionprojet:submit', $context);

                // Get the submission record.
                $record = gestionprojet_get_or_create_submission($gestionprojet, $params['groupid'],
                    $USER->id, $steptype);

                // Check if already submitted.
                if (!empty($record->status) && $record->status == 1) {
                    throw new \moodle_exception('alreadysubmitted', 'gestionprojet');
                }

                // Update status to submitted.
                $record->status = 1;
                $record->timesubmitted = time();
                $record->timemodified = time();

                $DB->update_record($tablename, $record);

                // Log the submission.
                gestionprojet_log_change(
                    $gestionprojet->id,
                    $steptype,
                    $record->id,
                    'status',
                    0,
                    1,
                    $USER->id,
                    $params['groupid']
                );

                return [
                    'success' => true,
                    'message' => get_string('submissionsuccess', 'gestionprojet'),
                    'timestamp' => time(),
                ];

            } else if ($params['action'] === 'unlock') {
                // Teacher unlocking a submission.
                require_capability('mod/gestionprojet:lock', $context);

                // Find the submission to unlock.
                $conditions = ['gestionprojetid' => $gestionprojet->id];
                if ($gestionprojet->group_submission && $params['groupid']) {
                    // Group submission mode: key by groupid, userid=0.
                    $conditions['groupid'] = $params['groupid'];
                    $conditions['userid'] = 0;
                } else if ($params['userid']) {
                    // Individual submission mode: key by userid.
                    $conditions['userid'] = $params['userid'];
                    $conditions['groupid'] = $params['groupid'];
                } else {
                    throw new \moodle_exception('invalidparams');
                }

                $record = $DB->get_record($tablename, $conditions);

                if (!$record) {
                    throw new \moodle_exception('submissionnotfound', 'gestionprojet');
                }

                // Unlock the submission.
                $record->status = 0;
                $record->timemodified = time();

                $DB->update_record($tablename, $record);

                // Log the unlock.
                gestionprojet_log_change(
                    $gestionprojet->id,
                    $steptype,
                    $record->id,
                    'status',
                    1,
                    0,
                    $USER->id,
                    $params['groupid']
                );

                return [
                    'success' => true,
                    'message' => get_string('submissionunlocked', 'gestionprojet'),
                    'timestamp' => time(),
                ];

            } else {
                throw new \moodle_exception('invalidaction');
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Status or error message'),
            'timestamp' => new external_value(PARAM_INT, 'Server timestamp'),
        ]);
    }
}

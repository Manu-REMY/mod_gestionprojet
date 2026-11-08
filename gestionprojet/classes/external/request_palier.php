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
 * External function: ask for the validation of a step 4 level (v2.11.2).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Ask for the validation of a level: the copy is frozen and an AI evaluation of that level is queued.
 */
class request_palier extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'groupid' => new external_value(PARAM_INT, 'Group ID (0 for individual work)', VALUE_DEFAULT, 0),
            'palier' => new external_value(PARAM_ALPHA, 'Level: F, S or TS'),
        ]);
    }

    /**
     * Ask for a level.
     *
     * @param int $cmid
     * @param int $groupid
     * @param string $palier
     * @return array
     */
    public static function execute(int $cmid, int $groupid, string $palier): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            ['cmid' => $cmid, 'groupid' => $groupid, 'palier' => $palier]);
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/gestionprojet:submit', $context);

        require_once(__DIR__ . '/../../lib.php');
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);
        // Never trust the group id sent by the browser.
        $groupid = gestionprojet_require_group_access($cm, $params['groupid'], (int)$USER->id, $context);

        $evaluationid = gestionprojet_palier_request($gestionprojet, $cm, $groupid, (int)$USER->id,
            strtoupper($params['palier']));
        return ['evaluationid' => $evaluationid];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'evaluationid' => new external_value(PARAM_INT, 'Queued AI evaluation of the level'),
        ]);
    }
}

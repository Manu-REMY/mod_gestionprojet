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
 * External function for testing AI API connection.
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
use mod_gestionprojet\ai_config;

/**
 * Test AI API connection with a given provider and optional API key.
 */
class test_api_connection extends external_api {

    /**
     * Describes the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID (0 for new activity)'),
            'provider' => new external_value(PARAM_ALPHA, 'AI provider name'),
            'apikey' => new external_value(PARAM_RAW, 'API key (empty for built-in providers)', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Course ID, required for a new activity (cmid 0)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Test the AI API connection.
     *
     * @param int $cmid Course module ID.
     * @param string $provider AI provider name.
     * @param string $apikey API key.
     * @param int $courseid Course ID, for a new activity.
     * @return array Result with success status and message.
     */
    public static function execute(int $cmid, string $provider, string $apikey = '', int $courseid = 0): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'provider' => $provider,
            'apikey' => $apikey,
            'courseid' => $courseid,
        ]);
        $cmid = $params['cmid'];
        $provider = $params['provider'];
        $apikey = $params['apikey'];

        // For providers with built-in keys, use the built-in key.
        if (ai_config::has_builtin_key($provider)) {
            $apikey = ai_config::get_builtin_api_key($provider);
        }

        // Set up context.
        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

            require_login($course, false, $cm);
            $context = \context_module::instance($cm->id);
            require_capability('mod/gestionprojet:configureteacherpages', $context);

            ai_config::log_access($gestionprojet->id, $USER->id, 'test_connection');
        } else {
            // New activity: only someone who may add one in this course can spend the provider quota.
            $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
            require_login($course, false);
            $context = \context_course::instance($course->id);
            self::validate_context($context);
            require_capability('mod/gestionprojet:addinstance', $context);
        }

        // Test the connection.
        $result = ai_config::test_connection($provider, $apikey);

        return [
            'success' => !empty($result['success']),
            'message' => $result['message'] ?? '',
        ];
    }

    /**
     * Describes the return value for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the connection test succeeded'),
            'message' => new external_value(PARAM_RAW, 'Result message'),
        ]);
    }
}

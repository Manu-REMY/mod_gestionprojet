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
 * External function for generating AI summaries.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

/**
 * External function to generate AI summary for a step.
 */
class generate_ai_summary extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number (4-8)'),
            'force' => new external_value(PARAM_BOOL, 'Force regeneration', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Generate AI summary for a step.
     *
     * @param int $cmid Course module ID
     * @param int $step Step number
     * @param bool $force Force regeneration
     * @return array Result with success status and message
     */
    public static function execute($cmid, $step, $force) {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
            'force' => $force,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check capability.
        require_capability('mod/gestionprojet:grade', $context);

        // Validate step range.
        if ($params['step'] < 4 || $params['step'] > 8) {
            return [
                'success' => false,
                'message' => get_string('error:invalidstep', 'gestionprojet'),
            ];
        }

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Check if AI is enabled.
        if (empty($gestionprojet->ai_enabled)) {
            return [
                'success' => false,
                'message' => get_string('dashboard:ai_disabled', 'gestionprojet'),
            ];
        }

        // Load and call the generator.
        require_once(__DIR__ . '/../dashboard/ai_summary_generator.php');

        try {
            $result = \mod_gestionprojet\dashboard\ai_summary_generator::generate_summary(
                $gestionprojet,
                $params['step'],
                $params['force']
            );

            return [
                'success' => $result->success ?? false,
                'message' => $result->message ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Status or error message'),
        ]);
    }
}

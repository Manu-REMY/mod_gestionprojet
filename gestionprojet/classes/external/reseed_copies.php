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
 * External function: give the current starting copy to the step 4 copies nobody worked on (v2.11.3).
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
 * Reseed the untouched step 4 copies of an activity.
 */
class reseed_copies extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Reseed.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/gestionprojet:configureteacherpages', $context);

        require_once(__DIR__ . '/../../lib.php');
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);
        return ['count' => gestionprojet_cdcf_reseed_untouched($gestionprojet)];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of copies reseeded'),
        ]);
    }
}

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
 * Adhoc task for AI evaluation of submissions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task to process AI evaluations.
 */
class evaluate_submission extends \core\task\adhoc_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_evaluate_submission', 'gestionprojet');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');
        require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_evaluator.php');

        $data = $this->get_custom_data();

        if (empty($data->evaluationid)) {
            mtrace('  ERROR: No evaluation ID provided');
            return;
        }

        mtrace('  Processing AI evaluation ID: ' . $data->evaluationid);

        try {
            $success = \mod_gestionprojet\ai_evaluator::process_evaluation($data->evaluationid);

            if ($success) {
                mtrace('  SUCCESS: AI evaluation completed');
            } else {
                mtrace('  WARNING: AI evaluation could not be processed');
            }
        } catch (\Exception $e) {
            mtrace('  ERROR: ' . $e->getMessage());
            throw $e; // Re-throw to mark task as failed.
        }
    }
}

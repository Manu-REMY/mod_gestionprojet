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
 * The mod_gestionprojet submission graded event.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_gestionprojet submission graded event class.
 *
 * Fired when a teacher grades a student submission.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_graded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'gestionprojet';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_submission_graded', 'gestionprojet');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' graded step '{$this->other['step']}' " .
            "with grade '{$this->other['grade']}' " .
            "in gestionprojet with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/gestionprojet/grading.php', [
            'id' => $this->contextinstanceid,
            'step' => $this->other['step'] ?? 0,
        ]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['step'])) {
            throw new \coding_exception('The \'step\' value must be set in other.');
        }

        if (!isset($this->other['grade'])) {
            throw new \coding_exception('The \'grade\' value must be set in other.');
        }
    }

    /**
     * Get objectid mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'gestionprojet', 'restore' => 'gestionprojet'];
    }

    /**
     * Get other mapping.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'step' => \core\event\base::NOT_MAPPED,
            'grade' => \core\event\base::NOT_MAPPED,
        ];
    }
}

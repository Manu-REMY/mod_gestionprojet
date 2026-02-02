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
 * Restore task for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/gestionprojet/backup/moodle2/restore_gestionprojet_stepslib.php');

/**
 * Provides all the settings and steps to perform one complete restore of the activity.
 */
class restore_gestionprojet_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_gestionprojet_activity_structure_step('gestionprojet_structure', 'gestionprojet.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('gestionprojet', ['intro'], 'gestionprojet');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array of restore_decode_rule
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('GESTIONPROJETVIEWBYID', '/mod/gestionprojet/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('GESTIONPROJETINDEX', '/mod/gestionprojet/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('gestionprojet', 'add', 'view.php?id={course_module}', '{gestionprojet}');
        $rules[] = new restore_log_rule('gestionprojet', 'update', 'view.php?id={course_module}', '{gestionprojet}');
        $rules[] = new restore_log_rule('gestionprojet', 'view', 'view.php?id={course_module}', '{gestionprojet}');

        return $rules;
    }

    /**
     * Define the restore log rules for course level.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('gestionprojet', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}

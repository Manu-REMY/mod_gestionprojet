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
 * Restore steps for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one gestionprojet activity.
 */
class restore_gestionprojet_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure to be restored.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('gestionprojet', '/activity/gestionprojet');
        $paths[] = new restore_path_element('gestionprojet_description', '/activity/gestionprojet/description');
        $paths[] = new restore_path_element('gestionprojet_besoin', '/activity/gestionprojet/besoin');
        $paths[] = new restore_path_element('gestionprojet_planning', '/activity/gestionprojet/planning');
        $paths[] = new restore_path_element('gestionprojet_cdcf_teacher', '/activity/gestionprojet/cdcf_teacher');
        $paths[] = new restore_path_element('gestionprojet_essai_teacher', '/activity/gestionprojet/essai_teacher');
        $paths[] = new restore_path_element('gestionprojet_rapport_teacher', '/activity/gestionprojet/rapport_teacher');
        $paths[] = new restore_path_element('gestionprojet_besoin_eleve_teacher', '/activity/gestionprojet/besoin_eleve_teacher');
        $paths[] = new restore_path_element('gestionprojet_carnet_teacher', '/activity/gestionprojet/carnet_teacher');

        if ($userinfo) {
            $paths[] = new restore_path_element('gestionprojet_cdcf', '/activity/gestionprojet/cdcfs/cdcf');
            $paths[] = new restore_path_element('gestionprojet_essai', '/activity/gestionprojet/essais/essai');
            $paths[] = new restore_path_element('gestionprojet_rapport', '/activity/gestionprojet/rapports/rapport');
            $paths[] = new restore_path_element('gestionprojet_besoin_eleve', '/activity/gestionprojet/besoin_eleves/besoin_eleve');
            $paths[] = new restore_path_element('gestionprojet_carnet', '/activity/gestionprojet/carnets/carnet');
            $paths[] = new restore_path_element('gestionprojet_ai_evaluation', '/activity/gestionprojet/ai_evaluations/ai_evaluation');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the gestionprojet element.
     *
     * @param array $data
     */
    protected function process_gestionprojet($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('gestionprojet', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the description element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_description($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_description', $data);
    }

    /**
     * Process the besoin element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_besoin($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_besoin', $data);
    }

    /**
     * Process the planning element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_planning($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->startdate = $this->apply_date_offset($data->startdate);
        $data->enddate = $this->apply_date_offset($data->enddate);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_planning', $data);
    }

    /**
     * Process the cdcf_teacher element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_cdcf_teacher($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->submission_date = $this->apply_date_offset($data->submission_date);
        $data->deadline_date = $this->apply_date_offset($data->deadline_date);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_cdcf_teacher', $data);
    }

    /**
     * Process the essai_teacher element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_essai_teacher($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->submission_date = $this->apply_date_offset($data->submission_date);
        $data->deadline_date = $this->apply_date_offset($data->deadline_date);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_essai_teacher', $data);
    }

    /**
     * Process the rapport_teacher element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_rapport_teacher($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->submission_date = $this->apply_date_offset($data->submission_date);
        $data->deadline_date = $this->apply_date_offset($data->deadline_date);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_rapport_teacher', $data);
    }

    /**
     * Process the besoin_eleve_teacher element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_besoin_eleve_teacher($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->submission_date = $this->apply_date_offset($data->submission_date);
        $data->deadline_date = $this->apply_date_offset($data->deadline_date);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_besoin_eleve_teacher', $data);
    }

    /**
     * Process the carnet_teacher element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_carnet_teacher($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->submission_date = $this->apply_date_offset($data->submission_date);
        $data->deadline_date = $this->apply_date_offset($data->deadline_date);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_carnet_teacher', $data);
    }

    /**
     * Process the cdcf element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_cdcf($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_cdcf', $data);
    }

    /**
     * Process the essai element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_essai($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_essai', $data);
    }

    /**
     * Process the rapport element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_rapport($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_rapport', $data);
    }

    /**
     * Process the besoin_eleve element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_besoin_eleve($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_besoin_eleve', $data);
    }

    /**
     * Process the carnet element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_carnet($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_carnet', $data);
    }

    /**
     * Process the ai_evaluation element.
     *
     * @param array $data
     */
    protected function process_gestionprojet_ai_evaluation($data) {
        global $DB;

        $data = (object)$data;
        $data->gestionprojetid = $this->get_new_parentid('gestionprojet');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->applied_by = $this->get_mappingid('user', $data->applied_by);
        $data->applied_at = $this->apply_date_offset($data->applied_at);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('gestionprojet_ai_evaluations', $data);
    }

    /**
     * Post-execution actions.
     */
    protected function after_execute() {
        $this->add_related_files('mod_gestionprojet', 'intro', null);
    }
}

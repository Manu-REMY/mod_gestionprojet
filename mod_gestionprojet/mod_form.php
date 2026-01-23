<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Module instance settings form.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 */
class mod_gestionprojet_mod_form extends moodleform_mod
{

    /**
     * Defines forms elements
     */
    public function definition()
    {
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('modulename', 'gestionprojet'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        // Adding autosave interval setting.
        $mform->addElement('header', 'autosavesettings', get_string('autosave_interval', 'gestionprojet'));

        $options = [
            10 => '10 ' . get_string('seconds'),
            30 => '30 ' . get_string('seconds'),
            60 => '60 ' . get_string('seconds'),
            120 => '120 ' . get_string('seconds'),
        ];
        $mform->addElement(
            'select',
            'autosave_interval',
            get_string('autosave_interval', 'gestionprojet'),
            $options
        );
        $mform->setDefault('autosave_interval', 30);
        $mform->setDefault('autosave_interval', 30);
        $mform->addHelpButton('autosave_interval', 'autosave_interval', 'gestionprojet');

        // Submission settings
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'gestionprojet'));

        $mform->addElement('selectyesno', 'group_submission', get_string('groupsubmission', 'gestionprojet'));
        $mform->setDefault('group_submission', 1);
        $mform->addHelpButton('group_submission', 'groupsubmission', 'gestionprojet');

        $mform->addElement('selectyesno', 'enable_submission', get_string('enable_submission', 'gestionprojet'));
        $mform->setDefault('enable_submission', 1);
        $mform->addHelpButton('enable_submission', 'enable_submission', 'gestionprojet');

        // Active steps settings
        $mform->addElement('header', 'activesteps', get_string('activesteps', 'gestionprojet'));

        for ($i = 1; $i <= 7; $i++) {
            $mform->addElement('advcheckbox', 'enable_step' . $i, get_string('step' . $i, 'gestionprojet'));
            $default = ($i == 7) ? 0 : 1;
            $mform->setDefault('enable_step' . $i, $default);
            // $mform->addHelpButton('enable_step' . $i, 'enable_step' . $i, 'gestionprojet'); // Optional: Add help strings if needed
        }

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Enforce validation rules.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors
     */
    public function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}

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
        global $CFG, $PAGE;

        $mform = $this->_form;

        // Load test API JavaScript module.
        $cmid = $this->_cm ? $this->_cm->id : 0;
        $PAGE->requires->js_call_amd('mod_gestionprojet/test_api', 'init', [$cmid]);

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

        $steps_order = [1, 3, 2, 7, 4, 5, 8, 6];
        foreach ($steps_order as $i) {
            $mform->addElement('advcheckbox', 'enable_step' . $i, get_string('step' . $i, 'gestionprojet'));
            $default = ($i == 7 || $i == 8) ? 0 : 1;
            $mform->setDefault('enable_step' . $i, $default);
        }

        // AI Evaluation settings.
        $mform->addElement('header', 'ai_settings', get_string('ai_settings', 'gestionprojet'));
        $mform->setExpanded('ai_settings', false);

        $mform->addElement('selectyesno', 'ai_enabled', get_string('ai_enabled', 'gestionprojet'));
        $mform->setDefault('ai_enabled', 0);
        $mform->addHelpButton('ai_enabled', 'ai_enabled', 'gestionprojet');

        $providers = [
            '' => get_string('ai_provider_select', 'gestionprojet'),
            'openai' => 'OpenAI (GPT-4)',
            'anthropic' => 'Anthropic (Claude)',
            'mistral' => 'Mistral AI',
        ];
        $mform->addElement('select', 'ai_provider', get_string('ai_provider', 'gestionprojet'), $providers);
        $mform->setDefault('ai_provider', '');
        $mform->addHelpButton('ai_provider', 'ai_provider', 'gestionprojet');
        $mform->hideIf('ai_provider', 'ai_enabled', 'eq', 0);

        $mform->addElement('passwordunmask', 'ai_api_key', get_string('ai_api_key', 'gestionprojet'));
        $mform->setType('ai_api_key', PARAM_RAW);
        $mform->addHelpButton('ai_api_key', 'ai_api_key', 'gestionprojet');
        $mform->hideIf('ai_api_key', 'ai_enabled', 'eq', 0);

        // Test API button (will be handled by JavaScript).
        $mform->addElement(
            'button',
            'test_api_btn',
            get_string('ai_test_connection', 'gestionprojet'),
            ['id' => 'id_test_api_btn']
        );
        $mform->hideIf('test_api_btn', 'ai_enabled', 'eq', 0);

        // Auto-apply AI grades option.
        $mform->addElement('selectyesno', 'ai_auto_apply', get_string('ai_auto_apply', 'gestionprojet'));
        $mform->setDefault('ai_auto_apply', 0);
        $mform->addHelpButton('ai_auto_apply', 'ai_auto_apply', 'gestionprojet');
        $mform->hideIf('ai_auto_apply', 'ai_enabled', 'eq', 0);

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

        // Validate AI settings.
        if (!empty($data['ai_enabled'])) {
            if (empty($data['ai_provider'])) {
                $errors['ai_provider'] = get_string('ai_provider_required', 'gestionprojet');
            }
            if (empty($data['ai_api_key'])) {
                $errors['ai_api_key'] = get_string('ai_api_key_required', 'gestionprojet');
            }
        }

        return $errors;
    }
}

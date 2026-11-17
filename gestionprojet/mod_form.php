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
        $PAGE->requires->js_call_amd('mod_gestionprojet/test_api', 'init', [$cmid, (int)$this->current->course]);

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
        $mform->addHelpButton('autosave_interval', 'autosave_interval', 'gestionprojet');

        // Submission settings
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'gestionprojet'));

        $mform->addElement('selectyesno', 'group_submission', get_string('groupsubmission', 'gestionprojet'));
        $mform->setDefault('group_submission', 1);
        $mform->addHelpButton('group_submission', 'groupsubmission', 'gestionprojet');

        $mform->addElement('selectyesno', 'enable_submission', get_string('enable_submission', 'gestionprojet'));
        $mform->setDefault('enable_submission', 1);
        $mform->addHelpButton('enable_submission', 'enable_submission', 'gestionprojet');

        // CDCF (step 4) settings.
        $mform->addElement('header', 'step4settings', get_string('step4settings', 'gestionprojet'));
        $mform->addElement('selectyesno', 'step4_flexibility', get_string('step4_flexibility', 'gestionprojet'));
        $mform->setDefault('step4_flexibility', 1);
        $mform->addHelpButton('step4_flexibility', 'step4_flexibility', 'gestionprojet');

        // Level validation F / S / TS (v2.11.2): one button per level instead of a grade out of 20.
        $mform->addElement('selectyesno', 'step4_paliers', get_string('step4_paliers', 'gestionprojet'));
        $mform->setDefault('step4_paliers', 0);
        $mform->addHelpButton('step4_paliers', 'step4_paliers', 'gestionprojet');
        $mform->addElement('text', 'step4_palier_seuil', get_string('step4_palier_seuil', 'gestionprojet'), ['size' => 4]);
        $mform->setType('step4_palier_seuil', PARAM_INT);
        $mform->setDefault('step4_palier_seuil', 80);
        $mform->addHelpButton('step4_palier_seuil', 'step4_palier_seuil', 'gestionprojet');

        // Spelling graded at step 4 (request 32, D-024): on by default, 2 points out of 20 at least.
        $mform->addElement('selectyesno', 'langue_enabled', get_string('langue_enabled', 'gestionprojet'));
        $mform->setDefault('langue_enabled', 1);
        $mform->addHelpButton('langue_enabled', 'langue_enabled', 'gestionprojet');
        $mform->addElement('text', 'langue_points', get_string('langue_points', 'gestionprojet'), ['size' => 4]);
        $mform->setType('langue_points', PARAM_FLOAT);
        $mform->setDefault('langue_points', 2);
        $mform->addHelpButton('langue_points', 'langue_points', 'gestionprojet');
        $mform->hideIf('langue_points', 'langue_enabled', 'eq', 0);
        $mform->addElement('textarea', 'langue_accepted', get_string('langue_accepted', 'gestionprojet'),
            ['rows' => 4, 'cols' => 40]);
        $mform->setType('langue_accepted', PARAM_TEXT);
        $mform->addHelpButton('langue_accepted', 'langue_accepted', 'gestionprojet');
        $mform->hideIf('langue_accepted', 'langue_enabled', 'eq', 0);

        // FS per copy (request 42, D-088): every FS, N FS drawn at random, or one predefined subject drawn.
        $mform->addElement('select', 'step4_fs_mode', get_string('step4_fs_mode', 'gestionprojet'), [
            0 => get_string('step4_fs_mode_all', 'gestionprojet'),
            1 => get_string('step4_fs_mode_random', 'gestionprojet'),
            2 => get_string('step4_fs_mode_sujets', 'gestionprojet'),
        ]);
        $mform->setDefault('step4_fs_mode', 0);
        $mform->addHelpButton('step4_fs_mode', 'step4_fs_mode', 'gestionprojet');
        $mform->addElement('text', 'step4_fs_tirage', get_string('step4_fs_tirage', 'gestionprojet'), ['size' => 4]);
        $mform->setType('step4_fs_tirage', PARAM_INT);
        $mform->setDefault('step4_fs_tirage', 3);
        $mform->hideIf('step4_fs_tirage', 'step4_fs_mode', 'neq', 1);
        $mform->addElement('textarea', 'step4_fs_sujets', get_string('step4_fs_sujets', 'gestionprojet'),
            ['rows' => 4, 'cols' => 40]);
        $mform->setType('step4_fs_sujets', PARAM_TEXT);
        $mform->addHelpButton('step4_fs_sujets', 'step4_fs_sujets', 'gestionprojet');
        $mform->hideIf('step4_fs_sujets', 'step4_fs_mode', 'neq', 2);

        // AI Evaluation settings.
        $mform->addElement('header', 'ai_settings', get_string('ai_settings', 'gestionprojet'));
        $mform->setExpanded('ai_settings', false);

        $mform->addElement('selectyesno', 'ai_enabled', get_string('ai_enabled', 'gestionprojet'));
        $mform->setDefault('ai_enabled', 0);
        $mform->addHelpButton('ai_enabled', 'ai_enabled', 'gestionprojet');

        $providers = [
            '' => get_string('ai_provider_select', 'gestionprojet'),
            'albert' => 'Albert (Etalab) - ' . get_string('ai_provider_builtin', 'gestionprojet'),
            'openai' => 'OpenAI (GPT-4)',
            'anthropic' => 'Anthropic (Claude)',
            'mistral' => 'Mistral AI',
        ];
        $mform->addElement('select', 'ai_provider', get_string('ai_provider', 'gestionprojet'), $providers);
        $mform->setDefault('ai_provider', '');
        $mform->addHelpButton('ai_provider', 'ai_provider', 'gestionprojet');
        $mform->hideIf('ai_provider', 'ai_enabled', 'eq', 0);

        // Notice for Albert (built-in key).
        $mform->addElement('static', 'ai_api_key_notice', '', get_string('ai_api_key_builtin_notice', 'gestionprojet'));
        $mform->hideIf('ai_api_key_notice', 'ai_enabled', 'eq', 0);
        $mform->hideIf('ai_api_key_notice', 'ai_provider', 'neq', 'albert');

        // API key field (hidden for providers with built-in keys).
        $mform->addElement('passwordunmask', 'ai_api_key', get_string('ai_api_key', 'gestionprojet'));
        $mform->setType('ai_api_key', PARAM_RAW);
        $mform->addHelpButton('ai_api_key', 'ai_api_key', 'gestionprojet');
        $mform->hideIf('ai_api_key', 'ai_enabled', 'eq', 0);
        $mform->hideIf('ai_api_key', 'ai_provider', 'eq', 'albert');

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

        $mform->addElement('selectyesno', 'ai_hide_answers', get_string('ai_hide_answers', 'gestionprojet'));
        $mform->setDefault('ai_hide_answers', 1);
        $mform->addHelpButton('ai_hide_answers', 'ai_hide_answers', 'gestionprojet');

        // Formative feedback before submitting (v2.11.4).
        $mform->addElement('text', 'formative_limit', get_string('formative_limit', 'gestionprojet'), ['size' => 3]);
        $mform->setType('formative_limit', PARAM_INT);
        $mform->setDefault('formative_limit', 3);
        $mform->addHelpButton('formative_limit', 'formative_limit', 'gestionprojet');
        $mform->hideIf('ai_hide_answers', 'ai_enabled', 'eq', 0);

        // Gradebook mode settings.
        $mform->addElement('header', 'gradebook_settings', get_string('gradebook_settings', 'gestionprojet'));

        $grademodes = [
            0 => get_string('grade_mode_combined', 'gestionprojet'),
            1 => get_string('grade_mode_per_step', 'gestionprojet'),
        ];
        $mform->addElement('select', 'grade_mode', get_string('grade_mode', 'gestionprojet'), $grademodes);
        $mform->setDefault('grade_mode', 0);
        $mform->addHelpButton('grade_mode', 'grade_mode', 'gestionprojet');

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
            // API key is only required for providers without built-in keys.
            if (empty($data['ai_api_key']) && !empty($data['ai_provider'])) {
                if (\mod_gestionprojet\ai_config::requires_user_api_key($data['ai_provider'])) {
                    $errors['ai_api_key'] = get_string('ai_api_key_required', 'gestionprojet');
                }
            }
        }

        if (isset($data['formative_limit']) && ((int)$data['formative_limit'] < 0 || (int)$data['formative_limit'] > 20)) {
            $errors['formative_limit'] = get_string('error_formative_limit', 'gestionprojet');
        }
        $languepoints = $data['langue_points'] ?? null;
        if (!empty($data['langue_enabled'])
                && (!is_numeric($languepoints) || (float)$languepoints < 2 || (float)$languepoints > 10)) {
            $errors['langue_points'] = get_string('error_langue_points', 'gestionprojet');
        }
        $fsmode = (int)($data['step4_fs_mode'] ?? 0);
        if ($fsmode === 1 && ((int)($data['step4_fs_tirage'] ?? 0) < 1 || (int)$data['step4_fs_tirage'] > 20)) {
            $errors['step4_fs_tirage'] = get_string('error_fs_tirage', 'gestionprojet');
        }
        if ($fsmode === 2) {
            $consigne = gestionprojet_fs_consigne_of((int)($data['instance'] ?? 0));
            $parsed = \mod_gestionprojet\fs_tirage::parse_sujets((string)($data['step4_fs_sujets'] ?? ''), $consigne);
            if ($parsed['erreurs']) {
                $errors['step4_fs_sujets'] = implode(' ', array_map(function (array $e): string {
                    return get_string('error_fs_sujet', 'gestionprojet', (object)$e);
                }, $parsed['erreurs']));
            } else if (!$parsed['sujets']) {
                $errors['step4_fs_sujets'] = get_string('error_fs_sujets_empty', 'gestionprojet');
            }
        }
        if (isset($data['step4_palier_seuil'])
                && ((int)$data['step4_palier_seuil'] < 0 || (int)$data['step4_palier_seuil'] > 100)) {
            $errors['step4_palier_seuil'] = get_string('error_palier_seuil', 'gestionprojet');
        }

        return $errors;
    }

    /**
     * Accepted words (stored as JSON) shown one per line; predefined subjects shown as FS numbers.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (isset($defaultvalues['langue_accepted'])) {
            $words = json_decode((string)$defaultvalues['langue_accepted'], true);
            $defaultvalues['langue_accepted'] = is_array($words) ? implode("\n", $words) : '';
        }
        // Subjects stored as FS ids, shown as FS numbers of the consigne (request 42).
        if (isset($defaultvalues['step4_fs_sujets'])) {
            $defaultvalues['step4_fs_sujets'] = \mod_gestionprojet\fs_tirage::format_sujets(
                \mod_gestionprojet\fs_tirage::decode_sujets($defaultvalues['step4_fs_sujets']),
                gestionprojet_fs_consigne_of((int)($defaultvalues['instance'] ?? 0)));
        }
    }
}

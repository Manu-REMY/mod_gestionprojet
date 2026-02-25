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
 * External function for auto-saving form data.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External function to auto-save form data for any step.
 */
class autosave extends external_api {

    /**
     * Describe the parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'step' => new external_value(PARAM_INT, 'Step number (1-8)'),
            'data' => new external_value(PARAM_RAW, 'JSON-encoded form data'),
            'groupid' => new external_value(PARAM_INT, 'Group ID (0 for individual)', VALUE_DEFAULT, 0),
            'mode' => new external_value(PARAM_TEXT, 'Mode: empty for student, "teacher" for correction models', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Auto-save form data for a step.
     *
     * @param int $cmid Course module ID
     * @param int $step Step number
     * @param string $data JSON-encoded form data
     * @param int $groupid Group ID
     * @param string $mode Mode (empty or 'teacher')
     * @return array Result with success status, message, and timestamp
     */
    public static function execute($cmid, $step, $data, $groupid = 0, $mode = ''): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'step' => $step,
            'data' => $data,
            'groupid' => $groupid,
            'mode' => $mode,
        ]);

        // Get course module and validate context.
        $cm = get_coursemodule_from_id('gestionprojet', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        // Get instance.
        $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

        // Ensure lib.php is loaded.
        require_once(__DIR__ . '/../../lib.php');

        // Decode JSON data.
        $formdata = json_decode($params['data'], true);
        if (!$formdata) {
            return [
                'success' => false,
                'message' => 'Invalid JSON data',
                'timestamp' => time(),
            ];
        }

        $time = time();

        try {
            // Handle teacher correction model mode.
            if ($params['mode'] === 'teacher') {
                require_capability('mod/gestionprojet:configureteacherpages', $context);

                // Map step to teacher table.
                $teachertables = [
                    4 => [
                        'table' => 'gestionprojet_cdcf_teacher',
                        'fields' => ['produit', 'milieu', 'fp', 'interacteurs_data', 'ai_instructions',
                                     'submission_date', 'deadline_date'],
                    ],
                    5 => [
                        'table' => 'gestionprojet_essai_teacher',
                        'fields' => ['nom_essai', 'date_essai', 'groupe_eleves', 'fonction_service',
                                     'niveaux_reussite', 'etapes_protocole', 'materiel_outils', 'precautions',
                                     'resultats_obtenus', 'observations_remarques', 'conclusion', 'objectif',
                                     'ai_instructions', 'submission_date', 'deadline_date'],
                    ],
                    6 => [
                        'table' => 'gestionprojet_rapport_teacher',
                        'fields' => ['titre_projet', 'auteurs', 'besoin_projet', 'imperatifs', 'solutions',
                                     'justification', 'realisation', 'difficultes', 'validation',
                                     'ameliorations', 'bilan', 'perspectives', 'besoins', 'ai_instructions',
                                     'submission_date', 'deadline_date'],
                    ],
                    7 => [
                        'table' => 'gestionprojet_besoin_eleve_teacher',
                        'fields' => ['aqui', 'surquoi', 'dansquelbut', 'ai_instructions',
                                     'submission_date', 'deadline_date'],
                    ],
                    8 => [
                        'table' => 'gestionprojet_carnet_teacher',
                        'fields' => ['tasks_data', 'ai_instructions', 'submission_date', 'deadline_date'],
                    ],
                ];

                if (!isset($teachertables[$params['step']])) {
                    return [
                        'success' => false,
                        'message' => get_string('invalidstep', 'gestionprojet'),
                        'timestamp' => $time,
                    ];
                }

                $tableinfo = $teachertables[$params['step']];
                $tablename = $tableinfo['table'];
                $validfields = $tableinfo['fields'];

                $record = $DB->get_record($tablename, ['gestionprojetid' => $gestionprojet->id]);
                if (!$record) {
                    $record = new \stdClass();
                    $record->gestionprojetid = $gestionprojet->id;
                    $record->timecreated = $time;
                }

                foreach ($formdata as $key => $value) {
                    if ($key !== 'id' && in_array($key, $validfields)) {
                        $record->$key = $value;
                    }
                }

                $record->timemodified = $time;

                if (isset($record->id)) {
                    $DB->update_record($tablename, $record);
                } else {
                    $record->id = $DB->insert_record($tablename, $record);
                }

                return [
                    'success' => true,
                    'message' => get_string('autosave_success', 'gestionprojet'),
                    'timestamp' => $time,
                ];
            }

            // Handle student/teacher steps based on step number.
            switch ($params['step']) {
                case 1: // Description (teacher).
                    require_capability('mod/gestionprojet:configureteacherpages', $context);

                    $record = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
                    if (!$record) {
                        $record = new \stdClass();
                        $record->gestionprojetid = $gestionprojet->id;
                        $record->timecreated = $time;
                    }

                    $validfields = ['intitule', 'niveau', 'support', 'duree', 'besoin', 'production',
                                    'outils', 'evaluation', 'competences', 'imageid', 'locked'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;

                            if ($record->id ?? 0) {
                                gestionprojet_log_change($gestionprojet->id, 'description', $record->id,
                                    $key, $oldvalue, $value, $USER->id);
                            }
                        }
                    }

                    $record->timemodified = $time;

                    if (isset($record->id)) {
                        $DB->update_record('gestionprojet_description', $record);
                    } else {
                        $record->id = $DB->insert_record('gestionprojet_description', $record);
                    }
                    break;

                case 2: // Needs Expression (teacher).
                    require_capability('mod/gestionprojet:configureteacherpages', $context);

                    $record = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
                    if (!$record) {
                        $record = new \stdClass();
                        $record->gestionprojetid = $gestionprojet->id;
                        $record->timecreated = $time;
                    }

                    $validfields = ['aqui', 'surquoi', 'dansquelbut', 'locked'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;

                            if ($record->id ?? 0) {
                                gestionprojet_log_change($gestionprojet->id, 'besoin', $record->id,
                                    $key, $oldvalue, $value, $USER->id);
                            }
                        }
                    }

                    $record->timemodified = $time;

                    if (isset($record->id)) {
                        $DB->update_record('gestionprojet_besoin', $record);
                    } else {
                        $record->id = $DB->insert_record('gestionprojet_besoin', $record);
                    }
                    break;

                case 3: // Planning (teacher).
                    require_capability('mod/gestionprojet:configureteacherpages', $context);

                    $record = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);
                    if (!$record) {
                        $record = new \stdClass();
                        $record->gestionprojetid = $gestionprojet->id;
                        $record->timecreated = $time;
                    }

                    $validfields = ['projectname', 'startdate', 'enddate', 'vacationzone',
                                    'task1_hours', 'task2_hours', 'task3_hours', 'task4_hours',
                                    'task5_hours', 'locked'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;

                            if ($record->id ?? 0) {
                                gestionprojet_log_change($gestionprojet->id, 'planning', $record->id,
                                    $key, $oldvalue, $value, $USER->id);
                            }
                        }
                    }

                    $record->timemodified = $time;

                    if (isset($record->id)) {
                        $DB->update_record('gestionprojet_planning', $record);
                    } else {
                        $record->id = $DB->insert_record('gestionprojet_planning', $record);
                    }
                    break;

                case 4: // CDCF (student).
                    require_capability('mod/gestionprojet:submit', $context);

                    $record = gestionprojet_get_or_create_submission($gestionprojet, $params['groupid'],
                        $USER->id, 'cdcf');

                    if ($record->status == 1) {
                        throw new \moodle_exception('submissionlocked', 'gestionprojet');
                    }

                    $validfields = ['produit', 'milieu', 'fp', 'interacteurs_data'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;
                            gestionprojet_log_change($gestionprojet->id, 'cdcf', $record->id,
                                $key, $oldvalue, $value, $USER->id, $params['groupid']);
                        }
                    }

                    $record->timemodified = $time;
                    $DB->update_record('gestionprojet_cdcf', $record);
                    break;

                case 5: // Test Sheet (student).
                    require_capability('mod/gestionprojet:submit', $context);

                    $record = gestionprojet_get_or_create_submission($gestionprojet, $params['groupid'],
                        $USER->id, 'essai');

                    if ($record->status == 1) {
                        throw new \moodle_exception('submissionlocked', 'gestionprojet');
                    }

                    $validfields = ['nom_essai', 'date_essai', 'groupe_eleves', 'fonction_service',
                                    'niveaux_reussite', 'etapes_protocole', 'materiel_outils', 'precautions',
                                    'resultats_obtenus', 'observations_remarques', 'conclusion'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;
                            gestionprojet_log_change($gestionprojet->id, 'essai', $record->id,
                                $key, $oldvalue, $value, $USER->id, $params['groupid']);
                        }
                    }

                    $record->timemodified = $time;
                    $DB->update_record('gestionprojet_essai', $record);
                    break;

                case 6: // Report (student).
                    require_capability('mod/gestionprojet:submit', $context);

                    $record = gestionprojet_get_or_create_submission($gestionprojet, $params['groupid'],
                        $USER->id, 'rapport');

                    if ($record->status == 1) {
                        throw new \moodle_exception('submissionlocked', 'gestionprojet');
                    }

                    $validfields = ['titre_projet', 'auteurs', 'besoin_projet', 'imperatifs', 'solutions',
                                    'justification', 'realisation', 'difficultes', 'validation',
                                    'ameliorations', 'bilan', 'perspectives'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;
                            gestionprojet_log_change($gestionprojet->id, 'rapport', $record->id,
                                $key, $oldvalue, $value, $USER->id, $params['groupid']);
                        }
                    }

                    $record->timemodified = $time;
                    $DB->update_record('gestionprojet_rapport', $record);
                    break;

                case 7: // Student Needs Expression (student).
                    require_capability('mod/gestionprojet:submit', $context);

                    $record = gestionprojet_get_or_create_submission($gestionprojet, $params['groupid'],
                        $USER->id, 'besoin_eleve');

                    if ($record->status == 1) {
                        throw new \moodle_exception('submissionlocked', 'gestionprojet');
                    }

                    $validfields = ['aqui', 'surquoi', 'dansquelbut'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;
                            gestionprojet_log_change($gestionprojet->id, 'besoin_eleve', $record->id,
                                $key, $oldvalue, $value, $USER->id, $params['groupid']);
                        }
                    }

                    $record->timemodified = $time;
                    $DB->update_record('gestionprojet_besoin_eleve', $record);
                    break;

                case 8: // Logbook (student).
                    require_capability('mod/gestionprojet:submit', $context);

                    $record = gestionprojet_get_or_create_submission($gestionprojet, $params['groupid'],
                        $USER->id, 'carnet');

                    if ($record->status == 1) {
                        throw new \moodle_exception('submissionlocked', 'gestionprojet');
                    }

                    $validfields = ['tasks_data'];

                    foreach ($formdata as $key => $value) {
                        if ($key !== 'id' && in_array($key, $validfields)) {
                            $oldvalue = isset($record->$key) ? $record->$key : null;
                            $record->$key = $value;
                            gestionprojet_log_change($gestionprojet->id, 'carnet', $record->id,
                                $key, $oldvalue, $value, $USER->id, $params['groupid']);
                        }
                    }

                    $record->timemodified = $time;
                    $DB->update_record('gestionprojet_carnet', $record);
                    break;

                default:
                    return [
                        'success' => false,
                        'message' => get_string('invalidstep', 'gestionprojet'),
                        'timestamp' => $time,
                    ];
            }

            return [
                'success' => true,
                'message' => get_string('autosave_success', 'gestionprojet'),
                'timestamp' => $time,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'timestamp' => $time,
            ];
        }
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Status or error message'),
            'timestamp' => new external_value(PARAM_INT, 'Server timestamp of the save'),
        ]);
    }
}

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
 * External functions and service definitions for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_gestionprojet_generate_ai_summary' => [
        'classname'   => 'mod_gestionprojet\external\generate_ai_summary',
        'methodname'  => 'execute',
        'description' => 'Generate AI summary for a step\'s submissions',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/gestionprojet:grade',
    ],

    'mod_gestionprojet_autosave' => [
        'classname'    => 'mod_gestionprojet\external\autosave',
        'methodname'   => 'execute',
        'description'  => 'Auto-save form data for any step (student or teacher)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:submit, mod/gestionprojet:configureteacherpages',
    ],

    'mod_gestionprojet_submit_step' => [
        'classname'    => 'mod_gestionprojet\external\submit_step',
        'methodname'   => 'execute',
        'description'  => 'Submit or unlock a step submission',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:submit, mod/gestionprojet:lock',
    ],

    'mod_gestionprojet_save_grade' => [
        'classname'    => 'mod_gestionprojet\external\save_grade',
        'methodname'   => 'execute',
        'description'  => 'Save a manual grade and feedback for a step submission',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:grade',
    ],

    'mod_gestionprojet_evaluate' => [
        'classname'    => 'mod_gestionprojet\external\evaluate',
        'methodname'   => 'execute',
        'description'  => 'Trigger AI evaluation for a submission',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:grade',
    ],

    'mod_gestionprojet_get_evaluation_status' => [
        'classname'    => 'mod_gestionprojet\external\get_evaluation_status',
        'methodname'   => 'execute',
        'description'  => 'Get AI evaluation status and results for a submission',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:view',
    ],

    'mod_gestionprojet_apply_ai_grade' => [
        'classname'    => 'mod_gestionprojet\external\apply_ai_grade',
        'methodname'   => 'execute',
        'description'  => 'Apply an AI evaluation grade to a submission with visibility options',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:grade',
    ],

    'mod_gestionprojet_bulk_reevaluate' => [
        'classname'    => 'mod_gestionprojet\external\bulk_reevaluate',
        'methodname'   => 'execute',
        'description'  => 'Bulk re-evaluate all submissions for a step using AI',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:grade',
    ],

    'mod_gestionprojet_test_api_connection' => [
        'classname'    => 'mod_gestionprojet\external\test_api_connection',
        'methodname'   => 'execute',
        'description'  => 'Test AI API provider connection',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/gestionprojet:configureteacherpages',
    ],
];

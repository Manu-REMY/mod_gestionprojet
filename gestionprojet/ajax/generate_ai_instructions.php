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
 * AJAX endpoint: generate AI correction instructions from a teacher model.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php'); // Defines the Moodle curl class used by AI providers.
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_prompt_builder.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_config.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_evaluator.php');

$cmid = required_param('id', PARAM_INT);
$step = required_param('step', PARAM_INT);
$modeldata = required_param('model_data', PARAM_RAW);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:configureteacherpages', $context);

header('Content-Type: application/json; charset=utf-8');

$respond = function (array $payload) {
    if (empty($payload['success'])) {
        http_response_code(400);
    }
    echo json_encode($payload);
    exit;
};

if (!in_array($step, [4, 5, 6, 7, 8, 9], true)) {
    $respond(['success' => false, 'error' => 'invalid_step']);
}

$decoded = json_decode($modeldata, true);
if (!is_array($decoded)) {
    $respond(['success' => false, 'error' => 'invalid_payload']);
}

// Whitelist fields against STEP_FIELDS for the requested step.
$allowedfields = \mod_gestionprojet\ai_prompt_builder::STEP_FIELDS[$step] ?? [];
$tmpmodel = new stdClass();
$hasvalue = false;
foreach ($allowedfields as $field) {
    if (array_key_exists($field, $decoded)) {
        $value = is_string($decoded[$field]) ? trim($decoded[$field]) : '';
        $tmpmodel->$field = $value;
        if ($value !== '') {
            $hasvalue = true;
        }
    } else {
        $tmpmodel->$field = '';
    }
}

if (!$hasvalue) {
    $respond(['success' => false, 'error' => 'model_empty']);
}

$aiconfig = \mod_gestionprojet\ai_config::get_config($cm->instance);
if (!$aiconfig || empty($aiconfig->enabled)) {
    $respond(['success' => false, 'error' => 'ai_disabled']);
}

$apikey = \mod_gestionprojet\ai_config::get_effective_api_key(
    $aiconfig->provider,
    $aiconfig->api_key ?? ''
);
if ($apikey === '') {
    $respond(['success' => false, 'error' => 'no_provider']);
}

try {
    $builder = new \mod_gestionprojet\ai_prompt_builder();
    $prompts = $builder->build_meta_prompt($step, $tmpmodel);

    $provider = \mod_gestionprojet\ai_evaluator::get_provider($aiconfig->provider, $apikey);
    $model = \mod_gestionprojet\ai_evaluator::get_model_for_provider($aiconfig->provider);

    $response = $provider->evaluate($prompts['system'], $prompts['user'], $model, 1500);

    $instructions = trim($response['content'] ?? '');
    if ($instructions === '') {
        $respond(['success' => false, 'error' => 'ai_failed']);
    }

    $respond(['success' => true, 'instructions' => $instructions]);
} catch (\Throwable $e) {
    debugging('generate_ai_instructions: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $respond([
        'success' => false,
        'error' => 'ai_failed',
    ]);
}

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
 * Server flow of the level validation (v2.11.2).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_prompt_builder.php');

use mod_gestionprojet\ai_prompt_builder;

/**
 * Source of a plugin file.
 *
 * @param string $path Relative to the plugin root.
 * @return string
 */
function code(string $path): string {
    return is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
}

/**
 * Body of a function (top-level: up to "}" in column 0; method: up to "}" indented by 4).
 *
 * @param string $src
 * @param string $name
 * @return string
 */
function fn_body(string $src, string $name): string {
    $start = strpos($src, 'function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $toplevel = $start === 0 || $src[$start - 1] === "\n";
    $stop = strpos($src, $toplevel ? "\n}\n" : "\n    }\n", $start);
    return substr($src, $start, ($stop === false ? strlen($src) : $stop) - $start);
}

// Prompt: only the parts of the level asked.
$grid = json_encode([
    ['id' => 1, 'nom' => 'Fonctions', 'poids' => 6, 'zones' => ['fonctions'], 'palier' => 'S'],
    ['id' => 2, 'nom' => 'Niveaux', 'poids' => 4, 'zones' => ['niveaux'], 'palier' => 'TS'],
    ['id' => 3, 'nom' => 'Contraintes', 'poids' => 3, 'zones' => ['contraintes'], 'palier' => ''],
]);
$model = (object) ['grille_json' => $grid, 'ai_instructions' => ''];
test('palier S: only the S parts are graded', fn() =>
    array_column((new ai_prompt_builder(['palier' => 'S']))->graded_parts(4, $model), 'nom') === ['Fonctions']);
test('palier TS: only the TS parts are graded', fn() =>
    array_column((new ai_prompt_builder(['palier' => 'TS']))->graded_parts(4, $model), 'nom') === ['Niveaux']);
test('no palier: every graded part', fn() => count((new ai_prompt_builder())->graded_parts(4, $model)) === 3);
test('palier S: the grid of the prompt is the S part alone, on 20', fn() =>
    strpos((new ai_prompt_builder(['palier' => 'S']))->build_system_prompt(4, $model), '- Fonctions (poids: 20/20)') !== false);

// Web services.
$services = code('db/services.php');
foreach (['mod_gestionprojet_request_palier' => 'request_palier', 'mod_gestionprojet_retire_palier' => 'retire_palier'] as $ws => $class) {
    test("services: $ws declared, ajax", fn() => preg_match("/'$ws' => \\[[^\\]]*'classname'\\s*=> 'mod_gestionprojet\\\\\\\\external\\\\\\\\$class'[^\\]]*'ajax'\\s*=> true/s", $services) === 1);
}
$request = code('classes/external/request_palier.php');
test('request_palier validates the context', fn() => strpos($request, 'self::validate_context($context)') !== false);
test('request_palier requires mod/gestionprojet:submit', fn() => strpos($request, "require_capability('mod/gestionprojet:submit'") !== false);
test('request_palier checks the group server-side', fn() => strpos($request, 'gestionprojet_require_group_access(') !== false);
test('request_palier calls gestionprojet_palier_request', fn() => strpos($request, 'gestionprojet_palier_request(') !== false);
$retire = code('classes/external/retire_palier.php');
test('retire_palier requires mod/gestionprojet:grade', fn() => strpos($retire, "require_capability('mod/gestionprojet:grade'") !== false);
test('retire_palier calls gestionprojet_palier_retire', fn() => strpos($retire, 'gestionprojet_palier_retire(') !== false);

// Evaluator.
$evaluator = code('classes/ai_evaluator.php');
test('queue: one evaluation pending or processing per copy', fn() =>
    strpos(fn_body($evaluator, 'queue_evaluation'), "'processing'") !== false);
test('queue stores the level and the frozen copy', fn() => strpos(fn_body($evaluator, 'queue_evaluation'), 'contenu_json') !== false
    && strpos(fn_body($evaluator, 'queue_evaluation'), '->palier') !== false);
$process = fn_body($evaluator, 'process_evaluation');
test('process: the verdict is computed by palier::verdict', fn() => strpos($process, 'palier::verdict(') !== false);
test('process: the verdict is recorded', fn() => strpos($process, 'gestionprojet_palier_record(') !== false);
test('process: a level evaluation judges the frozen copy', fn() => strpos($process, 'contenu_json') !== false);
test('process: answers always hidden for a level', fn() => preg_match('/ispalier\s*\|\|/', $process) === 1);
test('process: no auto-apply for a level', fn() => preg_match('/ai_auto_apply\)\s*&&\s*!\$ispalier/', $process) === 1);
test('apply refuses a level evaluation', fn() => strpos(fn_body($evaluator, 'apply_evaluation'), '->palier') !== false);

// lib.php.
$lib = code('lib.php');
foreach (['gestionprojet_palier_state', 'gestionprojet_palier_request', 'gestionprojet_palier_record', 'gestionprojet_palier_retire',
        'gestionprojet_palier_parts'] as $fn) {
    test("lib defines $fn", fn() => strpos($lib, "function $fn(") !== false);
}
test('request: refuses when a level evaluation is pending', fn() => strpos(fn_body($lib, 'gestionprojet_palier_request'), "'pending'") !== false);
test('request: only a requestable level', fn() => strpos(fn_body($lib, 'gestionprojet_palier_request'), "['requestable']") !== false);
test('record: a level already validated is not inserted twice', fn() =>
    strpos(fn_body($lib, 'gestionprojet_palier_record_locked'), 'record_exists') !== false);
test('record: members are stored', fn() => strpos(fn_body($lib, 'gestionprojet_palier_record_locked'), 'membres') !== false);
test('retire: cascade to the higher levels', fn() => strpos(fn_body($lib, 'gestionprojet_palier_retire'), 'palier::retire_cascade(') !== false);
test('enforce applies the frozen zones', fn() => strpos(fn_body($lib, 'gestionprojet_cdcf_enforce_consigne'), 'palier::freeze(') !== false);
test('grade item of step 4 not updated in level mode', fn() =>
    strpos(fn_body($lib, 'gestionprojet_grade_item_update'), 'step4_paliers') !== false
    && strpos(fn_body($lib, 'gestionprojet_update_grades'), 'step4_paliers') !== false);
foreach (['ajax/autosave.php', 'classes/external/autosave.php'] as $file) {
    test("$file passes the copy to the enforce function", fn() =>
        preg_match('/gestionprojet_cdcf_enforce_consigne\(\$gestionprojet, \(string\)\$value, \$record\)/', code($file)) === 1);
}
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['palier_disabled', 'palier_pending_error', 'palier_not_requestable'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

// Security review: same gates as the submission.
$requestbody = fn_body($lib, 'gestionprojet_palier_request');
test('request: step 4 must be enabled', fn() => strpos($requestbody, 'gestionprojet_is_step_enabled($gestionprojet, 4)') !== false);
test('request: submissions must be enabled', fn() => strpos($requestbody, 'enable_submission') !== false);
test('request: a submitted (locked) copy is refused', fn() => strpos($requestbody, '->status') !== false);
test('request: refused after the deadline (effective deadline of the copy, v2.14.0)', fn() =>
    strpos($requestbody, 'gestionprojet_effective_deadline($gestionprojet, 4, $submission)') !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    test("string submissionlocked in $l (thrown by autosave and level request)", fn() => !empty($string['submissionlocked']));
}

// Preprod run: in level mode the AI must answer one criterion per part, with its exact name.
$sys = (new ai_prompt_builder(['palier' => 'S']))->build_system_prompt(4, $model);
test('palier prompt asks for exactly one criterion per part with its exact name', fn() =>
    strpos($sys, 'exactement un critère par partie') !== false && strpos($sys, '« Fonctions »') !== false);
// Outside the level mode a grid with levels also asks for the exact names (level reached, v2.13.5),
// but never the level-mode rule that nothing else is graded.
test('no level mode: no level-only instruction', fn() =>
    strpos((new ai_prompt_builder())->build_system_prompt(4, $model), "N'évalue rien d'autre") === false);

// Preprod run: in a SAT request the AI still penalised the missing levels (another level's zone).
test('palier prompt names what is not graded in this level', fn() =>
    strpos($sys, 'Ne pénalise pas') !== false && strpos($sys, 'les niveaux') !== false && strpos($sys, 'les contraintes') !== false);
test('palier prompt does not exclude the zones of the level asked', fn() =>
    preg_match('/Ne pénalise pas[^\n]*les fonctions de service/u', $sys) === 0);

// Preprod run: "niveau : (non précisé)" in the copy made the AI penalise the levels in a SAT request.
$copy = (object) ['interacteurs_data' => json_encode(['interactors' => [['id' => 1, 'name' => 'Robot']],
    'fonctionsService' => [['id' => 1, 'description' => 'Rouler', 'interactor1Id' => 1, 'interactor2Id' => 0,
        'criteres' => [['id' => 1, 'description' => 'Vitesse', 'niveau' => '', 'flexibilite' => '']]]], 'contraintes' => []])];
$satuser = (new ai_prompt_builder(['palier' => 'S']))->build_user_prompt(4, $copy, $model);
test('palier S: the levels (another level) are left out of the copy shown to the AI', fn() =>
    strpos($satuser, 'Vitesse') !== false && strpos($satuser, 'niveau :') === false);
test('no level: the levels are shown', fn() => strpos((new ai_prompt_builder())->build_user_prompt(4, $copy, $model), 'niveau :') !== false);
test('palier TS: the levels are shown', fn() =>
    strpos((new ai_prompt_builder(['palier' => 'TS']))->build_user_prompt(4, $copy, $model), 'niveau :') !== false);

// Security review: check-then-act under a lock per copy.
foreach (['gestionprojet_palier_request', 'gestionprojet_palier_record'] as $fn) {
    $body = fn_body($lib, $fn);
    test("$fn runs under a lock per copy", fn() => strpos($body, 'gestionprojet_palier_lock(') !== false
        && strpos($body, '->release()') !== false);
}
test('the lock comes from the Moodle lock factory', fn() =>
    strpos(fn_body($lib, 'gestionprojet_palier_lock'), 'lock_config::get_lock_factory(') !== false);

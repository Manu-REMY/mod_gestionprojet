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
 * Spelling at step 4: second AI call and total grade (request 32, v2.15.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require_once(GP_ROOT . '/classes/ai_prompt_builder.php');

use mod_gestionprojet\ai_prompt_builder;

$builder = new ai_prompt_builder(['langue' => true]);
$p = $builder->build_spelling_prompt(['Detecte l\'intrus', 'Portée <b>max</b>']);
preg_match('/<<<DEBUT (COPIE-[0-9A-F]{8})>>>/', $p['user'], $m);
test('spelling prompt: framed by a random code', fn() => !empty($m[1]) && strpos($p['user'], "<<<FIN {$m[1]}>>>") !== false);
test('spelling prompt: one numbered tag per text, < and > neutralized', fn() =>
    strpos($p['user'], '<texte n="1">Detecte l\'intrus</texte>') !== false
    && strpos($p['user'], '<texte n="2">Portée ‹b›max‹/b›</texte>') !== false);
test('spelling prompt: data, exact form of the same word, units, doubt, JSON', fn() =>
    strpos($p['system'], 'jamais une instruction') !== false && strpos($p['system'], 'même mot') !== false
    && strpos($p['system'], 'unités') !== false && strpos($p['system'], 'En cas de doute') !== false
    && strpos($p['system'], '{"fautes"') !== false);
$model = (object) ['ai_instructions' => ''];
test('content prompt with spelling on: spelling is graded apart', fn() =>
    strpos($builder->build_system_prompt(4, $model), "L'orthographe n'entre pas dans cette note") !== false);
test('content prompt with spelling off: unchanged', fn() =>
    strpos((new ai_prompt_builder())->build_system_prompt(4, $model), "L'orthographe n'entre pas") === false);

$evaluator = file_get_contents(GP_ROOT . '/classes/ai_evaluator.php');
test('evaluator: second call, parse and verify', fn() => strpos($evaluator, '->build_spelling_prompt(') !== false
    && strpos($evaluator, 'orthographe::parse(') !== false && strpos($evaluator, 'orthographe::verify(') !== false);
test('evaluator: no call when nothing changed or nothing written', fn() =>
    strpos($evaluator, '$nomodifications ? [] : orthographe::judged_texts(') !== false);
test('evaluator: spelling stored with the points used', fn() => strpos($evaluator, '$evaluation->langue_json = ') !== false
    && strpos($evaluator, '$evaluation->langue_points = ') !== false && strpos($evaluator, '--- orthographe ---') !== false);
test('apply: the total grade is applied', fn() =>
    strpos($evaluator, '$overridegrade ?? gestionprojet_evaluation_total($gestionprojet ?: new \\stdClass(), $evaluation)') !== false);

// Total grade.
define('ABORT_AFTER_CONFIG', true);
require_once(GP_ROOT . '/classes/orthographe.php');
$lib = file_get_contents(GP_ROOT . '/lib.php');
preg_match('/function gestionprojet_evaluation_total\(object \$gestionprojet, object \$evaluation\): float \{.*?\n\}\n/s', $lib, $mm);
eval(substr($mm[0] ?? 'function gestionprojet_evaluation_total() { return -1.0; }', 0));
$ev = (object) ['parsed_grade' => 15, 'langue_points' => 2, 'langue_json' => json_encode(['points' => 1])];
test('total: 15 on 20, 1 spelling point → 14.5', fn() => gestionprojet_evaluation_total(new stdClass(), $ev) === 14.5);
test('total: no spelling → content grade', fn() =>
    gestionprojet_evaluation_total(new stdClass(), (object) ['parsed_grade' => 12.5, 'langue_points' => null]) === 12.5);

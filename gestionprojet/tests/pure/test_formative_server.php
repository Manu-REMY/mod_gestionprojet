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
 * Server side of the formative feedback (v2.11.4).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

/**
 * Source of a plugin file.
 *
 * @param string $path
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

$lib = code('lib.php');
$state = fn_body($lib, 'gestionprojet_formative_state');
$request = fn_body($lib, 'gestionprojet_formative_request');
test('state: defined, counts the formative evaluations not failed', fn() => strpos($state, "'formatif' => 1") !== false
    || strpos($state, 'formatif = 1') !== false);
test('state: failed feedbacks do not count', fn() => strpos($state, "'failed'") !== false);
test('request: refused when the limit is 0 or reached', fn() => strpos($request, "['remaining']") !== false);
test('request: refused when a feedback is running', fn() => strpos($request, "['pending']") !== false);
test('request: refused on a submitted copy', fn() => strpos($request, '->status') !== false);
test('request: refused after the deadline', fn() => strpos($request, 'deadline_date') !== false);
test('request: refused in level mode for step 4', fn() => strpos($request, 'step4_paliers') !== false);
test('request: under the lock of the copy', fn() => strpos($request, 'gestionprojet_palier_lock(') !== false && strpos($request, '->release()') !== false);
test('request: freezes the step fields of the copy', fn() => strpos($request, 'ai_prompt_builder::STEP_FIELDS') !== false);
test('request: queues a formative evaluation', fn() => preg_match('/queue_evaluation\([^;]*true\)/s', $request) === 1);

$ev = code('classes/ai_evaluator.php');
test('queue: stores formatif', fn() => strpos(fn_body($ev, 'queue_evaluation'), '->formatif') !== false);
test('queue: a running evaluation is reused only if of the same kind', fn() =>
    strpos(fn_body($ev, 'queue_evaluation'), 'formatif = :formatif') !== false);
test('process: a formative feedback judges the frozen copy, answers hidden, no auto-apply', fn() =>
    strpos(fn_body($ev, 'process_evaluation'), '$isformative') !== false);
test('apply refuses a formative feedback', fn() => strpos(fn_body($ev, 'apply_evaluation'), '->formatif') !== false);
test('get_evaluation ignores formative feedbacks', fn() => strpos(fn_body($ev, 'get_evaluation'), 'formatif = 0') !== false);

test('services: request_formative declared, ajax, submit capability', fn() =>
    preg_match("/'mod_gestionprojet_request_formative' => \\[[^\\]]*'ajax'\\s*=> true[^\\]]*'capabilities'\\s*=> 'mod\\/gestionprojet:submit'/s", code('db/services.php')) === 1);
$ws = code('classes/external/request_formative.php');
test('WS checks the group server-side and the capability', fn() => strpos($ws, 'gestionprojet_require_group_access(') !== false
    && strpos($ws, "require_capability('mod/gestionprojet:submit'") !== false && strpos($ws, 'self::validate_context($context)') !== false);
test('the lock takes the table of the copy', fn() => strpos($lib, 'function gestionprojet_palier_lock(int $submissionid, string $table = ') !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['formative_none_left', 'formative_pending_error', 'formative_disabled'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

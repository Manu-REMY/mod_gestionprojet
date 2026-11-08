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
 * Student side of the formative feedback (v2.11.4).
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

$display = code('pages/student_formative_display.php');
test('display: uses gestionprojet_formative_state', fn() => strpos($display, 'gestionprojet_formative_state(') !== false);
test('display: the feedback is always filtered', fn() => strpos($display, 'answer_leak_filter::student_view(') !== false);
test('display: no grade shown', fn() => strpos($display, 'parsed_grade') === false);
test('display: remaining count in the button', fn() => strpos($display, "'formative_request'") !== false);
test('display: none left with ⊘ and a word', fn() => strpos($display, "'⊘ '") !== false && strpos($display, "'formative_none_left'") !== false);
test('display: a running feedback is followed by the button module, never by the auto-reloading poller', fn() =>
    strpos($display, "'mod_gestionprojet/student_ai_progress'") === false && strpos($display, "'pendingId'") !== false);
test('display: loads the button module', fn() => strpos($display, "'mod_gestionprojet/formative_button'") !== false);
test('display: nothing for a submitted copy', fn() => strpos($display, '$isSubmitted') !== false);
test('helper includes the block (steps 4, 5, 9)', fn() => strpos(code('pages/student_submit_helper.php'), 'student_formative_display.php') !== false);
foreach ([6, 7, 8] as $n) {
    test("step$n includes the block", fn() => strpos(code("pages/step$n.php"), 'student_formative_display.php') !== false);
}
$js = code('amd/src/formative_button.js');
test('button calls the WS after saving the copy', fn() => strpos($js, "'mod_gestionprojet_request_formative'") !== false
    && strpos($js, 'Autosave.save()') !== false);
test('button: no innerHTML, cache-buster reload', fn() => $js !== '' && strpos($js, 'innerHTML') === false
    && strpos($js, "searchParams.set('_'") !== false);
test('autosave.save() returns the request promise', fn() => preg_match('/return Ajax\.call\(\[\{\s*methodname: \'mod_gestionprojet_autosave\'/', code('amd/src/autosave.js')) === 1);
foreach (['formative_button', 'autosave'] as $m) {
    test("build $m.min.js up to date", fn() => is_file(GP_ROOT . "/amd/build/$m.min.js")
        && filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js"));
}
test('styles: formative block', fn() => strpos(code('styles.css'), '.path-mod-gestionprojet .gp-formative') !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['formative_title', 'formative_request', 'formative_pending', 'formative_last', 'formative_error', 'formative_help'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

// Final review: save every editor before asking (step 9 has its own), and never reload by itself.
test('button flushes the registered savers (save_hooks) and Autosave', fn() => strpos($js, 'SaveHooks.flushAll()') !== false
    && strpos($js, 'Autosave.save()') !== false);
test('button: no automatic reload when the feedback is ready, a "show" button instead', fn() =>
    strpos($js, 'location.reload') === false && strpos($js, 'cfg.strings.show') !== false);
$hooks = code('amd/src/save_hooks.js');
test('save_hooks: register and flushAll', fn() => strpos($hooks, 'register') !== false && strpos($hooks, 'flushAll') !== false);
test('the FAST editor registers its immediate save', fn() => strpos(code('amd/src/fast_editor.js'), 'SaveHooks.register(') !== false);
foreach (['save_hooks', 'fast_editor', 'formative_button'] as $m) {
    test("build $m.min.js up to date (review)", fn() => is_file(GP_ROOT . "/amd/build/$m.min.js")
        && filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js"));
}
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['formative_ready', 'formative_show'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

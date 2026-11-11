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
 * The teacher dismisses a spelling mistake for the whole activity (request 32, v2.15.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$read = fn(string $path): string => is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
$ajax = $read('ajax/spelling_accept.php');
$lib = $read('lib.php');
$js = $read('amd/src/spelling_accept.js');
$grading = $read('grading.php');
test('endpoint: login, sesskey, grade capability, text param', fn() => strpos($ajax, 'require_login(') !== false
    && strpos($ajax, 'require_sesskey()') !== false && strpos($ajax, "require_capability('mod/gestionprojet:grade'") !== false
    && strpos($ajax, "required_param('cite', PARAM_TEXT)") !== false && strpos($ajax, 'gestionprojet_spelling_accept(') !== false);
preg_match('/function gestionprojet_spelling_accept\(object \$gestionprojet, string \$cite\): int \{.*?\n\}\n/s', $lib, $m);
$fn = $m[0] ?? '';
test('lib: the word joins the accepted list once', fn() => strpos($fn, 'orthographe::normalize($cite)') !== false
    && strpos($fn, "if (!in_array(\$word, \$accepted, true))") !== false);
test('lib: every corrected copy of the activity holding the word is recomputed', fn() =>
    strpos($fn, "langue_json IS NOT NULL") !== false && strpos($fn, 'orthographe::points(') !== false);
test('lib: a grade the teacher typed by hand is never overwritten', fn() =>
    strpos($fn, 'abs((float)$submission->grade - $oldtotal) <= 0.01') !== false);
test('lib: grade book and level follow', fn() => strpos($fn, 'gestionprojet_update_grades(') !== false
    && strpos($fn, 'gestionprojet_level_sync($gestionprojet, $evaluation)') !== false);
test('grading: a "Dismiss" button per mistake, through the block', fn() =>
    strpos($grading, 'gestionprojet_spelling_block_html(') !== false && strpos($lib, 'gp-langue-dismiss') !== false
    && strpos($lib, "'data-cite' => \$faute['cite']") !== false);
test('grading: loads the module', fn() => strpos($grading, "js_call_amd('mod_gestionprojet/spelling_accept', 'init'") !== false);
test('js: posts to the endpoint, reloads fresh, never innerHTML', fn() => strpos($js, 'spelling_accept.php') !== false
    && strpos($js, "searchParams.set('_'") !== false && $js !== '' && strpos($js, 'innerHTML') === false);
test('build up to date', fn() => is_file(GP_ROOT . '/amd/build/spelling_accept.min.js')
    && filemtime(GP_ROOT . '/amd/build/spelling_accept.min.js') >= filemtime(GP_ROOT . '/amd/src/spelling_accept.js'));
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("strings ($lang)", fn() => !empty($string['langue_dismiss']) && !empty($string['langue_dismiss_confirm']));
}

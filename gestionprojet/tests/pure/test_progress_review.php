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
 * Fixes of the final review of the progress panel (v2.12.1).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$lib = file_get_contents(GP_ROOT . '/lib.php');
$start = strpos($lib, 'function gestionprojet_progress_state(');
$body = substr($lib, $start, strpos($lib, "\n}\n", $start) - $start);
$panel = file_get_contents(GP_ROOT . '/pages/student_progress_panel.php');
$js = file_get_contents(GP_ROOT . '/amd/src/progress_panel.js');
$css = file_get_contents(GP_ROOT . '/styles.css');

test('a just-created copy has no grade property: isset', fn() => strpos($body, 'isset($submission->grade)') !== false);
test('a grade typed by hand is dated by the submission', fn() => strpos($body, '(int)($submission->timesubmitted ?? 0)') !== false);
test('the teacher comment shows even when AI is off', fn() => strpos($body, '$applied = $aion ?') !== false
    && strpos($body, "if (\$aion) {\n        \$applied") === false);
test('the teacher comment is not filtered', fn() => strpos($body, "\$pick['source'] !== 'teacher'") !== false);
// v2.12.2: the feedback markup lives in gestionprojet_progress_feedback_html() (lib.php), shared with the endpoint.
test('the criteria of the applied evaluation are back (show_criteria)', fn() => strpos($body, 'show_criteria') !== false
    && strpos(file_get_contents(GP_ROOT . '/lib.php'), "'gp-progress-criteria'") !== false);
test('the keywords found are back (show_keywords_found)', fn() => strpos($body, 'show_keywords_found') !== false
    && strpos(file_get_contents(GP_ROOT . '/lib.php'), "'gp-progress-keywords'") !== false);
test('submitted but not graded: its own words', fn() => strpos($panel, "'progress_grade_pending'") !== false);
test('read-only only on someone else\'s copy', fn() => strpos($panel, '$USER->id') !== false);
test('step 4 list reads the fields being typed', fn() => strpos($js, "hasAttribute('data-gp-field')") !== false
    && strpos($js, "'[data-gp-field=\"' + ") !== false);
test('one delegated click listener on the list', fn() => strpos($js, "list.addEventListener('click'") !== false);
test('the list is only rebuilt when it changes', fn() => strpos($js, 'lastSignature') !== false);
test('narrow screen: a bottom bar with status and buttons', fn() => strpos($panel, "'gp-progress-bottombar'") !== false
    && strpos($panel, 'data-gp-forward') !== false);
test('the bar forwards to the real buttons (one #submitButton)', fn() => strpos($js, 'data-gp-forward') !== false
    && substr_count($panel, "'id' => 'submitButton'") === 1);
test('styles: the bar is hidden on a wide screen and shown below 992px', fn() =>
    preg_match('/\.path-mod-gestionprojet \.gp-progress-bottombar\s*\{[^}]*display:\s*none/', $css) === 1
    && preg_match('/@media \(max-width: 991\.98px\)[^@]*\.gp-progress-bottombar\s*\{[^}]*display:\s*flex/s', $css) === 1);
test('styles: room under the form for the bar', fn() => preg_match('/\.gp-progress-main\s*\{[^}]*padding-bottom/', $css) === 1);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['progress_grade_pending', 'progress_criteria', 'progress_keywords'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

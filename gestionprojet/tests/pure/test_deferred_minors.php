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
 * Deferred minors of the 2.11.4 to 2.12.4 reviews (v2.12.5).
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
function srcm(string $path): string {
    return is_file(GP_ROOT . '/' . $path) ? (string)file_get_contents(GP_ROOT . '/' . $path) : '';
}

$lib = srcm('lib.php');
$panel = srcm('pages/student_progress_panel.php');

// 1. After the deadline: no ask button, a sentence instead; the server refuses with its own words.
test('state: deadline passed computed and cuts canask', fn() => strpos($lib, "'deadlinepassed' => \$deadlinepassed,") !== false
    && strpos($lib, "&& !\$deadlinepassed,") !== false);
test('panel: sentence when the deadline is passed', fn() =>
    strpos($panel, "get_string('formative_deadline_passed', 'gestionprojet')") !== false);
test('request: refused past the deadline with its own message', fn() =>
    strpos($lib, "throw new moodle_exception('formative_deadline_passed', 'gestionprojet');") !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    test("string formative_deadline_passed in $l", fn() => !empty($string['formative_deadline_passed']));
}

// 2. A formative feedback that fails alerts no teacher (everything automatic).
test('evaluator: no teacher alert for a formative failure', fn() =>
    strpos(srcm('classes/ai_evaluator.php'), "if (empty(\$evaluation->formatif)) {\n                self::notify_teachers_of_failure(") !== false);

// 3. The AI class summary only reads graded evaluations.
$gen = srcm('classes/dashboard/ai_summary_generator.php');
test('summary: formative feedbacks excluded (both queries)', fn() => substr_count($gen, 'AND formatif = 0') === 2);

// 4. No empty right column when the page has no panel.
test('styles: one column when there is no panel', fn() =>
    strpos(srcm('styles.css'), '.path-mod-gestionprojet .gp-progress-layout:not(:has(.gp-progress-panel))') !== false);

// 5. Following the consigne: no history row per copy, consigne decoded once; orphan file removed.
preg_match('/function gestionprojet_cdcf_follow_consigne\(.*?\n}\n/s', $lib, $m);
test('follow: no history row per copy', fn() => isset($m[0]) && strpos($m[0], 'gestionprojet_log_change(') === false);
test('orphan student_ai_feedback_display.php removed', fn() => !is_file(GP_ROOT . '/pages/student_ai_feedback_display.php')
    && strpos(srcm('ajax/get_evaluation_status.php'), 'student_ai_feedback_display') === false);

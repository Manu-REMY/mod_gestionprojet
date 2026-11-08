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
 * Teacher side of the level validation (v2.11.2).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

/**
 * Source of a plugin file.
 *
 * @param string $path Relative to the plugin root.
 * @return string
 */
function code(string $path): string {
    return is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
}

$grading = code('grading.php');
$block = code('pages/teacher_palier_display.php');
test('grading: level mode flag for step 4', fn() => preg_match('/\$palierteacher = [^;]*step4_paliers/', $grading) === 1);
test('grading: the graded AI block (apply buttons) is skipped in level mode', fn() =>
    preg_match('/in_array\(\$step, \[4, 5, 6, 7, 8\]\) && !empty\(\$gestionprojet->ai_enabled\) && !\$palierteacher/', $grading) === 1);
test('grading: includes the teacher level block', fn() => strpos($grading, 'teacher_palier_display.php') !== false);
test('block: uses gestionprojet_palier_state', fn() => strpos($block, 'gestionprojet_palier_state(') !== false);
test('block: a remove button per validated level', fn() => strpos($block, 'data-gp-retire') !== false);
test('block: attempts with ✓ / ⊘ and words', fn() => strpos($block, "'✓ '") !== false && strpos($block, "'⊘ '") !== false
    && strpos($block, "'palier_attempt_ok'") !== false && strpos($block, "'palier_attempt_ko'") !== false);
test('block: loads palier_retire', fn() => strpos($block, "'mod_gestionprojet/palier_retire'") !== false);
$retire = code('amd/src/palier_retire.js');
test('palier_retire calls mod_gestionprojet_retire_palier', fn() => strpos($retire, "'mod_gestionprojet_retire_palier'") !== false);
test('palier_retire asks for a confirmation in a Moodle modal', fn() => strpos($retire, 'saveCancelPromise') !== false);
test('palier_retire never uses innerHTML', fn() => $retire !== '' && strpos($retire, 'innerHTML') === false);
test('step4_teacher passes paliersMode', fn() => strpos(code('pages/step4_teacher.php'), "'paliersMode'") !== false);
test('grille_editor reads paliersMode', fn() => strpos(code('amd/src/grille_editor.js'), 'paliersMode') !== false);
foreach (['palier_retire', 'grille_editor'] as $module) {
    test("build $module.min.js is up to date", fn() => is_file(GP_ROOT . "/amd/build/$module.min.js")
        && filemtime(GP_ROOT . "/amd/build/$module.min.js") >= filemtime(GP_ROOT . "/amd/src/$module.js"));
}
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['palier_retire', 'palier_retire_confirm', 'palier_attempts', 'palier_attempt_ok', 'palier_attempt_ko',
            'palier_attempt_error', 'palier_attempt_pending', 'palier_none_validated', 'grille_palier_none_mode'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

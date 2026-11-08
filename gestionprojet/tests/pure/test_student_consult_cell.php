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
 * A student who must complete a provided consigne goes straight to the work page (preco 18).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/lib.php');

$gp = fn(array $f) => (object) $f;
test('consigne provided, student step enabled: no consult cell', fn() =>
    gestionprojet_student_consult_only($gp(['step4_provided' => 1, 'enable_step4' => 1]), 4) === false);
test('consigne provided, student step disabled: consult only', fn() =>
    gestionprojet_student_consult_only($gp(['step4_provided' => 1, 'enable_step4' => 0]), 4) === true);
test('no consigne: no consult cell', fn() => gestionprojet_student_consult_only($gp(['step4_provided' => 0, 'enable_step4' => 0]), 4) === false);
test('step 5 and 9 follow the same rule', fn() => gestionprojet_student_consult_only($gp(['step5_provided' => 1, 'enable_step5' => 0]), 5)
    && !gestionprojet_student_consult_only($gp(['step9_provided' => 1, 'enable_step9' => 1]), 9));

$home = file_get_contents(GP_ROOT . '/pages/home.php');
test('home: the student consult cell uses gestionprojet_student_consult_only for 4, 5 and 9', fn() =>
    substr_count($home, 'gestionprojet_student_consult_only($gestionprojet, ') >= 3);
foreach ([4, 5, 9] as $step) {
    $src = file_get_contents(GP_ROOT . "/pages/step{$step}_provided.php");
    test("step{$step}_provided: a student who must complete is sent to the work page", fn() =>
        preg_match('/if \(\$readonly && !gestionprojet_student_consult_only\(\$gestionprojet, ' . $step . '\)\)\s*\{\s*redirect\(/', $src) === 1);
}

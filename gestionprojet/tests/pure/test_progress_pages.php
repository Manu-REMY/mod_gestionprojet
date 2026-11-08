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
 * The six student step pages use the progress panel (v2.12.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

foreach ([4, 5, 6, 7, 8, 9] as $n) {
    $src = file_get_contents(GP_ROOT . "/pages/step$n.php");
    test("step$n opens the layout right after the student tabs", fn() =>
        preg_match("/gestionprojet_build_step_tabs\\(\\\$gestionprojet, \\\$cm->id, $n, 'student'\\)\\s*\\);\\s*(\\/\\/[^\\n]*\\n\\s*)?echo gestionprojet_progress_layout_open\\(\\);/", $src) === 1);
    test("step$n renders the panel before the footer", fn() =>
        preg_match("/require __DIR__ \\. '\\/student_progress_panel\\.php';\\s*\\n\\s*echo \\\$OUTPUT->footer\\(\\);/", $src) === 1);
    test("step$n has no submit button of its own any more", fn() => strpos($src, "'submitButton'") === false
        && strpos($src, 'id="submitButton"') === false);
    test("step$n no longer includes the old feedback, level and formative blocks", fn() =>
        strpos($src, 'student_ai_feedback_display.php') === false && strpos($src, 'student_palier_display.php') === false
        && strpos($src, 'student_formative_display.php') === false);
}
test('the submit helper no longer includes the formative block', fn() =>
    strpos(file_get_contents(GP_ROOT . '/pages/student_submit_helper.php'), 'student_formative_display.php') === false);

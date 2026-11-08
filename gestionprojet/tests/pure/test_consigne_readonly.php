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
 * A student opening a consigne page never gets the rich-text editor of the intro text.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

foreach ([4, 5, 9] as $step) {
    $src = file_get_contents(GP_ROOT . "/pages/step{$step}_provided.php");
    $id = "gp_intro_text_step{$step}";
    // The textarea and the editor must sit inside a teacher-only block, with no block end in between.
    $guard = '(?:if \(!\$readonly\)|if \(\$canedit\))';
    test("step $step: the intro textarea is teacher-only", fn() =>
        preg_match('/' . $guard . '(?:(?!endif|^\}).)*?id="' . $id . '"/sm', $src) === 1);
    test("step $step: the intro editor is teacher-only", fn() =>
        preg_match('/' . $guard . '(?:(?!endif|^\}).)*?use_editor\(\'' . $id . '\'/sm', $src) === 1);
}
foreach ([4, 5] as $step) {
    $src = file_get_contents(GP_ROOT . "/pages/step{$step}_provided.php");
    test("step $step: a reader sees the intro text read-only", fn() =>
        strpos($src, "format_text(\$model->intro_text") !== false);
}

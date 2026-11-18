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
 * Moodle 5.3 removed core/modal_factory: the submit and reset confirmations use core/modal_save_cancel (v2.17.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

foreach (glob(GP_ROOT . '/amd/src/*.js') as $file) {
    $name = basename($file, '.js');
    $js = file_get_contents($file);
    test("$name: no core/modal_factory (removed in Moodle 5.3)", fn() => strpos($js, 'modal_factory') === false
        && strpos($js, 'ModalFactory') === false);
}
foreach (['submission', 'reset_button'] as $m) {
    $js = file_get_contents(GP_ROOT . "/amd/src/$m.js");
    test("$m: confirmation through ModalSaveCancel.create", fn() => strpos($js, "'core/modal_save_cancel'") !== false
        && strpos($js, 'ModalSaveCancel.create({') !== false);
    test("build $m.min.js up to date", fn() => filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js")
        && strpos(file_get_contents(GP_ROOT . "/amd/build/$m.min.js"), 'core/modal_save_cancel') !== false);
}

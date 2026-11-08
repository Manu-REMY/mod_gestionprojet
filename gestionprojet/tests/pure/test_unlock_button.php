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
 * "Revert to draft" sends what the server needs: action unlock, group and user of the copy (v2.11.4).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$grading = file_get_contents(GP_ROOT . '/amd/src/grading.js');
$unlock = substr($grading, strpos($grading, 'function initUnlockButton'), 1500);
test('grading.js sends the group of the button', fn() => strpos($unlock, 'groupid: parseInt(btn.dataset.groupid') !== false);
test('grading.js sends the user of the button', fn() => strpos($unlock, 'userid: parseInt(btn.dataset.userid') !== false);
$boot = file_get_contents(GP_ROOT . '/amd/src/cdcf_bootstrap.js');
test('step 4 page: action unlock (the server knows no "revert")', fn() => strpos($boot, "action: 'revert'") === false
    && strpos($boot, "action: 'unlock'") !== false);
test('step 4 page: sends the group and the user of the copy', fn() => strpos($boot, 'groupid: cfg.groupid') !== false
    && strpos($boot, 'userid: cfg.revertUserid') !== false);
test('step4.php passes the user of the copy', fn() => strpos(file_get_contents(GP_ROOT . '/pages/step4.php'), "'revertUserid'") !== false);
foreach (['grading', 'cdcf_bootstrap'] as $m) {
    test("build $m.min.js up to date", fn() => filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js"));
}

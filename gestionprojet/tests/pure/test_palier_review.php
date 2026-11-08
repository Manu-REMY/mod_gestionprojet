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
 * Fixes of the final review of v2.11.2 (levels).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/lib.php');

use mod_gestionprojet\grille;
use mod_gestionprojet\palier;

/**
 * Source of a plugin file.
 *
 * @param string $path Relative to the plugin root.
 * @return string
 */
function code(string $path): string {
    return file_get_contents(GP_ROOT . '/' . $path);
}

/**
 * Body of a function (top-level: up to "}" in column 0; method: up to "}" indented by 4).
 *
 * @param string $src
 * @param string $name
 * @return string
 */
function fn_body(string $src, string $name): string {
    $start = strpos($src, 'function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $toplevel = $start === 0 || $src[$start - 1] === "\n";
    $stop = strpos($src, $toplevel ? "\n}\n" : "\n    }\n", $start);
    return substr($src, $start, ($stop === false ? strlen($src) : $stop) - $start);
}

// 1. Individual work in a course with groups: the level belongs to the student alone.
test('owner of an individual copy inside a group: the student alone (groupid 0)', fn() =>
    gestionprojet_palier_owner((object) ['groupid' => 7, 'userid' => 42]) === ['groupid' => 0, 'userid' => 42]);
test('owner of a group copy: the group (userid 0)', fn() =>
    gestionprojet_palier_owner((object) ['groupid' => 7, 'userid' => 0]) === ['groupid' => 7, 'userid' => 0]);
$lib = code('lib.php');
test('record: members are the group only for a group copy', fn() =>
    preg_match('/if \(\(int\)\$submission->userid === 0 && \(int\)\$submission->groupid > 0\)/', fn_body($lib, 'gestionprojet_palier_record_locked')) === 1);
foreach (['gestionprojet_palier_state', 'gestionprojet_palier_record_locked'] as $fn) {
    test("$fn uses gestionprojet_palier_owner", fn() => strpos(fn_body($lib, $fn), 'gestionprojet_palier_owner(') !== false);
}

// 2. Autosave writes only the copy, never a stale zones_validees; retire runs under the lock.
foreach (['ajax/autosave.php' => 'case 4: // CDCF', 'classes/external/autosave.php' => 'case 4: // CDCF (student).'] as $file => $marker) {
    $src = code($file);
    $case = substr($src, strpos($src, $marker), strpos($src, 'case 5:', strpos($src, $marker)) - strpos($src, $marker));
    test("$file: step 4 saves only id, interacteurs_data and timemodified", fn() =>
        preg_match("/update_record\\('gestionprojet_cdcf', \\(object\\)\\s*\\[\\s*'id' => \\\$record->id,\\s*'interacteurs_data' => \\\$record->interacteurs_data,\\s*'timemodified' => /", $case) === 1);
}
test('retire runs under the lock of the copy', fn() => strpos(fn_body($lib, 'gestionprojet_palier_retire'), 'gestionprojet_palier_lock(') !== false
    && strpos(fn_body($lib, 'gestionprojet_palier_retire'), '->release()') !== false);

// 3. Reset to the consigne keeps the frozen zones.
test('reset of step 4 goes through gestionprojet_cdcf_enforce_consigne', fn() =>
    strpos(code('classes/reset_helper.php'), 'gestionprojet_cdcf_enforce_consigne(') !== false);

// 4. Combined grade mode: step 4 left out in level mode.
test('combined grade skips step 4 in level mode', fn() => substr_count(fn_body($lib, 'gestionprojet_get_user_grades'), 'step4_paliers') >= 1);

// 5. Unknown criterion names never validate (no positional fallback).
$parts = palier::parts_for(grille::effective_parts(grille::normalize([
    ['id' => 1, 'nom' => 'Fonctions', 'poids' => 6, 'zones' => ['fonctions'], 'palier' => 'S'],
    ['id' => 2, 'nom' => 'Critères', 'poids' => 4, 'zones' => ['criteres'], 'palier' => 'S'],
])), 'S');
test('unknown names, as many as the parts: not validated', fn() =>
    palier::verdict([['name' => 'X', 'score' => 10], ['name' => 'Y', 'score' => 10]], $parts, 80)['valide'] === false);

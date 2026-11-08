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
 * Static checks: the step 4 grid is stored, saved, normalized and backed up.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$src = fn(string $p) => file_get_contents(GP_ROOT . '/' . $p);

test('install.xml declares grille_json in gestionprojet_cdcf_teacher', fn() =>
    preg_match('/<TABLE NAME="gestionprojet_cdcf_teacher".*?<FIELD NAME="grille_json"/s', $src('db/install.xml')) === 1
    && preg_match('/<TABLE NAME="gestionprojet_cdcf_teacher".*?<\/TABLE>/s', $src('db/install.xml'), $m)
    && strpos($m[0], 'grille_json') !== false);
test('upgrade step 2026110803 adds it', fn() =>
    preg_match("/oldversion < 2026110803.*?'grille_json'.*?2026110803, 'gestionprojet'/s", $src('db/upgrade.php')) === 1);
test('version 2.11.0', fn() => strpos($src('version.php'), '2026110803') !== false
    && strpos($src('version.php'), "'2.11.0'") !== false);
foreach (['ajax/autosave.php', 'classes/external/autosave.php'] as $f) {
    test("$f accepts and normalizes grille_json for step 4", function () use ($src, $f) {
        $code = $src($f);
        return preg_match("/'gestionprojet_cdcf_teacher',\s*'fields' => \[[^\]]*'grille_json'/s", $code) === 1
            && strpos($code, 'grille::normalize(') > strpos($code, "'gestionprojet_cdcf_teacher'");
    });
}
test('the backup keeps the grid', fn() =>
    preg_match("/'cdcf_teacher', \['id'\], \[[^\]]*'grille_json'/s", $src('backup/moodle2/backup_gestionprojet_stepslib.php')) === 1);

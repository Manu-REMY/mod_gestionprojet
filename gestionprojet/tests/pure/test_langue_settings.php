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
 * Spelling at step 4: settings, storage, upgrade (request 32, v2.15.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$read = fn(string $path): string => file_get_contents(GP_ROOT . '/' . $path);
$install = $read('db/install.xml');
$upgrade = $read('db/upgrade.php');
foreach (['langue_enabled', 'langue_points', 'langue_accepted', 'langue_json'] as $field) {
    test("install.xml: $field", fn() => strpos($install, 'FIELD NAME="' . $field . '"') !== false);
}
test('install.xml: langue_points twice (instance and evaluation)', fn() => substr_count($install, 'FIELD NAME="langue_points"') === 2);
preg_match('/if \(\$oldversion < 2026111106\) \{.*?upgrade_mod_savepoint\(true, 2026111106/s', $upgrade, $m);
$step = $m[0] ?? '';
test('upgrade: step 2026111106 adds the fields', fn() => $step !== '' && substr_count($step, 'new xmldb_field(') === 5 && strpos($step, 'add_field(') !== false);
test('upgrade: every instance gets spelling on, 2 points', fn() =>
    strpos($step, "set_field('gestionprojet', 'langue_enabled', 1, [])") !== false
    && strpos($step, "set_field('gestionprojet', 'langue_points', 2, [])") !== false);
test('upgrade: gap 6 s becomes 12 s, another choice is kept', fn() =>
    preg_match("/get_config\('mod_gestionprojet', 'ai_interval'\) === '6'.*?set_config\('ai_interval', 12, 'mod_gestionprojet'\)/s", $step) === 1);
test('version 2.15.0 or later', fn() => preg_match('/\$plugin->version = (\d+);/', $read('version.php'), $m) === 1
    && (int)$m[1] >= 2026111106);
$form = $read('mod_form.php');
test('form: the three settings', fn() => strpos($form, "'langue_enabled'") !== false && strpos($form, "'langue_points'") !== false
    && strpos($form, "'langue_accepted'") !== false);
test('form: points between 2 and 10 when on', fn() => strpos($form, "\$errors['langue_points'] = get_string('error_langue_points'") !== false);
test('form: accepted words shown one per line', fn() => strpos($form, 'function data_preprocessing(') !== false);
$lib = $read('lib.php');
test('lib: accepted words stored as JSON on add and update', fn() =>
    substr_count($lib, 'gestionprojet_langue_accepted_from_text(') >= 3);
test('settings: gap 12 s by default', fn() => preg_match("/'mod_gestionprojet\/ai_interval'.*?'12',\s*PARAM_INT/s", $read('settings.php')) === 1);
$backup = $read('backup/moodle2/backup_gestionprojet_stepslib.php');
test('backup: instance and evaluation fields', fn() => strpos($backup, "'langue_enabled', 'langue_points', 'langue_accepted'") !== false
    && strpos($backup, "'langue_json', 'langue_points'") !== false);
$privacy = $read('classes/privacy/provider.php');
test('privacy: langue_json declared and exported', fn() =>
    strpos($privacy, "'langue_json' => 'privacy:metadata:gestionprojet_ai_evaluations:langue_json'") !== false
    && strpos($privacy, "'spelling' => \$eval->langue_json") !== false);
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("strings ($lang)", fn() => !empty($string['langue_enabled']) && !empty($string['langue_enabled_help'])
        && !empty($string['langue_points']) && !empty($string['langue_points_help']) && !empty($string['langue_accepted'])
        && !empty($string['langue_accepted_help']) && !empty($string['error_langue_points'])
        && !empty($string['privacy:metadata:gestionprojet_ai_evaluations:langue_json']));
}

// Conversion of the accepted words.
require_once(GP_ROOT . '/classes/orthographe.php');

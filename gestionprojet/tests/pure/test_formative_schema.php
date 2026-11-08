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
 * Schema and setting of the formative feedback (v2.11.4).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$xml = simplexml_load_string(file_get_contents(GP_ROOT . '/db/install.xml'));
$field = function (string $table, string $name) use ($xml) {
    foreach ($xml->TABLES->TABLE as $t) {
        if ((string)$t['NAME'] === $table) {
            foreach ($t->FIELDS->FIELD as $f) {
                if ((string)$f['NAME'] === $name) {
                    return $f;
                }
            }
        }
    }
    return null;
};
test('install: gestionprojet.formative_limit default 3', fn() => ($f = $field('gestionprojet', 'formative_limit')) && (string)$f['DEFAULT'] === '3');
test('install: ai_evaluations.formatif default 0', fn() => ($f = $field('gestionprojet_ai_evaluations', 'formatif')) && (string)$f['DEFAULT'] === '0');
$up = file_get_contents(GP_ROOT . '/db/upgrade.php');
$step = substr($up, (int)strpos($up, 'oldversion < 2026110807'));
test('upgrade 2026110807 adds both fields', fn() => strpos($step, "'formative_limit'") !== false && strpos($step, "'formatif'") !== false
    && strpos($step, "upgrade_mod_savepoint(true, 2026110807, 'gestionprojet')") !== false);
$form = file_get_contents(GP_ROOT . '/mod_form.php');
test('mod_form: formative_limit with default 3', fn() => strpos($form, "'formative_limit'") !== false
    && strpos($form, "setDefault('formative_limit', 3)") !== false);
test('mod_form: formative_limit validated 0 to 20', fn() => strpos($form, "\$data['formative_limit']") !== false);
$backup = file_get_contents(GP_ROOT . '/backup/moodle2/backup_gestionprojet_stepslib.php');
test('backup: formative_limit and formatif', fn() => strpos($backup, "'formative_limit'") !== false && strpos($backup, "'formatif'") !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['formative_limit', 'formative_limit_help', 'error_formative_limit'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}
foreach (['MATURITY_ALPHA' => 50, 'MATURITY_BETA' => 100, 'MATURITY_RC' => 150, 'MATURITY_STABLE' => 200] as $name => $value) {
    defined($name) || define($name, $value);
}
$plugin = new stdClass();
include(GP_ROOT . '/version.php');
test('version is at least 2026110807 (the formative upgrade step runs)', fn() => $plugin->version >= 2026110807);

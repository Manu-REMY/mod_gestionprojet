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
 * Schema, settings, deletion, backup and privacy of the level validation (v2.11.2).
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
    return file_get_contents(GP_ROOT . '/' . $path);
}

/**
 * Body of a PHP function (from its signature to the next top-level "}").
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
    // A top-level function ends at the first "}" in column 0, a method at the first "}" indented by 4.
    $toplevel = $start === 0 || $src[$start - 1] === "\n";
    $stop = strpos($src, $toplevel ? "\n}\n" : "\n    }\n", $start);
    $stop = $stop === false ? strlen($src) : $stop;
    return substr($src, $start, $stop - $start);
}

$xml = simplexml_load_string(code('db/install.xml'));
$fields = function (string $table) use ($xml): array {
    foreach ($xml->TABLES->TABLE as $t) {
        if ((string)$t['NAME'] === $table) {
            $out = [];
            foreach ($t->FIELDS->FIELD as $f) {
                $out[(string)$f['NAME']] = $f;
            }
            return $out;
        }
    }
    return [];
};
$g = $fields('gestionprojet');
test('install: step4_paliers default 0', fn() => isset($g['step4_paliers']) && (string)$g['step4_paliers']['DEFAULT'] === '0');
test('install: step4_palier_seuil default 80', fn() => isset($g['step4_palier_seuil'])
    && (string)$g['step4_palier_seuil']['DEFAULT'] === '80');
$e = $fields('gestionprojet_ai_evaluations');
foreach (['palier', 'contenu_json', 'palier_valide'] as $f) {
    test("install: ai_evaluations.$f", fn() => isset($e[$f]));
}
test('install: cdcf.zones_validees', fn() => isset($fields('gestionprojet_cdcf')['zones_validees']));
$p = $fields('gestionprojet_palier');
foreach (['id', 'gestionprojetid', 'step', 'groupid', 'userid', 'palier', 'evaluationid', 'membres', 'retire', 'timecreated',
        'timemodified'] as $f) {
    test("install: gestionprojet_palier.$f", fn() => isset($p[$f]));
}

$up = code('db/upgrade.php');
$step = substr($up, (int)strpos($up, 'oldversion < 2026110805'));
foreach (['step4_paliers', 'step4_palier_seuil', 'contenu_json', 'palier_valide', 'zones_validees', 'gestionprojet_palier',
        "upgrade_mod_savepoint(true, 2026110805, 'gestionprojet')"] as $needle) {
    test("upgrade 2026110805 creates $needle", fn() => strpos($step, $needle) !== false);
}

test('delete_instance removes gestionprojet_palier', fn() =>
    strpos(fn_body(code('lib.php'), 'gestionprojet_delete_instance'), "'gestionprojet_palier'") !== false);

$privacy = code('classes/privacy/provider.php');
foreach (['get_metadata', 'get_contexts_for_userid', 'get_users_in_context', 'export_user_data',
        'delete_data_for_all_users_in_context', 'delete_data_for_user', 'delete_data_for_users'] as $fn) {
    test("privacy $fn covers gestionprojet_palier", fn() => strpos(fn_body($privacy, $fn), 'gestionprojet_palier') !== false);
}

$backup = code('backup/moodle2/backup_gestionprojet_stepslib.php');
test('backup has the palier element with membres', fn() =>
    preg_match("/new backup_nested_element\\('palier', \\['id'\\], \\[[^\\]]*'membres'/s", $backup) === 1);
test('backup has the new ai_evaluation fields', fn() => strpos($backup, "'contenu_json'") !== false
    && strpos($backup, "'palier_valide'") !== false);
test('backup has cdcf.zones_validees', fn() => strpos($backup, "'zones_validees'") !== false);
$restore = code('backup/moodle2/restore_gestionprojet_stepslib.php');
test('restore processes palier rows', fn() => strpos($restore, 'function process_gestionprojet_palier(') !== false);
test('restore remaps the evaluation of a level', fn() =>
    strpos(fn_body($restore, 'process_gestionprojet_palier'), "'gestionprojet_ai_evaluation'") !== false
    || strpos($restore, 'after_execute') !== false);

$form = code('mod_form.php');
test('mod_form: step4_paliers', fn() => strpos($form, "'step4_paliers'") !== false);
test('mod_form: step4_palier_seuil', fn() => strpos($form, "'step4_palier_seuil'") !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['step4_paliers', 'step4_paliers_help', 'step4_palier_seuil', 'step4_palier_seuil_help', 'privacy:metadata:palier',
            'error_palier_seuil'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

foreach (['MATURITY_ALPHA' => 50, 'MATURITY_BETA' => 100, 'MATURITY_RC' => 150, 'MATURITY_STABLE' => 200] as $name => $value) {
    defined($name) || define($name, $value);
}
$plugin = new stdClass();
include(GP_ROOT . '/version.php');
test('version 2026110805', fn() => $plugin->version === 2026110805);
test('release 2.11.2', fn() => $plugin->release === '2.11.2');

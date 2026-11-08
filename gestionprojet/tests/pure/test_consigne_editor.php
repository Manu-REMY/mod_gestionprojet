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
 * Consigne editor: lock and gap toggles, enforced display for students (v2.11.1).
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

$js = code('amd/src/cdcf.js');
test('cdcf.js never uses innerHTML', fn() => strpos($js, 'innerHTML') === false);
foreach (['editFlags', 'locked', 'gap', 'niveauLocked', 'niveauGap', 'hint', 'niveauHint'] as $key) {
    test("cdcf.js reads $key", fn() => preg_match('/\b' . $key . '\b/', $js) === 1);
}
test('toggles expose their state with aria-pressed', fn() => strpos($js, 'aria-pressed') !== false);
test('the bootstrap passes editFlags to the editor', fn() => strpos(code('amd/src/cdcf_bootstrap.js'), 'editFlags: cfg.editFlags') !== false);
test('the consigne page turns the toggles on', fn() => strpos(code('pages/step4_provided.php'), "'editFlags'") !== false);
test('the student page shows the copy with the consigne flags', fn() =>
    strpos(code('pages/step4.php'), 'consigne_lock::with_flags') !== false);

$css = code('styles.css');
test('styles: a field to fill in has a dashed border', fn() =>
    preg_match('/\.path-mod-gestionprojet \.gp-cdcf-gap[^{]*\{[^}]*dashed/', $css) === 1);
test('styles: a locked field has its own style', fn() => strpos($css, '.path-mod-gestionprojet .gp-cdcf-locked') !== false);

$keys = ['step4_flag_locked', 'step4_flag_locked_help', 'step4_flag_gap', 'step4_flag_gap_help', 'step4_gap_placeholder'];
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach ($keys as $key) {
        test("$key exists in $l", fn() => !empty($string[$key]));
    }
}
foreach (['step4_provided.php', 'step4.php'] as $page) {
    $src = code('pages/' . $page);
    foreach (['flagLocked' => 'step4_flag_locked', 'gapPlaceholder' => 'step4_gap_placeholder'] as $jskey => $key) {
        test("$page passes the string $key", fn() => strpos($src, "'$jskey'") !== false && strpos($src, "'$key'") !== false);
    }
}

foreach (['cdcf', 'cdcf_bootstrap'] as $module) {
    test("build $module.min.js is newer than its source", fn() =>
        filemtime(GP_ROOT . "/amd/build/$module.min.js") >= filemtime(GP_ROOT . "/amd/src/$module.js"));
}

foreach (['MATURITY_ALPHA' => 50, 'MATURITY_BETA' => 100, 'MATURITY_RC' => 150, 'MATURITY_STABLE' => 200] as $name => $value) {
    defined($name) || define($name, $value);
}
$plugin = new stdClass();
include(GP_ROOT . '/version.php');
test('version is at least 2026110804 (2.11.1)', fn() => $plugin->version >= 2026110804);

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
 * Student page of the level validation (v2.11.2).
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
    return is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
}

$step4 = code('pages/step4.php');
$display = code('pages/student_palier_display.php');
test('step4: no submit button in level mode', fn() =>
    preg_match('/\$canSubmit = [^;]*!\$palierenabled/', $step4) === 1);
test('the progress panel includes the level display (since v2.12.0)', fn() =>
    strpos(code('pages/student_progress_panel.php'), 'student_palier_display.php') !== false);
test('step4: passes the frozen zones to the editor', fn() => strpos($step4, "'frozenZones'") !== false);
test('step4: frozen zones come from gestionprojet_palier_frozen_zones', fn() =>
    strpos($step4, 'gestionprojet_palier_frozen_zones(') !== false);
test('display: uses gestionprojet_palier_state', fn() => strpos($display, 'gestionprojet_palier_state(') !== false);
test('display: validated level with ✓ and a word', fn() => strpos($display, "'✓ '") !== false
    && strpos($display, "'palier_validated'") !== false);
test('display: failed level with ⊘ and a word', fn() => strpos($display, "'⊘ '") !== false
    && strpos($display, "'palier_failed'") !== false);
test('display: the feedback of an attempt is always filtered', fn() =>
    strpos($display, 'answer_leak_filter::student_view(') !== false);
test('display: loads the level buttons', fn() => strpos($display, "'mod_gestionprojet/palier_buttons'") !== false);
test('display: polls a pending level', fn() => strpos($display, "'mod_gestionprojet/student_ai_progress'") !== false);
test('display: no inline script or style', fn() => stripos($display, '<script') === false && stripos($display, '<style') === false);

$buttons = code('amd/src/palier_buttons.js');
test('palier_buttons calls mod_gestionprojet_request_palier', fn() => strpos($buttons, "'mod_gestionprojet_request_palier'") !== false);
test('palier_buttons never uses innerHTML', fn() => $buttons !== '' && strpos($buttons, 'innerHTML') === false);
test('palier_buttons reloads with a cache-buster', fn() => strpos($buttons, "searchParams.set('_'") !== false);
test('cdcf.js reads frozenZones', fn() => strpos(code('amd/src/cdcf.js'), 'frozenZones') !== false);
test('cdcf_bootstrap passes frozenZones', fn() => strpos(code('amd/src/cdcf_bootstrap.js'), 'frozenZones: cfg.frozenZones') !== false);
foreach (['palier_buttons', 'cdcf', 'cdcf_bootstrap'] as $module) {
    test("build $module.min.js is up to date", fn() => is_file(GP_ROOT . "/amd/build/$module.min.js")
        && filemtime(GP_ROOT . "/amd/build/$module.min.js") >= filemtime(GP_ROOT . "/amd/src/$module.js"));
}
test('styles: level banners', fn() => strpos(code('styles.css'), '.path-mod-gestionprojet .gp-palier') !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['palier_request', 'palier_validated', 'palier_failed', 'palier_pending', 'palier_error', 'palier_frozen_help',
            'palier_title', 'palier_score'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

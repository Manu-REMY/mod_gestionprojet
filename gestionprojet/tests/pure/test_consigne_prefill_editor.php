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
 * Consigne editor: three states, links toggle, auto-mark, warnings (v2.11.3).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$js = file_get_contents(GP_ROOT . '/amd/src/cdcf.js');
foreach (['prefill', 'niveauPrefill', 'linksLocked', 'automark', 'noLockWarning', 'prefillNotice', 'prefillBadge', 'flagLinks'] as $k) {
    test("cdcf.js uses $k", fn() => preg_match('/\b' . $k . '\b/', $js) === 1);
}
test('cdcf.js: pre-filled fields get their own class', fn() => strpos($js, "'gp-cdcf-prefill'") !== false);
test('cdcf.js: still no innerHTML', fn() => strpos($js, 'innerHTML') === false);
foreach (['step4_provided.php', 'step4.php'] as $page) {
    $src = file_get_contents(GP_ROOT . '/pages/' . $page);
    foreach (['flagPrefill' => 'step4_flag_prefill', 'prefillBadge' => 'step4_prefill_badge', 'flagLinks' => 'step4_flag_links'] as $jskey => $key) {
        test("$page passes $key", fn() => strpos($src, "'$jskey'") !== false && strpos($src, "'$key'") !== false);
    }
}
test('step4_provided passes the auto-mark and warning strings', fn() =>
    strpos(file_get_contents(GP_ROOT . '/pages/step4_provided.php'), "'step4_nolock_warning'") !== false
    && strpos(file_get_contents(GP_ROOT . '/pages/step4_provided.php'), "'step4_automark'") !== false);
test('step4 passes the pre-filled notice', fn() => strpos(file_get_contents(GP_ROOT . '/pages/step4.php'), "'step4_prefill_notice'") !== false);
$css = file_get_contents(GP_ROOT . '/styles.css');
test('styles: pre-filled field', fn() => strpos($css, '.path-mod-gestionprojet .gp-cdcf-prefill') !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['step4_flag_prefill', 'step4_flag_prefill_help', 'step4_flag_links', 'step4_flag_links_help', 'step4_prefill_badge',
            'step4_prefill_notice', 'step4_automark', 'step4_automark_help', 'step4_nolock_warning'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}
test('build cdcf.min.js is up to date', fn() =>
    filemtime(GP_ROOT . '/amd/build/cdcf.min.js') >= filemtime(GP_ROOT . '/amd/src/cdcf.js'));
foreach (['MATURITY_ALPHA' => 50, 'MATURITY_BETA' => 100, 'MATURITY_RC' => 150, 'MATURITY_STABLE' => 200] as $name => $value) {
    defined($name) || define($name, $value);
}
$plugin = new stdClass();
include(GP_ROOT . '/version.php');
test('version is at least 2026110806 (2.11.3)', fn() => $plugin->version >= 2026110806);

// Final review: the auto-mark asks for a confirmation (it replaces the flags set by hand).
test('auto-mark asks for a confirmation', fn() => strpos($js, 'saveCancelPromise') !== false && strpos($js, "'core/notification'") !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    test("string step4_automark_confirm in $l", fn() => !empty($string['step4_automark_confirm']));
}

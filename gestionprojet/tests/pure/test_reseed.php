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
 * Reseed untouched copies (preco 22) and never block students with a provided consigne (preco 17 bis), v2.11.3.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/lib.php');

/**
 * Source of a plugin file.
 *
 * @param string $path
 * @return string
 */
function code(string $path): string {
    return is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
}

/**
 * Body of a top-level function.
 *
 * @param string $src
 * @param string $name
 * @return string
 */
function top_body(string $src, string $name): string {
    $start = strpos($src, "\nfunction $name(");
    if ($start === false) {
        return '';
    }
    $stop = strpos($src, "\n}\n", $start);
    return substr($src, $start, ($stop === false ? strlen($src) : $stop) - $start);
}

$lib = code('lib.php');
$reseed = top_body($lib, 'gestionprojet_cdcf_reseed_untouched');
test('reseed: defined', fn() => $reseed !== '');
test('reseed: only when the consigne is shown', fn() => strpos($reseed, 'step4_provided') !== false);
test('reseed: drafts only', fn() => strpos($reseed, "'status' => 0") !== false);
test('reseed: never a copy with frozen zones', fn() => strpos($reseed, 'zones_validees') !== false);
test('reseed: never a copy with an AI evaluation', fn() => strpos($reseed, "'gestionprojet_ai_evaluations'") !== false);
test('reseed: only untouched copies', fn() => strpos($reseed, 'consigne_lock::is_untouched(') !== false);
test('reseed: writes the starting copy', fn() => strpos($reseed, 'consigne_lock::seed(') !== false);

$services = code('db/services.php');
test('WS reseed_copies declared with the teacher capability', fn() =>
    preg_match("/'mod_gestionprojet_reseed_copies' => \\[[^\\]]*'capabilities'\\s*=> 'mod\\/gestionprojet:configureteacherpages'/s", $services) === 1);
$ws = code('classes/external/reseed_copies.php');
test('WS reseed_copies checks the capability', fn() => strpos($ws, "require_capability('mod/gestionprojet:configureteacherpages'") !== false);
test('WS reseed_copies validates the context', fn() => strpos($ws, 'self::validate_context($context)') !== false);
$js = code('amd/src/reseed_copies.js');
test('reseed button module calls the WS after a confirmation', fn() => strpos($js, "'mod_gestionprojet_reseed_copies'") !== false
    && strpos($js, 'saveCancelPromise') !== false && strpos($js, 'innerHTML') === false);
test('build reseed_copies.min.js up to date', fn() => is_file(GP_ROOT . '/amd/build/reseed_copies.min.js')
    && filemtime(GP_ROOT . '/amd/build/reseed_copies.min.js') >= filemtime(GP_ROOT . '/amd/src/reseed_copies.js'));
test('consigne page loads the reseed button', fn() => strpos(code('pages/step4_provided.php'), "'mod_gestionprojet/reseed_copies'") !== false);

// Preco 17 bis.
$gp = fn(array $f) => (object) $f;
test('provided step 4 enabled: students not blocked', fn() =>
    gestionprojet_has_provided_student_step($gp(['step4_provided' => 1, 'enable_step4' => 1])) === true);
test('provided step 4 disabled: no', fn() =>
    gestionprojet_has_provided_student_step($gp(['step4_provided' => 1, 'enable_step4' => 0])) === false);
test('step 9 provided and enabled: yes', fn() =>
    gestionprojet_has_provided_student_step($gp(['step9_provided' => 1, 'enable_step9' => 1])) === true);
test('home uses it', fn() => strpos(code('pages/home.php'), 'gestionprojet_has_provided_student_step($gestionprojet)') !== false);

foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['reseed_copies', 'reseed_copies_help', 'reseed_copies_confirm', 'reseed_copies_done'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

// Final review: the consigne on screen is saved before reseeding (same batch, run in order).
test('reseed button saves the consigne shown before reseeding', fn() =>
    ($a = strpos($js, "'mod_gestionprojet_autosave'")) !== false && ($b = strpos($js, "'mod_gestionprojet_reseed_copies'")) !== false && $a < $b);
test('consigne page passes the data field to the reseed button', fn() => strpos(code('pages/step4_provided.php'), "'dataFieldId'") !== false);

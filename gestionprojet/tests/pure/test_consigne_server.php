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
 * The server is the authority on the step 4 consigne locks (v2.11.1).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_evaluator.php');

use mod_gestionprojet\ai_evaluator;
use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\consigne_lock;

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
 * Text between two markers (the first marker included).
 *
 * @param string $code
 * @param string $from
 * @param string $to
 * @return string
 */
function between(string $code, string $from, string $to): string {
    $start = strpos($code, $from);
    if ($start === false) {
        return '';
    }
    $end = strpos($code, $to, $start + strlen($from));
    return substr($code, $start, $end === false ? null : $end - $start);
}

$lib = code('lib.php');
$seeding = between($lib, "if (\$table === 'cdcf'", "// For Essai phase");
test('lib.php seeds the CDCF copy with consigne_lock::seed', fn() => strpos($seeding, 'consigne_lock::seed') !== false);
test('lib.php no longer copies the raw consigne JSON', fn() =>
    strpos($seeding, '$record->interacteurs_data = $provided->interacteurs_data') === false);
test('lib.php defines gestionprojet_cdcf_enforce_consigne', fn() =>
    strpos($lib, 'function gestionprojet_cdcf_enforce_consigne(object $gestionprojet, string $studentjson') !== false);
test('the enforce function uses consigne_lock::enforce',
    fn() => strpos(between($lib, 'function gestionprojet_cdcf_enforce_consigne', "\n}\n"), 'consigne_lock::enforce') !== false);
test('reset to the consigne uses consigne_lock::seed for step 4', fn() =>
    strpos(code('classes/reset_helper.php'), 'consigne_lock::seed') !== false);

foreach (['ajax/autosave.php' => '// CDCF', 'classes/external/autosave.php' => '// CDCF (student).'] as $file => $marker) {
    $src = code($file);
    test("$file: the student step 4 save goes through gestionprojet_cdcf_enforce_consigne", fn() =>
        strpos(between($src, "case 4: $marker", 'case 5:'), 'gestionprojet_cdcf_enforce_consigne') !== false);
    test("$file: only the student save is enforced (not consigne nor teacher modes)", fn() =>
        substr_count($src, 'gestionprojet_cdcf_enforce_consigne') === 1);
}

test('ai_evaluator compares step 4 to the starting copy', fn() =>
    strpos(code('classes/ai_evaluator.php'), 'cdcf_matches_seed(') !== false
    && strpos(between(code('classes/ai_evaluator.php'), 'function detect_no_modifications', 'default:'), 'cdcf_matches_seed') !== false);

$p = ['interactors' => [['id' => 1, 'name' => 'Robot', 'locked' => true], ['id' => 2, 'name' => 'Sol']],
    'fonctionsService' => [['id' => 1, 'description' => 'Rouler', 'interactor1Id' => 1, 'interactor2Id' => 2,
        'criteres' => [['id' => 1, 'description' => 'Vitesse', 'niveau' => 'en m/s', 'niveauGap' => true]]]],
    'contraintes' => []];
$pjson = json_encode($p);
$seedjson = json_encode(consigne_lock::seed(cdcf_helper::normalize($p)));
test('an untouched starting copy matches the seed', fn() => ai_evaluator::cdcf_matches_seed($seedjson, $pjson) === true);
$filled = consigne_lock::seed(cdcf_helper::normalize($p));
$filled['fonctionsService'][0]['criteres'][0]['niveau'] = '0,5 m/s';
test('a filled gap no longer matches', fn() => ai_evaluator::cdcf_matches_seed(json_encode($filled), $pjson) === false);
$plain = ['interactors' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']], 'fonctionsService' => [], 'contraintes' => []];
test('consigne without flag: an identical copy matches (current behaviour)', fn() =>
    ai_evaluator::cdcf_matches_seed(json_encode($plain), json_encode($plain)) === true);
test('invalid JSON never matches', fn() => ai_evaluator::cdcf_matches_seed('{oops', $pjson) === false);

$enforcebody = between(code('lib.php'), 'function gestionprojet_cdcf_enforce_consigne', "\n}\n");
test('enforce wrapper sanitizes flags before any return', fn() =>
    ($pos = strpos($enforcebody, 'consigne_lock::sanitize_student_json')) !== false && $pos < strpos($enforcebody, 'return'));
test('ai_evaluator strips student flags when the flagged copy is not used', fn() =>
    strpos(code('classes/ai_evaluator.php'), 'consigne_lock::sanitize_student_json') !== false);

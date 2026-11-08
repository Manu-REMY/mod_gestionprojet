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
 * No "fonction principale" shown anywhere: the FAST starts from the usage function (decision of 8 Nov, v2.13.1).
 *
 * The internal JSON key fonctionsPrincipales is kept (stored copies), never shown.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

foreach (['fr' => '/fonctions? principales?|\(FP\)|\bFP\d*\b|fonctions? contraintes?|\(FC\)/iu',
        'en' => '/main functions?|\(MF\)|\bFP\d*\b|constraint functions?|\(CF\)/iu'] as $l => $pattern) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    $hits = array_keys(array_filter($string, fn($v) => preg_match($pattern, (string)$v) === 1));
    test("lang $l: no main function nor constraint function shown", fn() => $hits === [] ?: print_r($hits, true) === '');
    test("lang $l: the FAST label speaks of the usage function", fn() =>
        preg_match($l === 'fr' ? "/fonction d'usage/iu" : '/usage function/iu', (string)($string['fast:fp_label'] ?? '')) === 1);
}
$editor = (string)file_get_contents(GP_ROOT . '/amd/src/fast_editor.js');
$diagram = (string)file_get_contents(GP_ROOT . '/amd/src/fast_diagram.js');
$lib = (string)file_get_contents(GP_ROOT . '/lib.php');
test('FAST editor: badge FU, never FP', fn() => strpos($editor, "'FP' +") === false && strpos($editor, "'FU' + (idx + 1)") !== false);
test('FAST diagram: no FP fallback', fn() => strpos($diagram, "|| 'FP'") === false);
test('FAST text for the AI: usage function', fn() => strpos($lib, "'Fonction principale : '") === false
    && strpos($lib, "'Fonction d\\'usage : '") !== false);
foreach (['fast_editor', 'fast_diagram'] as $m) {
    test("build $m.min.js up to date", fn() => filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js"));
}

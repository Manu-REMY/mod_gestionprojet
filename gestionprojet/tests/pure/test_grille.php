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
 * Pure checks of the editable step 4 grading grid model (v2.11.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/grille.php');

use mod_gestionprojet\grille;

$d = grille::default_grid();
test('default grid: the 5 current parts', fn() => count($d) === 5);
test('default grid: weights 4, 6, 4, 3, 3', fn() => array_column($d, 'poids') === [4, 6, 4, 3, 3]);
test('default grid: "Critères" covers criteria, levels and flexibility',
    fn() => $d[2]['zones'] === ['criteres', 'niveaux', 'flexibilite']);
test('default grid: every part is auto', fn() => array_unique(array_column($d, 'evaluee')) === ['auto']);
test('default grid: no level (palier)', fn() => array_unique(array_column($d, 'palier')) === ['']);

foreach ([null, '', '{oops', '[]'] as $bad) {
    test('decode(' . var_export($bad, true) . ') gives the default grid', fn() => grille::decode($bad) === $d);
}
test('decode reads a saved grid', fn() =>
    grille::decode(json_encode([['id' => 1, 'nom' => 'A', 'poids' => 2, 'zones' => ['fonctions']]]))[0]['nom'] === 'A');

test('a part without name is dropped', fn() => grille::normalize([['nom' => '', 'poids' => 5]]) === []);
$n = grille::normalize([['nom' => 'A', 'poids' => '-3', 'zones' => ['nope'], 'palier' => 'X', 'evaluee' => 'peut-être']])[0];
test('negative weight becomes 0', fn() => $n['poids'] === 0);
test('unknown zones become ["ensemble"]', fn() => $n['zones'] === ['ensemble']);
test('unknown level becomes ""', fn() => $n['palier'] === '');
test('unknown "evaluee" becomes auto', fn() => $n['evaluee'] === 'auto');
test('text weight becomes 0', fn() => grille::normalize([['nom' => 'A', 'poids' => 'beaucoup']])[0]['poids'] === 0);
test('zones are kept in ZONES order, without duplicates', fn() =>
    grille::normalize([['nom' => 'A', 'zones' => ['niveaux', 'criteres', 'niveaux']]])[0]['zones'] === ['criteres', 'niveaux']);
$dup = grille::normalize([['id' => 1, 'nom' => 'A'], ['id' => 1, 'nom' => 'B'], ['nom' => 'C']]);
test('duplicate or missing ids are renumbered', fn() => count(array_unique(array_column($dup, 'id'))) === 3
    && !in_array(0, array_column($dup, 'id'), true));

$parts = grille::normalize([
    ['id' => 1, 'nom' => 'I', 'zones' => ['interacteurs'], 'evaluee' => 'auto'],
    ['id' => 2, 'nom' => 'N', 'zones' => ['fonctions'], 'evaluee' => 'non'],
    ['id' => 3, 'nom' => 'O', 'zones' => ['interacteurs'], 'evaluee' => 'oui'],
]);
test('a part set to "non" is not graded', fn() => array_column(grille::effective_parts($parts), 'id') === [1, 3]);
test('an auto part whose zones are all locked is not graded', fn() =>
    array_column(grille::effective_parts($parts, ['interacteurs']), 'id') === [3]);
test('a part set to "oui" is graded even when locked', fn() =>
    in_array(3, array_column(grille::effective_parts($parts, ['interacteurs']), 'id'), true));

test('default weights on 20: 4, 6, 4, 3, 3', fn() => grille::scaled_weights($d) == [1 => 4, 2 => 6, 3 => 4, 4 => 3, 5 => 3]);
$w = fn(array $poids) => grille::scaled_weights(grille::normalize(array_map(
    fn($p, $i) => ['id' => $i + 1, 'nom' => "P$i", 'poids' => $p], $poids, array_keys($poids))));
test('weights 1 and 1 give 10 and 10', fn() => $w([1, 1]) == [1 => 10, 2 => 10]);
test('weights 0 and 0 give 10 and 10', fn() => $w([0, 0]) == [1 => 10, 2 => 10]);
test('weights 1, 1, 1 give 7, 6.5, 6.5', fn() => $w([1, 1, 1]) == [1 => 7, 2 => 6.5, 3 => 6.5]);
test('weights 6, 4, 3 give 9.5, 6, 4.5', fn() => $w([6, 4, 3]) == [1 => 9.5, 2 => 6, 3 => 4.5]);
test('no part: no weight', fn() => grille::scaled_weights([]) === []);

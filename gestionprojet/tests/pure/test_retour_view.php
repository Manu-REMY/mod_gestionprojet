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
 * Feedback window: criteria split into "what works" / "what is missing", submission offered from SAT (v2.17.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\retour_view;

$split = fn(array $c) => retour_view::split_criteria($c);

test('full score -> what works', fn() => $split([['name' => 'Interacteurs', 'score' => 4, 'max' => 4, 'comment' => 'ok']])
    === ['va' => [['name' => 'Interacteurs', 'comment' => 'ok', 'score' => 4.0, 'max' => 4.0]], 'manque' => []]);
test('partial score -> what is missing, with its comment', fn() =>
    $split([['name' => 'Critères', 'score' => 2, 'max' => 4, 'comment' => 'Précise les unités']])
    === ['va' => [], 'manque' => [['name' => 'Critères', 'comment' => 'Précise les unités', 'score' => 2.0, 'max' => 4.0]]]);
test('no max, a comment -> what is missing', fn() => $split([['name' => 'FS', 'comment' => 'À revoir']])['manque']
    === [['name' => 'FS', 'comment' => 'À revoir', 'score' => null, 'max' => null]]);
test('no max, no comment -> ignored', fn() => $split([['name' => 'FS']]) === ['va' => [], 'manque' => []]);
test('not an array, non-numeric score -> no warning', fn() =>
    $split(['x', ['name' => 'A', 'score' => 'abc', 'max' => 'n/a']]) === ['va' => [], 'manque' => []]);
test('max 0 -> never "what works"', fn() => $split([['name' => 'A', 'score' => 0, 'max' => 0]])['va'] === []);
test('string scores are read', fn() => $split([['name' => 'A', 'score' => '3.0', 'max' => '3']])['va']
    === [['name' => 'A', 'comment' => '', 'score' => 3.0, 'max' => 3.0]]);

$lvl = fn(string $r) => ['reached' => $r, 'next' => '', 'missing' => []];
test('SAT reached -> submission offered', fn() => retour_view::propose_submit($lvl('S'), true));
test('TS reached -> submission offered', fn() => retour_view::propose_submit($lvl('TS'), true));
test('FRA reached -> not offered', fn() => !retour_view::propose_submit($lvl('F'), true));
test('nothing reached -> not offered', fn() => !retour_view::propose_submit($lvl(''), true));
test('cannot submit -> not offered', fn() => !retour_view::propose_submit($lvl('S'), false));
test('no level (steps 5 to 9) -> not offered', fn() => !retour_view::propose_submit(null, true));
test('validated level (submitted copy) -> not offered', fn() =>
    !retour_view::propose_submit(['reached' => 'TS', 'next' => '', 'missing' => [], 'validated' => true], true));


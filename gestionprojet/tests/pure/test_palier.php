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
 * Pure rules of the level validation F / S / TS (v2.11.2).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\grille;
use mod_gestionprojet\palier;

$parts = grille::effective_parts(grille::normalize([
    ['id' => 1, 'nom' => 'Fonctions', 'poids' => 6, 'zones' => ['fonctions'], 'palier' => 'S'],
    ['id' => 2, 'nom' => 'Critères', 'poids' => 4, 'zones' => ['criteres'], 'palier' => 'S'],
    ['id' => 3, 'nom' => 'Niveaux', 'poids' => 4, 'zones' => ['niveaux', 'flexibilite'], 'palier' => 'TS'],
    ['id' => 4, 'nom' => 'Cohérence', 'poids' => 2, 'zones' => ['ensemble'], 'palier' => 'TS'],
    ['id' => 5, 'nom' => 'Contraintes', 'poids' => 3, 'zones' => ['contraintes'], 'palier' => ''],
]));

test('paliers_in_grid: S then TS', fn() => palier::paliers_in_grid($parts) === ['S', 'TS']);
test('parts_for(S): Fonctions and Critères', fn() => array_column(palier::parts_for($parts, 'S'), 'nom') === ['Fonctions', 'Critères']);
test('zones_for(S): fonctions, criteres', fn() => palier::zones_for($parts, 'S') === ['fonctions', 'criteres']);
test('zones_for(TS): niveaux, flexibilite (never ensemble)', fn() => palier::zones_for($parts, 'TS') === ['niveaux', 'flexibilite']);
test('a part without level belongs to no level', fn() => palier::parts_for($parts, '') === []);

test('requestable: nothing validated → S', fn() => palier::requestable(['S', 'TS'], []) === ['S']);
test('requestable: S validated → TS', fn() => palier::requestable(['S', 'TS'], ['S']) === ['TS']);
test('requestable: all validated → none', fn() => palier::requestable(['S', 'TS'], ['S', 'TS']) === []);
test('requestable: F, S, TS with F validated → S', fn() => palier::requestable(['F', 'S', 'TS'], ['F']) === ['S']);

// Verdict: the points of each part come from the server grid (scaled weights), never from the AI's "max".
$sparts = palier::parts_for($parts, 'S'); // Fonctions (weight 6 -> 12/20) and Critères (4 -> 8/20).
$ai = fn(array $rows) => array_map(fn($r) => ['name' => $r[0], 'score' => $r[1], 'max' => $r[2] ?? 99], $rows);
test('verdict: 10/12 + 6/8 at 80 % → validated, 80 %', fn() =>
    palier::verdict($ai([['Fonctions', 10], ['Critères', 6]]), $sparts, 80) === ['valide' => true, 'percent' => 80.0]);
test('verdict: 9/12 + 6/8 at 80 % → not validated', fn() => palier::verdict($ai([['Fonctions', 9], ['Critères', 6]]), $sparts, 80)['valide'] === false);
test('verdict: no criterion → not validated, 0 %', fn() => palier::verdict([], $sparts, 80) === ['valide' => false, 'percent' => 0.0]);
test('verdict: no part → not validated', fn() => palier::verdict($ai([['Fonctions', 12]]), [], 0)['valide'] === false);
test('verdict: a fabricated criterion is ignored', fn() =>
    palier::verdict($ai([['Fonctions', 2], ['Critères', 2], ['Bonus', 100, 100]]), $sparts, 50)['valide'] === false);
test('verdict: a score above the part weight counts as the weight', fn() =>
    palier::verdict($ai([['Fonctions', 500], ['Critères', 0]]), $sparts, 61)['valide'] === false);
test('verdict: the AI max is ignored (score 1 of "max 1" on a 12-point part is 1 point)', fn() =>
    palier::verdict($ai([['Fonctions', 1, 1], ['Critères', 8, 8]]), $sparts, 50)['valide'] === false);
test('verdict: a negative score counts as 0', fn() => palier::verdict($ai([['Fonctions', -5], ['Critères', 8]]), $sparts, 41)['valide'] === false);
test('verdict: a duplicate criterion counts once', fn() =>
    palier::verdict($ai([['Fonctions', 12], ['Fonctions', 12], ['Critères', 0]]), $sparts, 61)['valide'] === false);
test('verdict: names match regardless of case and spaces', fn() =>
    palier::verdict($ai([['  fonctions ', 12], ['CRITÈRES', 8]]), $sparts, 100)['valide'] === true);
test('verdict: unmatched names fall back on the order when the counts are equal', fn() =>
    palier::verdict($ai([['Partie 1', 12], ['Partie 2', 8]]), $sparts, 100)['valide'] === true);
test('verdict: a missing part scores 0', fn() => palier::verdict($ai([['Fonctions', 12]]), $sparts, 61)['valide'] === false);
test('verdict: 79.95 % is below 80 % even if it shows 80.0', fn() =>
    palier::verdict($ai([['Fonctions', 12], ['Critères', 3.99]]), $sparts, 80) === ['valide' => false, 'percent' => 80.0]
    || palier::verdict($ai([['Fonctions', 11.99], ['Critères', 4]]), $sparts, 80)['valide'] === false);
test('verdict: missing keys → not validated', fn() => palier::verdict([['name' => 'Fonctions']], $sparts, 0)['valide'] === false);

$frozen = cdcf_helper::normalize([
    'interactors' => [['id' => 1, 'name' => 'Robot'], ['id' => 2, 'name' => 'Sol']],
    'fonctionsService' => [['id' => 1, 'description' => 'Rouler', 'interactor1Id' => 1, 'interactor2Id' => 2,
        'criteres' => [['id' => 1, 'description' => 'Vitesse', 'niveau' => '', 'flexibilite' => '']]]],
    'contraintes' => [['id' => 1, 'description' => 'Budget', 'justification' => 'Mission', 'linkedFsId' => 0]],
]);
$s = $frozen;
$s['fonctionsService'][] = ['id' => 2, 'description' => 'Voler', 'interactor1Id' => 1, 'interactor2Id' => 0, 'criteres' => []];
$s['fonctionsService'][0]['description'] = 'Sauter';
$s['fonctionsService'][0]['criteres'][] = ['id' => 2, 'description' => 'Masse', 'niveau' => '', 'flexibilite' => ''];
$s['fonctionsService'][0]['criteres'][0]['niveau'] = '0,5 m/s';
$sat = palier::freeze($s, $frozen, ['fonctions', 'criteres']);
test('freeze SAT: an FS added by the student is removed', fn() => count($sat['fonctionsService']) === 1);
test('freeze SAT: a modified FS statement is restored', fn() => $sat['fonctionsService'][0]['description'] === 'Rouler');
test('freeze SAT: a criterion added to a frozen FS is removed', fn() => count($sat['fonctionsService'][0]['criteres']) === 1);
test('freeze SAT: the level of a criterion stays open', fn() => $sat['fonctionsService'][0]['criteres'][0]['niveau'] === '0,5 m/s');

$t = $frozen;
$t['fonctionsService'][0]['criteres'][0]['niveau'] = '2 m/s';
$t['fonctionsService'][0]['criteres'][] = ['id' => 2, 'description' => 'Masse', 'niveau' => '', 'flexibilite' => ''];
$ts = palier::freeze($t, $frozen, ['niveaux']);
test('freeze TS: a modified level is restored', fn() => $ts['fonctionsService'][0]['criteres'][0]['niveau'] === '');
test('freeze TS: a criterion added stays (criteria are not frozen)', fn() => count($ts['fonctionsService'][0]['criteres']) === 2);
test('freeze with no zone: copy unchanged', fn() => palier::freeze($s, $frozen, []) === $s);

$u = $frozen;
$u['interactors'][] = ['id' => 3, 'name' => 'Pierre'];
$u['contraintes'][0]['description'] = 'Poids';
$whole = palier::freeze($u, $frozen, ['interacteurs', 'contraintes']);
test('freeze interacteurs: list frozen as a whole', fn() => count($whole['interactors']) === 2);
test('freeze contraintes: list frozen as a whole', fn() => $whole['contraintes'][0]['description'] === 'Budget');
$v = $frozen;
$v['fonctionsService'] = [];
test('freeze SAT: a deleted frozen FS comes back', fn() => count(palier::freeze($v, $frozen, ['fonctions'])['fonctionsService']) === 1);

test('retire_cascade(S) with S, TS validated → S, TS', fn() => palier::retire_cascade('S', ['S', 'TS']) === ['S', 'TS']);
test('retire_cascade(TS) → TS only', fn() => palier::retire_cascade('TS', ['S', 'TS']) === ['TS']);
test('retire_cascade(F) with F, TS validated → F, TS', fn() => palier::retire_cascade('F', ['F', 'TS']) === ['F', 'TS']);

// Preprod run: the AI added details after the part name ("Fonctions de service (verbes, FP, FC)").
$fs = palier::parts_for($parts, 'S');
test('verdict: an AI name starting with the part name matches', fn() =>
    palier::verdict($ai([['Fonctions (verbes, FP)', 12], ['Critères et niveaux', 8], ['Autre', 0]]), $fs, 100)['valide'] === true);
test('verdict: a prefix must end on a word boundary ("Critèresxyz" does not match)', fn() =>
    palier::verdict($ai([['Fonctions', 12], ['Critèresxyz', 8], ['Autre', 0]]), $fs, 61)['valide'] === false);

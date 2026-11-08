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
 * Pre-filled fields, lockable links and untouched copies (v2.11.3).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\cdcf_helper as H;
use mod_gestionprojet\consigne_lock as L;

/**
 * FS by id.
 *
 * @param array $data
 * @param int $id
 * @return array|null
 */
function fs_of(array $data, int $id): ?array {
    foreach ($data['fonctionsService'] as $fs) {
        if ($fs['id'] === $id) {
            return $fs;
        }
    }
    return null;
}

// Normalisation of the three states.
$n = fn(array $flags) => H::normalize(['interactors' => [['id' => 1, 'name' => 'A'] + $flags]])['interactors'][0];
test('prefill is kept', fn() => $n(['prefill' => true]) === ['id' => 1, 'name' => 'A', 'prefill' => true]);
test('locked wins over prefill', fn() => $n(['locked' => true, 'prefill' => true]) === ['id' => 1, 'name' => 'A', 'locked' => true]);
test('gap wins over prefill', fn() => $n(['gap' => true, 'prefill' => true]) === ['id' => 1, 'name' => 'A', 'gap' => true]);
$c = H::normalize(['fonctionsService' => [['id' => 1, 'linksLocked' => true, 'prefill' => true,
    'criteres' => [['id' => 1, 'niveauPrefill' => true, 'locked' => true]]]]])['fonctionsService'][0];
test('niveauPrefill kept next to a locked description', fn() => !empty($c['criteres'][0]['niveauPrefill']) && !empty($c['criteres'][0]['locked']));
test('linksLocked is independent of the statement state', fn() => !empty($c['linksLocked']) && !empty($c['prefill']));
test('no flag: no key added', fn() => $n([]) === ['id' => 1, 'name' => 'A']);

// Consigne of the robot kind.
$p = H::normalize([
    'interactors' => [['id' => 1, 'name' => 'Utilisateur', 'locked' => true], ['id' => 2, 'name' => 'Atmosphère', 'locked' => true]],
    'fonctionsService' => [
        ['id' => 1, 'description' => 'Doit … terrain.', 'interactor1Id' => 1, 'interactor2Id' => 2, 'prefill' => true, 'linksLocked' => true,
            'criteres' => [['id' => 1, 'description' => 'Vitesse', 'niveau' => '… km/h', 'locked' => true, 'niveauPrefill' => true]]],
        ['id' => 2, 'description' => 'Résister au froid', 'interactor1Id' => 2, 'interactor2Id' => 0, 'locked' => true, 'criteres' => []],
    ],
    'contraintes' => [],
]);
$seed = L::seed($p);
test('seed keeps a pre-filled statement', fn() => $seed['fonctionsService'][0]['description'] === 'Doit … terrain.');
test('seed keeps a pre-filled level', fn() => $seed['fonctionsService'][0]['criteres'][0]['niveau'] === '… km/h');
$wf = L::with_flags($seed, $p);
test('with_flags: prefill flag and the consigne text as hint', fn() => !empty($wf['fonctionsService'][0]['prefill'])
    && $wf['fonctionsService'][0]['hint'] === 'Doit … terrain.');
test('with_flags: niveauPrefill and its hint', fn() => !empty($wf['fonctionsService'][0]['criteres'][0]['niveauPrefill'])
    && $wf['fonctionsService'][0]['criteres'][0]['niveauHint'] === '… km/h');
test('with_flags: linksLocked', fn() => !empty($wf['fonctionsService'][0]['linksLocked']));
test('strip_flags removes the new keys', fn() => L::strip_flags($wf) === $seed);

$s = $seed;
$s['fonctionsService'][0]['description'] = 'Doit rouler sur le terrain.';
$s['fonctionsService'][0]['criteres'][0]['niveau'] = '5 km/h';
$s['fonctionsService'][0]['interactor2Id'] = 0;
$e = L::enforce($s, $p);
test('enforce keeps a rewritten pre-filled statement', fn() => fs_of($e, 1)['description'] === 'Doit rouler sur le terrain.');
test('enforce keeps a rewritten pre-filled level', fn() => fs_of($e, 1)['criteres'][0]['niveau'] === '5 km/h');
test('enforce restores the links of a linksLocked FS', fn() => fs_of($e, 1)['interactor2Id'] === 2);
$d = $seed;
$d['fonctionsService'] = [$seed['fonctionsService'][1]];
test('enforce brings back a deleted linksLocked FS', fn() => fs_of(L::enforce($d, $p), 1) !== null);
test('has_locks is true with linksLocked alone', fn() => L::has_locks(H::normalize(['fonctionsService' => [['id' => 1, 'linksLocked' => true]]])));
$onlyprefill = H::normalize(['interactors' => [['id' => 1, 'name' => 'A', 'prefill' => true]]]);
$copy = H::normalize(['interactors' => [['id' => 1, 'name' => 'B']]]);
test('consigne with prefill only: enforce leaves the copy unchanged', fn() => L::enforce($copy, $onlyprefill) === $copy);

// Untouched copies (reseed, preco 22).
test('is_untouched: the current starting copy', fn() => L::is_untouched($seed, $p) === true);
$old = L::strip_flags($p);
test('is_untouched: the starting copy from before the flags', fn() => L::is_untouched($old, $p) === true);
test('is_untouched: false once a field differs', fn() => L::is_untouched($s, $p) === false);
$gapp = H::normalize(['interactors' => [['id' => 1, 'name' => '(A compléter)', 'gap' => true]]]);
test('is_untouched: old copy with "(A compléter)" before the gap flag', fn() =>
    L::is_untouched(H::normalize(['interactors' => [['id' => 1, 'name' => '(A compléter)']]]), $gapp) === true);

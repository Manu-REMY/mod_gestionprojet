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
 * Pure checks of the FS drawn at random per group at step 4 (request 42, v2.16.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\consigne_lock as L;
use mod_gestionprojet\fs_tirage as T;

$consigne = cdcf_helper::decode(file_get_contents(__DIR__ . '/fixtures/robot_martien_consigne.json'));
$model = cdcf_helper::decode(file_get_contents(__DIR__ . '/fixtures/robot_martien_modele.json'));

/**
 * FS of a CDCF by id.
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

// Eligible FS: at least one field to complete. ROBOT MARTIEN FS2 is entirely provided.
test('eligible: the six FS with a field to complete, never the provided FS2',
    fn() => T::eligible($consigne) === [1, 3, 4, 5, 6, 7]);
test('eligible: a consigne without flags has no FS to draw', fn() => T::eligible(L::strip_flags($consigne)) === []);

// Choice: the FS drawn the fewest times in the class first, then in the activity.
$first = fn(array $list) => $list[0];
test('choose: three FS among six', fn() => count(T::choose([1, 3, 4, 5, 6, 7], 3, [], [], $first)) === 3);
test('choose: result sorted, without duplicates', function () use ($first) {
    $r = T::choose([7, 1, 3, 4, 5, 6], 3, [], [], $first);
    return $r === array_values(array_unique($r)) && $r === (function ($x) { sort($x); return $x; })($r);
});
test('choose: the second group of a class gets the three other FS',
    fn() => T::choose([1, 3, 4, 5, 6, 7], 3, [1 => 1, 3 => 1, 4 => 1], [1 => 1, 3 => 1, 4 => 1], $first) === [5, 6, 7]);
test('choose: the class counts before the activity', fn() =>
    T::choose([1, 3, 4, 5, 6, 7], 3, [5 => 1, 6 => 1, 7 => 1], [1 => 9, 3 => 9, 4 => 9], $first) === [1, 3, 4]);
test('choose: ties in the class broken by the activity counts', fn() =>
    T::choose([1, 3, 4, 5, 6, 7], 3, [], [1 => 2, 3 => 2, 4 => 2, 5 => 1], $first) === [5, 6, 7]);
test('choose: n at least the number of eligible FS gives all of them',
    fn() => T::choose([1, 3], 3, [], [], $first) === [1, 3]);
test('choose: nothing to draw gives nothing', fn() => T::choose([], 3, [], [], $first) === []);
test('choose: ties broken by the random pick', function () {
    $last = fn(array $list) => end($list);
    return T::choose([1, 3, 4, 5, 6, 7], 1, [], [], $last) === [7];
});

// The consigne of a copy.
$mine = T::consigne($consigne, $model, [1, 4, 6]);
test('consigne: no draw gives the consigne unchanged', fn() => T::consigne($consigne, $model, []) === $consigne);
test('consigne: a drawn FS keeps its fields to complete', fn() =>
    fs_of($mine, 4)['criteres'][0]['gap'] === true && fs_of($mine, 4)['prefill'] === true);
test('consigne: a drawn FS carries "tiree"', fn() => !empty(fs_of($mine, 4)['tiree']) && empty(fs_of($mine, 4)['donnee']));
test('consigne: a non-drawn FS carries "donnee"', fn() => !empty(fs_of($mine, 3)['donnee']) && empty(fs_of($mine, 3)['tiree']));
test('consigne: the provided FS2 is given too', fn() => !empty(fs_of($mine, 2)['donnee']));
test('consigne: a non-drawn FS statement takes the model text, provided', fn() =>
    fs_of($mine, 3)['description'] === fs_of($model, 3)['description'] && fs_of($mine, 3)['locked'] === true
    && empty(fs_of($mine, 3)['prefill']));
test('consigne: a non-drawn criterion "to fill in" takes the model text, provided', fn() =>
    fs_of($mine, 7)['criteres'][0]['description'] === fs_of($model, 7)['criteres'][0]['description']
    && fs_of($mine, 7)['criteres'][0]['locked'] === true && empty(fs_of($mine, 7)['criteres'][0]['gap']));
test('consigne: a non-drawn level takes the model level and flexibility, provided', fn() =>
    fs_of($mine, 7)['criteres'][1]['niveau'] === fs_of($model, 7)['criteres'][1]['niveau']
    && fs_of($mine, 7)['criteres'][1]['flexibilite'] === fs_of($model, 7)['criteres'][1]['flexibilite']
    && fs_of($mine, 7)['criteres'][1]['niveauLocked'] === true && empty(fs_of($mine, 7)['criteres'][1]['niveauPrefill']));
test('consigne: a provided field of a non-drawn FS is not changed', fn() =>
    fs_of($mine, 5)['criteres'][0]['description'] === fs_of($consigne, 5)['criteres'][0]['description']
    && fs_of($mine, 5)['criteres'][0]['locked'] === true);
test('consigne: interactors and constraints unchanged', fn() =>
    $mine['interactors'] === $consigne['interactors'] && $mine['contraintes'] === $consigne['contraintes']);
test('consigne: the seed of a copy leaves nothing to complete outside the drawn FS', function () use ($mine) {
    $seed = L::seed($mine);
    foreach ([3, 5, 7] as $id) {
        $fs = fs_of($seed, $id);
        if (strpos($fs['description'], '…') !== false) {
            return false;
        }
        foreach ($fs['criteres'] as $c) {
            if ($c['description'] === '' || $c['niveau'] === '' || strpos($c['niveau'] . $c['description'], '…') !== false) {
                return false;
            }
        }
    }
    return true;
});
test('consigne: the seed still has the drawn FS to complete', fn() => fs_of(L::seed($mine), 6)['criteres'][0]['description'] === '');
test('consigne: a drawn id unknown to the consigne is ignored', fn() =>
    T::consigne($consigne, $model, [1, 4, 6, 99]) === $mine);

// Fields the model does not fill.
test('missing: the ROBOT MARTIEN model fills every field to complete', fn() => T::missing($consigne, $model) === []);
$holed = $model;
$holed['fonctionsService'][6]['criteres'][0]['description'] = '';
unset($holed['fonctionsService'][3]);
$holed['fonctionsService'] = array_values($holed['fonctionsService']);
test('missing: an empty model text and a missing model FS are listed', fn() =>
    T::missing($consigne, $holed) === [['fs' => 4, 'champ' => 'fs'], ['fs' => 4, 'champ' => 'critere'],
        ['fs' => 4, 'champ' => 'niveau'], ['fs' => 7, 'champ' => 'critere']]);
test('consigne: a field the model does not fill stays to complete', function () use ($consigne, $holed) {
    $c = fs_of(T::consigne($consigne, $holed, [1]), 7)['criteres'][0];
    return !empty($c['gap']) && empty($c['locked']);
});

// Flags of the copy: tiree and donnee reach the page, never the stored copy.
$copy = L::seed($mine);
$flagged = L::with_flags($copy, $mine);
test('with_flags: the page knows the drawn and the given FS', fn() =>
    !empty(fs_of($flagged, 1)['tiree']) && !empty(fs_of($flagged, 2)['donnee']));
test('strip_flags: tiree and donnee are never stored', fn() =>
    !isset(fs_of(L::strip_flags($flagged), 1)['tiree']) && !isset(fs_of(L::strip_flags($flagged), 2)['donnee']));
test('enforce: a forged text in a given FS gets the model text back', function () use ($flagged, $mine, $model) {
    $forged = $flagged;
    foreach ($forged['fonctionsService'] as &$fs) {
        if ($fs['id'] === 3) {
            $fs['description'] = 'Autre chose';
        }
    }
    unset($fs);
    return fs_of(L::enforce($forged, $mine), 3)['description'] === fs_of($model, 3)['description'];
});
test('is_untouched: the seed of the copy consigne is a starting copy', fn() => L::is_untouched($copy, $mine));

// Criteria added by hand to a given FS are dropped (the page offers no button there).
$added = $copy;
foreach ($added['fonctionsService'] as &$fs) {
    if ($fs['id'] === 3) {
        $fs['criteres'][] = ['id' => 9, 'description' => 'En plus', 'niveau' => '', 'flexibilite' => ''];
    }
}
unset($fs);
test('given_shape: a criterion added to a given FS is dropped', fn() => T::given_shape($added, $mine) === $copy);
test('given_shape: a copy without draw is unchanged', fn() => T::given_shape($added, $consigne) === $added);

// Predefined subjects (precision of 17/11 15h45): the teacher writes FS numbers (rank in the consigne), one subject a line.
$p = T::parse_sujets("FS1, FS4, FS6\n\n 3 5 7 \n", $consigne);
test('parse_sujets: one subject a line, blank lines skipped', fn() => $p['sujets'] === [[1, 4, 6], [3, 5, 7]] && !$p['erreurs']);
test('parse_sujets: duplicates removed and ids sorted', fn() => T::parse_sujets('6, 1, 6', $consigne)['sujets'] === [[1, 6]]);
test('parse_sujets: an FS with nothing to complete is refused', fn() =>
    T::parse_sujets("1, 2, 4", $consigne)['erreurs'] === [['ligne' => 1, 'fs' => 2]]);
test('parse_sujets: a number beyond the consigne is refused', fn() =>
    T::parse_sujets("1, 4\n1, 9", $consigne)['erreurs'] === [['ligne' => 2, 'fs' => 9]]);
test('parse_sujets: ranks, not ids, when they differ', function () use ($consigne) {
    $moved = $consigne;
    $moved['fonctionsService'] = array_reverse($moved['fonctionsService']);
    return T::parse_sujets('FS1', $moved)['sujets'] === [[7]];
});
test('format_sujets: back to FS numbers', fn() => T::format_sujets([[1, 4, 6], [3, 5, 7]], $consigne) === "FS1, FS4, FS6\nFS3, FS5, FS7");
test('rangs: FS numbers of ids', fn() => T::rangs($consigne, [7, 1]) === [1, 7]);
test('sujet_of: index of the subject of a draw', fn() => T::sujet_of([[1, 4, 6], [3, 5, 7]], [3, 5, 7]) === 1
    && T::sujet_of([[1, 4, 6]], [1, 4]) === null);
test('choose_sujet: the subject drawn the fewest times in the class', fn() =>
    T::choose_sujet([[1, 4, 6], [3, 5, 7]], [0 => 2, 1 => 1], [], $first) === [3, 5, 7]);
test('choose_sujet: no subject gives nothing', fn() => T::choose_sujet([], [], [], $first) === []);
test('decode_sujets: stored JSON read back, junk dropped', fn() =>
    T::decode_sujets('[[1,4,6],[],"x",[3,"5",7]]') === [[1, 4, 6], [3, 5, 7]] && T::decode_sujets(null) === []);

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

// A criterion added by hand to a given FS is kept: student text is never dropped (review of 2.16.0).
$added = $flagged;
foreach ($added['fonctionsService'] as &$fs) {
    if ($fs['id'] === 3) {
        $fs['criteres'][] = ['id' => 9, 'description' => 'En plus', 'niveau' => '', 'flexibilite' => ''];
    }
}
unset($fs);
test('enforce: a criterion added to a given FS is kept', fn() =>
    in_array('En plus', array_column(fs_of(L::enforce($added, $mine), 3)['criteres'], 'description'), true));

// Back to "every FS": the fields given with the model text get back the shared starting text; student text stays.
$released = T::release($copy, $mine, $consigne);
test('release: a given FS statement gets back its pre-filled text', fn() =>
    fs_of($released, 3)['description'] === fs_of($consigne, 3)['description']);
test('release: a given criterion "to fill in" is emptied', fn() => fs_of($released, 7)['criteres'][0]['description'] === '');
test('release: a given level gets back its pre-filled text', fn() =>
    fs_of($released, 7)['criteres'][1]['niveau'] === fs_of($consigne, 7)['criteres'][1]['niveau']);
test('release: the copy is the shared starting copy again', fn() => $released === L::seed($consigne));
$edited = $copy;
foreach ($edited['fonctionsService'] as &$fs) {
    if ($fs['id'] === 6) {
        $fs['criteres'][0]['description'] = 'Délai de réponse';
    }
    if ($fs['id'] === 1) {
        $fs['criteres'][0]['niveau'] = '2 kb/s';
    }
}
unset($fs);
$released = T::release($edited, $mine, $consigne);
test('release: a drawn FS keeps the student text', fn() => fs_of($released, 1)['criteres'][0]['niveau'] === '2 kb/s');
test('release: a given field the students changed keeps their text', function () use ($mine, $consigne, $copy) {
    $changed = $copy;
    foreach ($changed['fonctionsService'] as &$fs) {
        if ($fs['id'] === 7) {
            $fs['criteres'][0]['description'] = 'Durée de recharge';
        }
    }
    unset($fs);
    return fs_of(T::release($changed, $mine, $consigne), 7)['criteres'][0]['description'] === 'Durée de recharge';
});

// A draw whose FS left the consigne no longer applies.
test('valid: drawn FS still to complete', fn() => T::valid([1, 4, 6], $consigne) === [1, 4, 6]);
test('valid: a deleted FS or an FS now provided is left out', fn() => T::valid([1, 2, 99], $consigne) === [1]);

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
 * Pure checks of the lock and gap flags of the step 4 consigne (v2.11.1).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\consigne_lock as L;

const FLAG_KEYS = ['locked', 'gap', 'niveauLocked', 'niveauGap', 'hint', 'niveauHint'];

/**
 * True when no element of the CDCF array carries a flag key.
 *
 * @param array $data
 * @return bool
 */
function no_flags(array $data): bool {
    $found = false;
    array_walk_recursive($data, function ($v, $k) use (&$found) {
        if (in_array($k, FLAG_KEYS, true)) {
            $found = true;
        }
    });
    return !$found;
}

/**
 * Find an FS by id.
 *
 * @param array $data
 * @param int $id
 * @return array|null
 */
function fs_by_id(array $data, int $id): ?array {
    foreach ($data['fonctionsService'] as $fs) {
        if ($fs['id'] === $id) {
            return $fs;
        }
    }
    return null;
}

$p = cdcf_helper::normalize([
    'interactors' => [
        ['id' => 1, 'name' => 'Robot', 'locked' => true],
        ['id' => 2, 'name' => 'Sol martien'],
    ],
    'fonctionsService' => [
        ['id' => 1, 'description' => 'Se déplacer sur le sol', 'interactor1Id' => 1, 'interactor2Id' => 2, 'locked' => true,
            'criteres' => [
                ['id' => 1, 'description' => 'Masse', 'niveau' => 'en kg', 'flexibilite' => '', 'locked' => true, 'niveauGap' => true],
                ['id' => 2, 'description' => 'Vitesse ?', 'niveau' => '', 'flexibilite' => '', 'gap' => true],
            ]],
        ['id' => 2, 'description' => 'Résister au froid', 'interactor1Id' => 2, 'interactor2Id' => 0,
            'criteres' => [['id' => 1, 'description' => 'Température', 'niveau' => '-80 °C', 'flexibilite' => '']]],
    ],
    'contraintes' => [
        ['id' => 1, 'description' => 'Budget', 'justification' => 'Mission', 'linkedFsId' => 0, 'gap' => true],
    ],
]);

// Normalize keeps flags, only when true.
test('normalize keeps "locked" on an interactor', fn() => $p['interactors'][0]['locked'] === true);
test('normalize adds no flag key to an unflagged interactor', fn() => !array_key_exists('locked', $p['interactors'][1])
    && !array_key_exists('gap', $p['interactors'][1]));
test('normalize keeps "locked" and "niveauGap" on criterion 1', fn() =>
    $p['fonctionsService'][0]['criteres'][0]['locked'] === true && $p['fonctionsService'][0]['criteres'][0]['niveauGap'] === true);
test('normalize adds no flag key to FS2', fn() => no_flags($p['fonctionsService'][1]));
test('normalize: locked wins over gap', fn() =>
    cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'A', 'locked' => true, 'gap' => true]]])['interactors'][0]
        === ['id' => 1, 'name' => 'A', 'locked' => true]);
test('normalize: niveauLocked wins over niveauGap', fn() => cdcf_helper::normalize(['fonctionsService' => [[
    'id' => 1, 'criteres' => [['id' => 1, 'niveauLocked' => true, 'niveauGap' => true]]]]])['fonctionsService'][0]['criteres'][0]
        === ['id' => 1, 'description' => '', 'niveau' => '', 'flexibilite' => '', 'niveauLocked' => true]);
test('normalize: a false flag is not written', fn() =>
    cdcf_helper::normalize(['contraintes' => [['id' => 1, 'locked' => false]]])['contraintes'][0]
        === ['id' => 1, 'description' => '', 'justification' => '', 'linkedFsId' => 0]);
// v2.12.4: normalize keeps the starting text the page showed (enforce reads it); a student save never stores it.
test('hint keys are never stored (stripped on every student save)', fn() =>
    !array_key_exists('hint', L::strip_flags(cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'A', 'hint' => 'x']]]))['interactors'][0])
    && strpos(L::sanitize_student_json('{"interactors":[{"id":1,"name":"A","prefill":true,"hint":"x"}]}'), 'hint') === false);

// Seed.
$seed = L::seed($p);
test('seed keeps a locked criterion description', fn() => $seed['fonctionsService'][0]['criteres'][0]['description'] === 'Masse');
test('seed empties a "niveau à compléter"', fn() => $seed['fonctionsService'][0]['criteres'][0]['niveau'] === '');
test('seed empties a gap criterion description', fn() => $seed['fonctionsService'][0]['criteres'][1]['description'] === '');
test('seed empties a gap constraint (description and justification)', fn() =>
    $seed['contraintes'][0]['description'] === '' && $seed['contraintes'][0]['justification'] === '');
test('seed keeps unflagged content', fn() => $seed['fonctionsService'][1]['criteres'][0]['niveau'] === '-80 °C');
test('seed carries no flag', fn() => no_flags($seed));

// With flags.
$wf = L::with_flags($seed, $p);
test('with_flags puts "locked" on criterion 1', fn() => ($wf['fonctionsService'][0]['criteres'][0]['locked'] ?? false) === true);
test('with_flags gives the level hint of criterion 1', fn() => ($wf['fonctionsService'][0]['criteres'][0]['niveauHint'] ?? '') === 'en kg');
test('with_flags gives the hint of a gap constraint', fn() => ($wf['contraintes'][0]['hint'] ?? '') === 'Budget');
test('with_flags marks a locked interactor', fn() => ($wf['interactors'][0]['locked'] ?? false) === true);
test('with_flags leaves FS2 unflagged', fn() => no_flags($wf['fonctionsService'][1]));
test('with_flags matches criteria by FS id and criterion id', fn() =>
    no_flags(L::with_flags(cdcf_helper::normalize(['fonctionsService' => [['id' => 2, 'criteres' => [['id' => 1]]]]]), $p)
        ['fonctionsService']));

// Enforce.
$s = $seed;
$s['fonctionsService'][0]['criteres'][0]['niveau'] = '2 kg';
$s['fonctionsService'][0]['criteres'][1]['description'] = 'Vitesse';
$e = L::enforce($s, $p);
test('enforce keeps an open level typed by the student', fn() => fs_by_id($e, 1)['criteres'][0]['niveau'] === '2 kg');
test('enforce keeps a gap criterion filled by the student', fn() => fs_by_id($e, 1)['criteres'][1]['description'] === 'Vitesse');
test('enforce output carries no flag', fn() => no_flags(L::enforce($wf, $p)));

$s2 = $seed;
$s2['fonctionsService'] = [$seed['fonctionsService'][1]];
$e2 = L::enforce($s2, $p);
test('enforce restores a deleted locked FS', fn() => fs_by_id($e2, 1) !== null);
test('enforce restores it at its rank in the consigne (first)', fn() => $e2['fonctionsService'][0]['id'] === 1);
test('restored FS has its statement and interactors', fn() => $e2['fonctionsService'][0]['description'] === 'Se déplacer sur le sol'
    && $e2['fonctionsService'][0]['interactor1Id'] === 1 && $e2['fonctionsService'][0]['interactor2Id'] === 2);
test('restored FS has its locked criterion "Masse"', fn() => $e2['fonctionsService'][0]['criteres'][0]['description'] === 'Masse');

$s3 = $seed;
$s3['fonctionsService'][0]['description'] = 'Voler';
$s3['fonctionsService'][0]['interactor2Id'] = 0;
$s3['fonctionsService'][0]['criteres'][0]['description'] = 'Poids';
$s3['interactors'][0]['name'] = 'Drone';
$e3 = L::enforce($s3, $p);
test('enforce restores a modified locked FS statement', fn() => fs_by_id($e3, 1)['description'] === 'Se déplacer sur le sol');
test('enforce restores the interactors of a locked FS', fn() => fs_by_id($e3, 1)['interactor2Id'] === 2);
test('enforce restores a locked criterion', fn() => fs_by_id($e3, 1)['criteres'][0]['description'] === 'Masse');
test('enforce restores a locked interactor name', fn() => $e3['interactors'][0]['name'] === 'Robot');

$s4 = $seed;
$s4['fonctionsService'] = [$seed['fonctionsService'][0]];
test('enforce lets the student delete an unlocked FS', fn() => fs_by_id(L::enforce($s4, $p), 2) === null);

$s5 = $seed;
$s5['interactors'] = [$seed['interactors'][0]];
test('enforce restores an interactor used by a locked FS', fn() =>
    in_array(2, array_column(L::enforce($s5, $p)['interactors'], 'id'), true));

$s6 = $seed;
$s6['fonctionsService'][] = ['id' => 1, 'description' => 'Copie', 'interactor1Id' => 1, 'interactor2Id' => 0, 'criteres' => []];
$e6 = L::enforce($s6, $p);
$ids = array_column($e6['fonctionsService'], 'id');
test('duplicate FS ids: every id is unique after enforce', fn() => count($ids) === count(array_unique($ids)));
test('duplicate FS ids: the duplicate is kept with a new id', fn() => in_array('Copie', array_column($e6['fonctionsService'], 'description'), true)
    && fs_by_id($e6, 1)['description'] === 'Se déplacer sur le sol');

$s7 = $seed;
$s7['fonctionsService'][1]['criteres'] = [];
$s7['fonctionsService'][0]['criteres'] = [$seed['fonctionsService'][0]['criteres'][1]];
$e7 = L::enforce($s7, $p);
test('enforce restores a deleted locked criterion at its rank', fn() => fs_by_id($e7, 1)['criteres'][0]['id'] === 1
    && fs_by_id($e7, 1)['criteres'][0]['description'] === 'Masse');

$pfs = cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']],
    'fonctionsService' => [['id' => 3, 'description' => 'F', 'interactor1Id' => 1,
        'criteres' => [['id' => 1, 'description' => 'C', 'locked' => true]]]]]);
$e8 = L::enforce(cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]]), $pfs);
test('a locked criterion brings back its deleted (unlocked) FS', fn() => fs_by_id($e8, 3) !== null
    && fs_by_id($e8, 3)['criteres'][0]['description'] === 'C');

// No lock: the student copy is untouched.
$plain = cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'A']],
    'fonctionsService' => [['id' => 1, 'description' => 'F', 'interactor1Id' => 1, 'criteres' => [['id' => 1, 'description' => 'x', 'gap' => true]]]]]);
$any = cdcf_helper::normalize(['interactors' => [['id' => 5, 'name' => 'Z']], 'fonctionsService' => [
    ['id' => 1, 'description' => 'G', 'interactor1Id' => 5], ['id' => 1, 'description' => 'H', 'interactor1Id' => 5]]]);
test('has_locks is false without lock (a gap is not a lock)', fn() => L::has_locks($plain) === false);
test('has_locks is true with a lock', fn() => L::has_locks($p) === true);
test('consigne without lock: enforce returns the copy unchanged', fn() => L::enforce($any, $plain) === $any);

// Locked zones.
test('locked_zones of the reference consigne is empty', fn() => L::locked_zones($p) === []);
$all = $p;
foreach ($all['interactors'] as &$i) {
    $i['locked'] = true;
}
unset($i);
foreach ($all['fonctionsService'] as &$fs) {
    $fs['locked'] = true;
}
unset($fs);
test('all interactors and FS locked → interacteurs, fonctions', fn() => L::locked_zones($all) === ['interacteurs', 'fonctions']);
$every = $all;
foreach ($every['fonctionsService'] as &$fs) {
    foreach ($fs['criteres'] as &$c) {
        $c = ['id' => $c['id'], 'description' => $c['description'], 'niveau' => $c['niveau'], 'flexibilite' => '',
            'locked' => true, 'niveauLocked' => true];
    }
    unset($c);
}
unset($fs);
$every['contraintes'] = [];
test('no constraint → "contraintes" never locked; levels lock levels and flexibility', fn() =>
    L::locked_zones($every) === ['interacteurs', 'fonctions', 'criteres', 'niveaux', 'flexibilite']);
test('empty consigne → no locked zone', fn() => L::locked_zones(cdcf_helper::normalize(['interactors' => []])) === []);

test('strip_flags removes every flag key', fn() => no_flags(L::strip_flags($wf)));



// Review fix: an element written by the student is never overwritten by a locked consigne element with the same id.
$texts = function (array $list, string $key): array {
    return array_column($list, $key);
};
$cons1 = cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'Robot'], ['id' => 2, 'name' => 'Sol']],
    'fonctionsService' => [['id' => 1, 'description' => 'F1', 'interactor1Id' => 1, 'criteres' => [['id' => 1, 'description' => 'c']]],
        ['id' => 2, 'description' => 'F2', 'interactor1Id' => 2, 'criteres' => [['id' => 1, 'description' => 'd']]]]]);
$stud = L::seed($cons1);
$stud['fonctionsService'][] = ['id' => 3, 'description' => 'MY OWN WORK', 'interactor1Id' => 1, 'interactor2Id' => 0,
    'criteres' => [['id' => 1, 'description' => 'mine', 'niveau' => '5 kg', 'flexibilite' => '']]];
$cons2 = $cons1;
$cons2['fonctionsService'][] = ['id' => 3, 'description' => 'Teacher FS3', 'interactor1Id' => 2, 'interactor2Id' => 0, 'locked' => true,
    'criteres' => [['id' => 1, 'description' => 't', 'niveau' => '', 'flexibilite' => '']]];
$r = L::enforce($stud, cdcf_helper::normalize($cons2));
$fsids = $texts($r['fonctionsService'], 'id');
test('id collision: the student FS text is kept', fn() => in_array('MY OWN WORK', $texts($r['fonctionsService'], 'description'), true));
test('id collision: the student FS keeps its criteria', fn() => array_values(array_filter($r['fonctionsService'],
    fn($f) => $f['description'] === 'MY OWN WORK'))[0]['criteres'][0]['niveau'] === '5 kg');
test('id collision: the locked consigne FS 3 is there', fn() => fs_by_id($r, 3)['description'] === 'Teacher FS3');
test('id collision: ids stay unique', fn() => count($fsids) === count(array_unique($fsids)));

$cons3 = cdcf_helper::normalize($cons1);
$cons3['fonctionsService'][0]['criteres'][] = ['id' => 2, 'description' => 'Teacher crit', 'niveau' => '', 'flexibilite' => '', 'locked' => true];
$stud3 = L::seed($cons1);
$stud3['fonctionsService'][0]['criteres'][] = ['id' => 2, 'description' => 'Student crit', 'niveau' => '3 m', 'flexibilite' => ''];
$r3 = L::enforce($stud3, $cons3);
$crits = $texts(fs_by_id($r3, 1)['criteres'], 'description');
test('criterion id collision: both criteria are there', fn() => in_array('Student crit', $crits, true) && in_array('Teacher crit', $crits, true));
test('criterion id collision: ids stay unique', fn() =>
    count(array_unique($texts(fs_by_id($r3, 1)['criteres'], 'id'))) === count($crits));

$cons4 = $cons1;
$cons4['interactors'][1]['locked'] = true;
$stud4 = L::seed($cons1);
$stud4['interactors'][1]['name'] = 'Sable';
$r4 = L::enforce($stud4, cdcf_helper::normalize($cons4));
$sable = array_values(array_filter($r4['interactors'], fn($i) => $i['name'] === 'Sable'));
test('interactor collision: the student name is kept on a new interactor', fn() => count($sable) === 1 && $sable[0]['id'] !== 2);
test('interactor collision: the locked consigne interactor 2 is there', fn() =>
    array_values(array_filter($r4['interactors'], fn($i) => $i['id'] === 2))[0]['name'] === 'Sol');
test('interactor collision: the student FS follow their interactor', fn() => fs_by_id($r4, 2)['interactor1Id'] === $sable[0]['id']);
test('an empty field on a locked id is simply filled (no copy)', fn() =>
    count(L::enforce(cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => ''], ['id' => 2, 'name' => 'Sol']]]),
        cdcf_helper::normalize($cons4))['interactors']) === 2);

// Review fix: flags sent by the browser are never stored, whatever the consigne.
$withflags = json_encode(['interactors' => [['id' => 1, 'name' => 'A', 'locked' => true, 'hint' => 'x']]]);
test('sanitize_student_json removes flags', fn() => no_flags(json_decode(L::sanitize_student_json($withflags), true)));
$raw = '{"interactors":[{"id":1,"name":"A"}],"fonctionsService":[],"contraintes":[]}';
test('sanitize_student_json returns flag-free JSON byte for byte', fn() => L::sanitize_student_json($raw) === $raw);
test('sanitize_student_json leaves invalid JSON unchanged', fn() => L::sanitize_student_json('{oops') === '{oops');
$arrayflag = '{"interactors":[{"id":1,"name":"A","locked":[1]}],"fonctionsService":[{"id":1,"criteres":[{"id":1,"niveauGap":{"x":1}}]}]}';
test('sanitize_student_json removes flags whose value is an array', fn() =>
    no_flags(cdcf_helper::normalize(json_decode(L::sanitize_student_json($arrayflag), true))));

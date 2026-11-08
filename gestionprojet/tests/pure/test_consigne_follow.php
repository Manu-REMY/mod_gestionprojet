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
 * A consigne changed after copies were started (request 23, v2.12.3): elements matched by id, no duplicate.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\consigne_lock as L;

/**
 * Criteria of an FS of a copy.
 *
 * @param array $data
 * @param int $fsid
 * @return array
 */
function crits(array $data, int $fsid): array {
    foreach ($data['fonctionsService'] as $fs) {
        if ($fs['id'] === $fsid) {
            return $fs['criteres'];
        }
    }
    return [];
}

/**
 * Consigne of the robot, before or after the teacher renamed two provided criteria and two pre-filled ones.
 *
 * @param bool $after
 * @param bool $added A third criterion added in FS1.
 * @return array
 */
function robot(bool $after, bool $added = false): array {
    $c = [
        ['id' => 1, 'description' => $after ? 'Hauteur d\'obstacle franchissable (marche)'
            : 'Capacité de franchissement d\'un obstacle (marche)', 'niveau' => '≥ 5 cm', 'flexibilite' => '',
            'locked' => true, 'niveauLocked' => true],
        ['id' => 2, 'description' => $after ? 'Pente maximale gravie …' : 'Capacité d\'ascension …',
            'niveau' => '', 'flexibilite' => '', 'prefill' => true, 'niveauGap' => true],
    ];
    if ($added) {
        $c[] = ['id' => 3, 'description' => 'Vitesse', 'niveau' => '', 'flexibilite' => '', 'locked' => true,
            'niveauGap' => true];
    }
    return cdcf_helper::normalize([
        'interactors' => [['id' => 1, 'name' => 'Sol martien', 'locked' => true], ['id' => 2, 'name' => 'Opérateur']],
        'fonctionsService' => [
            ['id' => 1, 'description' => 'Se déplacer sur le sol martien', 'interactor1Id' => 1, 'interactor2Id' => 0,
                'locked' => true, 'criteres' => $c],
        ],
        'contraintes' => [['id' => 1, 'description' => $after ? 'Masse ≤ 2 kg' : 'Masse < 2 kg', 'justification' => '',
            'linkedFsId' => 0, 'locked' => true]],
    ]);
}

$old = robot(false);
$new = robot(true);

// A copy the student started: level of criterion 2 written.
$started = L::seed($old);
$started['fonctionsService'][0]['criteres'][1]['niveau'] = '≥ 15°';

$followed = L::follow($started, $old, $new);
test('follow: no duplicate, the provided criterion is replaced', fn() => count(crits($followed, 1)) === 2
    && crits($followed, 1)[0]['description'] === 'Hauteur d\'obstacle franchissable (marche)');
test('follow: an untouched pre-filled criterion takes the new text', fn() =>
    crits($followed, 1)[1]['description'] === 'Pente maximale gravie …');
test('follow: what the student wrote is kept', fn() => crits($followed, 1)[1]['niveau'] === '≥ 15°');
test('follow: a provided constraint renamed is replaced, not duplicated', fn() => count($followed['contraintes']) === 1
    && $followed['contraintes'][0]['description'] === 'Masse ≤ 2 kg');
test('follow: the result is what enforce keeps (stable)', fn() => L::enforce($followed, $new) === $followed);

// The student changed the pre-filled text: kept.
$edited = $started;
$edited['fonctionsService'][0]['criteres'][1]['description'] = 'Pente de 20° gravie';
test('follow: a pre-filled text the student changed is kept', fn() =>
    crits(L::follow($edited, $old, $new), 1)[1]['description'] === 'Pente de 20° gravie');

// Untouched copy: the new starting copy, even when a criterion was added.
$withadded = robot(true, true);
test('follow: an untouched copy gets the new starting copy (criterion added)', fn() =>
    L::follow(L::seed($old), $old, $withadded) === L::seed($withadded));
test('follow: a started copy gets the added provided criterion once', fn() =>
    count(crits(L::follow($started, $old, $withadded), 1)) === 3);

// Text the student wrote in a field the teacher locks now: still kept (under a new id), as before.
$open = cdcf_helper::normalize(['interactors' => $old['interactors'], 'contraintes' => $old['contraintes'],
    'fonctionsService' => [array_merge($old['fonctionsService'][0], ['criteres' => [
        array_merge($old['fonctionsService'][0]['criteres'][0], ['locked' => false]),
        $old['fonctionsService'][0]['criteres'][1]]])]]);
$mine = L::seed($open);
$mine['fonctionsService'][0]['criteres'][0]['description'] = 'Ma marche à moi';
$locked = L::follow($mine, $open, $new);
$texts = array_column(crits($locked, 1), 'description');
test('follow: student text in a field locked now is kept next to the provided one', fn() =>
    in_array('Ma marche à moi', $texts, true) && in_array('Hauteur d\'obstacle franchissable (marche)', $texts, true));

// A student page opened before the change sends the old provided text, with the flags it showed (v2.12.4).
$browser = L::with_flags($started, $old);
test('enforce: a field the page showed as provided is overwritten, not duplicated', fn() =>
    count(crits(L::enforce($browser, $new), 1)) === 2
    && crits(L::enforce($browser, $new), 1)[0]['description'] === 'Hauteur d\'obstacle franchissable (marche)');
test('enforce: a provided constraint shown by the page is not duplicated', fn() =>
    count(L::enforce($browser, $new)['contraintes']) === 1);
test('enforce: a pre-filled text untouched on an old page takes the new text', fn() =>
    crits(L::enforce($browser, $new), 1)[1]['description'] === 'Pente maximale gravie …');
test('enforce: what the student wrote on the old page is kept', fn() => crits(L::enforce($browser, $new), 1)[1]['niveau'] === '≥ 15°');
test('enforce without flags (no page): unchanged behaviour (text kept under a new id)', fn() =>
    count(crits(L::enforce($started, $new), 1)) === 3);

// As in production: the browser JSON goes through cdcf_helper::decode() (normalize) before enforce.
$viaserver = L::enforce(cdcf_helper::normalize($browser), $new);
test('normalize keeps the starting texts the page showed (hint), enforce uses them', fn() =>
    crits($viaserver, 1)[1]['description'] === 'Pente maximale gravie …' && count(crits($viaserver, 1)) === 2);
test('the stored copy never keeps a starting text (hint)', fn() =>
    strpos(json_encode($viaserver), 'hint') === false && strpos(json_encode($viaserver), 'Hint') === false);

// Final review, scenario A: the teacher locks a field the student is editing (it was pre-filled on the page).
$pageA = L::with_flags($mine, $open);
$pageA['fonctionsService'][0]['criteres'][0]['description'] = 'Ma marche à moi, plus';
$textsA = array_column(crits(L::enforce($pageA, $new), 1), 'description');
test('review A: text typed in a field locked meanwhile is kept', fn() => in_array('Ma marche à moi, plus', $textsA, true)
    && in_array('Hauteur d\'obstacle franchissable (marche)', $textsA, true));
// Scenario B: a gap field locked while the student types.
$gap = cdcf_helper::normalize(['interactors' => $old['interactors'], 'contraintes' => $old['contraintes'],
    'fonctionsService' => [array_merge($old['fonctionsService'][0], ['criteres' => [
        array_merge($old['fonctionsService'][0]['criteres'][0], ['locked' => false, 'gap' => true]),
        $old['fonctionsService'][0]['criteres'][1]]])]]);
$pageB = L::with_flags(L::seed($gap), $gap);
$pageB['fonctionsService'][0]['criteres'][0]['description'] = 'Réponse en cours';
test('review B: an answer typed in a gap locked meanwhile is kept', fn() =>
    in_array('Réponse en cours', array_column(crits(L::enforce($pageB, $new), 1), 'description'), true));
// Scenario C: a copy submitted during the change, back to draft later: the consigne moved on twice.
$v2 = robot(true);
$v2['fonctionsService'][0]['criteres'][0]['description'] = 'Hauteur de marche franchissable';
$reverted = L::follow($started, $new, $v2);
test('review C: a copy two consigne versions behind gets no duplicate', fn() => count(crits($reverted, 1)) === 2
    && crits($reverted, 1)[0]['description'] === 'Hauteur de marche franchissable');

// Server side: the consigne save follows the copies; the student save passes the stored copy.
$lib = (string)file_get_contents(GP_ROOT . '/lib.php');
$ws = (string)file_get_contents(GP_ROOT . '/classes/external/autosave.php');
test('lib: gestionprojet_cdcf_follow_consigne() updates the draft copies', fn() =>
    strpos($lib, 'function gestionprojet_cdcf_follow_consigne(object $gestionprojet, string $oldjson): int') !== false
    && strpos($lib, 'consigne_lock::follow(') !== false);
test('lib: the student save no longer needs the stored copy (the page flags decide)', fn() =>
    strpos($lib, '$stored') === false);
test('lib: the copies follow inside a transaction', fn() => strpos($lib, 'start_delegated_transaction()') !== false);
test('autosave (consigne, step 4): the copies follow the saved consigne', fn() =>
    strpos($ws, 'gestionprojet_cdcf_follow_consigne($gestionprojet, $oldconsigne)') !== false);

// The teacher is told, on the consigne page, that the copies follow the consigne.
$page = (string)file_get_contents(GP_ROOT . '/pages/step4_provided.php');
test('consigne page: notice with the number of draft copies', fn() =>
    strpos($page, "get_string('consigne_copies_follow', 'gestionprojet', \$copiesfollowing)") !== false
    && strpos($page, "'gestionprojet_cdcf', ['gestionprojetid' => \$gestionprojet->id, 'status' => 0]") !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    test("string consigne_copies_follow in $l", fn() => !empty($string['consigne_copies_follow'])
        && strpos($string['consigne_copies_follow'], '{$a}') !== false);
}

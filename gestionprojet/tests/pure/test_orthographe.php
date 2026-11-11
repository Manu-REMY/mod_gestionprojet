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
 * Spelling at step 4: pure rules (request 32, v2.15.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\orthographe;

// Scale of Emmanuel: 0 mistake → P, 1 or 2 → P/2, 3 or more → 0.
test('0 mistake: 2 points', fn() => orthographe::points(0, 2) === 2.0);
test('1 mistake: 1 point', fn() => orthographe::points(1, 2) === 1.0);
test('2 mistakes: 1 point', fn() => orthographe::points(2, 2) === 1.0);
test('3 mistakes: 0', fn() => orthographe::points(3, 2) === 0.0);
test('P = 5, 1 mistake: 2.5', fn() => orthographe::points(1, 5) === 2.5);
test('total 20 content, 2 points: 20', fn() => orthographe::total(20, 2, 2) === 20.0);
test('total 15 content, 1 point: 14.5', fn() => orthographe::total(15, 2, 1) === 14.5);
test('TS with a mistake: S', fn() => orthographe::cap_level('TS', 1) === 'S');
test('TS without mistake: TS', fn() => orthographe::cap_level('TS', 0) === 'TS');
test('S with mistakes: S', fn() => orthographe::cap_level('S', 3) === 'S');
test('F with mistakes: F', fn() => orthographe::cap_level('F', 3) === 'F');
test('no level: no level', fn() => orthographe::cap_level('', 2) === '');

// Verification by the server.
$text = "Il faut detecte l'intrus\nPortée de détection en mm\nAlarme sonor";
$f = fn(string $cite, string $correction) => ['cite' => $cite, 'correction' => $correction, 'type' => 'lexicale'];
$v = orthographe::verify([$f('detecte', 'détecter')], $text, []);
test('a real mistake is kept', fn() => count($v['fautes']) === 1 && $v['ecartees'] === 0
    && $v['fautes'][0]['cite'] === 'detecte' && $v['fautes'][0]['correction'] === 'détecter');
test('a quotation not in the copy is dismissed', fn() => orthographe::verify([$f('detectter', 'détecter')], $text, [])['ecartees'] === 1);
test('a correction that is another word is dismissed', fn() =>
    orthographe::verify([$f('sonor', 'stridente')], $text, [])['fautes'] === []);
test('a correction equal to the quotation is dismissed', fn() =>
    orthographe::verify([$f('detecte', 'Detecte')], $text, [])['fautes'] === []);
test('a single word must be a whole word ("tec" inside "detecte")', fn() =>
    orthographe::verify([$f('tec', 'tek')], $text, [])['fautes'] === []);
test('a word accepted by the teacher is dismissed', fn() =>
    orthographe::verify([$f('sonor', 'sonore')], $text, ['sonor'])['fautes'] === []);
test('the same mistake twice counts once', fn() =>
    count(orthographe::verify([$f('detecte', 'détecter'), $f('Detecte', 'détecter')], $text, [])['fautes']) === 1);
test('case and spaces ignored when looking for the quotation', fn() =>
    count(orthographe::verify([$f('ALARME  sonor', 'alarme sonore')], $text, [])['fautes']) === 1);

// Reading the AI answer.
test('empty list', fn() => orthographe::parse('{"fautes": []}') === []);
test('text around the JSON', fn() => count(orthographe::parse("Voici :\n{\"fautes\": [{\"cite\": \"a\", \"correction\": \"b\", \"type\": \"accord\"}]}")) === 1);
test('cut answer: exception', fn() => throws(fn() => orthographe::parse('{"fautes": [{"cite"'), \UnexpectedValueException::class));
test('no list: exception', fn() => throws(fn() => orthographe::parse('{"autre": 1}'), \UnexpectedValueException::class));

// Text judged: only what the student wrote.
$copy = ['interactors' => [['id' => 1, 'name' => 'Propriétaire', 'locked' => true], ['id' => 2, 'name' => 'Voleur']],
    'fonctionsService' => [
        ['id' => 1, 'description' => 'Permettre au … son domicile.', 'prefill' => true, 'hint' => 'Permettre au … son domicile.',
            'criteres' => [['id' => 1, 'description' => 'Portée de detection', 'prefill' => true, 'hint' => 'Portée de …',
                'niveau' => '8 m', 'niveauLocked' => true, 'flexibilite' => '']]],
    ],
    'contraintes' => [['id' => 1, 'description' => '', 'justification' => 'Prix bas']]];
$texts = orthographe::judged_texts($copy);
test('a provided field is not judged', fn() => !in_array('Propriétaire', $texts, true) && !in_array('8 m', $texts, true));
test('a pre-filled field left as is is not judged', fn() => !in_array('Permettre au … son domicile.', $texts, true));
test('a completed pre-filled field and free fields are judged', fn() =>
    in_array('Portée de detection', $texts, true) && in_array('Voleur', $texts, true) && in_array('Prix bas', $texts, true));
test('empty fields are not judged', fn() => !in_array('', $texts, true));

// Review of 2.15.0.
test('review: a multi-word quotation cut inside a word is dismissed', fn() =>
    orthographe::verify([['cite' => 'Alarme son', 'correction' => 'alarme sont', 'type' => 'homophone']], $text, [])['fautes'] === []);
$consigne = ['contraintes' => [['id' => 1, 'description' => 'Coût', 'justification' => 'Texte du prof']]];
$copy2 = ['contraintes' => [['id' => 1, 'description' => 'Coût', 'locked' => true, 'justification' => 'Texte du prof'],
    ['id' => 2, 'description' => '', 'justification' => 'Mon idée']]];
$t2 = orthographe::judged_texts($copy2, $consigne);
test('review: a justification left as the consigne gave it is not judged', fn() =>
    !in_array('Texte du prof', $t2, true) && in_array('Mon idée', $t2, true));
test('review: flexibility codes are not text', fn() =>
    orthographe::judged_texts(['fonctionsService' => [['description' => '', 'criteres' => [['description' => '', 'niveau' => '',
        'flexibilite' => 'F1']]]]]) === []);

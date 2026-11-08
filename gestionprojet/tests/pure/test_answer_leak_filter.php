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
 * Pure check of the filter that keeps the answer key out of the feedback (preco 3, robot CDC case).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/answer_leak_filter.php');

use mod_gestionprojet\answer_leak_filter as f;

// Answer key of the robot CDC (teacher correction model, JSON as stored).
$key = json_encode(['fonctionsService' => [
    ['description' => 'Doit être autonome en énergie', 'criteres' => [
        ['description' => 'Temps de recharge', 'niveau' => '2 h'],
    ]],
    ['description' => 'Doit franchir les obstacles du terrain', 'criteres' => [
        ['description' => 'Hauteur de marche', 'niveau' => '10 cm'],
        ['description' => 'Rayon de braquage', 'niveau' => '50 cm'],
    ]],
]], JSON_UNESCAPED_UNICODE);
// SAT copy: functions and criteria right, no level. The student wrote "obstacles" and "terrain".
$student = json_encode(['fonctionsService' => [
    ['description' => 'Doit franchir les obstacles du terrain', 'criteres' => [
        ['description' => 'Hauteur franchissable', 'niveau' => ''],
    ]],
]], JSON_UNESCAPED_UNICODE);
$forbidden = f::forbidden_roots($key, $student);

test('a sentence naming a criterion the student did not write is removed', fn() =>
    f::filter('Pense à préciser le temps de recharge. Ton travail est sérieux.', $forbidden)
        === 'Ton travail est sérieux.');
test('the leak seen on 8 Nov ("être autonome") is removed', fn() =>
    f::filter('Ta fonction FS2 devrait dire que le robot doit être autonome. Bonne organisation.', $forbidden)
        === 'Bonne organisation.');
test('words the student wrote can be quoted back', fn() =>
    f::filter('Tu as bien identifié les obstacles du terrain.', $forbidden)
        === 'Tu as bien identifié les obstacles du terrain.');
test('plural, case and accents do not hide a key word', fn() =>
    f::filter('Quels RAYONS faut-il ?', $forbidden) === '');
test('the CDC vocabulary itself is never a secret', fn() =>
    f::filter('Complète les niveaux de chaque critère.', $forbidden)
        === 'Complète les niveaux de chaque critère.');
test('JSON keys of the stored data are not words of the key', fn() =>
    !in_array('fonct', $forbidden, true) && !in_array('descr', $forbidden, true));
test('short words (2 h, cm) are ignored', fn() => !in_array('cm', $forbidden, true));
test('an empty answer key forbids nothing', fn() => f::forbidden_roots('', $student) === []);
test('text_of reads nested arrays and JSON strings', fn() =>
    strpos(f::text_of(['a' => '{"x":"Rayon"}', 'b' => [['c' => 'Hauteur']]]), 'Rayon') !== false
    && strpos(f::text_of(['a' => '{"x":"Rayon"}', 'b' => [['c' => 'Hauteur']]]), 'Hauteur') !== false);
test('every common root is a real root (4 or 5 letters, as roots() makes them)', function () {
    foreach (f::COMMON_ROOTS as $r) {
        if (f::roots($r) !== [$r]) {
            echo "      bad root: $r\n";
            return false;
        }
    }
    return true;
});

// What the student page shows.
$criteria = [['name' => 'Fonctions', 'score' => 6, 'max' => 6, 'comment' => 'Il manque le rayon de braquage.'],
    ['name' => 'Critères', 'score' => 4, 'max' => 6, 'comment' => 'Critères pertinents.']];
$v = f::student_view('Pense au temps de recharge. Bon travail.', $criteria, ['Indique le temps de recharge.', 'Relis ta copie.'], $forbidden);
test('the AI feedback is filtered', fn() => $v['feedback'] === 'Bon travail.');
test('criteria comments are filtered, scores kept', fn() =>
    $v['criteria'][0]['comment'] === '' && $v['criteria'][0]['score'] === 6
    && $v['criteria'][1]['comment'] === 'Critères pertinents.');
test('a leaking suggestion is dropped', fn() => $v['suggestions'] === ['Relis ta copie.']);

// Wiring in the student page.
$page = file_get_contents(GP_ROOT . '/pages/student_ai_feedback_display.php');
test('missing keywords are never shown to students', fn() =>
    strpos($page, '$showKeywordsMissing = false;') !== false && strpos($page, "keyword-tag missing") === false);
test('students only see an evaluation the teacher applied', fn() => strpos($page, "'status' => 'completed'") === false);
test('the student page filters with student_view when the option is on', fn() =>
    strpos($page, 'answer_leak_filter::student_view(') !== false
    && strpos($page, 'ai_hide_answers') !== false);

// Security review of 8 Nov.
$record = (object) ['id' => 7, 'gestionprojetid' => 3, 'groupid' => 12, 'userid' => 0, 'status' => 1, 'grade' => '9.00',
    'feedback' => 'Pense au temps de recharge et au rayon de braquage.', 'timemodified' => 1762590000,
    'interacteurs_data' => $student];
test('the stored feedback of a submission is not student text (it would permit every leaked word)', function () use ($key, $record) {
    $forbidden = f::forbidden_roots($key, f::content_text($record));
    return in_array('recha', $forbidden, true) && in_array('rayon', $forbidden, true)
        && strpos(f::content_text($record), 'Hauteur') !== false;
});
test('a level of the key (number) the student did not write is forbidden', fn() =>
    f::filter('Indique 50 cm.', f::forbidden_roots($key, $student)) === '');
test('a number the student wrote can be quoted back', fn() =>
    f::filter('Tu as écrit 10 cm.', f::forbidden_roots($key, json_encode(['n' => '10 cm']))) === 'Tu as écrit 10 cm.');
test('a feedback edited by the teacher is filtered too (fail closed)', fn() =>
    f::student_view('Pense au temps de recharge !', [], [], $forbidden)['feedback'] === '');
test('a level glued to its unit ("50cm") is still caught', fn() =>
    f::filter('Vise 50cm.', f::forbidden_roots($key, $student)) === '');
test('thousands separators do not hide a number', fn() =>
    f::filter('Il faut 1000 m.', f::forbidden_roots(json_encode(['n' => 'Portée 1 000 m']), '')) === ''
    && f::filter('Il faut 1 000 m.', f::forbidden_roots(json_encode(['n' => 'Portée 1000 m']), '')) === '');
test('a label like FS2 is still not a number', fn() => !in_array('2', f::roots('Ta FS2 est claire.'), true));

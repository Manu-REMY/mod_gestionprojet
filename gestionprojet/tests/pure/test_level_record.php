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
 * The level is recorded when the correction is applied (request 39, v2.14.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\palier;

test('up to S in F, S, TS: F and S', fn() => palier::levels_up_to('S', ['F', 'S', 'TS']) === ['F', 'S']);
test('up to S in S, TS: S', fn() => palier::levels_up_to('S', ['S', 'TS']) === ['S']);
test('nothing reached: none', fn() => palier::levels_up_to('', ['F', 'S']) === []);
test('up to TS in F, TS: F and TS', fn() => palier::levels_up_to('TS', ['F', 'TS']) === ['F', 'TS']);

$lib = file_get_contents(GP_ROOT . '/lib.php');
$evaluator = file_get_contents(GP_ROOT . '/classes/ai_evaluator.php');
preg_match('/function gestionprojet_level_sync\(object \$gestionprojet, object \$evaluation\): void \{.*?\n\}\n/s', $lib, $m);
$sync = $m[0] ?? '';
test('lib: gestionprojet_level_sync exists', fn() => $sync !== '');
test('sync: only step 4, outside the level mode, graded evaluations', fn() =>
    strpos($sync, '(int)$evaluation->step !== 4') !== false && strpos($sync, 'step4_paliers') !== false
    && strpos($sync, '$evaluation->formatif') !== false && strpos($sync, '$evaluation->palier') !== false);
test('sync: a grade changed by the teacher leaves the levels as they are', fn() =>
    strpos($sync, 'abs((float)$submission->grade - (float)$evaluation->parsed_grade) > 0.01') !== false);
test('sync: nothing when the level cannot be judged (null)', fn() =>
    preg_match('/\$level === null\) \{\s*return;/', $sync) === 1);
test('sync: a level no longer reached is marked retire, never deleted', fn() =>
    strpos($sync, "set_field('gestionprojet_palier', 'retire', 1") !== false && strpos($sync, 'delete_records') === false);
test('sync: under the lock of the copy', fn() => strpos($sync, 'gestionprojet_palier_lock(') !== false
    && strpos($sync, '->release()') !== false);
test('apply_evaluation calls the sync after updating the copy', fn() =>
    preg_match('/\$DB->update_record\(\$submissiontable, \$submission\);.*?gestionprojet_level_sync\(/s', $evaluator) === 1);

// Task 5: "Niveau … validé" for the student after submission.
preg_match('/function gestionprojet_progress_state\(.*?\n\}\n/s', $lib, $m);
$state = $m[0] ?? '';
preg_match('/function gestionprojet_level_banner_html\(.*?\n\}\n/s', $lib, $m);
$banner = $m[0] ?? '';
test('state: a submitted copy outside the level mode shows its highest validated level', fn() =>
    strpos($state, "get_records('gestionprojet_palier'") !== false && strpos($state, "'validated' => true") !== false
    && strpos($state, '$submitted && !$paliermode') !== false);
test('banner: validated level, a check mark and its own escaped sentence', fn() =>
    strpos($banner, "!empty(\$level['validated'])") !== false && strpos($banner, "'level_validated'") !== false
    && strpos($banner, "'✓'") !== false);
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("string level_validated ($lang)", fn() => !empty($string['level_validated'])
        && strpos($string['level_validated'], '{$a->acronym}') !== false);
}

// Review of 2.14.0, choice A of Emmanuel (11/11): a grade changed by the teacher rescales the parts.
$gparts = \mod_gestionprojet\grille::effective_parts(\mod_gestionprojet\grille::normalize([
    ['id' => 1, 'nom' => 'FS', 'poids' => 8, 'zones' => ['fonctions'], 'palier' => 'F'],
    ['id' => 2, 'nom' => 'Critères', 'poids' => 6, 'zones' => ['criteres'], 'palier' => 'S'],
    ['id' => 3, 'nom' => 'Niveaux', 'poids' => 6, 'zones' => ['niveaux'], 'palier' => 'TS'],
]));
$ai16 = [['name' => 'FS', 'score' => 8, 'max' => 8], ['name' => 'Critères', 'score' => 6, 'max' => 6],
    ['name' => 'Niveaux', 'score' => 2, 'max' => 6]];
$scaled = palier::criteria_for_grade($ai16, $gparts, 16.0, 9.0);
test('A: every score is multiplied by teacher grade / AI grade', fn() =>
    abs($scaled[0]['score'] - 4.5) < 0.001 && abs($scaled[1]['score'] - 3.375) < 0.001 && $scaled[0]['max'] === 8);
test('A: 16 → 9 lowers SAT to nothing (FS at 56 %)', fn() => palier::level_reached($scaled, $gparts, 80)['reached'] === '');
$ai16b = [['name' => 'FS', 'score' => 8, 'max' => 8], ['name' => 'Critères', 'score' => 6, 'max' => 6],
    ['name' => 'Niveaux', 'score' => 4, 'max' => 6]];
$up = palier::criteria_for_grade($ai16b, $gparts, 16.0, 20.0);
test('A: raising the grade can raise the level (levels 67 % → 83 %, others capped at their max)', fn() =>
    palier::level_reached($ai16b, $gparts, 80)['reached'] === 'S' && palier::level_reached($up, $gparts, 80)['reached'] === 'TS');
$zero = palier::criteria_for_grade([['name' => 'FS', 'score' => 0, 'max' => 8]], $gparts, 0.0, 15.0);
test('A: AI grade 0: every part at teacher grade / 20', fn() => count($zero) === 3
    && abs($zero[0]['score'] / $zero[0]['max'] - 0.75) < 0.001 && palier::level_reached($zero, $gparts, 80)['reached'] === '');
preg_match('/function gestionprojet_level_sync\(object \$gestionprojet, object \$evaluation\): void \{.*?\n\}\n/s', $lib, $m);
test('A: the sync rescales instead of leaving the levels', fn() => strpos($m[0] ?? '', 'palier::criteria_for_grade(') !== false);
test('review: an error in the level sync never turns an applied correction into a failed one', fn() =>
    preg_match('/try \{\s*gestionprojet_level_sync\(\$gestionprojet, \$evaluation\);\s*\} catch \(\\\\Throwable \$e\) \{\s*debugging\(/', $evaluator) === 1);

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

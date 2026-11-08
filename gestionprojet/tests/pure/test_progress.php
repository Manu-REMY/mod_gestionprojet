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
 * Rules of the student progress panel (v2.12.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\progress;

test('status: draft', fn() => progress::status(0, false) === 'draft');
test('status: submitted', fn() => progress::status(1, false) === 'submitted');
test('status: submitted and being graded', fn() => progress::status(1, true) === 'pending');
test('status: a draft with a running evaluation stays a draft', fn() => progress::status(0, true) === 'draft');

$applied = (object) ['id' => 1, 'applied_at' => 1000, 'timemodified' => 990];
$newer = (object) ['id' => 2, 'timemodified' => 2000, 'parsed_feedback' => 'formatif récent'];
$older = (object) ['id' => 3, 'timemodified' => 500, 'parsed_feedback' => 'formatif ancien'];
test('a formative feedback newer than the applied grade wins', fn() =>
    progress::pick_feedback($applied, $newer, 14.0, 'retour note', 900)['source'] === 'formative');
test('an older formative feedback: the applied grade feedback', fn() =>
    progress::pick_feedback($applied, $older, 14.0, 'retour note', 900)['source'] === 'applied');
test('a grade typed by the teacher (no AI evaluation): the teacher comment', fn() =>
    progress::pick_feedback(null, null, 12.0, 'Bon travail', 900) === ['source' => 'teacher', 'evaluation' => null,
        'text' => 'Bon travail', 'time' => 900]);
test('manual grade older than a formative feedback: the formative', fn() =>
    progress::pick_feedback(null, $newer, 12.0, 'Bon travail', 900)['source'] === 'formative');
test('manual grade newer than a formative feedback: the teacher comment', fn() =>
    progress::pick_feedback(null, $older, 12.0, 'Bon travail', 900)['source'] === 'teacher');
test('only a formative feedback', fn() => progress::pick_feedback(null, $older, null, '', 0)['source'] === 'formative');
test('nothing', fn() => progress::pick_feedback(null, null, null, '', 0)['source'] === 'none');
test('a grade without comment nor evaluation: nothing to read', fn() => progress::pick_feedback(null, null, 10.0, '', 900)['source'] === 'none');

$lib = file_get_contents(GP_ROOT . '/lib.php');
test('lib defines gestionprojet_progress_state', fn() =>
    strpos($lib, 'function gestionprojet_progress_state(object $gestionprojet, int $step, object $submission, bool $readonly): array') !== false);
test('lib defines gestionprojet_progress_layout_open', fn() => strpos($lib, 'function gestionprojet_progress_layout_open(): string') !== false);
$start = strpos($lib, 'function gestionprojet_progress_state(');
$body = $start === false ? '' : substr($lib, $start, strpos($lib, "\n}\n", $start) - $start);
test('read-only cuts submit and ask', fn() => preg_match("/'cansubmit' => !\\\$readonly/", $body) === 1
    && preg_match("/'canask' => !\\\$readonly/", $body) === 1);
test('the feedback is filtered for the student', fn() => strpos($body, 'answer_leak_filter::student_view(') !== false);
test('no feedback section when AI is off', fn() => strpos($body, 'ai_enabled') !== false);

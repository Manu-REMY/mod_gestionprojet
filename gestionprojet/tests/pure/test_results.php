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
 * Pure rules of the class results page (v2.13.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\results as R;

// One cell: state, grade, levels, formative feedbacks used.
test('cell: no copy → not started', fn() => R::cell(null, false, null, 0, 3)['state'] === 'none');
test('cell: draft', fn() => R::cell(['status' => 0, 'grade' => null], false, null, 1, 3)['state'] === 'draft');
test('cell: submitted, AI grading running → pending', fn() =>
    R::cell(['status' => 1, 'grade' => null], true, null, 0, 3)['state'] === 'pending');
test('cell: submitted with a grade', fn() => R::cell(['status' => 1, 'grade' => '14.50'], false, null, 2, 3)
    === ['state' => 'submitted', 'grade' => 14.5, 'levels' => null, 'used' => 2, 'limit' => 3]);
test('cell: a grade kept on a copy back to draft is still shown', fn() =>
    R::cell(['status' => 0, 'grade' => 12.0], false, null, 0, 3)['grade'] === 12.0);
test('cell: level mode → levels, no grade', fn() =>
    R::cell(['status' => 0, 'grade' => null], false, ['ingrid' => ['FRA', 'SAT', 'TS'], 'validated' => ['FRA']], 0, 0)['levels']
    === ['FRA' => true, 'SAT' => false, 'TS' => false]);

// Rows in group mode: groups by name, students by name, students without a group last.
$users = [
    10 => ['lastname' => 'Martin', 'firstname' => 'Léa'],
    11 => ['lastname' => 'Bernard', 'firstname' => 'Tom'],
    12 => ['lastname' => 'Arnaud', 'firstname' => 'Zoé'],
    13 => ['lastname' => 'Durand', 'firstname' => 'Paul'],
];
$rows = R::group_rows([5 => 'Groupe 10', 3 => 'groupe 2', 7 => 'Vide'], [5 => [10, 11], 3 => [12], 7 => [99]], $users);
test('groups: sorted by name (natural, case-insensitive)', fn() => array_column($rows, 'name') === ['groupe 2', 'Groupe 10', null]);
test('groups: students sorted by last name', fn() => $rows[1]['userids'] === [11, 10]);
test('groups: a group without students of the page is left out', fn() => !in_array(7, array_column($rows, 'groupid'), true));
test('groups: students in no group come last, group 0', fn() => $rows[2]['groupid'] === 0 && $rows[2]['userids'] === [13]);
test('groups: no "no group" block when everybody has a group', fn() =>
    count(R::group_rows([1 => 'A'], [1 => [10, 11, 12, 13]], $users)) === 1);

// Synthesis of a step column and average of a row.
$cells = [R::cell(['status' => 1, 'grade' => 10], false, null, 0, 3), R::cell(['status' => 1, 'grade' => null], true, null, 0, 3),
    R::cell(['status' => 0, 'grade' => null], false, null, 0, 3), R::cell(null, false, null, 0, 3)];
test('summary: submitted (pending included) over all rows, average of the grades', fn() =>
    R::summary($cells) === ['submitted' => 2, 'total' => 4, 'average' => 10.0]);
test('summary: no grade → no average', fn() => R::summary([R::cell(null, false, null, 0, 3)])['average'] === null);
test('average of a row: graded steps only', fn() =>
    R::average([R::cell(['status' => 1, 'grade' => 12], false, null, 0, 3), R::cell(['status' => 1, 'grade' => 16], false, null, 0, 3),
        R::cell(null, false, null, 0, 3)]) === 14.0);

// The page and its data.
$page = is_file(GP_ROOT . '/results.php') ? (string)file_get_contents(GP_ROOT . '/results.php') : '';
$lib = (string)file_get_contents(GP_ROOT . '/lib.php');
test('page: teachers only (login + viewallsubmissions)', fn() => strpos($page, 'require_login($course, false, $cm);') !== false
    && strpos($page, "require_capability('mod/gestionprojet:viewallsubmissions', \$context);") !== false);
test('page: group filter of Moodle and export of the same table', fn() =>
    strpos($page, 'groups_print_activity_menu(') !== false && strpos($page, 'download_dataformat_selector(') !== false
    && strpos($page, '\\core\\dataformat::download_data(') !== false);
test('page: merged cells for the group and its shared copy', fn() => strpos($page, '->rowspan = ') !== false);
test('page: a cell opens the copy in the grading page', fn() => strpos($page, "'/mod/gestionprojet/grading.php'") !== false);
test('page: state = symbol + word (never the colour alone)', fn() =>
    strpos($page, "'none' => '— '") !== false && strpos($page, "'submitted' => '✓ '") !== false);
test('page: no inline script or style', fn() => $page !== '' && stripos($page, '<script') === false && stripos($page, '<style') === false);
test('lib: lines built with the pure rules', fn() =>
    strpos($lib, 'function gestionprojet_results_lines(object $gestionprojet, object $cm, int $groupid): array') !== false
    && strpos($lib, '\\mod_gestionprojet\\results::cell(') !== false && strpos($lib, '\\mod_gestionprojet\\results::group_rows(') !== false);
test('lib: students without a group in group mode: their old individual copy, shown without a link', fn() =>
    strpos($lib, "// A student in no group cannot work in group mode") !== false);
test('menu: "Results" link for teachers', fn() => strpos($lib, "new moodle_url('/mod/gestionprojet/results.php'") !== false);
test('styles: results table', fn() => strpos((string)file_get_contents(GP_ROOT . '/styles.css'), '.path-mod-gestionprojet .gp-results') !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['results', 'results_group', 'results_student', 'results_start', 'results_last', 'results_average',
            'results_nogroup', 'results_state_none', 'results_state_draft', 'results_state_pending',
            'results_state_submitted', 'results_formative', 'results_summary', 'results_nostudents'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

// Final review (v2.13.0).
test('review: separate groups, teacher in no group without accessallgroups → stop', fn() =>
    strpos($page, 'groups_get_activity_groupmode($cm) == SEPARATEGROUPS') !== false
    && strpos($page, "has_capability('moodle/site:accessallgroups', \$context)") !== false
    && strpos($page, "get_string('notingroup')") !== false);
test('review: a "no group" student in group mode gets no link (grading opens groups only)', fn() =>
    strpos($lib, "'link' => \$block['groupid'] > 0 ? ['groupid' => \$block['groupid']] : null,") !== false
    && strpos($page, "\$line['link'] === null") !== false);
test('review: group names escaped once (raw in the data, format_string when shown)', fn() =>
    strpos($lib, '$groups[(int)$g->id] = (string)$g->name;') !== false
    && strpos($page, "format_string(\$line['groupname'] ?? get_string('results_nogroup', 'gestionprojet'))") !== false
    && strpos($page, "['escape' => false]") !== false);
test('review: active students only, teachers left out in one query, members with the groups', fn() =>
    strpos($lib, "'u.*', 'u.lastname, u.firstname', 0, 0, true)") !== false
    && strpos($lib, "get_enrolled_users(\$context, 'mod/gestionprojet:grade', 0, 'u.id')") !== false
    && strpos($lib, "groups_get_all_groups(\$cm->course, 0, \$cm->groupingid, 'g.*', true)") !== false
    && preg_match('/function gestionprojet_results_lines\(.*?\n}\n/s', $lib, $rl) === 1 && strpos($rl[0], 'groups_get_members(') === false
    && strpos($rl[0], 'has_capability(') === false);

// v2.13.1: an activity restricted to a grouping only lists the students of that grouping.
test('lib: only the members of the grouping of the activity', fn() =>
    preg_match('/function gestionprojet_results_lines\(.*?\n}\n/s', $lib, $rg) === 1
    && strpos($rg[0], "groups_get_grouping_members((int)\$cm->groupingid, 'u.id')") !== false
    && strpos($rg[0], 'if (!empty($cm->groupingid)) {') !== false);

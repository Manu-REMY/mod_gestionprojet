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
 * A deadline per group and per step (request 40, v2.14.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\deadline;

test('no date at all: 0', fn() => deadline::effective(0, []) === 0);
test('no group date: the step date', fn() => deadline::effective(100, []) === 100);
test('a class date replaces the step date, even earlier', fn() => deadline::effective(100, [50]) === 50);
test('several class dates: the latest', fn() => deadline::effective(0, [50, 80]) === 80);
test('a zero group date is ignored', fn() => deadline::effective(100, [0, 80]) === 80);
test('only zero group dates: the step date', fn() => deadline::effective(100, [0]) === 100);

$read = fn(string $path): string => is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
$install = $read('db/install.xml');
$upgrade = $read('db/upgrade.php');
$lib = $read('lib.php');
test('install.xml declares the table and its unique index', fn() =>
    strpos($install, '<TABLE NAME="gestionprojet_deadline_group"') !== false
    && preg_match('/<INDEX NAME="deadline_group_uix" UNIQUE="true" FIELDS="gestionprojetid, step, groupid"\/>/', $install) === 1);
test('upgrade.php creates the table at 2026111105', fn() =>
    preg_match("/if \\(\\\$oldversion < 2026111105\\) \\{.*?new xmldb_table\\('gestionprojet_deadline_group'\\).*?upgrade_mod_savepoint\\(true, 2026111105/s", $upgrade) === 1);
test('delete_instance cleans the table', fn() =>
    strpos($lib, "delete_records('gestionprojet_deadline_group'") !== false);
test('lib: gestionprojet_effective_deadline exists', fn() =>
    strpos($lib, 'function gestionprojet_effective_deadline(object $gestionprojet, int $step, object $submission): int') !== false);
test('version 2.14.0', fn() => strpos($read('version.php'), "\$plugin->version = 2026111105;") !== false
    && strpos($read('version.php'), "\$plugin->release = '2.14.0';") !== false);

// Task 2: the effective deadline drives every decision.
$task = $read('classes/task/auto_submit_deadline.php');
foreach (['classes/task/auto_submit_deadline.php', 'lib.php', 'pages/student_dates_display.php',
        'pages/student_submission_section.php'] as $path) {
    $code = $read($path);
    test("$path: no decision on the raw step deadline", fn() =>
        !preg_match('/deadline_date\s*(>|<=|<)\s*\$now|\$teachermodel->deadline_date > \$now/', $code));
}
test('lib: deadline checks use the effective deadline', fn() =>
    substr_count($lib, 'gestionprojet_effective_deadline($gestionprojet, ') >= 4);
test('the task also picks instances whose class date has passed', fn() =>
    strpos($task, '{gestionprojet_deadline_group}') !== false && strpos($task, 'gestionprojet_effective_deadline(') !== false);
test('the date displays read the effective deadline', fn() =>
    strpos($read('pages/student_dates_display.php'), 'gestionprojet_effective_deadline(') !== false
    && strpos($read('pages/student_submission_section.php'), 'gestionprojet_effective_deadline(') !== false);

// Review of 2.14.0.
preg_match('/function gestionprojet_effective_deadline\(.*?\n\}\n/s', $lib, $m);
$eff = $m[0] ?? '';
test('review: step and group dates read once per activity and step (static cache)', fn() =>
    strpos($eff, 'static $cache = [];') !== false && strpos($eff, "\$cache[\$key] ??") !== false);
test('review: only members who submit count (a teacher in every class group does not)', fn() =>
    strpos($eff, "get_enrolled_users(") !== false && strpos($eff, "'mod/gestionprojet:submit'") !== false
    && strpos($eff, 'groups_get_members(') === false);

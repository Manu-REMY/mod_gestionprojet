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
 * Pure check: a student only writes in a group they belong to.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/group_access.php');

use mod_gestionprojet\group_access;

// Student of group 12, course groups 12 and 13.
test('a student writes in their own group',
    fn() => group_access::is_allowed(12, [12], [12, 13], false));
test('a student cannot write in another group (the audit attack)',
    fn() => !group_access::is_allowed(13, [12], [12, 13], false));
test('no group (individual work) is always allowed',
    fn() => group_access::is_allowed(0, [12], [12, 13], false));
test('a student in two groups writes in either',
    fn() => group_access::is_allowed(13, [12, 13], [12, 13], false));
test('a teacher with accessallgroups writes in any group of the course',
    fn() => group_access::is_allowed(13, [], [12, 13], true));
test('nobody writes in a group of another course or grouping',
    fn() => !group_access::is_allowed(99, [12], [12, 13], true));
test('ids read from the database as strings still match',
    fn() => group_access::is_allowed(12, ['12'], ['12', '13'], false));

test('every student endpoint checks the group before writing', function () {
    $missing = [];
    foreach (['ajax/autosave.php', 'classes/external/autosave.php', 'ajax/submit_step.php',
            'classes/external/submit_step.php', 'ajax/reset_to_provided.php'] as $f) {
        $code = file_get_contents(GP_ROOT . '/' . $f);
        // The check must run before the first call that writes with the group id.
        $check = strpos($code, 'gestionprojet_require_group_access(');
        preg_match('/(get_or_create_submission|reset_step_to_provided)\(/', $code, $m, PREG_OFFSET_CAPTURE);
        if ($check === false || ($m && $m[0][1] < $check)) {
            $missing[] = $f;
        }
    }
    if ($missing) {
        echo '      ' . implode(', ', $missing) . "\n";
    }
    return !$missing;
});

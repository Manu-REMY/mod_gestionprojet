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
 * Static check: group work (userid 0) belongs to each group member for privacy requests.
 * Behaviour is checked on preprod (all production submissions are group work).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$code = file_get_contents(GP_ROOT . '/classes/privacy/provider.php');

/**
 * Body of a provider method.
 *
 * @param string $code
 * @param string $name
 * @return string
 */
function method_body(string $code, string $name): string {
    $start = strpos($code, 'function ' . $name . '(');
    $next = strpos($code, "\n    /**", $start);
    return substr($code, $start, $next === false ? null : $next - $start);
}

foreach (['get_contexts_for_userid', 'get_users_in_context', 'export_user_data'] as $m) {
    test("$m takes group membership into account", fn() =>
        strpos(method_body($code, $m), 'groups_members') !== false);
}
test('deleting one user keeps the shared group work (as mod_assign team submissions)', fn() =>
    strpos(method_body($code, 'delete_data_for_user'), 'groups_members') === false);

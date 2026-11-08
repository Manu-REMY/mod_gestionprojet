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
 * gestionprojet_teacher_pages_complete() only checks the teacher steps that are enabled.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/lib.php');

/**
 * In-memory stand-in for Moodle's $DB, answering get_record() by table.
 */
class fake_db {
    /** @var array table => record|false */
    public array $records = [];

    /**
     * Record of a table (conditions ignored: one instance only).
     *
     * @param string $table
     * @param array $conditions
     * @param string $fields
     * @param int $strictness
     * @return object|false
     */
    public function get_record($table, $conditions = [], $fields = '*', $strictness = 0) {
        return $this->records[$table] ?? false;
    }
}

/**
 * Run the function on an instance with these enabled steps and these filled teacher pages.
 *
 * @param array $enabled step => 0|1 for steps 1, 2, 3
 * @param array $filled step => bool (page filled)
 * @return bool
 */
function complete(array $enabled, array $filled): bool {
    global $DB;
    $DB = new fake_db();
    $DB->records['gestionprojet'] = (object) ['id' => 7, 'enable_step1' => $enabled[1], 'enable_step2' => $enabled[2],
        'enable_step3' => $enabled[3]];
    $DB->records['gestionprojet_description'] = !empty($filled[1]) ? (object) ['intitule' => 'Robot'] : false;
    $DB->records['gestionprojet_besoin'] = !empty($filled[2]) ? (object) ['aqui' => 'Élèves'] : false;
    $DB->records['gestionprojet_planning'] = !empty($filled[3]) ? (object) ['projectname' => 'P'] : false;
    return gestionprojet_teacher_pages_complete(7);
}

$all = [1 => 1, 2 => 1, 3 => 1];
test('all steps enabled and filled: complete', fn() => complete($all, [1 => true, 2 => true, 3 => true]) === true);
test('all steps enabled, step 2 empty: incomplete', fn() => complete($all, [1 => true, 3 => true]) === false);
test('step 2 disabled and empty: complete', fn() => complete([1 => 1, 2 => 0, 3 => 1], [1 => true, 3 => true]) === true);
test('steps 1 and 2 disabled, step 3 filled: complete', fn() => complete([1 => 0, 2 => 0, 3 => 1], [3 => true]) === true);
test('step 3 enabled but empty: incomplete', fn() => complete([1 => 0, 2 => 0, 3 => 1], []) === false);
test('every teacher step disabled: complete', fn() => complete([1 => 0, 2 => 0, 3 => 0], []) === true);
test('step 1 enabled with an empty title: incomplete', fn() => (function () {
    global $DB;
    complete($GLOBALS['all'], [1 => true, 2 => true, 3 => true]);
    $DB->records['gestionprojet_description'] = (object) ['intitule' => ''];
    return gestionprojet_teacher_pages_complete(7);
})() === false);

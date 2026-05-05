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
 * Unit tests for Gantt helper functions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

/**
 * Tests for Gantt helper functions.
 *
 * @group mod_gestionprojet
 */
class mod_gestionprojet_gantt_helpers_test extends advanced_testcase {

    public function test_column_defs_has_eight_columns(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        $this->assertCount(8, $defs);
    }

    public function test_column_defs_order(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        $stepnums = array_column($defs, 'stepnum');
        $this->assertSame([1, 3, 2, 4, 9, 5, 8, 6], $stepnums);
    }

    public function test_step_two_merges_with_step_seven(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        $col2 = null;
        foreach ($defs as $def) {
            if ($def['stepnum'] === 2) {
                $col2 = $def;
                break;
            }
        }
        $this->assertNotNull($col2);
        $this->assertSame(7, $col2['mergedwith']);
    }

    public function test_other_columns_have_null_merged(): void {
        $defs = gestionprojet_get_gantt_column_defs();
        foreach ($defs as $def) {
            if ($def['stepnum'] !== 2) {
                $this->assertNull($def['mergedwith'], "Column {$def['stepnum']} should have mergedwith=null");
            }
        }
    }

    public function test_build_cell_empty_when_not_filled(): void {
        $cell = gestionprojet_build_student_gantt_cell([
            'isfilled' => false,
        ]);
        $this->assertSame(['isfilled' => false], $cell);
    }

    public function test_build_cell_consult_complete(): void {
        $cell = gestionprojet_build_student_gantt_cell([
            'isfilled' => true,
            'role' => 'consult',
            'isenabled' => true,
            'iscomplete' => true,
            'name' => 'Step 1',
            'url' => '/view.php?id=1&step=1',
            'isprovided' => false,
        ]);
        $this->assertTrue($cell['isfilled']);
        $this->assertTrue($cell['isenabled']);
        $this->assertTrue($cell['iscomplete']);
        $this->assertSame('/view.php?id=1&step=1', $cell['url']);
        $this->assertFalse($cell['isprovided']);
    }

    public function test_build_cell_consult_disabled(): void {
        $cell = gestionprojet_build_student_gantt_cell([
            'isfilled' => true,
            'role' => 'consult',
            'isenabled' => false,
            'iscomplete' => false,
            'name' => 'Step 1',
            'url' => '#',
            'isprovided' => false,
        ]);
        $this->assertTrue($cell['isfilled']);
        $this->assertFalse($cell['isenabled']);
    }

    public function test_build_cell_work_with_grade(): void {
        $cell = gestionprojet_build_student_gantt_cell([
            'isfilled' => true,
            'role' => 'work',
            'isenabled' => true,
            'iscomplete' => true,
            'name' => 'Step 4',
            'url' => '/view.php?id=1&step=4',
            'grade' => 14.5,
        ]);
        $this->assertTrue($cell['hasgrade']);
        $this->assertSame('14.5 / 20', $cell['gradeformatted']);
    }

    public function test_build_cell_work_no_grade(): void {
        $cell = gestionprojet_build_student_gantt_cell([
            'isfilled' => true,
            'role' => 'work',
            'isenabled' => true,
            'iscomplete' => false,
            'name' => 'Step 4',
            'url' => '/view.php?id=1&step=4',
            'grade' => null,
        ]);
        $this->assertFalse($cell['hasgrade']);
        $this->assertArrayNotHasKey('gradeformatted', $cell);
    }

    public function test_build_cell_consult_provided_badge(): void {
        $cell = gestionprojet_build_student_gantt_cell([
            'isfilled' => true,
            'role' => 'consult',
            'isenabled' => true,
            'iscomplete' => true,
            'name' => 'Step 4',
            'url' => '/view.php?id=1&step=4&mode=provided',
            'isprovided' => true,
        ]);
        $this->assertTrue($cell['isprovided']);
    }
}

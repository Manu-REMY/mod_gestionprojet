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
 * Tests for reset_helper.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');
require_once($CFG->dirroot . '/mod/gestionprojet/classes/reset_helper.php');

/**
 * @covers \mod_gestionprojet\reset_helper
 */
final class reset_helper_test extends \advanced_testcase {

    public function test_reset_step4_overwrites_student_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step4_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $providedjson = json_encode(['fonctionsService' => [['id' => 1, 'description' => 'NEW FROM TEACHER']]]);
        $DB->insert_record('gestionprojet_cdcf_provided', (object) [
            'gestionprojetid' => $instance->id,
            'interacteurs_data' => $providedjson,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid' => $user->id,
            'groupid' => 0,
            'status' => 0,
            'interacteurs_data' => json_encode(['fonctionsService' => [['id' => 1, 'description' => 'OLD STUDENT WORK']]]),
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_cdcf', $studentrec);

        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 4, 0, $user->id);

        $this->assertTrue($result['success']);
        $updated = $DB->get_record('gestionprojet_cdcf', ['id' => $studentrec->id]);
        $this->assertSame($providedjson, $updated->interacteurs_data);
    }

    public function test_reset_step4_blocked_when_submitted(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step4_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $providedjson = json_encode(['fonctionsService' => [['id' => 1, 'description' => 'NEW']]]);
        $DB->insert_record('gestionprojet_cdcf_provided', (object) [
            'gestionprojetid' => $instance->id,
            'interacteurs_data' => $providedjson,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $oldjson = json_encode(['fonctionsService' => [['id' => 1, 'description' => 'OLD STUDENT (SUBMITTED)']]]);
        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid' => $user->id,
            'groupid' => 0,
            'status' => 1,
            'interacteurs_data' => $oldjson,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_cdcf', $studentrec);

        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 4, 0, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame('locked', $result['error']);
        $unchanged = $DB->get_record('gestionprojet_cdcf', ['id' => $studentrec->id]);
        $this->assertSame($oldjson, $unchanged->interacteurs_data);
    }

    public function test_reset_step4_returns_error_when_no_provided(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step4_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 4, 0, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame('no_provided', $result['error']);
    }

    public function test_reset_rejects_unsupported_step(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $gp = $DB->get_record('gestionprojet', ['id' => $instance->id], '*', MUST_EXIST);
        $result = reset_helper::reset_step_to_provided($gp, 7, 0, $user->id);

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported_step', $result['error']);
    }
}

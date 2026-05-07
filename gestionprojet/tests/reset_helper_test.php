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

    public function test_reset_step5_overwrites_all_essai_fields(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step5_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $DB->insert_record('gestionprojet_essai_provided', (object) [
            'gestionprojetid'        => $instance->id,
            'nom_essai'              => 'TEACHER NAME',
            'date_essai'             => '2026-06-01',
            'groupe_eleves'          => 'TEACHER GROUP',
            'objectif'               => 'TEACHER OBJ',
            'fonction_service'       => 'TEACHER FS',
            'niveaux_reussite'       => 'TEACHER NR',
            'etapes_protocole'       => 'TEACHER EP',
            'materiel_outils'        => 'TEACHER MO',
            'precautions'            => 'TEACHER PREC',
            'resultats_obtenus'      => 'TEACHER RES',
            'observations_remarques' => 'TEACHER OBS',
            'conclusion'             => 'TEACHER CONCL',
            'timecreated'            => time(),
            'timemodified'           => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'nom_essai'       => 'STUDENT NAME',
            'objectif'        => 'STUDENT OBJ',
            'status'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_essai', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 5, 0, $user->id);
        $this->assertTrue($result['success']);

        $updated = $DB->get_record('gestionprojet_essai', ['id' => $studentrec->id]);
        $this->assertSame('TEACHER NAME', $updated->nom_essai);
        $this->assertSame('TEACHER OBJ', $updated->objectif);
        $this->assertSame('TEACHER FS', $updated->fonction_service);
        $this->assertSame('TEACHER NR', $updated->niveaux_reussite);
        $this->assertSame('TEACHER EP', $updated->etapes_protocole);
        $this->assertSame('TEACHER MO', $updated->materiel_outils);
        $this->assertSame('TEACHER PREC', $updated->precautions);
        $this->assertSame('TEACHER RES', $updated->resultats_obtenus);
        $this->assertSame('TEACHER OBS', $updated->observations_remarques);
        $this->assertSame('TEACHER CONCL', $updated->conclusion);
        $this->assertSame('TEACHER GROUP', $updated->groupe_eleves);
        $this->assertSame('2026-06-01', $updated->date_essai);
    }

    public function test_reset_step9_overwrites_data_json(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step9_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $providedjson = json_encode(['nodes' => [['id' => 'fp1', 'label' => 'TEACHER FP']]]);
        $DB->insert_record('gestionprojet_fast_provided', (object) [
            'gestionprojetid' => $instance->id,
            'data_json'       => $providedjson,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'data_json'       => json_encode(['nodes' => [['id' => 'fp1', 'label' => 'STUDENT EDIT']]]),
            'status'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_fast', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 9, 0, $user->id);
        $this->assertTrue($result['success']);

        $updated = $DB->get_record('gestionprojet_fast', ['id' => $studentrec->id]);
        $this->assertSame($providedjson, $updated->data_json);
    }

    public function test_reset_step5_blocked_when_submitted(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step5_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $DB->insert_record('gestionprojet_essai_provided', (object) [
            'gestionprojetid' => $instance->id,
            'objectif'        => 'TEACHER OBJ',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'objectif'        => 'STUDENT OBJ',
            'status'          => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_essai', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 5, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('locked', $result['error']);

        $unchanged = $DB->get_record('gestionprojet_essai', ['id' => $studentrec->id]);
        $this->assertSame('STUDENT OBJ', $unchanged->objectif);
    }

    public function test_reset_step9_blocked_when_submitted(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step9_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('gestionprojet_fast_provided', (object) [
            'gestionprojetid' => $instance->id,
            'data_json'       => '{"nodes":[]}',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $studentrec = (object) [
            'gestionprojetid' => $instance->id,
            'userid'          => $user->id,
            'groupid'         => 0,
            'data_json'       => '{"student":"work"}',
            'status'          => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $studentrec->id = $DB->insert_record('gestionprojet_fast', $studentrec);

        $result = reset_helper::reset_step_to_provided($instance, 9, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('locked', $result['error']);
        $unchanged = $DB->get_record('gestionprojet_fast', ['id' => $studentrec->id]);
        $this->assertSame('{"student":"work"}', $unchanged->data_json);
    }

    public function test_reset_step5_no_provided_returns_error(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step5_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $result = reset_helper::reset_step_to_provided($instance, 5, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('no_provided', $result['error']);
    }

    public function test_reset_step9_no_provided_returns_error(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('gestionprojet', [
            'course' => $course->id,
            'step9_provided' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $result = reset_helper::reset_step_to_provided($instance, 9, 0, $user->id);
        $this->assertFalse($result['success']);
        $this->assertSame('no_provided', $result['error']);
    }
}

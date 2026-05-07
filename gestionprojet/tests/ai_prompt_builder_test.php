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
 * Unit tests for ai_prompt_builder generic intro injection and identical-copy guard.
 *
 * Verifies that the prompt builder is step-agnostic when receiving an intro_text
 * and a providedrec/nomodifications pair — covering steps 5 and 9 alongside the
 * already-validated step 4 behaviour.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_prompt_builder.php');

/**
 * Tests for generic intro injection and identical-copy alert across steps.
 *
 * @group mod_gestionprojet
 */
class mod_gestionprojet_ai_prompt_builder_test extends advanced_testcase {

    public function test_step5_intro_text_injected_in_system_prompt(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $student = (object)['fonction_service' => 'X'];
        $teacher = (object)['ai_instructions' => 'Eval criteria'];
        $intro = '<p>Consigne pédagogique step 5</p>';
        $prompts = $builder->build_prompt(5, $student, $teacher, $intro, null, false);
        $this->assertStringContainsString('Consigne pédagogique step 5', $prompts['system']);
    }

    public function test_step9_intro_text_injected_in_system_prompt(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $student = (object)['data_json' => '{}'];
        $teacher = (object)['ai_instructions' => 'Eval FAST'];
        $intro = '<p>Consigne FAST</p>';
        $prompts = $builder->build_prompt(9, $student, $teacher, $intro, null, false);
        $this->assertStringContainsString('Consigne FAST', $prompts['system']);
    }

    public function test_step5_identical_copy_alert_in_user_prompt(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $providedrec = (object)['objectif' => 'TEACHER'];
        $student = (object)['objectif' => 'TEACHER'];
        $teacher = (object)['ai_instructions' => ''];
        $prompts = $builder->build_prompt(5, $student, $teacher, null, $providedrec, true);
        $this->assertMatchesRegularExpression('/identique|identical|0\s*\/\s*20/i', $prompts['user']);
    }

    public function test_step9_identical_copy_alert_in_user_prompt(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $providedrec = (object)['data_json' => '{"a":1}'];
        $student = (object)['data_json' => '{"a":1}'];
        $teacher = (object)['ai_instructions' => ''];
        $prompts = $builder->build_prompt(9, $student, $teacher, null, $providedrec, true);
        $this->assertMatchesRegularExpression('/identique|identical|0\s*\/\s*20/i', $prompts['user']);
    }
}

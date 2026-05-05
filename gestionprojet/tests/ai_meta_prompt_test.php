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
 * Unit tests for ai_prompt_builder::build_meta_prompt().
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/classes/ai_prompt_builder.php');

/**
 * Tests for the meta-prompt builder.
 *
 * @group mod_gestionprojet
 */
class mod_gestionprojet_ai_meta_prompt_test extends advanced_testcase {

    public function test_step4_meta_prompt_includes_model_fields(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $model = (object)[
            'interacteurs_data' => json_encode([
                'interactors' => [
                    ['id' => 1, 'name' => 'Usager'],
                    ['id' => 2, 'name' => 'Espace urbain'],
                ],
                'fonctionsService' => [
                    [
                        'id' => 1,
                        'description' => 'Permettre à un usager de se déplacer en ville',
                        'interactor1Id' => 1,
                        'interactor2Id' => 2,
                        'criteres' => [],
                    ],
                ],
                'contraintes' => [],
            ]),
        ];
        $result = $builder->build_meta_prompt(4, $model);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('system', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertStringContainsString('expert pédagogique', $result['system']);
        $this->assertStringContainsString('INTERACTEURS', $result['user']);
        $this->assertStringContainsString('Usager', $result['user']);
        $this->assertStringContainsString('Permettre à un usager de se déplacer en ville', $result['user']);
    }

    public static function each_step_provider(): array {
        return [
            'step 4' => [4, 'Cahier des Charges Fonctionnel'],
            'step 5' => [5, 'Fiche d\'Essai'],
            'step 6' => [6, 'Rapport de Projet'],
            'step 7' => [7, 'Expression du Besoin'],
            'step 8' => [8, 'Carnet de Bord'],
            'step 9' => [9, 'Diagramme FAST'],
        ];
    }

    /**
     * @dataProvider each_step_provider
     */
    public function test_each_step_user_prompt_includes_step_context(int $step, string $expected): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $model = (object)[];
        $result = $builder->build_meta_prompt($step, $model);
        $this->assertStringContainsString($expected, $result['user']);
    }

    public function test_empty_model_uses_placeholder(): void {
        $builder = new \mod_gestionprojet\ai_prompt_builder();
        $result = $builder->build_meta_prompt(4, (object)[]);
        $this->assertStringContainsString('(Modèle de correction non renseigné', $result['user']);
    }
}

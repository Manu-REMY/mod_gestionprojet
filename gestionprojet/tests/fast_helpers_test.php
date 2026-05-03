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
 * Unit tests for FAST helper functions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

/**
 * Tests for gestionprojet_fast_to_text().
 *
 * @group mod_gestionprojet
 */
class mod_gestionprojet_fast_helpers_test extends advanced_testcase {

    public function test_empty_input_returns_empty_string(): void {
        $this->assertSame('', gestionprojet_fast_to_text(null));
        $this->assertSame('', gestionprojet_fast_to_text(''));
        $this->assertSame('', gestionprojet_fast_to_text('null'));
        $this->assertSame('', gestionprojet_fast_to_text('{}'));
    }

    public function test_invalid_json_returns_empty_string(): void {
        $this->assertSame('', gestionprojet_fast_to_text('not json'));
    }

    public function test_simple_function_with_solution(): void {
        $json = json_encode([
            'fonctionsPrincipales' => [['id' => 1, 'description' => 'Permettre la mesure']],
            'fonctions' => [
                [
                    'id' => 1,
                    'description' => 'Mesurer la température',
                    'solution' => 'Capteur DHT22',
                    'sousFonctions' => [],
                ],
            ],
        ]);
        $text = gestionprojet_fast_to_text($json);
        $this->assertStringContainsString('Fonction principale : Permettre la mesure', $text);
        $this->assertStringContainsString('FT1 — Mesurer la température', $text);
        $this->assertStringContainsString('Solution : Capteur DHT22', $text);
    }

    public function test_function_with_subfunctions(): void {
        $json = json_encode([
            'fonctionsPrincipales' => [],
            'fonctions' => [
                [
                    'id' => 1,
                    'description' => 'Acquérir les données',
                    'solution' => '',
                    'sousFonctions' => [
                        ['id' => 1, 'description' => 'Lire le capteur', 'solution' => 'I2C'],
                        ['id' => 2, 'description' => 'Convertir en JSON', 'solution' => 'json_encode'],
                    ],
                ],
            ],
        ]);
        $text = gestionprojet_fast_to_text($json);
        $this->assertStringContainsString('FT1 — Acquérir les données', $text);
        $this->assertStringContainsString('FT1.1 Lire le capteur', $text);
        $this->assertStringContainsString('FT1.2 Convertir en JSON', $text);
        $this->assertStringContainsString('Solution : I2C', $text);
        $this->assertStringContainsString('Solution : json_encode', $text);
    }
}

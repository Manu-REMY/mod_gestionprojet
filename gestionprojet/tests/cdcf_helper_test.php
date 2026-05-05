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
 * Tests for cdcf_helper.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/gestionprojet/classes/cdcf_helper.php');

/**
 * @covers \mod_gestionprojet\cdcf_helper
 */
final class cdcf_helper_test extends \basic_testcase {

    public function test_default_data_has_expected_shape(): void {
        $d = cdcf_helper::default_data();
        $this->assertArrayHasKey('interactors', $d);
        $this->assertArrayHasKey('fonctionsService', $d);
        $this->assertArrayHasKey('contraintes', $d);
        $this->assertCount(2, $d['interactors']);
    }

    public function test_migrate_legacy_converts_fp_to_first_fs(): void {
        $oldjson = json_encode([
            ['name' => 'Utilisateur', 'fcs' => []],
            ['name' => 'Environnement', 'fcs' => []],
        ]);
        $result = cdcf_helper::migrate_legacy($oldjson, 'Permettre la mesure de temperature');
        $this->assertCount(1, $result['fonctionsService']);
        $this->assertSame('Permettre la mesure de temperature',
            $result['fonctionsService'][0]['description']);
        $this->assertSame(1, $result['fonctionsService'][0]['interactor1Id']);
        $this->assertSame(0, $result['fonctionsService'][0]['interactor2Id']);
    }

    public function test_migrate_legacy_converts_fcs_to_fs_per_interactor(): void {
        $oldjson = json_encode([
            ['name' => 'A', 'fcs' => [
                ['value' => 'Mesurer X', 'criteres' => [
                    ['critere' => 'Precision', 'niveau' => '10', 'unite' => 'mm'],
                ]],
            ]],
            ['name' => 'B', 'fcs' => [
                ['value' => 'Resister a Y', 'criteres' => []],
            ]],
        ]);
        $result = cdcf_helper::migrate_legacy($oldjson, '');
        $this->assertCount(2, $result['fonctionsService']);
        $this->assertSame('Mesurer X', $result['fonctionsService'][0]['description']);
        $this->assertSame(1, $result['fonctionsService'][0]['interactor1Id']);
        $this->assertSame('10 mm', $result['fonctionsService'][0]['criteres'][0]['niveau']);
        $this->assertSame('Precision', $result['fonctionsService'][0]['criteres'][0]['description']);
        $this->assertSame('', $result['fonctionsService'][0]['criteres'][0]['flexibilite']);
        $this->assertSame('Resister a Y', $result['fonctionsService'][1]['description']);
        $this->assertSame(2, $result['fonctionsService'][1]['interactor1Id']);
    }

    public function test_migrate_legacy_is_idempotent_on_new_schema(): void {
        $newjson = json_encode([
            'interactors' => [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']],
            'fonctionsService' => [
                ['id' => 1, 'description' => 'X', 'interactor1Id' => 1, 'interactor2Id' => 0, 'criteres' => []],
            ],
            'contraintes' => [],
        ]);
        $result = cdcf_helper::migrate_legacy($newjson, '');
        $this->assertCount(1, $result['fonctionsService']);
        $this->assertSame('X', $result['fonctionsService'][0]['description']);
    }

    public function test_migrate_legacy_handles_empty_input(): void {
        $result = cdcf_helper::migrate_legacy('', '');
        $this->assertSame([], $result['fonctionsService']);
        $this->assertSame([], $result['contraintes']);
        $this->assertCount(2, $result['interactors']);
    }

    public function test_normalize_filters_invalid_flexibilite(): void {
        $data = [
            'interactors' => [['id' => 1, 'name' => 'A']],
            'fonctionsService' => [[
                'id' => 1, 'description' => 'X', 'interactor1Id' => 1, 'interactor2Id' => 0,
                'criteres' => [['id' => 1, 'description' => 'C', 'niveau' => 'N', 'flexibilite' => 'BOGUS']],
            ]],
        ];
        $result = cdcf_helper::normalize($data);
        $this->assertSame('', $result['fonctionsService'][0]['criteres'][0]['flexibilite']);
    }
}

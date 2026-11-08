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
 * Step 4 prompt with the real consigne of ROBOT MARTIEN (point 16 of the 8 Nov recommendations, v2.13.2).
 *
 * Data: the consigne shown to the students (tests/pure/fixtures/robot_martien_consigne.json, production, 8 Nov),
 * never the correction model (this repository is public). The activity has no saved grid: default grid.
 * A complete SAT copy must not lose points for constraints or flexibility it was not asked to write.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require_once(GP_ROOT . '/classes/grille.php');
require_once(GP_ROOT . '/classes/ai_prompt_builder.php');

use mod_gestionprojet\ai_prompt_builder;
use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\consigne_lock;

/**
 * The "CRITÈRES D'ÉVALUATION" section of a system prompt.
 *
 * @param string $prompt
 * @return string
 */
function robot_criteria(string $prompt): string {
    preg_match("/CRITÈRES D.ÉVALUATION:\n(.*?)\n\n/s", $prompt, $m);
    return $m[1] ?? '';
}

$consigne = cdcf_helper::normalize(json_decode((string)file_get_contents(__DIR__ . '/fixtures/robot_martien_consigne.json'), true));
$locked = consigne_lock::locked_zones($consigne);
$model = (object) ['ai_instructions' => ''];   // No saved grid on ROBOT MARTIEN: default grid.

test('robot data: 5 interactors, 7 FS, 13 criteria, 1 constraint', fn() => count($consigne['interactors']) === 5
    && count($consigne['fonctionsService']) === 7 && count($consigne['contraintes']) === 1
    && array_sum(array_map(fn($fs) => count($fs['criteres']), $consigne['fonctionsService'])) === 13);
test('robot consigne: interactors and constraints entirely provided, so never graded', fn() =>
    $locked === ['interacteurs', 'contraintes']);
test('robot consigne: no flexibility given to any criterion', fn() =>
    !array_filter(array_merge(...array_map(fn($fs) => $fs['criteres'], $consigne['fonctionsService'])),
        fn($c) => ($c['flexibilite'] ?? '') !== ''));

// The robot as it should be set: flexibility off.
$off = robot_criteria((new ai_prompt_builder(['flexibility' => false, 'locked_zones' => $locked]))->build_system_prompt(4, $model));
test('flexibility off: neither constraints nor interactors are graded', fn() =>
    strpos($off, 'Contraintes') === false && strpos($off, 'Interacteurs (poids') === false);
test('flexibility off: flexibility never asked', fn() => stripos($off, 'flexibilit') === false);
test('flexibility off: FS 9.5, criteria 6, consistency 4.5 on 20', fn() =>
    strpos($off, 'Fonctions de service (poids: 9.5/20)') !== false && strpos($off, 'Critères (poids: 6/20)') !== false
    && strpos($off, 'Cohérence globale (poids: 4.5/20)') !== false);

// As set in production on 8 Nov (flexibility on): the criteria part asks for a flexibility the consigne never gives,
// so a complete SAT copy would lose points. The setting of the activity must be changed (Emmanuel's decision).
$on = robot_criteria((new ai_prompt_builder(['flexibility' => true, 'locked_zones' => $locked]))->build_system_prompt(4, $model));
test('flexibility on (production setting of 8 Nov): the criteria part asks for flexibility', fn() =>
    strpos($on, 'flexibilité (F0–F3)') !== false && strpos($on, 'Contraintes') === false);

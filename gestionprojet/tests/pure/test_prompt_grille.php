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
 * Pure checks: the AI grades step 4 with the teacher's grid (v2.11.0, preco 2 and 16).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/grille.php');
require(GP_ROOT . '/classes/ai_prompt_builder.php');

use mod_gestionprojet\ai_prompt_builder;

/**
 * The "CRITÈRES D'ÉVALUATION" section of a system prompt.
 *
 * @param string $prompt
 * @return string
 */
function criteria_section(string $prompt): string {
    preg_match("/CRITÈRES D.ÉVALUATION:\n(.*?)\n\n/s", $prompt, $m);
    return $m[1] ?? '';
}

// Section produced before v2.11.0 (captured on 8 Nov): an activity without saved grid must not change.
$before = "- Interacteurs (poids: 4/20): Les interacteurs sont pertinents et complets\n"
    . "- Fonctions de service (poids: 6/20): Chaque FS exprime un service rendu, est rattachée à 1 ou 2 interacteurs "
    . "et utilise un verbe d'action à l'infinitif\n"
    . "- Critères (poids: 4/20): Chaque critère a un niveau quantifié et une flexibilité (F0–F3) cohérente\n"
    . "- Contraintes (poids: 3/20): Les contraintes sont identifiées avec justification\n"
    . "- Cohérence globale (poids: 3/20): L'ensemble est cohérent avec la norme NF EN 16271";

$b = new ai_prompt_builder();
test('no saved grid: criteria section unchanged, character for character', fn() =>
    criteria_section($b->build_system_prompt(4, (object) ['ai_instructions' => ''])) === $before);
test('empty saved grid: same as no grid', fn() =>
    criteria_section($b->build_system_prompt(4, (object) ['ai_instructions' => '', 'grille_json' => ''])) === $before);

// Robot SAT grid: interacteurs provided, no constraints asked.
$robot = (object) ['ai_instructions' => '', 'grille_json' => json_encode([
    ['id' => 1, 'nom' => 'Interacteurs', 'poids' => 4, 'zones' => ['interacteurs'], 'evaluee' => 'non'],
    ['id' => 2, 'nom' => 'Fonctions de service', 'description' => 'FS justes', 'poids' => 6, 'zones' => ['fonctions']],
    ['id' => 3, 'nom' => 'Critères', 'description' => 'Critères justes', 'poids' => 4, 'zones' => ['criteres']],
    ['id' => 4, 'nom' => 'Contraintes', 'poids' => 3, 'zones' => ['contraintes'], 'evaluee' => 'non'],
    ['id' => 5, 'nom' => 'Cohérence globale', 'description' => 'Cohérent', 'poids' => 3, 'zones' => ['ensemble']],
])];
$section = criteria_section($b->build_system_prompt(4, $robot));
test('robot grid: the parts set to "non" are not in the grid', fn() =>
    strpos($section, 'Contraintes') === false && strpos($section, 'Interacteurs (poids') === false);
test('robot grid: weights 9.5, 6 and 4.5 on 20', fn() =>
    strpos($section, '- Fonctions de service (poids: 9.5/20): FS justes') !== false
    && strpos($section, '- Critères (poids: 6/20): Critères justes') !== false
    && strpos($section, '- Cohérence globale (poids: 4.5/20): Cohérent') !== false);

test('flexibility off: never in the system prompt, even with the default grid', fn() =>
    stripos((new ai_prompt_builder(['flexibility' => false]))->build_system_prompt(4, (object) ['ai_instructions' => '']),
        'flexibilit') === false);

$none = (object) ['ai_instructions' => '', 'grille_json' => json_encode([['id' => 1, 'nom' => 'A', 'poids' => 2, 'evaluee' => 'non']])];
test('graded_parts: none when every part is "non"', fn() => $b->graded_parts(4, $none) === []);
test('graded_parts: only step 4 has a grid', fn() => $b->graded_parts(5, $robot) === []);
test('graded_parts: robot grid has 3 parts', fn() => count($b->graded_parts(4, $robot)) === 3);
test('the evaluator refuses to grade with no graded part', fn() =>
    strpos(file_get_contents(GP_ROOT . '/classes/ai_evaluator.php'), "'ai_no_graded_part'") !== false);
test('other steps keep their fixed grid', fn() =>
    strpos(criteria_section($b->build_system_prompt(5, $robot)), 'Protocole') !== false);

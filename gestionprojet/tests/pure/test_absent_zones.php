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
 * A part of the grid about something absent from the correction model is not graded (request 34, v2.13.3).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_prompt_builder.php');

use mod_gestionprojet\ai_prompt_builder;
use mod_gestionprojet\cdcf_helper;
use mod_gestionprojet\consigne_lock;
use mod_gestionprojet\grille;

// Correction model of a CDCF without constraint nor flexibility (case of Projet PIEGE).
$model = cdcf_helper::normalize([
    'interactors' => [['id' => 1, 'name' => 'Propriétaire'], ['id' => 2, 'name' => 'Intrus']],
    'fonctionsService' => [['id' => 1, 'description' => 'Détecter l\'intrus', 'interactor1Id' => 2, 'interactor2Id' => 0,
        'criteres' => [['id' => 1, 'description' => 'Portée de détection', 'niveau' => '8 m', 'flexibilite' => '']]]],
    'contraintes' => [],
]);
$empty = cdcf_helper::default_data();

test('constraints and flexibility absent from the model and the consigne are absent zones', fn() =>
    consigne_lock::absent_zones($model, $empty) === ['flexibilite', 'contraintes']);

$consigne = $empty;
$consigne['contraintes'] = [['id' => 1, 'description' => 'A compléter', 'justification' => '', 'linkedFsId' => 0]];
test('a constraint in the consigne: the zone is not absent', fn() =>
    consigne_lock::absent_zones($model, cdcf_helper::normalize($consigne)) === ['flexibilite']);

$withc = $model;
$withc['contraintes'] = [['id' => 1, 'description' => 'Coût inférieur à 50 €', 'justification' => '', 'linkedFsId' => 0]];
test('a constraint in the model: the zone is not absent', fn() =>
    consigne_lock::absent_zones(cdcf_helper::normalize($withc), $empty) === ['flexibilite']);

test('an empty model says nothing: no absent zone', fn() => consigne_lock::absent_zones($empty, $empty) === []);

$blank = $withc;
$blank['contraintes'] = [['id' => 1, 'description' => '  ', 'justification' => '', 'linkedFsId' => 0]];
test('a blank constraint does not count', fn() =>
    in_array('contraintes', consigne_lock::absent_zones(cdcf_helper::normalize($blank), $empty), true));

// Grading: the default grid loses its "Contraintes" part, "auto" only.
$names = array_column(grille::effective_parts(grille::default_grid(false), ['contraintes']), 'nom');
test('the default grid does not grade the constraints when they are absent', fn() =>
    !in_array('Contraintes', $names, true) && in_array('Cohérence globale', $names, true));
$forced = grille::default_grid(false);
$forced[3]['evaluee'] = 'oui';
test('a part forced to "Oui" stays graded', fn() =>
    in_array('Contraintes', array_column(grille::effective_parts($forced, ['contraintes']), 'nom'), true));

// Prompt: the AI neither grades nor asks for an absent zone.
$teacher = (object) ['ai_instructions' => '', 'interacteurs_data' => json_encode($model)];
$builder = new ai_prompt_builder(['flexibility' => false, 'absent_zones' => ['flexibilite', 'contraintes']]);
$parts = array_column($builder->graded_parts(4, $teacher), 'nom');
test('the builder does not grade an absent zone', fn() => !in_array('Contraintes', $parts, true) && count($parts) === 4);
$system = $builder->build_system_prompt(4, $teacher);
test('the system prompt tells the AI not to ask for the absent zone', fn() =>
    strpos($system, 'Le modèle de correction ne contient pas : les contraintes.') !== false
    && strpos($system, '- Contraintes (poids') === false);
test('flexibility off: the absent flexibility is not mentioned', fn() => strpos($system, 'la flexibilité') === false);
$plain = (new ai_prompt_builder(['flexibility' => false]))->build_system_prompt(4, $teacher);
test('no absent zone: no sentence about it', fn() => strpos($plain, 'Le modèle de correction ne contient pas') === false);

// A part forced to "Oui" stays graded: the AI must not be told that nothing is expected there (review).
$forcedteacher = clone $teacher;
$forcedgrid = grille::default_grid(false);
$forcedgrid[3]['evaluee'] = 'oui';
$forcedteacher->grille_json = json_encode($forcedgrid);
$forcedsystem = $builder->build_system_prompt(4, $forcedteacher);
test('forced part: graded and not said absent', fn() => strpos($forcedsystem, '- Contraintes (poids') !== false
    && strpos($forcedsystem, 'ne contient pas : les contraintes') === false);

// The instructions generator judges the model shown on the page, not the saved one (review).
$ajax = file_get_contents(GP_ROOT . '/ajax/generate_ai_instructions.php');
test('generator: absent zones from the model being edited', fn() =>
    strpos($ajax, "gestionprojet_cdcf_absent_zones(\$gestionprojet,\n            (string)(\$tmpmodel->interacteurs_data") !== false);

// Teacher page: the grid editor tells why a part is not graded.
$page = file_get_contents(GP_ROOT . '/pages/step4_teacher.php');
$js = file_get_contents(GP_ROOT . '/amd/src/grille_editor.js');
test('the grid editor receives the absent zones', fn() => strpos($page, "'absentZones' => gestionprojet_cdcf_absent_zones(")
    !== false && strpos($page, "'status_absent'") !== false);
test('the grid editor shows the absent status', fn() => strpos($js, 'cfg.absentZones') !== false
    && strpos($js, 'lang.statusAbsent') !== false);
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("status string ($lang)", fn() => !empty($string['grille_status_absent']));
}
$lib = file_get_contents(GP_ROOT . '/lib.php');
$evaluator = file_get_contents(GP_ROOT . '/classes/ai_evaluator.php');
test('the evaluator and the level mode use the absent zones', fn() =>
    strpos($evaluator, "\$builderoptions['absent_zones']") !== false
    && preg_match('/function gestionprojet_palier_parts.*?gestionprojet_cdcf_absent_zones\(/s', $lib));

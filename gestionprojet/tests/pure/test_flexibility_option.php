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
 * Checks of the "ask for flexibility (F0-F3)" instance option (preco 7).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/cdcf_helper.php');
require(GP_ROOT . '/classes/ai_prompt_builder.php');

use mod_gestionprojet\ai_prompt_builder;

$cdcf = json_encode(['interactors' => [['id' => 1, 'name' => 'Robot'], ['id' => 2, 'name' => 'Terrain']],
    'fonctionsService' => [['id' => 1, 'description' => 'Doit franchir les obstacles du terrain', 'interactor1Id' => 2,
        'interactor2Id' => 0, 'criteres' => [['id' => 1, 'description' => 'Hauteur', 'niveau' => '10 cm', 'flexibilite' => 'F1']]]],
    'contraintes' => []]);
$sub = (object) ['interacteurs_data' => $cdcf];
$model = (object) ['interacteurs_data' => $cdcf, 'ai_instructions' => ''];

$off = new ai_prompt_builder(['flexibility' => false]);
$on = new ai_prompt_builder();

test('flexibility off: absent from the grading grid', fn() =>
    stripos($off->build_system_prompt(4, $model), 'flexibilit') === false);
test('flexibility off: absent from the student data and the model', function () use ($off, $sub, $model) {
    $p = $off->build_prompt(4, $sub, $model);
    return stripos($p['user'], 'flexibilit') === false && strpos($p['user'], 'F1') === false
        && strpos($p['user'], '10 cm') !== false;
});
test('flexibility on (default): unchanged', fn() =>
    stripos($on->build_system_prompt(4, $model), 'flexibilit') !== false
    && stripos($on->build_prompt(4, $sub, $model)['user'], 'flexibilité : F1') !== false);

$js = file_get_contents(GP_ROOT . '/amd/src/cdcf.js');
test('the CDCF editor hides the F0-F3 select when told to', fn() =>
    strpos($js, 'opts.showFlexibility === false') !== false);
test('the bootstrap passes the option to the editor', fn() =>
    strpos(file_get_contents(GP_ROOT . '/amd/src/cdcf_bootstrap.js'), 'showFlexibility: cfg.showFlexibility') !== false);
test('the three CDCF pages pass the instance option', function () {
    foreach (['pages/step4.php', 'pages/step4_teacher.php', 'pages/step4_provided.php'] as $p) {
        if (strpos(file_get_contents(GP_ROOT . '/' . $p), "'showFlexibility'") === false) {
            echo "      $p\n";
            return false;
        }
    }
    return true;
});
test('the evaluator and the instructions generator pass the option to the builder', fn() =>
    strpos(file_get_contents(GP_ROOT . '/classes/ai_evaluator.php'), "new ai_prompt_builder(['flexibility'") !== false
    && strpos(file_get_contents(GP_ROOT . '/ajax/generate_ai_instructions.php'), "ai_prompt_builder(['flexibility'") !== false);
test('the grading page hides the flexibility when off', fn() =>
    strpos(file_get_contents(GP_ROOT . '/grading.php'), 'step4_flexibility') !== false);

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
 * Checks of the v2.11.0 final review fixes (flexibility leak, instructions generator, level labels).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\grille;
use mod_gestionprojet\ai_prompt_builder;

// 1. Flexibility off: the default grid itself carries no flexibility, so editing its wording cannot bring it back.
$noflex = grille::default_grid(false);
test('default grid without flexibility: no flexibility wording', fn() =>
    stripos(json_encode($noflex, JSON_UNESCAPED_UNICODE), 'flexibilit') === false);
test('default grid without flexibility: no flexibility zone', fn() =>
    !in_array('flexibilite', array_merge(...array_column($noflex, 'zones')), true));
test('default grid with flexibility: unchanged', fn() => grille::default_grid(true) === grille::default_grid());
test('decode falls back to the grid of the option', fn() => grille::decode(null, false) === $noflex);
$edited = json_decode(json_encode(grille::default_grid(false)), true);
$edited[2]['description'] .= ' (vérifier les unités)';
test('flexibility off: a teacher edit of the default wording never reintroduces flexibility', fn() =>
    stripos((new ai_prompt_builder(['flexibility' => false]))->build_system_prompt(4,
        (object) ['ai_instructions' => '', 'grille_json' => json_encode($edited)]), 'flexibilit') === false);
$page = file_get_contents(GP_ROOT . '/pages/step4_teacher.php');
test('the page gives the editor the grid of the option', fn() =>
    strpos($page, "grille::decode(\$model->grille_json ?? null, \$showflex)") !== false
    && strpos($page, "grille::default_grid(\$showflex)") !== false);
$js = file_get_contents(GP_ROOT . '/amd/src/grille_editor.js');
test('ticking a zone keeps the zones the editor does not show', fn() => strpos($js, 'function keepHiddenZones(') !== false);

// 2. The instructions generator sees the grid.
$gen = file_get_contents(GP_ROOT . '/ajax/generate_ai_instructions.php');
test('generate_ai_instructions reads the saved grid', fn() => strpos($gen, '$tmpmodel->grille_json') !== false);
$robot = (object) ['ai_instructions' => '', 'grille_json' => json_encode([
    ['id' => 1, 'nom' => 'Fonctions de service', 'poids' => 6, 'zones' => ['fonctions']],
    ['id' => 2, 'nom' => 'Contraintes', 'poids' => 3, 'zones' => ['contraintes'], 'evaluee' => 'non'],
])];
test('the meta prompt follows the grid', function () use ($robot) {
    $p = (new ai_prompt_builder())->build_meta_prompt(4, $robot);
    $all = $p['system'] . $p['user'];
    return strpos($all, 'Fonctions de service (poids: 20/20)') !== false && strpos($all, 'Contraintes (poids') === false;
});

// 3. Level labels come from the language files.
test('no hard-coded level label in the editor', fn() => strpos($js, "'FRA'") === false && strpos($js, "'SAT'") === false);
test('level labels are passed by the page', fn() => strpos($page, "'palier_f'") !== false);

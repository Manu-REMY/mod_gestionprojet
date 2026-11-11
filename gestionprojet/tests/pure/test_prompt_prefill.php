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
 * The AI sees pre-filled fields and provided links (v2.11.3).
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

$p = cdcf_helper::normalize([
    'interactors' => [['id' => 1, 'name' => 'Utilisateur', 'locked' => true], ['id' => 2, 'name' => 'Atmosphère', 'locked' => true]],
    'fonctionsService' => [['id' => 1, 'description' => 'Doit … [terrain].', 'interactor1Id' => 1, 'interactor2Id' => 2,
        'prefill' => true, 'linksLocked' => true,
        'criteres' => [['id' => 1, 'description' => 'Vitesse', 'niveau' => '… km/h', 'locked' => true, 'niveauPrefill' => true]]]],
    'contraintes' => [],
]);
$student = consigne_lock::seed($p);
$student['fonctionsService'][0]['criteres'][0]['niveau'] = '5 km/h';
$model = (object) ['ai_instructions' => '', 'interacteurs_data' => ''];
$user = (new ai_prompt_builder())->build_user_prompt(4,
    (object) ['interacteurs_data' => json_encode(consigne_lock::with_flags($student, $p))], $model,
    (object) ['interacteurs_data' => json_encode($p)]);
$part = substr($user, strpos($user, "PRODUCTION DE L'ÉLÈVE"));
test('a pre-filled statement left as is is marked as such (request 33)', fn() =>
    strpos($part, 'Doit … (terrain). [pré-rempli, laissé tel quel]') !== false);
test('a completed pre-filled level is marked as the student work, with the starting text', fn() =>
    strpos($part, "5 km/h [pré-rempli, complété par l'élève ; texte de départ : « … km/h »]") !== false);
test('provided links are marked', fn() => strpos($part, '[Utilisateur ↔ Atmosphère] [liaison fournie]') !== false);
test('the rule explains pre-filled fields', fn() =>
    strpos($user, 'Un champ [pré-rempli, laissé tel quel] ne rapporte aucun point') !== false);
$plain = cdcf_helper::normalize(['interactors' => [['id' => 1, 'name' => 'A']]]);
$plainuser = (new ai_prompt_builder())->build_user_prompt(4, (object) ['interacteurs_data' => json_encode($plain)], $model,
    (object) ['interacteurs_data' => json_encode($plain)]);
test('no flag: no pre-filled marker nor rule', fn() => strpos($plainuser, 'pré-rempli') === false
    && strpos($plainuser, 'liaison fournie') === false);

// Final review: the consigne section carries no hint: no empty quote.
test('consigne section: no empty pre-filled quote', fn() => strpos($user, '[pré-rempli : «  »]') === false
    && strpos($user, '[pré-rempli]') !== false);

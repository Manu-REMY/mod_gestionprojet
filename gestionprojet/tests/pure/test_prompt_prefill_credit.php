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
 * A pre-filled field completed by the student counts fully (request 33, v2.13.3).
 *
 * Projet PIEGE, 11/11/2026: a copy equal to the correction model, every "…" completed, got 14/20.
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
    'interactors' => [['id' => 1, 'name' => 'Propriétaire', 'locked' => true], ['id' => 2, 'name' => 'Intrus', 'locked' => true]],
    'fonctionsService' => [
        ['id' => 1, 'description' => 'Permettre au propriétaire de … son domicile.', 'interactor1Id' => 1, 'interactor2Id' => 0,
            'prefill' => true, 'criteres' => [['id' => 1, 'description' => 'Portée de …', 'niveau' => '… m',
                'prefill' => true, 'niveauPrefill' => true]]],
        ['id' => 2, 'description' => '… l\'intrus.', 'interactor1Id' => 2, 'interactor2Id' => 0, 'prefill' => true,
            'criteres' => [['id' => 2, 'description' => 'Temps de …', 'niveau' => '', 'prefill' => true, 'niveauGap' => true]]],
    ],
    'contraintes' => [],
]);
$student = consigne_lock::seed($p);
$student['fonctionsService'][0]['description'] = 'Permettre au propriétaire de sécuriser son domicile.';
$student['fonctionsService'][0]['criteres'][0]['description'] = 'Portée de détection';
$student['fonctionsService'][0]['criteres'][0]['niveau'] = '';
// Only the "…" removed and the case changed: nothing added (review).
$student['fonctionsService'][1]['criteres'][0]['description'] = 'temps de';
$model = (object) ['ai_instructions' => '', 'interacteurs_data' => ''];
$user = (new ai_prompt_builder())->build_user_prompt(4,
    (object) ['interacteurs_data' => json_encode(consigne_lock::with_flags($student, $p))], $model,
    (object) ['interacteurs_data' => json_encode($p)]);
$part = substr($user, strpos($user, "PRODUCTION DE L'ÉLÈVE"));

test('a completed pre-filled statement is marked as completed by the student', fn() =>
    strpos($part, "Permettre au propriétaire de sécuriser son domicile. [pré-rempli, complété par l'élève ; "
        . "texte de départ : « Permettre au propriétaire de … son domicile. »]") !== false);
test('a pre-filled field left as is says so, without a quote that looks like an empty field', fn() =>
    strpos($part, "… l'intrus. [pré-rempli, laissé tel quel]") !== false);
test('removing the "…" or changing the case is not completing', fn() =>
    strpos($part, "temps de [pré-rempli, laissé tel quel]") !== false);
test('a pre-filled field emptied by the student says so', fn() =>
    strpos($part, "niveau : (non précisé) [pré-rempli, vidé par l'élève]") !== false);

test('a completed pre-filled field counts fully', fn() =>
    strpos($user, "Un champ [pré-rempli, complété par l'élève] est le travail de l'élève : évalue-le en entier") !== false
    && strpos($user, 'ne retire aucun point parce qu\'il est parti d\'un texte fourni') !== false);
test('a copy equivalent to the correction model gets 20/20', fn() =>
    substr_count($user, 'Une production équivalente au modèle de correction vaut 20/20') === 2);
test('the old wording that undercounted is gone', fn() =>
    strpos($user, 'évalue seulement ce que') === false
    && strpos($user, 'ne doivent pas être pris en compte dans l\'évaluation positive') === false);
test('content left as is still earns nothing', fn() =>
    strpos($user, 'Tout contenu resté identique à la consigne') !== false
    && strpos($user, 'Si l\'élève n\'a rien modifié, la note doit être 0.') !== false);

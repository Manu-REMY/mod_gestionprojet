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
 * The answer leak filter removes whole sentences, never leaving a piece of one (request 35, v2.13.3).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\answer_leak_filter;

$forbidden = answer_leak_filter::forbidden_roots('Sécuriser le domicile. Détecter un intrus.',
    'protéger la maison code Caméra alerter fonctionner');

// Case seen on Projet PIEGE (11/11/2026): the split after "ex." left the end of the sentence alone.
$out = answer_leak_filter::filter('Tes fonctions ne disent pas le service rendu, il fallait sécuriser '
    . '(ex. « protéger la maison », « code », « Caméra »). Commence chaque FS par un verbe.', $forbidden);
test('no split after "ex.": the whole sentence goes', fn() => $out === 'Commence chaque FS par un verbe.');

$out = answer_leak_filter::filter('Le domicile manque (par ex. « alerter », « fonctionner »). Bon travail.', $forbidden);
test('no split after "par ex."', fn() => $out === 'Bon travail.');

$out = answer_leak_filter::filter('Évite le domicile, ex. Caméra ou Code. Bon travail.', $forbidden);
test('no split after an abbreviation outside parentheses', fn() => $out === 'Bon travail.');

$out = answer_leak_filter::filter('Revois le domicile (voir FS1. Puis FS2). Bon travail.', $forbidden);
test('no split inside parentheses', fn() => $out === 'Bon travail.');

$out = answer_leak_filter::filter('Le domicile est à revoir. Tes critères sont clairs ! Continue ainsi.', $forbidden);
test('real sentences are still split', fn() => $out === 'Tes critères sont clairs ! Continue ainsi.');

$out = answer_leak_filter::filter("Points forts :\n- Le domicile est bien vu\n- Tes critères sont clairs", $forbidden);
test('lines are separate sentences and line breaks stay', fn() => $out === "Points forts :\n- Tes critères sont clairs");

$out = answer_leak_filter::filter('Il manque le domicile. « Caméra » est un objet, pas une fonction.', $forbidden);
test('a sentence opening on a quote is split', fn() => $out === '« Caméra » est un objet, pas une fonction.');

$out = answer_leak_filter::filter('Pense au domicile… mais aussi à l\'alarme. Bon travail.', $forbidden);
test('a lowercase word after an ellipsis continues the sentence', fn() => $out === 'Bon travail.');

test('no forbidden root: the comment is unchanged', fn() =>
    answer_leak_filter::filter("  Bien (ex. FS1). Continue.\n", []) === 'Bien (ex. FS1). Continue.');

$all = answer_leak_filter::filter('Il fallait sécuriser le domicile.', $forbidden);
test('everything forbidden: empty text', fn() => $all === '');

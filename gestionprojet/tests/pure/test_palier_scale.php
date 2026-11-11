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
 * Level verdict on the teacher's weights (request 38) and level reached in the 20-point mode (request 37), v2.13.5.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

use mod_gestionprojet\grille;
use mod_gestionprojet\palier;

// Grid of Projet PIEGE (11/11/2026): FS 8 (F), criteria 6 (S), levels 6 (TS).
$parts = grille::effective_parts(grille::normalize([
    ['id' => 1, 'nom' => 'Fonctions de service', 'poids' => 8, 'zones' => ['fonctions'], 'palier' => 'F'],
    ['id' => 2, 'nom' => "Critères d'appréciation", 'poids' => 6, 'zones' => ['criteres'], 'palier' => 'S'],
    ['id' => 3, 'nom' => "Niveaux d'exigence", 'poids' => 6, 'zones' => ['niveaux'], 'palier' => 'TS'],
]));
$ai = fn(array $rows) => array_map(fn($r) => ['name' => $r[0], 'score' => $r[1], 'max' => $r[2] ?? null], $rows);

// Request 38: a level of two parts (8 and 6, scaled to 11.5 and 8.5), the AI grading on the teacher's weights.
$two = grille::effective_parts(grille::normalize([
    ['id' => 1, 'nom' => 'Fonctions de service', 'poids' => 8, 'zones' => ['fonctions'], 'palier' => 'S'],
    ['id' => 2, 'nom' => "Critères d'appréciation", 'poids' => 6, 'zones' => ['criteres'], 'palier' => 'S'],
]));
$sparts = palier::parts_for($two, 'S');
test('38: a perfect copy graded on the teacher weights (8/8, 6/6) validates at 100 %', fn() =>
    palier::verdict($ai([['Fonctions de service', 8, 8], ["Critères d'appréciation", 6, 6]]), $sparts, 80)
    === ['valide' => true, 'percent' => 100.0]);
test('38: graded on the scaled weights (11.5, 8.5) also 100 %', fn() =>
    palier::verdict($ai([['Fonctions de service', 11.5, 11.5], ["Critères d'appréciation", 8.5, 8.5]]), $sparts, 80)
    === ['valide' => true, 'percent' => 100.0]);
test('38: half of each on the teacher weights is 50 %', fn() =>
    palier::verdict($ai([['Fonctions de service', 4, 8], ["Critères d'appréciation", 3, 6]]), $sparts, 50)['percent'] === 50.0);
test('38: an invented max is never trusted (1/1 is 1 point of 11.5)', fn() =>
    palier::verdict($ai([['Fonctions de service', 1, 1], ["Critères d'appréciation", 1, 1]]), $sparts, 50)['valide'] === false);
test('38: no max: the scaled weight', fn() =>
    palier::verdict($ai([['Fonctions de service', 11.5], ["Critères d'appréciation", 8.5]]), $sparts, 100)['valide'] === true);
test('38: a score above its max counts as the max', fn() =>
    palier::verdict($ai([['Fonctions de service', 80, 8], ["Critères d'appréciation", 0, 6]]), $sparts, 60)['valide'] === false);

// Request 37: level reached from the grade of the whole grid.
$full = $ai([['Fonctions de service', 8, 8], ["Critères d'appréciation", 6, 6], ["Niveaux d'exigence", 6, 6]]);
$level = palier::level_reached($full, $parts, 80);
test('37: everything acquired: TS, nothing next', fn() => $level['reached'] === 'TS' && $level['next'] === ''
    && $level['missing'] === []);
$mid = palier::level_reached($ai([['Fonctions de service', 8, 8], ["Critères d'appréciation", 3, 6], ["Niveaux d'exigence", 6, 6]]),
    $parts, 80);
test('37: criteria at 50 %: F reached, S next, criteria to work on (TS acquired alone does not count)', fn() =>
    $mid['reached'] === 'F' && $mid['next'] === 'S' && $mid['missing'] === ["Critères d'appréciation"]);
$none = palier::level_reached($ai([['Fonctions de service', 5, 8], ["Critères d'appréciation", 6, 6], ["Niveaux d'exigence", 6, 6]]),
    $parts, 80);
test('37: FS at 62.5 %: no level, F next', fn() => $none['reached'] === '' && $none['next'] === 'F'
    && $none['missing'] === ['Fonctions de service']);
test('37: at exactly the threshold the part is acquired', fn() =>
    palier::level_reached($ai([['Fonctions de service', 6.4, 8]]), $parts, 80)['reached'] === 'F');
test('37: a grid without levels gives nothing', fn() =>
    palier::level_reached($full, grille::effective_parts(grille::default_grid(false)), 80) === null);
test('37: no criteria gives nothing', fn() => palier::level_reached([], $parts, 80) === null);
// In the 20-point mode the AI saw the weights scaled on the whole grid: 8, 6, 6 here, i.e. the teacher's.
$scaled = palier::level_reached($ai([['Fonctions de service', 8, 8], ["Critères d'appréciation", 6, 6],
    ["Niveaux d'exigence", 2, 6]]), $parts, 80);
test('37: levels at 33 %: S reached, TS next', fn() => $scaled['reached'] === 'S' && $scaled['next'] === 'TS');

// Display: banner at the top of the feedback, level doubled by its acronym and a mark (never the colour alone).
$read = fn(string $path): string => is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
$lib = $read('lib.php');
$css = $read('styles.css');
test('lib: the progress state computes the level for step 4 outside the level mode', fn() =>
    preg_match("/'level' => \\\$level/", $lib) === 1 && strpos($lib, 'palier::level_reached(') !== false);
test('lib: the feedback view renders the level banner first', fn() =>
    preg_match('/function gestionprojet_progress_feedback_html.*?gestionprojet_level_banner_html\(.*?gp-progress-date/s', $lib) === 1
    && preg_match("/function gestionprojet_level_banner_html.*?'gp-level-banner gp-level-'/s", $lib) === 1);
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("strings ($lang)", fn() => !empty($string['level_reached']) && !empty($string['level_none'])
        && !empty($string['level_next']) && !empty($string['level_name_f']) && !empty($string['level_name_s'])
        && !empty($string['level_name_ts']));
}
test('css: one style per level, namespaced', fn() => strpos($css, '.path-mod-gestionprojet .gp-level-banner.gp-level-f') !== false
    && strpos($css, '.path-mod-gestionprojet .gp-level-banner.gp-level-s') !== false
    && strpos($css, '.path-mod-gestionprojet .gp-level-banner.gp-level-ts') !== false
    && strpos($css, '.path-mod-gestionprojet .gp-level-banner.gp-level-none') !== false);
test('lib: the banner escapes every sentence (part names typed by the teacher)', function () use ($lib) {
    preg_match('/function gestionprojet_level_banner_html.*?\n}\n/s', $lib, $m);
    $body = $m[0] ?? '';
    return $body !== '' && substr_count($body, 'html_writer::span(s(') === 3 && substr_count($body, 'html_writer::div(s(') === 2;
});

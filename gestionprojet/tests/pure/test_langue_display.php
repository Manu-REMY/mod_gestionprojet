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
 * Spelling at step 4: level cap and spelling block (request 32, v2.15.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$lib = file_get_contents(GP_ROOT . '/lib.php');
$grading = file_get_contents(GP_ROOT . '/grading.php');
preg_match('/function gestionprojet_level_sync\(.*?\n\}\n/s', $lib, $m);
$sync = $m[0] ?? '';
preg_match('/function gestionprojet_progress_state\(.*?\n\}\n/s', $lib, $m);
$state = $m[0] ?? '';
preg_match('/function gestionprojet_spelling_block_html\(.*?\n\}\n/s', $lib, $m);
$block = $m[0] ?? '';
test('sync: the level is capped by the spelling mistakes', fn() => strpos($sync, 'orthographe::cap_level(') !== false);
test('sync: a teacher grade is compared with the total, and rescales from it', fn() =>
    strpos($sync, '$total = gestionprojet_evaluation_total($gestionprojet, $evaluation);') !== false
    && strpos($sync, 'abs((float)$submission->grade - $total) > 0.01') !== false
    && strpos($sync, 'palier::criteria_for_grade($criteria, $parts, $total, (float)$submission->grade)') !== false);
test('banner: capped too, teacher grade compared with the total', fn() => strpos($state, 'orthographe::cap_level(') !== false
    && strpos($state, 'gestionprojet_evaluation_total($gestionprojet, $pick[\'evaluation\'])') !== false);
test('state: the spelling block follows "show feedback"', fn() => strpos($state, "'langue' => \$langue") !== false
    && preg_match('/show_feedback\) && !\$evaluation->show_feedback\) \{.*?\$langue = null;/s', $state) === 1);
test('feedback view: banner, then spelling block', fn() =>
    preg_match('/gestionprojet_level_banner_html\(.*?gestionprojet_spelling_block_html\(/s', $lib) === 1);
test('block: points, one escaped line per mistake, "no mistake", nothing without spelling', fn() =>
    strpos($block, "get_string('langue_block_title'") !== false && strpos($block, "s(\$faute['cite'])") !== false
    && strpos($block, "s(\$faute['correction'])") !== false && strpos($block, "'langue_no_mistake'") !== false
    && preg_match('/if \(\$langue === null\) \{\s*return \'\';/', $block) === 1);
test('grading: total grade shown and applied, with the detail', fn() =>
    strpos($grading, 'gestionprojet_evaluation_total($gestionprojet, $aievaluation)') !== false
    && substr_count($grading, 'data-grade="<?php echo $aitotal; ?>"') === 2 && strpos($grading, "'langue_detail'") !== false);
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("strings ($lang)", fn() => !empty($string['langue_block_title']) && !empty($string['langue_no_mistake'])
        && !empty($string['langue_detail']) && strpos($string['langue_detail'], '{$a->content}') !== false);
}
test('css', fn() => strpos(file_get_contents(GP_ROOT . '/styles.css'), '.path-mod-gestionprojet .gp-langue-block') !== false);

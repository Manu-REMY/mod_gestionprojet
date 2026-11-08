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
 * Markup and styles of the student progress panel (v2.12.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$panel = is_file(GP_ROOT . '/pages/student_progress_panel.php') ? file_get_contents(GP_ROOT . '/pages/student_progress_panel.php') : '';
test('panel uses gestionprojet_progress_state', fn() => strpos($panel, 'gestionprojet_progress_state(') !== false);
test('panel is an aside with a heading', fn() => strpos($panel, "'aside'") !== false && strpos($panel, "'progress_title'") !== false);
test('panel closes the main column and the layout', fn() => substr_count($panel, "echo '</div>';") >= 2);
test('panel has the submit button', fn() => strpos($panel, "'id' => 'submitButton'") !== false);
test('panel has the feedback request button and banner', fn() => strpos($panel, "'gpFormativeButton'") !== false
    && strpos($panel, "'gpFormativeBanner'") !== false);
test('panel has the live list holder and a progress bar', fn() => strpos($panel, "'data-gp-progress-list'") !== false
    && strpos($panel, "'role' => 'progressbar'") !== false);
test('panel: read-only for a teacher looking at a copy', fn() => strpos($panel, "optional_param('groupid'") !== false
    && strpos($panel, "'mod/gestionprojet:grade'") !== false);
test('panel: level mode reuses the level block', fn() => strpos($panel, 'student_palier_display.php') !== false);
test('panel: no inline script or style', fn() => stripos($panel, '<script') === false && stripos($panel, '<style') === false);
test('panel: statuses with a symbol and a word', fn() => strpos($panel, "'✎ '") !== false && strpos($panel, "'✓ '") !== false);
$lib = file_get_contents(GP_ROOT . '/lib.php');
test('the main column has an id to go back to the form', fn() => strpos($lib, 'id="gpProgressMain"') !== false);
$css = file_get_contents(GP_ROOT . '/styles.css');
test('styles: two-column grid', fn() => preg_match('/\.path-mod-gestionprojet \.gp-progress-layout\s*\{[^}]*grid-template-columns/', $css) === 1);
test('styles: the panel is sticky', fn() => preg_match('/\.path-mod-gestionprojet \.gp-progress-panel\s*\{[^}]*position:\s*sticky/', $css) === 1);
test('styles: one column on a narrow screen', fn() => strpos($css, '@media (max-width: 991.98px)') !== false);
test('styles: one column when the Moodle block drawer is open', fn() => strpos($css, '#page.show-drawer-right') !== false);
test('styles: buttons stick at the bottom on a narrow screen', fn() => strpos($css, '.gp-progress-actions') !== false
    && preg_match('/bottom:\s*0/', $css) === 1);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['progress_title', 'progress_status', 'progress_status_draft', 'progress_status_submitted', 'progress_status_pending',
            'progress_grade', 'progress_grade_previous', 'progress_grade_none', 'progress_todo_title', 'progress_todo_count',
            'progress_todo_done', 'progress_goto_form', 'progress_feedback_title', 'progress_feedback_none', 'progress_submit',
            'progress_readonly'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

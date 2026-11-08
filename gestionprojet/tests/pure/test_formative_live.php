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
 * Formative feedback shown in place when ready, spinner while waiting (v2.12.2).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

/**
 * Source of a plugin file.
 *
 * @param string $path
 * @return string
 */
function src(string $path): string {
    return is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
}

$lib = src('lib.php');
$panel = src('pages/student_progress_panel.php');
$ajax = src('ajax/get_evaluation_status.php');
$js = src('amd/src/formative_button.js');
$css = src('styles.css');

// One renderer for the feedback, used by the panel and by the status endpoint.
test('lib: gestionprojet_progress_feedback_html() exists', fn() =>
    strpos($lib, 'function gestionprojet_progress_feedback_html(array $feedback): string') !== false);
test('panel: renders the feedback with gestionprojet_progress_feedback_html()', fn() =>
    strpos($panel, "gestionprojet_progress_feedback_html(\$progress['feedback'])") !== false);

// Endpoint: a student gets the filtered feedback of their own formative request, rendered by the server.
test('endpoint: formative feedback html for the student, from the progress state', fn() =>
    strpos($ajax, "'feedback_html'") !== false && strpos($ajax, 'gestionprojet_progress_state(') !== false
    && strpos($ajax, 'gestionprojet_progress_feedback_html(') !== false && strpos($ajax, '->formatif') !== false);
test('endpoint: tells whether the student can ask again (label or none left)', fn() =>
    strpos($ajax, "'ask_label'") !== false && strpos($ajax, "'none_left'") !== false);
test('endpoint: raw AI fields still for graders only', fn() =>
    strpos($ajax, "\$response['feedback'] = \$evaluation->parsed_feedback;") !== false
    && preg_match("/'applied'\) && \\\$isTeacher\)/", $ajax) === 1);

// Waiting: a spinner (symbol hidden from screen readers, the words announced).
test('panel: spinner in the pending banner, words announced', fn() =>
    strpos($panel, "'gp-spinner'") !== false && strpos($panel, "'aria-hidden' => 'true'") !== false
    && strpos($panel, "'aria-live' => 'polite'") !== false);
test('panel: the follow-up knows the step and the group (to ask again)', fn() =>
    preg_match("/'pendingId' => \(int\)\\\$formative\['pending'\]->id,\s*'step' => \(int\)\\\$step,\s*'groupid' => \(int\)\\\$groupid,/", $panel) === 1);
test('palier banner: spinner too', fn() => strpos(src('pages/student_palier_display.php'), "'gp-spinner'") !== false);
test('styles: spinner animated, still when reduced motion is asked', fn() =>
    strpos($css, '.path-mod-gestionprojet .gp-spinner {') !== false
    && preg_match('/prefers-reduced-motion: reduce\)\s*\{[^}]*\.gp-spinner/s', $css) === 1);

// Ready: the feedback replaces the banner by itself, no click, no reload.
test('js: puts the feedback in place with core/templates', fn() =>
    strpos($js, "'core/templates'") !== false && strpos($js, 'Templates.replaceNodeContents(') !== false
    && strpos($js, 'data.feedback_html') !== false);
test('js: no "show" button, no reload once the feedback is ready', fn() =>
    strpos($js, 'cfg.strings.show') === false && strpos($js, 'location.reload') === false
    && strpos($js, 'innerHTML') === false);
test('js: the ask button comes back (or "none left")', fn() =>
    strpos($js, 'data.ask_label') !== false && strpos($js, 'data.none_left') !== false);
test('build formative_button.min.js up to date', fn() => is_file(GP_ROOT . '/amd/build/formative_button.min.js')
    && filemtime(GP_ROOT . '/amd/build/formative_button.min.js') >= filemtime(GP_ROOT . '/amd/src/formative_button.js'));

// Final review (v2.12.2).
test('endpoint: read-only rule of the panel (a teacher on a copy that is not theirs, group copies included)', fn() =>
    strpos($ajax, '$readonly = $isTeacher && (int)$submission->userid !== (int)$USER->id;') !== false);
test('js: a finished feedback is handled once (overlapping polls)', fn() =>
    strpos($js, 'if (done || !data') !== false && strpos($js, 'done = true;') !== false);

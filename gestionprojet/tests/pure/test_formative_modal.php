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
 * Feedback window (v2.17.0, as mod_redaction D-086; replaces the waiting window of request 36).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$read = fn(string $path): string => is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
$js = $read('amd/src/formative_button.js');
$panel = $read('pages/student_progress_panel.php');
$endpoint = $read('ajax/get_evaluation_status.php');
$lib = $read('lib.php');
$css = $read('styles.css');

// Waiting.
test('js: a centred window, announced as a dialog', fn() => strpos($js, 'gp-formative-modal') !== false
    && strpos($js, "'role', 'dialog'") !== false && strpos($js, "'aria-modal', 'true'") !== false
    && strpos($js, "'aria-labelledby', 'gpRetourTitle'") !== false);
test('js: the window opens on the ask click, before saving', fn() =>
    preg_match("/button\.disabled = true;\s*openWait\(cfg\.strings, 'pending', 0\);\s*\/\/[^\n]*\n\s*saveCopy\(\)/", $js) === 1);
test('js: the window opens while a feedback is pending, with its status and time', fn() =>
    preg_match("/function follow\(cfg\) \{.*?openWait\(cfg\.strings, cfg\.pendingStatus \|\| 'pending', cfg\.elapsed \|\| 0\);/s", $js) === 1);
test('js: waiting cannot be closed (no cross, Escape only when closable)', fn() =>
    strpos($js, 'closable: false') !== false && strpos($js, 'if (modal.closable) {') !== false);
test('js: a timer and three true steps', fn() => strpos($js, 'gp-retour-timer') !== false
    && strpos($js, 'strings.sent') !== false && strpos($js, 'strings.show') !== false
    && strpos($js, "status === 'processing' ? strings.processing : strings.queue") !== false);
test('js: the status of each poll updates the second step', fn() => strpos($js, 'setStatus(cfg.strings, data.status);') !== false);
test('js: the steps are read aloud when they change', fn() => strpos($js, "'aria-live', 'polite'") !== false);
test('js: focus kept in the window and given back on close', fn() => strpos($js, "event.key === 'Tab'") !== false
    && strpos($js, 'returnFocus: document.activeElement') !== false);
test('js: a failed ask closes the window', fn() => preg_match('/\.catch\(function\(error\) \{\s*closeModal\(\);/', $js) === 1);

test('panel: a read-only teacher is never blocked by the waiting window (review)', fn() =>
    preg_match('/if \(!\$progressreadonly\) \{\s*\$PAGE->requires->js_call_amd\(\'mod_gestionprojet\/formative_button\', \'init\', \[\[\s*\'cmid\' => \(int\)\$cm->id,\s*\'pendingId\'/', $panel) === 1);
test('js: a deleted evaluation ends the wait as a failure (review)', fn() =>
    strpos($js, 'var gone = data.has_evaluation === false;') !== false
    && strpos($js, "if (!gone && data.status !== 'completed' && data.status !== 'failed')") !== false);
test('js: after 3 minutes the student may leave the window, polling goes on (review)', fn() =>
    strpos($js, 'LEAVE_AFTER_MS = 180000') !== false && strpos($js, 'allowLeave(cfg.strings);') !== false
    && preg_match('/function allowLeave\(strings\) \{.*?modal\.closable = true;.*?strings\.back/s', $js) === 1);

// Result.
test('js: the feedback is shown in the window (server HTML, escaped there)', fn() =>
    strpos($js, 'Templates.replaceNodeContents(body, data.feedback_html') !== false);
test('js: from SAT, submit put forward, improve while feedbacks are left', fn() =>
    preg_match('/if \(data\.propose_submit .*?data\.improve_label.*?strings\.submit, \'btn-primary\'/s', $js) === 1);
test('js: submit closes the window and opens the usual confirmation', fn() =>
    preg_match("/closeModal\(\);\s*var submit = document\.getElementById\('submitButton'\);/", $js) === 1);
test('js: below SAT, back to my copy', fn() => strpos($js, "addAction(actions, strings.back, 'btn-primary', closeModal)") !== false);
test('js: the result can be closed (cross, Escape)', fn() => strpos($js, 'modal.closable = true;') !== false
    && strpos($js, "cross.setAttribute('aria-label', strings.close)") !== false);
test('js: failure -> not counted, back to the copy', fn() =>
    preg_match('/function showFailed\(strings, message\).*?strings\.failed.*?strings\.back/s', $js) === 1);
test('js: a failed render does not leave the window on "in preparation"', fn() =>
    preg_match('/return showResult\(cfg\.strings, data\);.*?\.catch\(function\(\) \{.*?showFailed\(cfg\.strings, cfg\.strings\.error\);/s', $js) === 1);
test('js: replaceNodeContents is not a promise: never .then on it (preprod, 18/11)', fn() =>
    preg_match('/replaceNodeContents\([^;]*?\)\s*\.then/', $js) === 0);
test('js: never innerHTML', fn() => $js !== '' && strpos($js, 'innerHTML') === false);

// Server.
test('endpoint: propose_submit from retour_view', fn() =>
    strpos($endpoint, "\\mod_gestionprojet\\retour_view::propose_submit(") !== false);
test('endpoint: improve label only when feedbacks are left', fn() =>
    preg_match("/remaining'\] > 0\) \{.*?'improve_label'/s", $endpoint) === 1);
test('panel: same words for both inits', fn() => substr_count($panel, "'strings' => \$retourstrings") === 2);
test('panel: status and elapsed time of a pending feedback', fn() => strpos($panel, "'pendingStatus' =>") !== false
    && strpos($panel, "'elapsed' => max(0, time() - (int)\$formative['pending']->timecreated)") !== false);
foreach (['pending', 'sent', 'queue', 'processing', 'show', 'patience', 'elapsed', 'title', 'back', 'submit', 'failed',
        'error', 'close'] as $key) {
    test("panel: string $key passed", fn() => preg_match("/\\\$retourstrings = \[.*?'$key' =>/s", $panel) === 1);
}
test('panel: SAT banner only on a formative feedback, nothing pending', fn() =>
    preg_match("/!\\\$progress\['formative'\]\['pending'\] && \\\$progress\['feedback'\]\['source'\] === 'formative'\s*&& \\\\mod_gestionprojet\\\\retour_view::propose_submit/", $panel) === 1);
test('panel: SAT banner forwards to the submit button', fn() =>
    preg_match("/'gp-retour-seuil'.*?/s", $panel) === 1 && strpos($panel, "'data-gp-forward' => 'submitButton']),") !== false);
test('progress_panel: the SAT banner goes to the top of the main column', fn() =>
    strpos($read('amd/src/progress_panel.js'), "'.gp-progress-main > .gp-retour-seuil'") !== false);
test('lib: columns, next frame, struck-out mistakes', fn() => strpos($lib, "'gp-retour-col gp-retour-' . \$kind") !== false
    && strpos($lib, "'gp-retour-next'") !== false && strpos($lib, "html_writer::tag('del', s(\$faute['cite']))") !== false);
test('lib: level always first', fn() =>
    preg_match('/function gestionprojet_progress_feedback_html\(array \$feedback\): string \{\s*\$out = gestionprojet_level_banner_html/', $lib) === 1);

foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    foreach (['retour_title', 'retour_va', 'retour_manque', 'retour_next', 'retour_step_sent', 'retour_step_queue',
            'retour_step_processing', 'retour_step_show', 'retour_patience', 'retour_elapsed', 'retour_back', 'retour_improve',
            'retour_failed', 'retour_seuil'] as $key) {
        test("string $key ($lang)", fn() => !empty($string[$key]));
    }
}
test('lang: the French labels of mod_redaction', function () {
    $string = [];
    include(GP_ROOT . '/lang/fr/gestionprojet.php');
    return $string['retour_va'] === 'Ce qui va' && $string['retour_manque'] === 'Ce qui manque'
        && $string['retour_next'] === 'Pour ton prochain retour';
});

test('css: namespaced columns and banner', fn() => strpos($css, '.path-mod-gestionprojet .gp-retour-va {') !== false
    && strpos($css, '.path-mod-gestionprojet .gp-retour-manque {') !== false
    && strpos($css, '.path-mod-gestionprojet .gp-retour-seuil {') !== false);
test('css: spinner still for reduced motion', fn() =>
    preg_match('/prefers-reduced-motion: reduce\)\s*\{\s*\.path-mod-gestionprojet \.gp-formative-modal-spinner/', $css) === 1);
foreach (['formative_button', 'progress_panel'] as $m) {
    test("build $m.min.js is up to date", fn() => is_file(GP_ROOT . "/amd/build/$m.min.js")
        && filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js"));
}

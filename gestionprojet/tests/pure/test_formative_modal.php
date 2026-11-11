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
 * Waiting for a formative feedback: a window in the middle of the screen (request 36, v2.13.4).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$read = fn(string $path): string => is_file(GP_ROOT . '/' . $path) ? file_get_contents(GP_ROOT . '/' . $path) : '';
$js = $read('amd/src/formative_button.js');
$panel = $read('pages/student_progress_panel.php');
$css = $read('styles.css');

test('js: a centred window, announced as a dialog', fn() => strpos($js, 'gp-formative-modal') !== false
    && strpos($js, "'role', 'dialog'") !== false && strpos($js, "'aria-modal', 'true'") !== false
    && strpos($js, "'aria-labelledby'") !== false);
test('js: the words are read aloud when they change', fn() => strpos($js, "'aria-live', 'polite'") !== false);
test('js: spinner plus words, never the spinner alone', fn() => strpos($js, 'gp-formative-modal-spinner') !== false
    && strpos($js, 'strings.pending') !== false && strpos($js, 'strings.waitDetail') !== false);
test('js: the window opens on the ask click, before saving', fn() =>
    preg_match('/button\.disabled = true;\s*openWait\(cfg\.strings\);\s*\/\/[^\n]*\n\s*saveCopy\(\)/', $js) === 1);
test('js: the window opens while a feedback is pending', fn() =>
    preg_match('/function follow\(cfg\) \{.*?openWait\(cfg\.strings\);/s', $js) === 1);
test('js: "keep working" closes it, Escape too', fn() => strpos($js, 'strings.keepWorking') !== false
    && strpos($js, "event.key === 'Escape'") !== false);
test('js: ready -> says so and shows the feedback on click', fn() => strpos($js, 'strings.ready') !== false
    && strpos($js, 'strings.show') !== false && strpos($js, 'scrollIntoView') !== false);
test('js: closed by the student -> not reopened when ready', fn() => strpos($js, 'if (!modal) {') !== false);
test('js: failure -> the error in the window, with a close button', fn() =>
    preg_match('/function showFailed\(strings\).*?strings\.error.*?strings\.close/s', $js) === 1);
test('js: a failed ask closes the window', fn() =>
    preg_match('/\.catch\(function\(error\) \{\s*closeWait\(\);/', $js) === 1);
test('js: never innerHTML', fn() => $js !== '' && strpos($js, 'innerHTML') === false);

foreach (['pending', 'waitDetail', 'keepWorking', 'ready', 'show', 'error', 'close'] as $key) {
    test("panel: string $key passed to the pending page", fn() =>
        preg_match("/'pendingId'.*?'strings' => \[.*?'$key' =>/s", $panel) === 1);
}
foreach (['pending', 'waitDetail', 'keepWorking', 'error', 'close'] as $key) {
    test("panel: string $key passed to the ask button", fn() =>
        preg_match("/'buttonId' => 'gpFormativeButton',\s*'strings' => \[.*?'$key' =>/s", $panel) === 1);
}
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("strings ($lang)", fn() => !empty($string['formative_wait_detail']) && !empty($string['formative_keep_working'])
        && !empty($string['formative_close']));
}

test('css: centred over the page, namespaced', fn() => strpos($css, '.path-mod-gestionprojet .gp-formative-modal {') !== false
    && strpos($css, '.path-mod-gestionprojet .gp-formative-modal-box {') !== false);
test('css: spinner slowed down for reduced motion', fn() =>
    preg_match('/prefers-reduced-motion: reduce\)\s*\{\s*\.path-mod-gestionprojet \.gp-formative-modal-spinner/', $css) === 1);
test('build formative_button.min.js is up to date', fn() => is_file(GP_ROOT . '/amd/build/formative_button.min.js')
    && filemtime(GP_ROOT . '/amd/build/formative_button.min.js') >= filemtime(GP_ROOT . '/amd/src/formative_button.js'));

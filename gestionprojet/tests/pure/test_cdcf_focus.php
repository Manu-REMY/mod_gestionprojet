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
 * CDCF editor: typing then clicking another field or button works in one click (v2.12.2).
 *
 * A text field's change event fires on the mousedown that leaves it; a full re-render at that moment replaced
 * the clicked field or button before it got the focus or the click (two clicks needed).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$js = (string)file_get_contents(GP_ROOT . '/amd/src/cdcf.js');

// Every text field (interactor, FS statement, criterion, level, constraint, justification) emits a text change.
$textfields = [
    'inter.name = e.target.value;',
    'fs.description = e.target.value;',
    'crit.description = e.target.value;',
    'crit.niveau = e.target.value;',
    'c.description = e.target.value;',
    'c.justification = e.target.value;',
];
foreach ($textfields as $assign) {
    test("text change without full re-render: $assign", fn() =>
        strpos($js, $assign . ' emitText(); }') !== false && strpos($js, $assign . ' emit(); }') === false);
}

// emitText saves and refreshes only what shows the text; it never rebuilds the fields.
preg_match('/function emitText\(\) \{(.*?)\n        \}/s', $js, $m);
$body = $m[1] ?? '';
test('emitText() exists, saves, and does not call render()', fn() =>
    $body !== '' && strpos($body, 'onChange(data);') !== false && strpos($body, 'render()') === false);
test('emitText() refreshes the diagram, the labels of the lists and the student notice', fn() =>
    strpos($body, 'Diagram.render(') !== false && strpos($body, 'refreshLabels()') !== false
    && strpos($body, 'refreshNotices()') !== false);
test('the lists carry their kind so their labels can be refreshed', fn() =>
    strpos($js, "'data-gp-options', 'interactors'") !== false && strpos($js, "'data-gp-options', 'fs'") !== false);
test('one label builder for the interactor and FS options (render and refresh agree)', fn() =>
    strpos($js, 'function interactorLabel(') !== false && strpos($js, 'function fsLabel(') !== false);
test('the teacher notices (automark button) are not rebuilt on typing', fn() =>
    preg_match('/function emitText\(\) \{.*?if \(!editFlags\)/s', $js) === 1);
test('build cdcf.min.js up to date', fn() => is_file(GP_ROOT . '/amd/build/cdcf.min.js')
    && filemtime(GP_ROOT . '/amd/build/cdcf.min.js') >= filemtime(GP_ROOT . '/amd/src/cdcf.js'));

// Final review: the student notice appearing or vanishing during a click moves the buttons under the pointer;
// its refresh waits until the pointer is released.
test('notice refresh deferred while a pointer is down', fn() =>
    strpos($js, "addEventListener('pointerdown'") !== false && strpos($js, "addEventListener('pointerup'") !== false
    && strpos($js, 'function refreshNotices()') !== false && strpos($js, 'if (pointerDown) {') !== false);

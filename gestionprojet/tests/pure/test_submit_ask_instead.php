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
 * Submit modal: a student who changes their mind can ask for a feedback instead (v2.12.2), and the modal is the
 * only confirmation of the submission (no native confirm left on steps 5, 6, 8).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$tpl = (string)file_get_contents(GP_ROOT . '/templates/submit_modal.mustache');
$js = (string)file_get_contents(GP_ROOT . '/amd/src/submission.js');

test('modal: "ask instead" block only when a feedback can be asked', fn() =>
    strpos($tpl, '{{#canask}}') !== false && strpos($tpl, 'data-action="gp-ask-instead"') !== false
    && strpos($tpl, '{{#str}}submit_modal_ask_intro, mod_gestionprojet{{/str}}') !== false
    && strpos($tpl, '{{asklabel}}') !== false);
test('modal js: reads the panel ask button and passes canask / asklabel', fn() =>
    strpos($js, "document.getElementById('gpFormativeButton')") !== false
    && strpos($js, 'canask:') !== false && strpos($js, 'asklabel:') !== false);
test('modal js: "ask instead" closes the modal and asks (the copy is not submitted)', fn() =>
    preg_match('/gp-ask-instead"\]\', function\(\) \{\s*modal\.hide\(\);\s*ask\.click\(\);/', $js) === 1);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    test("string submit_modal_ask_intro in $l", fn() => !empty($string['submit_modal_ask_intro']));
}
foreach ([5, 6, 8] as $n) {
    test("step$n.js no longer confirms the submission natively (the modal does)", fn() =>
        strpos((string)file_get_contents(GP_ROOT . "/amd/src/step$n.js"), "\$('#submitButton').on('click'") === false);
}
foreach (['submission', 'step5', 'step6', 'step8'] as $m) {
    test("build $m.min.js up to date", fn() => is_file(GP_ROOT . "/amd/build/$m.min.js")
        && filemtime(GP_ROOT . "/amd/build/$m.min.js") >= filemtime(GP_ROOT . "/amd/src/$m.js"));
}

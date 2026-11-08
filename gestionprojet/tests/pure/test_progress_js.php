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
 * Live list of what is left to complete (v2.12.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$cdcf = file_get_contents(GP_ROOT . '/amd/src/cdcf.js');
foreach (["'i-' + inter.id", "'fs-' + fs.id", "'c-' + fs.id + '-' + crit.id", "'n-' + fs.id + '-' + crit.id", "'k-' + c.id"] as $key) {
    test("cdcf.js sets data-gp-field $key", fn() => strpos($cdcf, "setAttribute('data-gp-field', $key)") !== false);
}
$js = is_file(GP_ROOT . '/amd/src/progress_panel.js') ? file_get_contents(GP_ROOT . '/amd/src/progress_panel.js') : '';
test('progress_panel: no innerHTML', fn() => $js !== '' && strpos($js, 'innerHTML') === false);
test('progress_panel: listens to input events', fn() => strpos($js, "addEventListener('input'") !== false);
test('progress_panel: reads the CDCF hidden field', fn() => strpos($js, 'cdcfDataField') !== false);
test('progress_panel: pre-filled rule ("…", "...", "A compléter")', fn() => strpos($js, '[aà] compl[eé]ter') !== false);
test('progress_panel: labels from label[for]', fn() => strpos($js, "label[for=") !== false);
test('progress_panel: click scrolls to the field and focuses it', fn() => strpos($js, 'scrollIntoView') !== false
    && strpos($js, '.focus()') !== false);
test('progress_panel: updates the progress bar value', fn() => strpos($js, "'aria-valuenow'") !== false);
test('build progress_panel.min.js up to date', fn() => is_file(GP_ROOT . '/amd/build/progress_panel.min.js')
    && filemtime(GP_ROOT . '/amd/build/progress_panel.min.js') >= filemtime(GP_ROOT . '/amd/src/progress_panel.js'));
test('build cdcf.min.js up to date', fn() => filemtime(GP_ROOT . '/amd/build/cdcf.min.js') >= filemtime(GP_ROOT . '/amd/src/cdcf.js'));
$panel = file_get_contents(GP_ROOT . '/pages/student_progress_panel.php');
test('the panel starts progress_panel with the form of steps 5 to 7', fn() =>
    strpos($panel, "'mod_gestionprojet/progress_panel'") !== false && strpos($panel, "'#essaiForm'") !== false
    && strpos($panel, "'#rapportForm'") !== false && strpos($panel, "'#besoinEleveForm'") !== false);
foreach (['en', 'fr'] as $l) {
    $string = [];
    include(GP_ROOT . "/lang/$l/gestionprojet.php");
    foreach (['progress_field_statement', 'progress_field_criterion', 'progress_field_level', 'progress_field_interactor',
            'progress_field_constraint'] as $k) {
        test("string $k in $l", fn() => !empty($string[$k]));
    }
}

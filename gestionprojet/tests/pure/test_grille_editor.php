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
 * Static checks of the grid editor on the step 4 correction model page.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$page = file_get_contents(GP_ROOT . '/pages/step4_teacher.php');
$jsfile = GP_ROOT . '/amd/src/grille_editor.js';
$js = is_file($jsfile) ? file_get_contents($jsfile) : '';

test('the form holds the grid field', function () use ($page) {
    $form = substr($page, strpos($page, '<form id="cdcfForm">'), strpos($page, '</form>') - strpos($page, '<form id="cdcfForm">'));
    return strpos($form, 'name="grille_json"') !== false && strpos($form, 'id="grilleDataField"') !== false;
});
test('the page starts the grid editor', fn() => strpos($page, "'mod_gestionprojet/grille_editor'") !== false);
test('the autosave sends the grid', fn() =>
    strpos(file_get_contents(GP_ROOT . '/amd/src/cdcf_bootstrap.js'), 'payload.grille_json') !== false);
test('the editor builds the DOM as text (no innerHTML, no .html()) — a part name may hold HTML', fn() =>
    $js !== '' && strpos($js, 'innerHTML') === false && strpos($js, '.html(') === false);
test('the editor tells the autosave the field changed', fn() => strpos($js, "new Event('input'") !== false);
test('built module exists', fn() => is_file(GP_ROOT . '/amd/build/grille_editor.min.js'));
test('styles are namespaced', fn() =>
    strpos(file_get_contents(GP_ROOT . '/styles.css'), '.path-mod-gestionprojet .gp-grille') !== false);
test('graded / excluded is said with a word, not only a colour', fn() =>
    strpos($page, "'status_graded'") !== false && strpos($page, "'status_excluded'") !== false
    && strpos($js, 'lang.statusGraded') !== false && strpos($js, 'lang.statusExcluded') !== false);
test('the section icon exists', function () use ($page) {
    preg_match("/icon::render\('([a-z-]+)', 'sm', 'purple'\); \?> <\?php echo get_string\('grille_title'/", $page, $m);
    return isset($m[1]) && is_file(GP_ROOT . '/pix/lucide/' . $m[1] . '.svg');
});

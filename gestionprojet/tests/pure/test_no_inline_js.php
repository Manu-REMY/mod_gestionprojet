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
 * No inline JavaScript in PHP files (Moodle plugin checklist, rule 3), v2.13.7.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$found = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GP_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = substr($file->getPathname(), strlen(GP_ROOT) + 1);
    if (substr($path, -4) !== '.php' || strpos($path, 'tests/') === 0) {
        continue;
    }
    $code = file_get_contents($file->getPathname());
    if (preg_match('/js_init_code\(|js_init_call\(|js_amd_inline\(|html_writer::script\(|<script\b/i', $code)) {
        $found[] = $path;
    }
}
test('no js_init_code, js_amd_inline or <script> in PHP files', function () use ($found) {
    foreach ($found as $path) {
        echo "      $path\n";
    }
    return $found === [];
});

foreach (['fast_ai_instructions', 'report_log'] as $module) {
    test("module $module exists and its build is up to date", fn() =>
        is_file(GP_ROOT . "/amd/src/$module.js") && is_file(GP_ROOT . "/amd/build/$module.min.js")
        && filemtime(GP_ROOT . "/amd/build/$module.min.js") >= filemtime(GP_ROOT . "/amd/src/$module.js"));
}
test('step 9 teacher page loads fast_ai_instructions', fn() =>
    strpos(file_get_contents(GP_ROOT . '/pages/step9_teacher.php'), "js_call_amd('mod_gestionprojet/fast_ai_instructions', 'init'") !== false);
test('report page loads report_log with its strings', function () {
    $code = file_get_contents(GP_ROOT . '/report.php');
    return strpos($code, "js_call_amd('mod_gestionprojet/report_log', 'init'") !== false
        && strpos($code, "'showLess' => get_string('show_less', 'gestionprojet')") !== false;
});

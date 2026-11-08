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
 * Pure check: every literal string key used by the plugin exists in English and French.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

/**
 * Keys defined in a language file.
 *
 * @param string $lang
 * @return array
 */
function lang_keys(string $lang): array {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    return array_keys($string);
}

$en = lang_keys('en');
$fr = lang_keys('fr');

$used = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GP_ROOT));
foreach ($it as $file) {
    if (!preg_match('/\.(php|js|mustache)$/', $file) || strpos($file, '/tests/') !== false
            || strpos($file, '/amd/build/') !== false || strpos($file, '/lang/') !== false) {
        continue;
    }
    $code = file_get_contents($file);
    // PHP get_string / new lang_string, and JS core/str with a literal key and our component.
    preg_match_all("/(?:get_string|lang_string)\(\s*'([a-zA-Z0-9_:.-]+)'\s*,\s*'(?:mod_)?gestionprojet'/", $code, $m1);
    preg_match_all("/key:\s*'([a-zA-Z0-9_:.-]+)'\s*,\s*component:\s*'mod_gestionprojet'/", $code, $m2);
    preg_match_all("/\{\{#str\}\}\s*([a-zA-Z0-9_:.-]+)\s*,\s*(?:mod_)?gestionprojet/", $code, $m3);
    foreach (array_merge($m1[1], $m2[1], $m3[1]) as $key) {
        $used[$key][] = substr($file, strlen(GP_ROOT) + 1);
    }
}

$missing = array_diff(array_keys($used), $en);
test('every used key exists in English', function () use ($missing, $used) {
    foreach ($missing as $k) {
        echo "      $k (" . implode(', ', array_unique($used[$k])) . ")\n";
    }
    return !$missing;
});
test('English and French have the same keys', function () use ($en, $fr) {
    $diff = array_merge(array_diff($en, $fr), array_diff($fr, $en));
    foreach ($diff as $k) {
        echo "      $k\n";
    }
    return !$diff;
});

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
 * Static check: in a namespaced class file, a short class name used with "::" or "new" is imported
 * or lives in the same namespace (8 Nov: privacy provider used transform:: without importing it,
 * so every privacy export crashed).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

test('short class names in namespaced files are resolvable', function () {
    $bad = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GP_ROOT . '/classes'));
    foreach ($it as $file) {
        if (substr($file, -4) !== '.php') {
            continue;
        }
        $tokens = token_get_all(file_get_contents($file));
        $namespace = null;
        $imports = [];
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (!is_array($t)) {
                continue;
            }
            if ($t[0] === T_NAMESPACE) {
                $namespace = trim($tokens[$i + 2][1] ?? '');
            } else if ($t[0] === T_USE && $namespace !== null) {
                $name = is_array($tokens[$i + 2] ?? null) ? $tokens[$i + 2][1] : '';
                $parts = explode('\\', $name);
                $imports[strtolower(end($parts))] = true;
            } else if ($t[0] === T_STRING && $namespace !== null) {
                $next = $tokens[$i + 1] ?? null;
                $prev = $i > 1 ? $tokens[$i - 2] : null;
                $isstatic = is_array($next) && $next[0] === T_DOUBLE_COLON;
                $isnew = is_array($prev) && $prev[0] === T_NEW;
                $name = strtolower($t[1]);
                if (!($isstatic || $isnew) || in_array($name, ['self', 'static', 'parent'], true) || isset($imports[$name])) {
                    continue;
                }
                $rel = str_replace('\\', '/', preg_replace('/^mod_gestionprojet\\\\?/', '', $namespace));
                $local = GP_ROOT . '/classes/' . ($rel !== '' ? $rel . '/' : '') . $t[1] . '.php';
                if (!file_exists($local)) {
                    $bad[] = substr($file, strlen(GP_ROOT) + 1) . ': ' . $t[1];
                }
            }
        }
    }
    foreach (array_unique($bad) as $b) {
        echo "      $b\n";
    }
    return !$bad;
});

test('external functions use the core_external classes, not the deprecated global aliases', function () {
    // Moodle 4.2 renamed external_api & co. to core_external\...; the global aliases log a
    // deprecation notice on Moodle 5.3 (12 Nov: generate_ai_summary still used them).
    $bad = [];
    foreach (glob(GP_ROOT . '/classes/external/*.php') as $file) {
        // Comments are ignored. Legacy forms: "use external_x;", "use \external_x;", a grouped use
        // starting with external_x, or a fully qualified \external_x reference in the code.
        $code = '';
        foreach (token_get_all(file_get_contents($file)) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($t) ? $t[1] : $t;
        }
        if (preg_match('/^\s*use\s+\\\\?external_\w+/m', $code, $m)
                || preg_match('/(?<![\w\\\\])\\\\external_\w+/', $code, $m)
                || preg_match('/externallib\.php/', $code, $m)) {
            $bad[] = basename($file) . ': ' . trim($m[0]);
        }
    }
    foreach ($bad as $b) {
        echo "      $b\n";
    }
    return !$bad;
});

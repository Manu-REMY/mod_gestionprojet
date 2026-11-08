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
 * Static check: a function using a Moodle global declares it (8 Nov: mod_form::validation used $CFG
 * without "global $CFG", saving the activity settings failed with "Failed opening required '/mod/...'").
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

const GLOBALS_TO_CHECK = ['$CFG', '$DB', '$USER', '$PAGE', '$OUTPUT', '$COURSE', '$SESSION'];

/**
 * Functions of a file that use a Moodle global without declaring it.
 *
 * @param string $file
 * @return string[] "function: $VAR"
 */
function undeclared_globals(string $file): array {
    $tokens = token_get_all(file_get_contents($file));
    $found = [];
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        // Name (closures have none), then the body between matching braces.
        $name = 'closure';
        for ($j = $i + 1; $j < $n && $tokens[$j] !== '(' ; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
            }
        }
        while ($j < $n && $tokens[$j] !== '{' && $tokens[$j] !== ';') {
            $j++;
        }
        if ($j >= $n || $tokens[$j] === ';') {
            continue; // Abstract or interface method.
        }
        $depth = 0;
        $declared = [];
        $used = [];
        $inglobal = false;
        for ($k = $j; $k < $n; $k++) {
            $t = $tokens[$k];
            if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } else if ($t === '}') {
                if (--$depth === 0) {
                    break;
                }
            } else if (is_array($t) && $t[0] === T_FUNCTION) {
                // Nested function or closure: checked on its own.
                $sub = $k;
                while ($sub < $n && $tokens[$sub] !== '{') {
                    $sub++;
                }
                $d = 0;
                for (; $sub < $n; $sub++) {
                    if ($tokens[$sub] === '{') {
                        $d++;
                    } else if ($tokens[$sub] === '}' && --$d === 0) {
                        break;
                    }
                }
                $k = $sub;
            } else if (is_array($t) && $t[0] === T_GLOBAL) {
                $inglobal = true;
            } else if ($t === ';') {
                $inglobal = false;
            } else if (is_array($t) && $t[0] === T_VARIABLE && in_array($t[1], GLOBALS_TO_CHECK, true)) {
                if ($inglobal) {
                    $declared[$t[1]] = true;
                } else {
                    $used[$t[1]] = true;
                }
            }
        }
        foreach (array_keys(array_diff_key($used, $declared)) as $var) {
            $found[] = "$name(): $var";
        }
    }
    return $found;
}

test('every function using a Moodle global declares it', function () {
    $all = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GP_ROOT));
    foreach ($it as $file) {
        if (substr($file, -4) !== '.php' || strpos($file, '/tests/') !== false || strpos($file, '/lang/') !== false) {
            continue;
        }
        foreach (undeclared_globals((string) $file) as $f) {
            $all[] = substr($file, strlen(GP_ROOT) + 1) . ' ' . $f;
        }
    }
    foreach ($all as $a) {
        echo "      $a\n";
    }
    return !$all;
});

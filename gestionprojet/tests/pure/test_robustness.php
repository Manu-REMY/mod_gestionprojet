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
 * Static checks of the AI pipeline robustness (runs without Moodle).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

/**
 * Source of a plugin file.
 *
 * @param string $path Relative to the plugin root.
 * @return string
 */
function src(string $path): string {
    return file_get_contents(GP_ROOT . '/' . $path);
}

/**
 * Body of a function or method, from its declaration to the next declaration.
 *
 * @param string $code
 * @param string $name
 * @return string
 */
function body(string $code, string $name): string {
    $start = strpos($code, 'function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $next = strpos($code, "\n    /**", $start);
    return substr($code, $start, $next === false ? null : $next - $start);
}

// Preco 14: "Class curl not found" in CLI and in the adhoc task.
test('every file creating a curl object loads filelib.php first', function () {
    $missing = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(GP_ROOT));
    foreach ($it as $file) {
        if (substr($file, -4) !== '.php' || strpos($file, '/tests/') !== false) {
            continue;
        }
        $code = file_get_contents($file);
        if (preg_match('/new\s+\\\\?curl\s*\(/', $code) && strpos($code, "/filelib.php'") === false) {
            $missing[] = substr($file, strlen(GP_ROOT) + 1);
        }
    }
    if ($missing) {
        echo '      ' . implode(', ', $missing) . "\n";
    }
    return !$missing;
});

// An \Error (TypeError, missing class) must not leave an evaluation "processing" forever.
test('process_evaluation turns any Throwable into a failed evaluation',
    fn() => preg_match('/catch\s*\(\s*\\\\Throwable\s+\$e\s*\)/', body(src('classes/ai_evaluator.php'), 'process_evaluation')) === 1);

// The deadline task queued evaluations with an object and shifted arguments: none was ever queued.
test('the deadline task reuses the lib.php trigger instead of its own copy', function () {
    $code = src('classes/task/auto_submit_deadline.php');
    return strpos($code, 'gestionprojet_trigger_ai_evaluation_safe(') !== false
        && strpos($code, 'queue_evaluation(') === false;
});

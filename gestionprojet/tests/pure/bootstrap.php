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
 * Minimal harness for pure PHP checks that run without Moodle.
 *
 * Usage: php tests/pure/test_xxx.php (exit code 1 when a check fails).
 * These files are excluded from the release ZIP.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MOODLE_INTERNAL', true);
define('GP_ROOT', dirname(__DIR__, 2));

$GLOBALS['gp_failures'] = 0;

// Classes that require_once $CFG->dirroot . '/mod/gestionprojet/lib.php' get an empty stub:
// pure checks must not depend on Moodle functions.
$stubroot = sys_get_temp_dir() . '/gestionprojet_pure_stub';
if (!is_dir($stubroot . '/mod/gestionprojet')) {
    mkdir($stubroot . '/mod/gestionprojet', 0777, true);
}
file_put_contents($stubroot . '/mod/gestionprojet/lib.php', "<?php\n");
if (!function_exists('get_string')) {
    /**
     * Stub of Moodle's get_string: returns the key, so that checks can tell strings apart.
     *
     * @param string $key
     * @param string $component
     * @param mixed $a
     * @return string
     */
    function get_string($key, $component = '', $a = null) {
        return '[[' . $key . ']]';
    }
}
$CFG = (object) ['dirroot' => $stubroot, 'libdir' => $stubroot . '/lib'];

/**
 * Runs one check and prints PASS or FAIL.
 *
 * @param string $label
 * @param callable $fn Returns true when the check passes.
 */
function test(string $label, callable $fn): void {
    $ok = false;
    try {
        $ok = (bool) $fn();
    } catch (\Throwable $e) {
        echo 'EXC   ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $label . "\n";
    if (!$ok) {
        $GLOBALS['gp_failures']++;
    }
}

/**
 * True when the callable throws an exception of the given class.
 *
 * @param callable $fn
 * @param string $class
 * @return bool
 */
function throws(callable $fn, string $class = \Throwable::class): bool {
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

register_shutdown_function(function () {
    $n = $GLOBALS['gp_failures'];
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // A fatal error stopped the script: never report it as a pass.
        echo "\nFATAL  " . $last['message'] . "\n";
        $n++;
    }
    echo $n ? "\n$n check(s) failed\n" : "\nAll checks passed\n";
    exit($n ? 1 : 0);
});

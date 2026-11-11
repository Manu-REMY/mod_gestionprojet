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
 * Graded corrections leave one every N seconds (request 40, v2.14.0).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require_once(GP_ROOT . '/classes/ai_evaluator.php');

use mod_gestionprojet\ai_evaluator;

test('first slot: now', fn() => ai_evaluator::slot_time(1000, 0, 6) === 1000);
test('a recent slot: 6 s after it', fn() => ai_evaluator::slot_time(1000, 998, 6) === 1004);
test('a queue already long: after its last slot', fn() => ai_evaluator::slot_time(1000, 5000, 6) === 5006);
test('an old slot (idle site): now, not later', fn() => ai_evaluator::slot_time(100000, 1000, 6) === 100000);
test('interval 0: no spacing', fn() => ai_evaluator::slot_time(1000, 1004, 0) === 1000);

$read = fn(string $path): string => file_get_contents(GP_ROOT . '/' . $path);
$evaluator = $read('classes/ai_evaluator.php');
test('queue_evaluation spaces graded corrections only', fn() =>
    preg_match('/if \(!\$formatif && \$palier === \'\'\) \{.*?self::slot_time\(.*?set_config\(\'ai_next_slot\', \$slot, \'mod_gestionprojet\'\).*?\$task->set_next_run_time\(\$slot\);/s', $evaluator) === 1);
// Default 6 s in 2.14.0, 12 s since 2.15.0 (two AI calls per graded correction): see test_langue_settings.
test('setting ai_interval exists (integer seconds)', fn() => preg_match("/'mod_gestionprojet\/ai_interval'.*?PARAM_INT/s", $read('settings.php')) === 1);
test('an unset gap falls back on 12 s, as the setting default', fn() =>
    strpos($evaluator, "\$interval === false || \$interval === '' ? 12 : (int)\$interval") !== false);
foreach (['en', 'fr'] as $lang) {
    $string = [];
    include(GP_ROOT . "/lang/$lang/gestionprojet.php");
    test("strings ($lang)", fn() => !empty($string['setting_ai_interval']) && !empty($string['setting_ai_interval_desc']));
}
test('review: a level validation request is not spaced (a student waits for it)', fn() =>
    strpos($evaluator, 'if (!$formatif && $palier === \'\') {') !== false);

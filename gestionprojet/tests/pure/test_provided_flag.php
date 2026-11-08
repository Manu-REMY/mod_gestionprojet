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
 * The AI only compares a copy with a consigne the students were shown (stepN_provided = 1).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$src = file_get_contents(GP_ROOT . '/classes/ai_evaluator.php');
$start = strpos($src, 'if (isset($providedmap[$evaluation->step])');
test('the consigne is used only when it is shown to students', fn() => $start !== false
    && preg_match('/if \(isset\(\$providedmap\[\$evaluation->step\]\)\s*&&.{0,120}?_provided/s', $src) === 1);

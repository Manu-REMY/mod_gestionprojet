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
 * Pure check of the AI response parser: no invented grade.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');
require(GP_ROOT . '/classes/ai_response_parser.php');

$parser = new \mod_gestionprojet\ai_response_parser();

test('a valid grade is read', fn() => $parser->parse('{"grade": 14.5, "feedback": "ok"}')->grade === 14.5);
test('a grade above 20 is clamped', fn() => $parser->parse('{"grade": 25, "feedback": "ok"}')->grade === 20.0);
test('a grade written as a string is read', fn() => $parser->parse('{"grade": "12", "feedback": "ok"}')->grade === 12.0);
test('an answer without grade fails instead of giving 10/20',
    fn() => throws(fn() => $parser->parse('{"feedback": "ok"}')));
test('a non-numeric grade fails instead of giving 0/20',
    fn() => throws(fn() => $parser->parse('{"grade": "bien", "feedback": "ok"}')));

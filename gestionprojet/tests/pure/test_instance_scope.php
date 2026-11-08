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
 * Static checks: ids sent by the browser are tied to the current activity.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$evaluator = file_get_contents(GP_ROOT . '/classes/ai_evaluator.php');

/**
 * Body of a static method of ai_evaluator.
 *
 * @param string $code
 * @param string $name
 * @return string
 */
function method(string $code, string $name): string {
    $start = strpos($code, 'function ' . $name . '(');
    $next = strpos($code, "\n    /**", $start);
    return substr($code, $start, $next - $start);
}

test('queue_evaluation refuses a submission of another activity', function () use ($evaluator) {
    return preg_match("/'id' => \\\$submissionid,\s*'gestionprojetid' => \\\$gestionprojetid/",
        method($evaluator, 'queue_evaluation')) === 1;
});
test('process_evaluation reads the submission within its activity', function () use ($evaluator) {
    return preg_match("/'id' => \\\$evaluation->submissionid,\s*'gestionprojetid' => \\\$evaluation->gestionprojetid/",
        method($evaluator, 'process_evaluation')) === 1;
});
test('apply_evaluation reads the submission within its activity', function () use ($evaluator) {
    $body = method($evaluator, 'apply_evaluation');
    return strpos($body, "'id' => \$evaluation->submissionid]") === false
        && preg_match("/'gestionprojetid' => \\\$evaluation->gestionprojetid/", $body) === 1;
});
test('delete_evaluation only deletes in the given activity', function () use ($evaluator) {
    return strpos(method($evaluator, 'delete_evaluation'), 'int $gestionprojetid') !== false
        && strpos(file_get_contents(GP_ROOT . '/ajax/delete_evaluation.php'),
            'delete_evaluation($evaluationid, (int)$gestionprojet->id)') !== false;
});
test('the get_evaluation_status web service is for graders only (students poll ajax/)',
    fn() => strpos(file_get_contents(GP_ROOT . '/classes/external/get_evaluation_status.php'),
        "require_capability('mod/gestionprojet:grade', \$context)") !== false);

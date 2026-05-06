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
 * AJAX endpoint: toggle the activation of a single step from the home Gantt.
 *
 * Inputs (POST):
 *   cmid    int  Course module ID
 *   stepnum int  Step number (1..9)
 *   enabled int  0 to disable, 1 to enable (mode student); 2 reserved for step4 provided mode
 *   flag    str  'enable' (default) or 'provided' (only valid for steps 4 and 9)
 *
 * Output: JSON {success: bool, message?: string}
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

$cmid = required_param('cmid', PARAM_INT);
$stepnum = required_param('stepnum', PARAM_INT);
$enabled = required_param('enabled', PARAM_INT);
$flag = optional_param('flag', 'enable', PARAM_ALPHA);

if ($stepnum < 1 || $stepnum > 9) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if (!in_array($enabled, [0, 1, 2], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if ($enabled === 2 && $stepnum !== 4) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if (!in_array($flag, ['enable', 'provided'], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}
if ($flag === 'provided' && !in_array($stepnum, [4, 5, 9], true)) {
    throw new \moodle_exception('invalidparameter', 'error');
}

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_capability('mod/gestionprojet:configureteacherpages', $context);

$field = ($flag === 'provided') ? ('step' . $stepnum . '_provided') : ('enable_step' . $stepnum);
$update = new stdClass();
$update->id = $gestionprojet->id;
$update->$field = $enabled;
$DB->update_record('gestionprojet', $update);

echo json_encode([
    'success' => true,
    'message' => get_string('gantt_toggle_success', 'gestionprojet'),
]);

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
 * AJAX endpoint: reset a student step to the latest teacher-provided consigne.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/reset_helper.php');

global $DB, $USER;

$id = required_param('id', PARAM_INT);
$step = required_param('step', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:submit', $context);

// Whitelist of steps that support reset-to-provided. Defence-in-depth: the
// helper itself also validates against STEP_MAP, but rejecting unknown steps
// here avoids loading the course/module records for a request that cannot succeed.
if (!in_array($step, [4, 5, 9], true)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'unsupported_step',
        'message' => get_string('reset_error_unsupported_step', 'gestionprojet'),
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    $result = \mod_gestionprojet\reset_helper::reset_step_to_provided(
        $gestionprojet,
        $step,
        $groupid,
        (int)$USER->id
    );

    if (!$result['success']) {
        $errorcode = $result['error'] ?? 'unknown';
        $errormap = [
            'locked'           => ['msg' => get_string('reset_error_locked', 'gestionprojet'),            'http' => 403],
            'no_provided'      => ['msg' => get_string('reset_error_no_provided', 'gestionprojet'),       'http' => 400],
            'unsupported_step' => ['msg' => get_string('reset_error_unsupported_step', 'gestionprojet'),  'http' => 400],
        ];
        $info = $errormap[$errorcode] ?? [
            'msg'  => get_string('reset_error_internal', 'gestionprojet'),
            'http' => 500,
        ];
        http_response_code($info['http']);
        echo json_encode([
            'success' => false,
            'error'   => $errorcode,
            'message' => $info['msg'],
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => get_string('reset_success', 'gestionprojet'),
    ]);
} catch (\Throwable $e) {
    // Log full exception server-side. The client only sees a generic message
    // (avoids leaking SQL fragments, file paths, or stack details).
    debugging('reset_to_provided exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'exception',
        'message' => get_string('reset_error_internal', 'gestionprojet'),
    ]);
}

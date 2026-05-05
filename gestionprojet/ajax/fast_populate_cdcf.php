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
 * AJAX endpoint — return CDCF teacher's FS data for FAST pre-fill.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../classes/cdcf_helper.php');

header('Content-Type: application/json');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:configureteacherpages', $context);

$cdcfteacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);

$fonctionsprincipales = [];
$fonctionsservice = [];

if ($cdcfteacher) {
    // New CDCF schema: all data lives in interacteurs_data ({interactors, fonctionsService, contraintes}).
    // The teacher's fonctions de service become FAST pre-fill candidates. The legacy "fp" text field has
    // been removed; FS now play the role of fonctions principales for FAST seeding.
    $cdcfdata = \mod_gestionprojet\cdcf_helper::decode($cdcfteacher->interacteurs_data ?? null);

    // Build a map of interactor id -> name for FS labelling.
    $interactornames = [];
    foreach ($cdcfdata['interactors'] as $interacteur) {
        $interactornames[(int)$interacteur['id']] = (string)$interacteur['name'];
    }

    foreach ($cdcfdata['fonctionsService'] as $idx => $fs) {
        $description = (string)$fs['description'];
        if ($description === '') {
            continue;
        }
        $fonctionsprincipales[] = [
            'id' => $idx + 1,
            'description' => $description,
        ];
        $intname1 = $interactornames[(int)($fs['interactor1Id'] ?? 0)] ?? '';
        $fonctionsservice[] = [
            'id' => $idx + 1,
            'description' => $description,
            'interactor' => $intname1,
        ];
    }
}

echo json_encode([
    'success' => true,
    'fonctionsPrincipales' => $fonctionsprincipales,
    'fonctionsService' => $fonctionsservice,
]);

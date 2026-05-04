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
    if (!empty($cdcfteacher->fp)) {
        $fonctionsprincipales[] = ['id' => 1, 'description' => $cdcfteacher->fp];
    }

    // interacteurs_data is a JSON array of interactors. Each interactor's "fcs" (or
    // "fonctions") becomes a Service Function candidate for FAST pre-fill.
    if (!empty($cdcfteacher->interacteurs_data)) {
        $interacteurs = json_decode($cdcfteacher->interacteurs_data, true);
        if (is_array($interacteurs)) {
            $idcounter = 1;
            foreach ($interacteurs as $interacteur) {
                $intname = $interacteur['name'] ?? $interacteur['nom'] ?? '';
                $fcs = $interacteur['fcs'] ?? $interacteur['fonctions'] ?? [];
                if (is_array($fcs)) {
                    foreach ($fcs as $fc) {
                        $desc = is_string($fc)
                            ? $fc
                            : ($fc['value'] ?? $fc['description'] ?? $fc['name'] ?? $fc['nom'] ?? '');
                        if (!empty($desc)) {
                            $fonctionsservice[] = [
                                'id' => $idcounter++,
                                'description' => $desc,
                                'interactor' => $intname,
                            ];
                        }
                    }
                }
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'fonctionsPrincipales' => $fonctionsprincipales,
    'fonctionsService' => $fonctionsservice,
]);

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
 * Static check: after a restore, AI evaluations point to the restored submissions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$code = file_get_contents(GP_ROOT . '/backup/moodle2/restore_gestionprojet_stepslib.php');

foreach (['cdcf', 'essai', 'rapport', 'besoin_eleve', 'carnet', 'fast'] as $t) {
    test("restored $t submissions record their new id", fn() =>
        strpos($code, "\$this->set_mapping('gestionprojet_$t', \$oldid, \$newid)") !== false);
}
test('AI evaluations take the new id of their submission', fn() =>
    preg_match('/\$data->submissionid = \$this->get_mappingid\(/', $code) === 1);

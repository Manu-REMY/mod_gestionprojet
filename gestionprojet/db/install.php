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
 * Installation script for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-installation procedure.
 *
 * This function is called after the plugin is installed.
 * It sets up secure API keys for built-in providers.
 *
 * @return bool Success status
 */
function xmldb_gestionprojet_install() {
    // Set up the Albert API key securely.
    // The key is encrypted before storage and cannot be retrieved by users.
    require_once(__DIR__ . '/../classes/ai_config.php');

    // Albert API key (Etalab government service).
    // This key is stored encrypted in Moodle config, not in the database.
    $albertkey = 'sk-eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo5NTMxLCJ0b2tlbl9pZCI6MTA0MzcsImV4cGlyZXMiOjE4MDA5ODU0MDV9.h4Kx-kUU4yXzEifV3-L63f791urH2owDSai5n8Ru7eo';

    \mod_gestionprojet\ai_config::set_builtin_api_key('albert', $albertkey);

    return true;
}

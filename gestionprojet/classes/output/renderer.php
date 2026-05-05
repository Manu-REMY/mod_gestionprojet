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
 * Renderer for mod_gestionprojet.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer class for the gestionprojet module.
 *
 * Provides methods to render Mustache templates with structured context data.
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the home/navigation page.
     *
     * @param array $data Template context data.
     * @return string Rendered HTML.
     */
    public function render_home($data) {
        return $this->render_from_template('mod_gestionprojet/home', $data);
    }

    /**
     * Render the grading navigation (step tabs + nav bar).
     *
     * @param array $data Template context data.
     * @return string Rendered HTML.
     */
    public function render_grading_navigation($data) {
        return $this->render_from_template('mod_gestionprojet/grading_navigation', $data);
    }
}

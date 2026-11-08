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
 * Pieuvre diagram: FS sharing the same two ends get one line and one label (point 13, v2.13.2).
 *
 * ROBOT MARTIEN: FS2 and FS3 both link the Martian atmosphere to the product, FS4 and FS5 the ground; their lines
 * and labels were drawn on top of each other (one label readable).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/bootstrap.php');

$js = (string)file_get_contents(GP_ROOT . '/amd/src/cdcf_diagram.js');
test('links grouped by their two ends (order does not matter)', fn() => strpos($js, 'function groupLinks(fonctionsService)') !== false
    && strpos($js, 'Math.min(fs.interactor1Id, fs.interactor2Id)') !== false);
test('one label per group, the FS numbers joined', fn() => strpos($js, "group.labels.join(', ')") !== false);
test('groupLinks exported (checked with node in the 2.13.2 delivery)', fn() => strpos($js, 'groupLinks: groupLinks') !== false);
test('build cdcf_diagram.min.js up to date', fn() =>
    filemtime(GP_ROOT . '/amd/build/cdcf_diagram.min.js') >= filemtime(GP_ROOT . '/amd/src/cdcf_diagram.js'));

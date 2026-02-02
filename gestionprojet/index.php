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
 * Display list of all gestionprojet activities in a course.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/gestionprojet/lib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/gestionprojet/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_course::instance($id));

echo $OUTPUT->header();

if (!$gestionprojets = get_all_instances_in_course('gestionprojet', $course)) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'gestionprojet')), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head = array(get_string('week'), get_string('name'));
    $table->align = array('center', 'left');
} else if ($course->format == 'topics') {
    $table->head = array(get_string('topic'), get_string('name'));
    $table->align = array('center', 'left');
} else {
    $table->head = array(get_string('name'));
    $table->align = array('left');
}

foreach ($gestionprojets as $gestionprojet) {
    if (!$gestionprojet->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/gestionprojet/view.php', array('id' => $gestionprojet->coursemodule)),
            format_string($gestionprojet->name, true),
            array('class' => 'dimmed')
        );
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/gestionprojet/view.php', array('id' => $gestionprojet->coursemodule)),
            format_string($gestionprojet->name, true)
        );
    }

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = array($gestionprojet->section, $link);
    } else {
        $table->data[] = array($link);
    }
}

echo html_writer::table($table);

echo $OUTPUT->footer();

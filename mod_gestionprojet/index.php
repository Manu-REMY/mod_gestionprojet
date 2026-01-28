<?php

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

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
 * Results of the class on every student step, like the results page of a quiz (v2.13.0).
 *
 * Individual work: one line per student. Group submission: students listed by group, the group and its shared copy
 * merged over its students; students in no group last, with their own copy.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:viewallsubmissions', $context);

$url = new moodle_url('/mod/gestionprojet/results.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($gestionprojet->name) . ' — ' . get_string('results', 'gestionprojet'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$groupid = (int)groups_get_activity_group($cm, true);
// Separate groups: a teacher in no group and without access to all groups sees nobody (as the quiz report).
$blocked = groups_get_activity_groupmode($cm) == SEPARATEGROUPS && $groupid === 0
    && !has_capability('moodle/site:accessallgroups', $context);
if ($blocked && $download !== '') {
    throw new moodle_exception('notingroup');
}
$data = $blocked ? ['groupmode' => false, 'steps' => [], 'lines' => [], 'summary' => []]
    : gestionprojet_results_lines($gestionprojet, $cm, $groupid);

$stepnames = [];
foreach ($data['steps'] as $step) {
    $stepnames[$step] = get_string('step' . $step, 'gestionprojet');
}
$symbols = ['none' => '— ', 'draft' => '✎ ', 'pending' => '… ', 'submitted' => '✓ '];

/**
 * Text of a cell: state, grade or levels, formative feedbacks used.
 *
 * @param array $cell results::cell()
 * @param array $symbols State => symbol.
 * @return string[] Lines of text (plain, not escaped).
 */
$celltext = function (array $cell) use ($symbols): array {
    $lines = [$symbols[$cell['state']] . get_string('results_state_' . $cell['state'], 'gestionprojet')];
    if ($cell['levels'] !== null) {
        $levels = [];
        foreach ($cell['levels'] as $code => $done) {
            $levels[] = ($done ? '✓ ' : '· ') . get_string('grille_palier_' . strtolower($code), 'gestionprojet');
        }
        if ($levels) {
            $lines[] = implode('  ', $levels);
        }
    } else if ($cell['grade'] !== null) {
        $lines[] = format_float($cell['grade'], 1) . '/20';
    }
    if ($cell['limit'] > 0 && $cell['state'] !== 'none') {
        $lines[] = get_string('results_formative', 'gestionprojet', (object)['used' => $cell['used'], 'limit' => $cell['limit']]);
    }
    return $lines;
};
$date = function (int $time): string {
    return $time ? userdate($time, get_string('strftimedatetimeshort')) : '—';
};

// Export: the same table, one line per student, values of a shared copy repeated.
if ($download !== '') {
    $columns = [];
    if ($data['groupmode']) {
        $columns[] = get_string('results_group', 'gestionprojet');
    }
    $columns[] = get_string('results_student', 'gestionprojet');
    $columns[] = get_string('results_start', 'gestionprojet');
    $columns[] = get_string('results_last', 'gestionprojet');
    foreach ($stepnames as $name) {
        $columns[] = $name;
    }
    $columns[] = get_string('results_average', 'gestionprojet');
    $rows = new ArrayIterator($data['lines']);
    \core\dataformat::download_data(clean_filename(format_string($gestionprojet->name) . ' - ' .
        get_string('results', 'gestionprojet')), $download, $columns, $rows,
        function (array $line) use ($data, $celltext, $date): array {
            $out = [];
            if ($data['groupmode']) {
                $out[] = $line['groupname'] === null ? get_string('results_nogroup', 'gestionprojet')
                    : format_string($line['groupname'], true, ['escape' => false]);
            }
            $out[] = $line['student'];
            $out[] = $date($line['start']);
            $out[] = $date($line['last']);
            foreach ($data['steps'] as $step) {
                $out[] = implode(' · ', $celltext($line['cells'][$step]));
            }
            $out[] = $line['average'] === null ? '' : format_float($line['average'], 2);
            return $out;
        });
    die();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($gestionprojet->name) . ' — ' . get_string('results', 'gestionprojet'));
groups_print_activity_menu($cm, $url);

if ($blocked) {
    echo $OUTPUT->notification(get_string('notingroup'), 'info');
    echo $OUTPUT->footer();
    die();
}
if (!$data['lines']) {
    echo $OUTPUT->notification(get_string('results_nostudents', 'gestionprojet'), 'info');
    echo $OUTPUT->footer();
    die();
}

$table = new html_table();
$table->attributes['class'] = 'generaltable gp-results';
$table->head = [];
if ($data['groupmode']) {
    $table->head[] = get_string('results_group', 'gestionprojet');
}
$table->head[] = get_string('results_student', 'gestionprojet');
$table->head[] = get_string('results_start', 'gestionprojet');
$table->head[] = get_string('results_last', 'gestionprojet');
foreach ($stepnames as $name) {
    $table->head[] = $name;
}
$table->head[] = get_string('results_average', 'gestionprojet');

foreach ($data['lines'] as $line) {
    $row = new html_table_row();
    if ($data['groupmode'] && $line['groupspan'] > 0) {
        $cell = new html_table_cell(format_string($line['groupname'] ?? get_string('results_nogroup', 'gestionprojet')));
        $cell->rowspan = $line['groupspan'];
        $cell->attributes['class'] = 'gp-results-group';
        $row->cells[] = $cell;
    }
    $row->cells[] = new html_table_cell(s($line['student']));
    if ($line['ownerspan'] > 0) {
        $shared = [];
        $shared[] = new html_table_cell(s($date($line['start'])));
        $shared[] = new html_table_cell(s($date($line['last'])));
        foreach ($data['steps'] as $step) {
            $cell = $line['cells'][$step];
            $text = implode(html_writer::empty_tag('br'), array_map('s', $celltext($cell)));
            $class = 'gp-results-cell gp-results-' . $cell['state'];
            if ($line['link'] === null) {
                $shared[] = new html_table_cell(html_writer::span($text, $class));
            } else {
                $link = new moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id, 'step' => $step] + $line['link']);
                $shared[] = new html_table_cell(html_writer::link($link, $text, ['class' => $class]));
            }
        }
        $shared[] = new html_table_cell($line['average'] === null ? '—' : format_float($line['average'], 2) . '/20');
        foreach ($shared as $cell) {
            $cell->rowspan = $line['ownerspan'];
            $row->cells[] = $cell;
        }
    }
    $table->data[] = $row;
}

// Synthesis of each step: copies submitted over all, average.
$row = new html_table_row();
$row->attributes['class'] = 'gp-results-summary';
$label = new html_table_cell(get_string('results_summary', 'gestionprojet'));
$label->colspan = ($data['groupmode'] ? 2 : 1) + 2;
$row->cells[] = $label;
foreach ($data['steps'] as $step) {
    $sum = $data['summary'][$step];
    $row->cells[] = new html_table_cell(s(get_string('results_summary_cell', 'gestionprojet', (object)[
        'submitted' => $sum['submitted'], 'total' => $sum['total'],
        'average' => $sum['average'] === null ? '—' : format_float($sum['average'], 1) . '/20',
    ])));
}
$row->cells[] = new html_table_cell('');
$table->data[] = $row;

echo html_writer::div(html_writer::table($table), 'gp-results-wrap');
echo $OUTPUT->download_dataformat_selector(get_string('results_download', 'gestionprojet'), $url->out_omit_querystring(),
    'download', ['id' => $cm->id]);
echo $OUTPUT->footer();

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI usage report for the gestionprojet module.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check login and capability.
require_login();
$context = context_system::instance();
require_capability('mod/gestionprojet:viewailogs', $context);

// Get filter parameters.
$datefrom = optional_param('datefrom', 0, PARAM_INT);
$dateto = optional_param('dateto', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$provider = optional_param('provider', '', PARAM_ALPHA);
$status = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

// Set up the page.
$PAGE->set_url('/mod/gestionprojet/report.php', [
    'datefrom' => $datefrom,
    'dateto' => $dateto,
    'courseid' => $courseid,
    'userid' => $userid,
    'provider' => $provider,
    'status' => $status,
]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('ai_log_report', 'gestionprojet'));
$PAGE->set_heading(get_string('ai_log_report', 'gestionprojet'));

// Add CSS.
$PAGE->requires->css('/mod/gestionprojet/styles.css');

// Build filters array.
$filters = [];
if ($datefrom) {
    $filters['datefrom'] = $datefrom;
}
if ($dateto) {
    // Add 23:59:59 to include the full day.
    $filters['dateto'] = $dateto + 86399;
}
if ($courseid) {
    $filters['courseid'] = $courseid;
}
if ($userid) {
    $filters['userid'] = $userid;
}
if ($provider) {
    $filters['provider'] = $provider;
}
if ($status) {
    $filters['status'] = $status;
}

// Create table instance.
require_once(__DIR__ . '/classes/report/ai_log_table.php');
$table = new \mod_gestionprojet\report\ai_log_table('ai-log-report', $filters);
$table->define_baseurl($PAGE->url);

// Get summary.
$summary = $table->get_summary();

// Get courses with gestionprojet activities for filter.
$courses = $DB->get_records_sql(
    "SELECT DISTINCT c.id, c.shortname, c.fullname
     FROM {course} c
     JOIN {gestionprojet} g ON g.course = c.id
     ORDER BY c.shortname"
);

// Get users who have evaluations for filter.
$users = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
     FROM {user} u
     JOIN {gestionprojet_ai_evaluations} e ON e.userid = u.id
     WHERE e.userid > 0
     ORDER BY u.lastname, u.firstname
     LIMIT 200"
);

// Available providers.
$providers = [
    'openai' => 'OpenAI',
    'anthropic' => 'Anthropic',
    'mistral' => 'Mistral',
    'albert' => 'Albert (Etalab)',
];

// Available statuses.
$statuses = [
    'pending' => get_string('status_pending', 'gestionprojet'),
    'processing' => get_string('status_processing', 'gestionprojet'),
    'completed' => get_string('status_completed', 'gestionprojet'),
    'failed' => get_string('status_failed', 'gestionprojet'),
    'applied' => get_string('status_applied', 'gestionprojet'),
];

// Output starts here.
echo $OUTPUT->header();

// Filter form.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'mb-4',
]);

echo html_writer::start_div('card');
echo html_writer::start_div('card-body');

echo html_writer::tag('h5', get_string('filter', 'moodle'), ['class' => 'card-title mb-3']);

echo html_writer::start_div('row');

// Date from.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('filter_date_from', 'gestionprojet'), ['for' => 'datefrom', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'datefrom',
    'id' => 'datefrom',
    'class' => 'form-control',
    'value' => $datefrom ? date('Y-m-d', $datefrom) : '',
]);
echo html_writer::end_div();

// Date to.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('filter_date_to', 'gestionprojet'), ['for' => 'dateto', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'date',
    'name' => 'dateto',
    'id' => 'dateto',
    'class' => 'form-control',
    'value' => $dateto ? date('Y-m-d', $dateto) : '',
]);
echo html_writer::end_div();

// Course.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('filter_course', 'gestionprojet'), ['for' => 'courseid', 'class' => 'form-label']);
$courseoptions = ['' => get_string('all_courses', 'gestionprojet')];
foreach ($courses as $course) {
    $courseoptions[$course->id] = $course->shortname;
}
echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control']);
echo html_writer::end_div();

// User.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('filter_user', 'gestionprojet'), ['for' => 'userid', 'class' => 'form-label']);
$useroptions = ['' => get_string('all_users', 'gestionprojet')];
foreach ($users as $user) {
    $useroptions[$user->id] = fullname($user);
}
echo html_writer::select($useroptions, 'userid', $userid, false, ['class' => 'form-control']);
echo html_writer::end_div();

// Provider.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('filter_provider', 'gestionprojet'), ['for' => 'provider', 'class' => 'form-label']);
$provideroptions = ['' => get_string('all_providers', 'gestionprojet')];
$provideroptions = array_merge($provideroptions, $providers);
echo html_writer::select($provideroptions, 'provider', $provider, false, ['class' => 'form-control']);
echo html_writer::end_div();

// Status.
echo html_writer::start_div('col-md-2 mb-2');
echo html_writer::tag('label', get_string('filter_status', 'gestionprojet'), ['for' => 'status', 'class' => 'form-label']);
$statusoptions = ['' => get_string('all_statuses', 'gestionprojet')];
$statusoptions = array_merge($statusoptions, $statuses);
echo html_writer::select($statusoptions, 'status', $status, false, ['class' => 'form-control']);
echo html_writer::end_div();

echo html_writer::end_div(); // row

// Buttons.
echo html_writer::start_div('mt-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('filter_apply', 'gestionprojet'),
    'class' => 'btn btn-primary mr-2',
]);
echo html_writer::link(
    new moodle_url('/mod/gestionprojet/report.php'),
    get_string('filter_clear', 'gestionprojet'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

echo html_writer::end_tag('form');

// Summary stats.
echo html_writer::start_div('alert alert-info mb-4');
echo html_writer::start_div('d-flex justify-content-between align-items-center');
echo html_writer::tag('span', get_string('summary_requests', 'gestionprojet', number_format($summary->total_requests)));
echo html_writer::tag('span', ' | ');
echo html_writer::tag('span', get_string('summary_tokens', 'gestionprojet', number_format($summary->total_tokens)));
echo html_writer::end_div();
echo html_writer::end_div();

// Handle date conversion for form submission.
echo html_writer::script("
    document.querySelector('form').addEventListener('submit', function(e) {
        var datefrom = document.getElementById('datefrom').value;
        var dateto = document.getElementById('dateto').value;

        if (datefrom) {
            document.getElementById('datefrom').value = Math.floor(new Date(datefrom).getTime() / 1000);
        }
        if (dateto) {
            document.getElementById('dateto').value = Math.floor(new Date(dateto).getTime() / 1000);
        }
    });
");

// Output table.
$table->out($perpage, true);

// Load AMD module for expand/collapse.
$PAGE->requires->js_amd_inline("
    require(['jquery'], function(\$) {
        // Handle show more buttons.
        \$(document).on('click', '.show-more-btn', function(e) {
            e.preventDefault();
            var btn = \$(this);
            var fullText = btn.data('full-text');
            var pre = btn.prev('pre');

            if (btn.hasClass('expanded')) {
                // Collapse.
                pre.text(fullText.substring(0, 500) + '...');
                btn.text('" . get_string('show_more', 'gestionprojet') . "');
                btn.removeClass('expanded');
            } else {
                // Expand.
                pre.text(fullText);
                btn.text('" . get_string('show_less', 'gestionprojet') . "');
                btn.addClass('expanded');
            }
        });

        // Handle expand/collapse buttons.
        \$(document).on('click', '.ai-log-expand', function() {
            var btn = \$(this);
            var target = \$(btn.data('target'));

            if (target.hasClass('show')) {
                btn.text('" . get_string('expand_prompts', 'gestionprojet') . "');
            } else {
                btn.text('" . get_string('collapse_prompts', 'gestionprojet') . "');
            }
        });
    });
");

echo $OUTPUT->footer();

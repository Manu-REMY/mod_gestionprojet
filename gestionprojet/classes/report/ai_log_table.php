<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI log table for displaying AI evaluation history.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_gestionprojet\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table class for AI log report.
 */
class ai_log_table extends \table_sql {

    /** @var array Filters applied to the table */
    protected $filters = [];

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique ID for the table
     * @param array $filters Filter parameters
     */
    public function __construct($uniqueid, array $filters = []) {
        parent::__construct($uniqueid);
        $this->filters = $filters;

        // Define columns.
        $columns = [
            'id',
            'timecreated',
            'coursename',
            'activityname',
            'step',
            'username',
            'provider',
            'model',
            'tokens',
            'status',
            'actions',
        ];
        $this->define_columns($columns);

        // Define headers.
        $headers = [
            get_string('column_id', 'gestionprojet'),
            get_string('column_datetime', 'gestionprojet'),
            get_string('column_course', 'gestionprojet'),
            get_string('column_activity', 'gestionprojet'),
            get_string('column_step', 'gestionprojet'),
            get_string('column_user', 'gestionprojet'),
            get_string('column_provider', 'gestionprojet'),
            get_string('column_model', 'gestionprojet'),
            get_string('column_tokens', 'gestionprojet'),
            get_string('column_status', 'gestionprojet'),
            get_string('actions'),
        ];
        $this->define_headers($headers);

        // Define sortable columns.
        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('actions');
        $this->no_sorting('tokens');

        // Set up pagination.
        $this->pageable(true);

        // Set up the SQL.
        $this->setup_sql();
    }

    /**
     * Set up the SQL query.
     */
    protected function setup_sql() {
        global $DB;

        $fields = "e.id, e.timecreated, e.step, e.provider, e.model,
                   e.prompt_tokens, e.completion_tokens, e.status,
                   e.prompt_system, e.prompt_user, e.raw_response, e.error_message,
                   e.userid, e.groupid,
                   g.name AS activityname, g.id AS gestionprojetid,
                   c.id AS courseid, c.shortname AS coursename,
                   u.id AS uid, u.firstname, u.lastname, u.email";

        $from = "{gestionprojet_ai_evaluations} e
                 JOIN {gestionprojet} g ON g.id = e.gestionprojetid
                 JOIN {course} c ON c.id = g.course
                 LEFT JOIN {user} u ON u.id = e.userid";

        $where = "1=1";
        $params = [];

        // Apply filters.
        if (!empty($this->filters['datefrom'])) {
            $where .= " AND e.timecreated >= :datefrom";
            $params['datefrom'] = $this->filters['datefrom'];
        }

        if (!empty($this->filters['dateto'])) {
            $where .= " AND e.timecreated <= :dateto";
            $params['dateto'] = $this->filters['dateto'];
        }

        if (!empty($this->filters['courseid'])) {
            $where .= " AND c.id = :courseid";
            $params['courseid'] = $this->filters['courseid'];
        }

        if (!empty($this->filters['userid'])) {
            $where .= " AND e.userid = :userid";
            $params['userid'] = $this->filters['userid'];
        }

        if (!empty($this->filters['provider'])) {
            $where .= " AND e.provider = :provider";
            $params['provider'] = $this->filters['provider'];
        }

        if (!empty($this->filters['status'])) {
            $where .= " AND e.status = :status";
            $params['status'] = $this->filters['status'];
        }

        if (!empty($this->filters['gestionprojetid'])) {
            $where .= " AND e.gestionprojetid = :gestionprojetid";
            $params['gestionprojetid'] = $this->filters['gestionprojetid'];
        }

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
    }

    /**
     * Format the ID column.
     *
     * @param object $row Row data
     * @return string Formatted ID
     */
    public function col_id($row) {
        return $row->id;
    }

    /**
     * Format the datetime column.
     *
     * @param object $row Row data
     * @return string Formatted datetime
     */
    public function col_timecreated($row) {
        return userdate($row->timecreated, get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Format the course column.
     *
     * @param object $row Row data
     * @return string Formatted course link
     */
    public function col_coursename($row) {
        $url = new \moodle_url('/course/view.php', ['id' => $row->courseid]);
        return \html_writer::link($url, s($row->coursename));
    }

    /**
     * Format the activity column.
     *
     * @param object $row Row data
     * @return string Formatted activity link
     */
    public function col_activityname($row) {
        global $DB;

        // Get the course module ID.
        $cm = get_coursemodule_from_instance('gestionprojet', $row->gestionprojetid, $row->courseid);
        if ($cm) {
            $url = new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]);
            return \html_writer::link($url, s($row->activityname));
        }
        return s($row->activityname);
    }

    /**
     * Format the step column.
     *
     * @param object $row Row data
     * @return string Formatted step
     */
    public function col_step($row) {
        $stepnames = [
            4 => get_string('step4_title', 'gestionprojet'),
            5 => get_string('step5_title', 'gestionprojet'),
            6 => get_string('step6_title', 'gestionprojet'),
            7 => get_string('step7_title', 'gestionprojet'),
            8 => get_string('step8_title', 'gestionprojet'),
        ];
        $stepname = $stepnames[$row->step] ?? $row->step;
        return \html_writer::tag('span', $row->step, ['class' => 'badge badge-secondary', 'title' => $stepname]);
    }

    /**
     * Format the username column.
     *
     * @param object $row Row data
     * @return string Formatted username
     */
    public function col_username($row) {
        global $DB;

        if (!empty($row->groupid)) {
            // Group submission - show group name.
            $group = $DB->get_record('groups', ['id' => $row->groupid]);
            if ($group) {
                return \html_writer::tag('span', s($group->name), ['class' => 'badge badge-info']) .
                       ' ' . get_string('group');
            }
        }

        if (!empty($row->uid)) {
            $url = new \moodle_url('/user/profile.php', ['id' => $row->uid]);
            return \html_writer::link($url, fullname($row));
        }

        return '-';
    }

    /**
     * Format the provider column.
     *
     * @param object $row Row data
     * @return string Formatted provider
     */
    public function col_provider($row) {
        $providers = [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'mistral' => 'Mistral',
            'albert' => 'Albert (Etalab)',
        ];
        return $providers[$row->provider] ?? $row->provider;
    }

    /**
     * Format the model column.
     *
     * @param object $row Row data
     * @return string Formatted model
     */
    public function col_model($row) {
        return \html_writer::tag('code', s($row->model));
    }

    /**
     * Format the tokens column.
     *
     * @param object $row Row data
     * @return string Formatted tokens
     */
    public function col_tokens($row) {
        $prompt = (int)$row->prompt_tokens;
        $completion = (int)$row->completion_tokens;
        $total = $prompt + $completion;

        return \html_writer::tag('span', number_format($total), [
            'class' => 'badge badge-light',
            'title' => sprintf('Prompt: %s, Completion: %s', number_format($prompt), number_format($completion)),
        ]);
    }

    /**
     * Format the status column.
     *
     * @param object $row Row data
     * @return string Formatted status badge
     */
    public function col_status($row) {
        $statuses = [
            'pending' => ['class' => 'badge-secondary', 'label' => get_string('status_pending', 'gestionprojet')],
            'processing' => ['class' => 'badge-info', 'label' => get_string('status_processing', 'gestionprojet')],
            'completed' => ['class' => 'badge-success', 'label' => get_string('status_completed', 'gestionprojet')],
            'failed' => ['class' => 'badge-danger', 'label' => get_string('status_failed', 'gestionprojet')],
            'applied' => ['class' => 'badge-primary', 'label' => get_string('status_applied', 'gestionprojet')],
        ];

        $info = $statuses[$row->status] ?? ['class' => 'badge-secondary', 'label' => $row->status];

        return \html_writer::tag('span', $info['label'], ['class' => 'badge ' . $info['class']]);
    }

    /**
     * Format the actions column.
     *
     * @param object $row Row data
     * @return string Actions HTML
     */
    public function col_actions($row) {
        global $OUTPUT;

        $html = \html_writer::start_tag('button', [
            'class' => 'btn btn-sm btn-outline-primary ai-log-expand',
            'type' => 'button',
            'data-bs-toggle' => 'collapse',
            'data-bs-target' => '#prompts-' . $row->id,
            'aria-expanded' => 'false',
            'data-evaluation-id' => $row->id,
        ]);
        $html .= get_string('expand_prompts', 'gestionprojet');
        $html .= \html_writer::end_tag('button');

        return $html;
    }

    /**
     * Generate the prompts detail row after each data row.
     *
     * @param object $row Row data
     * @return string HTML for the detail row
     */
    public function col_prompts_detail($row) {
        $html = '';

        // System prompt.
        $html .= \html_writer::start_div('mb-3');
        $html .= \html_writer::tag('strong', get_string('prompt_system', 'gestionprojet') . ':');
        if (!empty($row->prompt_system)) {
            $truncated = $this->truncate_text($row->prompt_system, 500);
            $html .= \html_writer::tag('pre', s($truncated), ['class' => 'bg-light p-2 mt-1', 'style' => 'white-space: pre-wrap; max-height: 200px; overflow-y: auto;']);
            if (strlen($row->prompt_system) > 500) {
                $html .= \html_writer::tag('button', get_string('show_more', 'gestionprojet'), [
                    'class' => 'btn btn-sm btn-link show-more-btn',
                    'data-full-text' => s($row->prompt_system),
                    'data-field' => 'prompt_system',
                ]);
            }
        } else {
            $html .= \html_writer::tag('em', get_string('prompt_not_recorded', 'gestionprojet'), ['class' => 'text-muted']);
        }
        $html .= \html_writer::end_div();

        // User prompt.
        $html .= \html_writer::start_div('mb-3');
        $html .= \html_writer::tag('strong', get_string('prompt_user', 'gestionprojet') . ':');
        if (!empty($row->prompt_user)) {
            $truncated = $this->truncate_text($row->prompt_user, 500);
            $html .= \html_writer::tag('pre', s($truncated), ['class' => 'bg-light p-2 mt-1', 'style' => 'white-space: pre-wrap; max-height: 200px; overflow-y: auto;']);
            if (strlen($row->prompt_user) > 500) {
                $html .= \html_writer::tag('button', get_string('show_more', 'gestionprojet'), [
                    'class' => 'btn btn-sm btn-link show-more-btn',
                    'data-full-text' => s($row->prompt_user),
                    'data-field' => 'prompt_user',
                ]);
            }
        } else {
            $html .= \html_writer::tag('em', get_string('prompt_not_recorded', 'gestionprojet'), ['class' => 'text-muted']);
        }
        $html .= \html_writer::end_div();

        // AI Response.
        $html .= \html_writer::start_div('mb-3');
        $html .= \html_writer::tag('strong', get_string('ai_response', 'gestionprojet') . ':');
        if (!empty($row->raw_response)) {
            $truncated = $this->truncate_text($row->raw_response, 500);
            $html .= \html_writer::tag('pre', s($truncated), ['class' => 'bg-light p-2 mt-1', 'style' => 'white-space: pre-wrap; max-height: 200px; overflow-y: auto;']);
            if (strlen($row->raw_response) > 500) {
                $html .= \html_writer::tag('button', get_string('show_more', 'gestionprojet'), [
                    'class' => 'btn btn-sm btn-link show-more-btn',
                    'data-full-text' => s($row->raw_response),
                    'data-field' => 'raw_response',
                ]);
            }
        } else {
            $html .= \html_writer::tag('em', '-', ['class' => 'text-muted']);
        }
        $html .= \html_writer::end_div();

        // Error message (if failed).
        if ($row->status === 'failed' && !empty($row->error_message)) {
            $html .= \html_writer::start_div('mb-3');
            $html .= \html_writer::tag('strong', get_string('error') . ':', ['class' => 'text-danger']);
            $html .= \html_writer::tag('pre', s($row->error_message), ['class' => 'bg-danger text-white p-2 mt-1']);
            $html .= \html_writer::end_div();
        }

        return $html;
    }

    /**
     * Truncate text to a maximum length.
     *
     * @param string $text Text to truncate
     * @param int $maxlength Maximum length
     * @return string Truncated text
     */
    protected function truncate_text($text, $maxlength) {
        if (strlen($text) <= $maxlength) {
            return $text;
        }
        return substr($text, 0, $maxlength) . '...';
    }

    /**
     * Override the display method to add collapse rows.
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $OUTPUT;

        $this->setup();
        $this->query_db($pagesize, $useinitialsbar);

        // Build the table.
        $this->build_table();

        // Close the table.
        $this->close_recordset();

        // Output the table with custom rendering for expandable rows.
        $this->start_output();
        $this->start_html();
        $this->print_headers();

        foreach ($this->rawdata as $row) {
            $formattedrow = $this->format_row($row);
            $this->print_row($formattedrow, '');

            // Add expandable detail row.
            $colspan = count($this->columns);
            echo \html_writer::start_tag('tr', ['class' => 'collapse', 'id' => 'prompts-' . $row->id]);
            echo \html_writer::tag('td', $this->col_prompts_detail($row), ['colspan' => $colspan, 'class' => 'bg-light p-3']);
            echo \html_writer::end_tag('tr');
        }

        $this->finish_html();
        $this->finish_output();
    }

    /**
     * Get summary statistics.
     *
     * @return object Summary data
     */
    public function get_summary() {
        global $DB;

        $from = "{gestionprojet_ai_evaluations} e
                 JOIN {gestionprojet} g ON g.id = e.gestionprojetid
                 JOIN {course} c ON c.id = g.course";

        $where = "1=1";
        $params = [];

        // Apply same filters.
        if (!empty($this->filters['datefrom'])) {
            $where .= " AND e.timecreated >= :datefrom";
            $params['datefrom'] = $this->filters['datefrom'];
        }

        if (!empty($this->filters['dateto'])) {
            $where .= " AND e.timecreated <= :dateto";
            $params['dateto'] = $this->filters['dateto'];
        }

        if (!empty($this->filters['courseid'])) {
            $where .= " AND c.id = :courseid";
            $params['courseid'] = $this->filters['courseid'];
        }

        if (!empty($this->filters['userid'])) {
            $where .= " AND e.userid = :userid";
            $params['userid'] = $this->filters['userid'];
        }

        if (!empty($this->filters['provider'])) {
            $where .= " AND e.provider = :provider";
            $params['provider'] = $this->filters['provider'];
        }

        if (!empty($this->filters['status'])) {
            $where .= " AND e.status = :status";
            $params['status'] = $this->filters['status'];
        }

        if (!empty($this->filters['gestionprojetid'])) {
            $where .= " AND e.gestionprojetid = :gestionprojetid";
            $params['gestionprojetid'] = $this->filters['gestionprojetid'];
        }

        $sql = "SELECT COUNT(*) as total_requests,
                       COALESCE(SUM(e.prompt_tokens), 0) as total_prompt_tokens,
                       COALESCE(SUM(e.completion_tokens), 0) as total_completion_tokens
                FROM $from
                WHERE $where";

        $summary = $DB->get_record_sql($sql, $params);
        $summary->total_tokens = $summary->total_prompt_tokens + $summary->total_completion_tokens;

        return $summary;
    }
}

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
 * "Mon avancement" panel of the student step pages (v2.12.0).
 *
 * Expects: $gestionprojet, $cm, $step, $submission, $groupid, $context. Closes the main column opened by
 * gestionprojet_progress_layout_open(), renders the panel, closes the layout. Status and grade (or levels), what is
 * left to complete (list filled live by mod_gestionprojet/progress_panel), last AI feedback (filtered), feedback
 * request and submit buttons. A teacher looking at a group's or a student's copy gets it read-only.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

echo '</div>';

if (!empty($submission)) {
    // Read-only for a teacher looking at someone else's copy (a group's, or another user's); their own copy is theirs.
    $progressreadonly = (int)$submission->userid !== (int)$USER->id && has_capability('mod/gestionprojet:grade', $context);
    $progress = gestionprojet_progress_state($gestionprojet, (int)$step, $submission, $progressreadonly);

    echo html_writer::start_tag('aside', ['class' => 'gp-progress-panel', 'aria-label' => get_string('progress_title', 'gestionprojet')]);
    echo html_writer::tag('h2', get_string('progress_title', 'gestionprojet'));
    if ($progressreadonly) {
        echo html_writer::tag('p', get_string('progress_readonly', 'gestionprojet'), ['class' => 'gp-progress-readonly']);
    }

    // Status and grade (or levels).
    if ($progress['status'] === 'submitted') {
        $statustext = '✓ ' . get_string('progress_status_submitted', 'gestionprojet',
            userdate((int)$submission->timesubmitted, get_string('strftimedatetimeshort')));
    } else if ($progress['status'] === 'pending') {
        $statustext = '… ' . get_string('progress_status_pending', 'gestionprojet');
    } else {
        $statustext = '✎ ' . get_string('progress_status_draft', 'gestionprojet');
    }
    if ($progress['palier']) {
        $levels = [];
        foreach ($progress['palier']['ingrid'] as $code) {
            $label = get_string('grille_palier_' . strtolower($code), 'gestionprojet');
            $levels[] = in_array($code, $progress['palier']['validated'], true) ? '✓ ' . $label : '· ' . $label;
        }
        $gradetext = implode('  ', $levels);
    } else if ($progress['grade'] !== null) {
        $value = format_float($progress['grade'], 1);
        $gradetext = $progress['previousgrade'] ? get_string('progress_grade_previous', 'gestionprojet', $value) : $value . '/20';
    } else if ($progress['status'] !== 'draft') {
        // Submitted, waiting for its grade: never "not submitted yet" next to "submitted".
        $gradetext = get_string('progress_grade_pending', 'gestionprojet');
    } else {
        $gradetext = get_string('progress_grade_none', 'gestionprojet');
    }
    echo html_writer::start_div('gp-progress-cards');
    echo html_writer::div(html_writer::div(get_string('progress_status', 'gestionprojet'), 'gp-progress-label')
        . html_writer::div(s($statustext), 'gp-progress-value'), 'gp-progress-card');
    echo html_writer::div(html_writer::div(get_string('progress_grade', 'gestionprojet'), 'gp-progress-label')
        . html_writer::div(s($gradetext), 'gp-progress-value'), 'gp-progress-card');
    echo html_writer::end_div();

    // What is left to complete: filled live by mod_gestionprojet/progress_panel (steps 4 to 7).
    echo html_writer::start_div('gp-progress-todo');
    if (in_array((int)$step, [4, 5, 6, 7], true) && $progress['status'] === 'draft') {
        echo html_writer::div(html_writer::span(get_string('progress_todo_title', 'gestionprojet'))
            . html_writer::span('', 'gp-progress-count', ['data-gp-progress-count' => '1']), 'gp-progress-todo-head');
        echo html_writer::div(html_writer::div('', 'gp-progress-bar-fill'), 'gp-progress-bar',
            ['role' => 'progressbar', 'aria-valuemin' => 0, 'aria-valuemax' => 100, 'aria-valuenow' => 0,
                'aria-label' => get_string('progress_todo_title', 'gestionprojet')]);
        echo html_writer::tag('ul', '', ['class' => 'gp-progress-list', 'data-gp-progress-list' => '1']);
    } else {
        echo html_writer::link('#gpProgressMain', get_string('progress_goto_form', 'gestionprojet'));
    }
    echo html_writer::end_div();
    // Live list (steps 4 to 7) and the buttons of the narrow-screen bar: started on every step.
    $forms = [5 => '#essaiForm', 6 => '#rapportForm', 7 => '#besoinEleveForm'];
    $PAGE->requires->js_call_amd('mod_gestionprojet/progress_panel', 'init', [[
            'step' => (int)$step,
            'formSelector' => $forms[(int)$step] ?? '',
            'strings' => [
                'count' => get_string('progress_todo_count', 'gestionprojet'),
                'done' => get_string('progress_todo_done', 'gestionprojet'),
                'statement' => get_string('progress_field_statement', 'gestionprojet'),
                'criterion' => get_string('progress_field_criterion', 'gestionprojet'),
                'level' => get_string('progress_field_level', 'gestionprojet'),
                'interactor' => get_string('progress_field_interactor', 'gestionprojet'),
                'constraint' => get_string('progress_field_constraint', 'gestionprojet'),
            ],
        ]]);

    // Level mode (step 4): the level block (buttons, attempt feedback) stays as it is; read-only shows the summary.
    if ($progress['palier'] && !$progressreadonly) {
        require(__DIR__ . '/student_palier_display.php');
    } else if (!empty($gestionprojet->ai_enabled) || $progress['feedback']['source'] === 'teacher') {
        echo html_writer::start_div('gp-progress-feedback');
        echo html_writer::tag('h3', get_string('progress_feedback_title', 'gestionprojet'));
        $formative = $progress['formative'];
        if ($formative && $formative['pending']) {
            // Followed by formative_button: no automatic reload while the copy is open.
            echo html_writer::div(html_writer::span(get_string('formative_pending', 'gestionprojet'), 'ai-progress-label'),
                'gp-formative-status ai-progress-banner status-' . s($formative['pending']->status),
                ['id' => 'gpFormativeBanner']);
            $PAGE->requires->js_call_amd('mod_gestionprojet/formative_button', 'init', [[
                'cmid' => (int)$cm->id,
                'pendingId' => (int)$formative['pending']->id,
                'statusUrl' => (new moodle_url('/mod/gestionprojet/ajax/get_evaluation_status.php'))->out(false),
                'bannerId' => 'gpFormativeBanner',
                'strings' => [
                    'ready' => get_string('formative_ready', 'gestionprojet'),
                    'show' => get_string('formative_show', 'gestionprojet'),
                    'error' => get_string('formative_error', 'gestionprojet'),
                ],
            ]]);
        } else if ($progress['feedback']['source'] !== 'none') {
            echo html_writer::tag('p', userdate($progress['feedback']['time'], get_string('strftimedatetimeshort')),
                ['class' => 'gp-progress-date']);
            if ($progress['feedback']['text'] !== '') {
                echo html_writer::tag('p', nl2br(s($progress['feedback']['text'])));
            }
            if ($progress['feedback']['suggestions']) {
                echo html_writer::alist(array_map(function ($suggestion): string {
                    return s(is_scalar($suggestion) ? (string)$suggestion : '');
                }, $progress['feedback']['suggestions']));
            }
            // Criteria of the grade (score per criterion), as the former grade block showed them.
            if ($progress['feedback']['criteria']) {
                echo html_writer::tag('h4', get_string('progress_criteria', 'gestionprojet'));
                $items = [];
                foreach ($progress['feedback']['criteria'] as $criterion) {
                    if (!is_array($criterion)) {
                        continue;
                    }
                    $line = (string)($criterion['name'] ?? '');
                    if (isset($criterion['score'], $criterion['max'])) {
                        $line .= ' : ' . format_float((float)$criterion['score'], 1) . '/' . format_float((float)$criterion['max'], 1);
                    }
                    if (!empty($criterion['comment'])) {
                        $line .= ' — ' . (string)$criterion['comment'];
                    }
                    $items[] = s($line);
                }
                echo html_writer::alist($items, ['class' => 'gp-progress-criteria']);
            }
            if ($progress['feedback']['keywords']) {
                echo html_writer::tag('h4', get_string('progress_keywords', 'gestionprojet'));
                echo html_writer::alist(array_map(function ($keyword): string {
                    return '✓ ' . s(is_scalar($keyword) ? (string)$keyword : '');
                }, $progress['feedback']['keywords']), ['class' => 'gp-progress-keywords']);
            }
        } else {
            echo html_writer::tag('p', get_string('progress_feedback_none', 'gestionprojet'), ['class' => 'gp-progress-date']);
        }
        echo html_writer::end_div();
    }

    // Buttons: feedback request and submission (a teacher looking at a copy gets none).
    echo html_writer::start_div('gp-progress-actions');
    if ($progress['canask'] && !$progress['formative']['pending']) {
        if ($progress['formative']['remaining'] > 0) {
            echo html_writer::tag('button', get_string('formative_request', 'gestionprojet', $progress['formative']['remaining']),
                ['type' => 'button', 'class' => 'btn btn-outline-primary', 'id' => 'gpFormativeButton']);
            $PAGE->requires->js_call_amd('mod_gestionprojet/formative_button', 'init', [[
                'cmid' => (int)$cm->id,
                'step' => (int)$step,
                'groupid' => (int)$groupid,
                'buttonId' => 'gpFormativeButton',
            ]]);
        } else {
            echo html_writer::div('⊘ ' . get_string('formative_none_left', 'gestionprojet'), 'gp-formative-none');
        }
    }
    if ($progress['cansubmit']) {
        echo html_writer::tag('button', get_string('progress_submit', 'gestionprojet'),
            ['type' => 'button', 'class' => 'btn btn-primary btn-submit-large', 'id' => 'submitButton']);
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('aside');

    // Narrow screen (or Moodle's block drawer open): a bar at the bottom keeps the status and the buttons in sight.
    // Its buttons forward the click to the panel's own buttons (one #submitButton per page).
    $barbuttons = '';
    if ($progress['canask'] && !$progress['formative']['pending'] && $progress['formative']['remaining'] > 0) {
        $barbuttons .= html_writer::tag('button', get_string('formative_request', 'gestionprojet', $progress['formative']['remaining']),
            ['type' => 'button', 'class' => 'btn btn-outline-primary', 'data-gp-forward' => 'gpFormativeButton']);
    }
    if ($progress['cansubmit']) {
        $barbuttons .= html_writer::tag('button', get_string('progress_submit', 'gestionprojet'),
            ['type' => 'button', 'class' => 'btn btn-primary', 'data-gp-forward' => 'submitButton']);
    }
    echo html_writer::div(html_writer::span(s($statustext . ' · ' . $gradetext), 'gp-progress-bottombar-status') . $barbuttons,
        'gp-progress-bottombar');
}

echo '</div>';

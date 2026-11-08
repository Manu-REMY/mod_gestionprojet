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
 * Formative feedback block of the student step pages (v2.11.4).
 *
 * Expects: $gestionprojet, $cm, $step, $submission, $groupid, $isSubmitted.
 * The student asks the AI for a feedback without submitting (N per step); only the last feedback is shown, filtered,
 * without grade. Nothing is shown once the copy is submitted.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!empty($submission) && empty($isSubmitted)) {
    $formative = gestionprojet_formative_state($gestionprojet, (int)$step, $submission);
    if ($formative['enabled']) {
        echo html_writer::start_div('gp-formative');
        echo html_writer::tag('h4', get_string('formative_title', 'gestionprojet'));
        echo html_writer::tag('p', get_string('formative_help', 'gestionprojet'), ['class' => 'text-muted small']);

        if ($formative['pending']) {
            // Banner followed by formative_button (status polling, then a "show" button).
            echo html_writer::div(html_writer::span(get_string('formative_pending', 'gestionprojet'), 'ai-progress-label'),
                'gp-formative-status ai-progress-banner status-' . s($formative['pending']->status),
                ['id' => 'gpFormativeBanner', 'data-status' => s($formative['pending']->status)]);
            // No automatic reload when it is ready: the student keeps working (a button shows the feedback).
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
        } else if ($formative['remaining'] > 0) {
            echo html_writer::tag('button', get_string('formative_request', 'gestionprojet', $formative['remaining']),
                ['type' => 'button', 'class' => 'btn btn-outline-primary', 'id' => 'gpFormativeButton']);
            $PAGE->requires->js_call_amd('mod_gestionprojet/formative_button', 'init', [[
                'cmid' => (int)$cm->id,
                'step' => (int)$step,
                'groupid' => (int)$groupid,
                'buttonId' => 'gpFormativeButton',
            ]]);
        } else {
            echo html_writer::div('⊘ ' . get_string('formative_none_left', 'gestionprojet'), 'gp-formative-status gp-formative-none');
        }

        // The last feedback only (no history), always filtered: no word of the correction model the student did not write.
        $last = $formative['last'];
        if ($last && !$formative['pending']) {
            $criteria = json_decode((string)$last->criteria_json, true) ?: [];
            $suggestions = json_decode((string)$last->suggestions, true) ?: [];
            $view = \mod_gestionprojet\answer_leak_filter::student_view((string)$last->parsed_feedback, $criteria, $suggestions,
                gestionprojet_answer_leak_roots($gestionprojet, (int)$step, $submission));
            echo html_writer::start_div('gp-formative-feedback');
            echo html_writer::tag('h5', get_string('formative_last', 'gestionprojet',
                userdate($last->timemodified, get_string('strftimedatetimeshort'))));
            if ($view['feedback'] !== '') {
                echo html_writer::tag('p', nl2br(s($view['feedback'])));
            }
            if ($view['suggestions']) {
                echo html_writer::alist(array_map(function ($suggestion): string {
                    return s(is_scalar($suggestion) ? (string)$suggestion : '');
                }, $view['suggestions']));
            }
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }
}

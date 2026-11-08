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
 * Level validation block of the step 4 student page (v2.11.2).
 *
 * Expects: $gestionprojet, $cm, $submission (gestionprojet_cdcf), $groupid.
 * Validated level: "✓" + word; level not validated: "⊘" + word; never the colour alone.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$palierstate = gestionprojet_palier_state($gestionprojet, $submission);
$palierlabel = function (string $code): string {
    return get_string('grille_palier_' . strtolower($code), 'gestionprojet');
};
$pending = $palierstate['pending'];

echo html_writer::start_div('gp-palier');
echo html_writer::tag('h3', get_string('palier_title', 'gestionprojet'));

foreach ($palierstate['ingrid'] as $code) {
    $label = $palierlabel($code);
    if (in_array($code, $palierstate['validated'], true)) {
        echo html_writer::div('✓ ' . get_string('palier_validated', 'gestionprojet', $label), 'gp-palier-status gp-palier-ok');
    } else if ($pending && $pending->palier === $code) {
        // Same markup as the submission progress banner: student_ai_progress polls it and reloads at the end.
        echo html_writer::div(html_writer::span(get_string('palier_pending', 'gestionprojet', $label), 'ai-progress-label'),
            'gp-palier-status gp-palier-pending ai-progress-banner status-' . s($pending->status),
            ['id' => 'ai-progress-banner', 'data-status' => s($pending->status)]);
    } else if (!$pending && in_array($code, $palierstate['requestable'], true)) {
        echo html_writer::tag('button', get_string('palier_request', 'gestionprojet', $label),
            ['type' => 'button', 'class' => 'btn btn-primary gp-palier-request', 'data-gp-palier' => $code]);
    } else {
        echo html_writer::div('· ' . $label, 'gp-palier-status gp-palier-later');
    }
}

// Feedback of the last attempt, always filtered: it never shows words of the correction model the group did not write.
$last = $palierstate['last'];
if ($last && !$pending) {
    $label = $palierlabel((string)$last->palier);
    if ($last->status === 'failed') {
        echo html_writer::div('⊘ ' . get_string('palier_error', 'gestionprojet'), 'gp-palier-status gp-palier-ko');
    } else {
        $criteria = json_decode((string)$last->criteria_json, true) ?: [];
        $suggestions = json_decode((string)$last->suggestions, true) ?: [];
        $view = \mod_gestionprojet\answer_leak_filter::student_view((string)$last->parsed_feedback, $criteria, $suggestions,
            gestionprojet_answer_leak_roots($gestionprojet, 4, $submission));
        $parts = \mod_gestionprojet\palier::parts_for(gestionprojet_palier_parts($gestionprojet), (string)$last->palier);
        $verdict = \mod_gestionprojet\palier::verdict($criteria, $parts, (int)($gestionprojet->step4_palier_seuil ?? 80));
        echo html_writer::start_div('gp-palier-feedback');
        if ((int)$last->palier_valide !== 1) {
            echo html_writer::div('⊘ ' . get_string('palier_failed', 'gestionprojet', $label), 'gp-palier-status gp-palier-ko');
        }
        echo html_writer::tag('p', get_string('palier_score', 'gestionprojet', format_float($verdict['percent'], 1)),
            ['class' => 'gp-palier-score']);
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
}
echo html_writer::end_div();

if ($pending) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/student_ai_progress', 'init', [[
        'evaluationid' => (int)$pending->id,
        'cmid' => (int)$cm->id,
        'statusUrl' => (new moodle_url('/mod/gestionprojet/ajax/get_evaluation_status.php'))->out(false),
        'strings' => [
            'pending_student' => get_string('palier_pending', 'gestionprojet', $palierlabel((string)$pending->palier)),
            'processing_student' => get_string('palier_pending', 'gestionprojet', $palierlabel((string)$pending->palier)),
            'failed_student' => get_string('palier_error', 'gestionprojet'),
        ],
    ]]);
} else if ($palierstate['requestable']) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/palier_buttons', 'init', [[
        'cmid' => (int)$cm->id,
        'groupid' => (int)$groupid,
        'dataFieldId' => 'cdcfDataField',
    ]]);
}

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
 * Level validation block of the grading page, step 4 (v2.11.2).
 *
 * Expects: $gestionprojet, $cm, $submission (gestionprojet_cdcf).
 * Shows the validated levels (with a remove button) and the history of the attempts.
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
$palierparts = gestionprojet_palier_parts($gestionprojet);
$seuil = (int)($gestionprojet->step4_palier_seuil ?? 80);

echo html_writer::start_div('ai-evaluation-container gp-palier');
echo html_writer::tag('h4', get_string('palier_title', 'gestionprojet'));

// Validated levels, each with a remove button (the higher levels are removed with it).
if (!$palierstate['rows']) {
    echo html_writer::tag('p', get_string('palier_none_validated', 'gestionprojet'), ['class' => 'gp-palier-later']);
}
foreach ($palierstate['rows'] as $row) {
    $label = $palierlabel($row->palier);
    echo html_writer::start_div('gp-palier-row');
    echo html_writer::span('✓ ' . get_string('palier_validated', 'gestionprojet', $label), 'gp-palier-status gp-palier-ok');
    echo html_writer::span(userdate($row->timecreated, get_string('strftimedatetimeshort')), 'gp-palier-date');
    echo html_writer::tag('button', get_string('palier_retire', 'gestionprojet'), [
        'type' => 'button',
        'class' => 'btn btn-sm btn-outline-secondary',
        'data-gp-retire' => (int)$row->id,
        'data-confirm' => get_string('palier_retire_confirm', 'gestionprojet', $label),
    ]);
    echo html_writer::end_div();
}

// Attempts, most recent first.
if ($palierstate['attempts']) {
    echo html_writer::tag('h5', get_string('palier_attempts', 'gestionprojet'));
    $items = [];
    foreach ($palierstate['attempts'] as $attempt) {
        $label = $palierlabel((string)$attempt->palier);
        $when = userdate($attempt->timecreated, get_string('strftimedatetimeshort'));
        if (in_array($attempt->status, ['pending', 'processing'], true)) {
            $status = get_string('palier_attempt_pending', 'gestionprojet');
        } else if ($attempt->status === 'failed') {
            $status = '⊘ ' . get_string('palier_attempt_error', 'gestionprojet');
        } else {
            $criteria = json_decode((string)$attempt->criteria_json, true) ?: [];
            $verdict = \mod_gestionprojet\palier::verdict($criteria,
                \mod_gestionprojet\palier::parts_for($palierparts, (string)$attempt->palier), $seuil);
            $status = ((int)$attempt->palier_valide === 1
                    ? '✓ ' . get_string('palier_attempt_ok', 'gestionprojet')
                    : '⊘ ' . get_string('palier_attempt_ko', 'gestionprojet'))
                . ' — ' . get_string('palier_score', 'gestionprojet', format_float($verdict['percent'], 1));
        }
        $items[] = s($label . ' · ' . $when . ' · ' . $status);
    }
    echo html_writer::alist($items, ['class' => 'gp-palier-attempts']);
}
echo html_writer::end_div();

$PAGE->requires->js_call_amd('mod_gestionprojet/palier_retire', 'init', [[
    'cmid' => (int)$cm->id,
    'title' => get_string('palier_retire', 'gestionprojet'),
]]);

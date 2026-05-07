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
 * Step 9: FAST diagram (student production).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_capability('mod/gestionprojet:submit', $context);

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9]);
$PAGE->set_title(get_string('step9', 'gestionprojet'));

// Resolve groupid / userid.
$groupid = groups_get_activity_group($cm, true);
$isgroup = ($gestionprojet->group_submission && $groupid != 0);
$effectivegroupid = $isgroup ? $groupid : 0;
$effectiveuserid = $isgroup ? 0 : $USER->id;

// get_or_create_submission already handles step9_provided seeding from teacher.
$submission = gestionprojet_get_or_create_submission(
    $gestionprojet,
    $effectivegroupid,
    $effectiveuserid,
    'fast'
);

$isLocked = ((int)$submission->status === 1);
$isSubmitted = $isLocked;
$canSubmit = !empty($gestionprojet->enable_submission) && !$isSubmitted;
$canRevert = has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;
// student_submit_helper.php uses $groupid (and $step) directly.
$groupid = $effectivegroupid;
$step = 9;

$tplcontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'isteacher' => false,
    'datajson' => $submission->data_json ?? '',
    'aiinstructions' => '',
    'canpopulatecdcf' => false,
    'isprovided' => (int)$gestionprojet->step9_provided === 1,
    'submitted' => (int)$submission->status === 1,
    'mode' => 'student',
];

$PAGE->requires->js_call_amd('mod_gestionprojet/fast_editor', 'init', [[
    'cmid' => (int)$cm->id,
    'mode' => 'student',
    'sesskey' => sesskey(),
    'groupid' => (int)$effectivegroupid,
]]);

if ((int)($gestionprojet->step9_provided ?? 0) === 1 && !$isLocked) {
    $PAGE->requires->js_call_amd('mod_gestionprojet/reset_button', 'init', [[
        'cmid'      => (int)$cm->id,
        'step'      => 9,
        'groupid'   => (int)$effectivegroupid,
        'sesskey'   => sesskey(),
        'resetUrl'  => (new moodle_url('/mod/gestionprojet/ajax/reset_to_provided.php'))->out(false),
        'resetLang' => [
            'modalTitle'   => get_string('reset_modal_title', 'gestionprojet'),
            'modalBody'    => get_string('reset_modal_body', 'gestionprojet'),
            'modalConfirm' => get_string('reset_modal_confirm', 'gestionprojet'),
            'modalCancel'  => get_string('reset_modal_cancel', 'gestionprojet'),
            'success'      => get_string('reset_success', 'gestionprojet'),
            'genericError' => get_string('error', 'core'),
        ],
    ]]);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 9, 'student')
);
echo $OUTPUT->heading(get_string('step9', 'gestionprojet'));

// Submission status notification (mirrors step 4/5 pattern).
if ($isSubmitted) {
    echo $OUTPUT->notification(
        get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted ?? time())),
        'success'
    );
}

echo html_writer::start_div('description');
echo html_writer::tag('h3', get_string('step9_desc_title', 'gestionprojet'));
echo html_writer::tag('p', get_string('step9_desc_text', 'gestionprojet'));
echo html_writer::end_div();

// Display teacher intro_text read-only above the FAST canvas.
if ((int)($gestionprojet->step9_provided ?? 0) === 1) {
    $providedforintro = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);
    if ($providedforintro && !empty(trim(strip_tags($providedforintro->intro_text ?? '')))) {
        echo html_writer::start_div('alert alert-info gp-consigne-intro');
        echo html_writer::tag('h4', get_string('intro_section_title', 'gestionprojet'));
        echo format_text($providedforintro->intro_text, FORMAT_HTML, ['context' => $context]);
        echo html_writer::end_div();
    }
}

echo html_writer::start_div('gp-student');

// Lock the FAST canvas after submission. The gp-fast-readonly class disables
// pointer events on descendants (CSS in styles.css) so the user cannot drag,
// edit text, or click action buttons inside the diagram. The action bar below
// is rendered outside this wrapper so the teacher's Revert button stays usable.
if ($isLocked) {
    echo html_writer::start_div('gp-fast-readonly');
}
echo $OUTPUT->render_from_template('mod_gestionprojet/step9_form', $tplcontext);
if ($isLocked) {
    echo html_writer::end_div();
}

// Action bar: Submit + (optional) Revert + Reset.
echo html_writer::start_div('export-section gp-fast-actions');

if ($canSubmit) {
    echo html_writer::tag('button',
        '📤 ' . get_string('submit', 'gestionprojet'),
        [
            'type'  => 'button',
            'class' => 'btn btn-primary btn-lg btn-submit-large',
            'id'    => 'submitButton',
        ]
    );
}

if ($canRevert) {
    echo html_writer::tag('button',
        get_string('unlock', 'gestionprojet'),
        [
            'type'  => 'button',
            'class' => 'btn btn-secondary btn-lg',
            'id'    => 'unlockButton',
        ]
    );
}

if ((int)($gestionprojet->step9_provided ?? 0) === 1) {
    $resetlabel = get_string('reset_button_label', 'gestionprojet');
    if ($isLocked) {
        echo html_writer::tag('span',
            html_writer::tag('button', $resetlabel, [
                'type'     => 'button',
                'class'    => 'btn btn-warning',
                'id'       => 'resetButton',
                'disabled' => 'disabled',
                'tabindex' => '-1',
            ]),
            [
                'class' => 'gp-reset-wrapper d-inline-block',
                'title' => get_string('reset_disabled_tooltip', 'gestionprojet'),
            ]
        );
    } else {
        echo html_writer::tag('button', $resetlabel, [
            'type'  => 'button',
            'class' => 'btn btn-warning',
            'id'    => 'resetButton',
        ]);
    }
}

echo html_writer::end_div();

// Wire the modal-based submit flow + AI progress banner.
require __DIR__ . '/student_submit_helper.php';

echo html_writer::end_div();
echo $OUTPUT->footer();

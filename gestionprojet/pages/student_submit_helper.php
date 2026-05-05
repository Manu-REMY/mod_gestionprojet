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
 * Shared submit helper for student step pages (4-8).
 *
 * Wires the new modal-based submission flow and renders the AI progress banner
 * when an evaluation is pending/processing/failed.
 *
 * Required variables in scope before including this file:
 *   - $gestionprojet : object — the activity instance record
 *   - $cm            : object — course module
 *   - $step          : int    — step number (4..8)
 *   - $submission    : object — current student submission record
 *   - $groupid       : int    — current group id (0 if individual)
 *   - $isSubmitted   : bool   — whether this submission is already locked (status=1)
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $PAGE;

// Derive group submission flag locally.
$isGroupSubmission = !empty($gestionprojet->group_submission) && !empty($groupid);

// Initialize the new modal-based submission JS (replaces inline confirm() in step pages).
$PAGE->requires->js_call_amd('mod_gestionprojet/submission', 'init', [[
    'cmid' => (int)$cm->id,
    'step' => (int)$step,
    'groupId' => (int)$groupid,
    'isGroup' => $isGroupSubmission,
    'aiEnabled' => !empty($gestionprojet->ai_enabled),
    'strings' => [
        'modal_title' => get_string('submit_modal_title', 'gestionprojet'),
        'confirm_submit_btn' => get_string('confirm_submit_btn', 'gestionprojet'),
        'submitting' => get_string('submitting', 'gestionprojet'),
        'submission_error' => get_string('submissionerror', 'gestionprojet'),
    ],
]]);

// Load pending AI evaluation if AI is enabled and submission was made.
$pendingEval = null;
if ($isSubmitted && !empty($submission) && !empty($gestionprojet->ai_enabled)
        && in_array($step, [4, 5, 6, 7, 8])) {
    require_once(__DIR__ . '/../classes/ai_evaluator.php');
    $pendingEval = \mod_gestionprojet\ai_evaluator::get_evaluation(
        $gestionprojet->id,
        $step,
        $submission->id
    );
}

// Render the AI progress banner if an evaluation is pending/processing/failed.
if ($isSubmitted && $pendingEval && in_array($pendingEval->status, ['pending', 'processing', 'failed'])) {
    $bannerKey = ($pendingEval->status === 'failed')
        ? 'ai_progress_failed_student'
        : 'ai_progress_' . $pendingEval->status . '_student';

    echo '<div id="ai-progress-banner" class="ai-progress-banner status-' . s($pendingEval->status) . '" data-status="' . s($pendingEval->status) . '">';
    echo '<span class="ai-progress-icon">' . \mod_gestionprojet\output\icon::render('zap', 'sm', 'inherit') . '</span>';
    echo '<span class="ai-progress-label">' . get_string($bannerKey, 'gestionprojet') . '</span>';
    echo '</div>';

    // Start polling only when the evaluation is still in progress.
    if (in_array($pendingEval->status, ['pending', 'processing'])) {
        $PAGE->requires->js_call_amd('mod_gestionprojet/student_ai_progress', 'init', [[
            'evaluationid' => (int)$pendingEval->id,
            'cmid' => (int)$cm->id,
            'statusUrl' => (new moodle_url('/mod/gestionprojet/ajax/get_evaluation_status.php'))->out(false),
            'strings' => [
                'pending_student' => get_string('ai_progress_pending_student', 'gestionprojet'),
                'processing_student' => get_string('ai_progress_processing_student', 'gestionprojet'),
                'failed_student' => get_string('ai_progress_failed_student', 'gestionprojet'),
            ],
        ]]);
    }
}

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
 * Step 5: Test Sheet - Validation (Student group page)
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Check if this file is included by view.php or accessed directly
if (!defined('MOODLE_INTERNAL')) {
    // Standalone mode - requires config
    require_once(__DIR__ . '/../../../config.php');
    require_once(__DIR__ . '/../lib.php');

    $id = required_param('id', PARAM_INT); // Course module ID

    $cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);

    $context = context_module::instance($cm->id);

    // Page setup
    $PAGE->set_url('/mod/gestionprojet/pages/step5.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('step5', 'gestionprojet'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);
}

// Variables are set - continue with page logic
// Variables are set - continue with page logic
require_capability('mod/gestionprojet:submit', $context);

// Get user's group or requested group (for teachers)
$groupid = optional_param('groupid', 0, PARAM_INT);
if ($groupid) {
    // Only teachers can view other groups
    require_capability('mod/gestionprojet:grade', $context);
} else {
    // If not showing a specific group...
    // If teacher, they start with groupid=0 (Teacher Workspace)
    if (has_capability('mod/gestionprojet:grade', $context)) {
        $groupid = 0;
    } else {
        // Students get their assigned group
        $groupid = gestionprojet_get_user_group($cm, $USER->id);
    }
}

// If group submission is enabled, user must be in a group (unless teacher)
// Teachers with groupid=0 are allowed (handled by lib.php as individual submission)
if ($gestionprojet->group_submission && !$groupid && !has_capability('mod/gestionprojet:grade', $context)) {
    throw new \moodle_exception('not_in_group', 'gestionprojet');
}

// Get or create submission
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'essai');

// Check if submitted
$isSubmitted = $submission->status == 1;
$isLocked = $isSubmitted; // Lock if submitted
$canSubmit = $gestionprojet->enable_submission && !$isSubmitted;
$canRevert = has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;

// Autosave handled inline at bottom of file
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [[
//     'cmid' => $cm->id,
//     'step' => 5,
//     'interval' => $gestionprojet->autosaveinterval * 1000
// ]]);

echo $OUTPUT->header();

// Render student step navigation tabs.
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 5, 'student')
);

// Status display
if ($isSubmitted) {
    echo $OUTPUT->notification(get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted)), 'success');
}

// Display submission dates
$step = 5;
require_once(__DIR__ . '/student_dates_display.php');

$disabled = $isLocked ? 'disabled readonly' : '';

// Get group info
$group = groups_get_group($groupid);
if (!$group) {
    $group = new stdClass(); // Fallback to avoid crash on name access
    $group->name = get_string('defaultgroup', 'group'); // Generic fallback
}

// Parse precautions: historically stored as a JSON array of 6 strings (one per cell).
// When seeded from the consigne (essai_provided.precautions), the value is a
// free-form text — split on newlines and clamp to 6 entries for back-compat with
// the 6-cell student layout. Trailing/empty lines are kept positionally to preserve
// the cell mapping.
$precautions = [];
if (!empty($submission->precautions)) {
    $decoded = json_decode($submission->precautions, true);
    if (is_array($decoded)) {
        $precautions = $decoded;
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $submission->precautions);
        $precautions = array_slice($lines, 0, 6);
    }
}
?>



<div class="step-container gp-student"
    data-str-error-submitting="<?php echo s(get_string('error_submitting', 'gestionprojet')); ?>"
    data-str-error-reverting="<?php echo s(get_string('error_reverting', 'gestionprojet')); ?>"
>
    <?php
    // Moodle-native heading + subtitle (replaces legacy colored banner).
    echo $OUTPUT->heading(get_string('step5_page_title', 'gestionprojet'), 2);
    ?>
    <p class="text-muted small"><?php echo get_string('step5_page_subtitle', 'gestionprojet'); ?></p>

    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- AI Evaluation Feedback Display -->
    <?php require_once(__DIR__ . '/student_ai_feedback_display.php'); ?>

    <form id="essaiForm" method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Informations générales -->
        <div class="info-section">
            <div class="section-title-simple"><?php echo get_string('step5_general_info', 'gestionprojet'); ?></div>

            <div class="info-row">
                <div class="info-group">
                    <label for="nom_essai"><?php echo get_string('nom_essai', 'gestionprojet'); ?> :</label>
                    <input type="text" id="nom_essai" name="nom_essai"
                        value="<?php echo s($submission->nom_essai ?? ''); ?>"
                        placeholder="<?php echo get_string('nom_essai_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>>
                </div>
                <div class="info-group">
                    <label for="date_essai"><?php echo get_string('date', 'gestionprojet'); ?> :</label>
                    <input type="date" id="date_essai" name="date_essai"
                        value="<?php echo s($submission->date_essai ?? date('Y-m-d')); ?>" <?php echo $disabled; ?>>
                </div>
            </div>

        </div>

        <!-- Section 1: Objectif de l'essai -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">1</div>
                <div class="section-header-text"><?php echo get_string('step5_section1_title', 'gestionprojet'); ?></div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="fonction_service">
                        <?php echo get_string('step5_fonction_service_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="fonction_service" name="fonction_service"
                        placeholder="<?php echo get_string('step5_fonction_service_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->fonction_service ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label" for="niveaux_reussite">
                        <?php echo get_string('step5_niveaux_reussite_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="niveaux_reussite" name="niveaux_reussite"
                        placeholder="<?php echo get_string('step5_niveaux_reussite_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->niveaux_reussite ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Section 2: Conception du protocole -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">2</div>
                <div class="section-header-text"><?php echo get_string('step5_section2_title', 'gestionprojet'); ?></div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="etapes_protocole">
                        <?php echo get_string('step5_etapes_protocole_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="etapes_protocole" name="etapes_protocole"
                        placeholder="<?php echo get_string('step5_etapes_protocole_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->etapes_protocole ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label" for="materiel_outils">
                        <?php echo get_string('step5_materiel_outils_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="materiel_outils" name="materiel_outils"
                        placeholder="<?php echo get_string('step5_materiel_outils_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->materiel_outils ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label">
                        <?php echo get_string('step5_precautions_label', 'gestionprojet'); ?>
                    </label>
                    <table class="precautions-table">
                        <tbody>
                            <tr>
                                <td>
                                    <textarea id="precaution_1" name="precaution_1"
                                        placeholder="<?php echo get_string('step5_precaution_placeholder', 'gestionprojet', 1); ?>" <?php echo $disabled; ?>><?php echo s($precautions[0] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_2" name="precaution_2"
                                        placeholder="<?php echo get_string('step5_precaution_placeholder', 'gestionprojet', 2); ?>" <?php echo $disabled; ?>><?php echo s($precautions[1] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_3" name="precaution_3"
                                        placeholder="<?php echo get_string('step5_precaution_placeholder', 'gestionprojet', 3); ?>" <?php echo $disabled; ?>><?php echo s($precautions[2] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <textarea id="precaution_4" name="precaution_4"
                                        placeholder="<?php echo get_string('step5_precaution_placeholder', 'gestionprojet', 4); ?>" <?php echo $disabled; ?>><?php echo s($precautions[3] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_5" name="precaution_5"
                                        placeholder="<?php echo get_string('step5_precaution_placeholder', 'gestionprojet', 5); ?>" <?php echo $disabled; ?>><?php echo s($precautions[4] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_6" name="precaution_6"
                                        placeholder="<?php echo get_string('step5_precaution_placeholder', 'gestionprojet', 6); ?>" <?php echo $disabled; ?>><?php echo s($precautions[5] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Section 3: Résultats et observations -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">3</div>
                <div class="section-header-text"><?php echo get_string('step5_section3_title', 'gestionprojet'); ?></div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="resultats_obtenus">
                        <?php echo get_string('step5_resultats_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="resultats_obtenus" name="resultats_obtenus"
                        placeholder="<?php echo get_string('step5_resultats_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->resultats_obtenus ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label" for="observations_remarques">
                        <?php echo get_string('step5_observations_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="observations_remarques" name="observations_remarques"
                        placeholder="<?php echo get_string('step5_observations_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->observations_remarques ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Section 4: Conclusion -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">4</div>
                <div class="section-header-text"><?php echo get_string('step5_section4_title', 'gestionprojet'); ?></div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="conclusion">
                        <?php echo get_string('step5_conclusion_label', 'gestionprojet'); ?>
                    </label>
                    <textarea id="conclusion" name="conclusion"
                        placeholder="<?php echo get_string('step5_conclusion_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->conclusion ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Actions section -->
        <div class="export-section">
            <?php if ($canSubmit): ?>
                <button type="button" class="btn btn-primary btn-lg btn-submit-large" id="submitButton">
                    📤 <?php echo get_string('submit', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <?php if ($canRevert): ?>
                <button type="button" class="btn btn-warning" id="revertButton">
                    ↩️ <?php echo get_string('revert_to_draft', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <button type="button" id="exportPdfBtn" class="btn-export btn-export-margin">
                📄 <?php echo get_string('export_pdf', 'gestionprojet'); ?> (2 pages)
            </button>
            <p class="export-notice">
                ℹ️ <?php echo get_string('export_pdf_notice', 'gestionprojet'); ?>
            </p>
        </div>
    </form>
</div>

<?php
// Wire the new modal-based submit flow + AI progress banner.
$isSubmitted = ($submission && (int)$submission->status === 1);
require __DIR__ . '/student_submit_helper.php';

$PAGE->requires->js_call_amd('mod_gestionprojet/step5', 'init', [[
    'cmid' => (int)$cm->id,
    'step' => 5,
    'groupid' => (int)$groupid,
    'autosaveInterval' => (int)$gestionprojet->autosave_interval * 1000,
    'isLocked' => (bool)$isLocked,
    'strings' => [
        'confirm_revert' => get_string('confirm_revert', 'gestionprojet'),
        'export_pdf_coming_soon' => get_string('export_pdf_coming_soon', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->footer();
?>
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 5: Fiche Essai - Validation (Student group page)
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

require_capability('mod/gestionprojet:submit', $context);

// Get user's group or requested group (for teachers)
$groupid = optional_param('groupid', 0, PARAM_INT);
if ($groupid) {
    // Only teachers can view other groups
    require_capability('mod/gestionprojet:grade', $context);
} else {
    $groupid = gestionprojet_get_user_group($cm, $USER->id);
}

// If group submission is enabled, user must be in a group
if ($gestionprojet->group_submission && !$groupid) {
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

// Status display
if ($isSubmitted) {
    echo $OUTPUT->notification(get_string('submitted_on', 'gestionprojet', userdate($submission->timesubmitted)), 'success');
}

$disabled = $isLocked ? 'disabled readonly' : '';

// Get group info
$group = groups_get_group($groupid);
if (!$group) {
    $group = new stdClass(); // Fallback to avoid crash on name access
    $group->name = get_string('defaultgroup', 'group'); // Generic fallback
}

// Parse precautions (stored as JSON array)
$precautions = [];
if ($submission->precautions) {
    $precautions = json_decode($submission->precautions, true) ?? [];
}
?>



<div class="step5-container">
    <!-- Navigation -->
    <div class="navigation-top">
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>🏠</span>
                <span><?php echo get_string('home', 'gestionprojet'); ?></span>
            </a>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/pages/step4.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>←</span>
                <span><?php echo get_string('previous', 'gestionprojet'); ?></span>
            </a>
        </div>
        <a href="<?php echo new moodle_url('/mod/gestionprojet/pages/step6.php', ['id' => $cm->id]); ?>"
            class="nav-button">
            <span><?php echo get_string('next', 'gestionprojet'); ?></span>
            <span>→</span>
        </a>
    </div>

    <!-- Header -->
    <div class="header-section">
        <div class="header-title">
            <h2>■ FICHE ESSAI</h2>
            <div class="header-subtitle">Démarche expérimentale - Technologie</div>
        </div>
        <div class="header-logo">🔬</div>
    </div>

    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- Grade display -->
    <?php if (isset($submission->grade) && $submission->grade !== null): ?>
        <div class="grade-display">
            ⭐ <strong><?php echo get_string('grade', 'gestionprojet'); ?>:</strong>
            <?php echo format_float($submission->grade, 2); ?> / 20
        </div>
        <?php if (!empty($submission->feedback)): ?>
            <div class="feedback-display">
                <h4>💬 <?php echo get_string('teacher_feedback', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->feedback, FORMAT_PLAIN); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form id="essaiForm" method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Informations générales -->
        <div class="info-section">
            <div class="section-title">Informations générales</div>

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

            <div class="info-group">
                <label for="groupe_eleves"><?php echo get_string('groupe_eleves', 'gestionprojet'); ?> :</label>
                <textarea id="groupe_eleves" name="groupe_eleves" rows="2"
                    placeholder="<?php echo get_string('groupe_eleves_placeholder', 'gestionprojet'); ?>" <?php echo $disabled; ?>><?php echo s($submission->groupe_eleves ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 1: Objectif de l'essai -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">1</div>
                <div class="section-header-text">OBJECTIF DE L'ESSAI</div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="fonction_service">
                        Quelle est la fonction de service/contrainte que doit satisfaire le système ?
                    </label>
                    <textarea id="fonction_service" name="fonction_service"
                        placeholder="Décrivez la fonction de service ou la contrainte à satisfaire..." <?php echo $disabled; ?>><?php echo s($submission->fonction_service ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label" for="niveaux_reussite">
                        Quelles sont les niveaux (valeurs et unité) que définissent la réussite du test ?
                    </label>
                    <textarea id="niveaux_reussite" name="niveaux_reussite"
                        placeholder="Précisez les valeurs attendues et les unités de mesure..." <?php echo $disabled; ?>><?php echo s($submission->niveaux_reussite ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Section 2: Conception du protocole -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">2</div>
                <div class="section-header-text">CONCEPTION DU PROTOCOLE</div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="etapes_protocole">
                        Quelles sont les étapes de votre protocole ?
                    </label>
                    <textarea id="etapes_protocole" name="etapes_protocole"
                        placeholder="Listez les étapes de votre protocole expérimental..." <?php echo $disabled; ?>><?php echo s($submission->etapes_protocole ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label" for="materiel_outils">
                        Quel matériel et quels outils allez-vous utiliser ?
                    </label>
                    <textarea id="materiel_outils" name="materiel_outils"
                        placeholder="Listez le matériel et les outils nécessaires..." <?php echo $disabled; ?>><?php echo s($submission->materiel_outils ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label">
                        Quelles précautions expérimentales devez-vous mettre en œuvre pour assurer la validité du test ?
                    </label>
                    <table class="precautions-table">
                        <tbody>
                            <tr>
                                <td>
                                    <textarea id="precaution_1" name="precaution_1"
                                        placeholder="Précaution 1..." <?php echo $disabled; ?>><?php echo s($precautions[0] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_2" name="precaution_2"
                                        placeholder="Précaution 2..." <?php echo $disabled; ?>><?php echo s($precautions[1] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_3" name="precaution_3"
                                        placeholder="Précaution 3..." <?php echo $disabled; ?>><?php echo s($precautions[2] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <textarea id="precaution_4" name="precaution_4"
                                        placeholder="Précaution 4..." <?php echo $disabled; ?>><?php echo s($precautions[3] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_5" name="precaution_5"
                                        placeholder="Précaution 5..." <?php echo $disabled; ?>><?php echo s($precautions[4] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <textarea id="precaution_6" name="precaution_6"
                                        placeholder="Précaution 6..." <?php echo $disabled; ?>><?php echo s($precautions[5] ?? ''); ?></textarea>
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
                <div class="section-header-text">RÉSULTATS ET OBSERVATIONS</div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="resultats_obtenus">
                        Résultats obtenus :
                    </label>
                    <textarea id="resultats_obtenus" name="resultats_obtenus"
                        placeholder="Décrivez les résultats de l'essai..." <?php echo $disabled; ?>><?php echo s($submission->resultats_obtenus ?? ''); ?></textarea>
                </div>

                <div class="question-block">
                    <label class="question-label" for="observations_remarques">
                        Observations et remarques :
                    </label>
                    <textarea id="observations_remarques" name="observations_remarques"
                        placeholder="Notez vos observations et remarques..." <?php echo $disabled; ?>><?php echo s($submission->observations_remarques ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Section 4: Conclusion -->
        <div class="numbered-section">
            <div class="section-header">
                <div class="section-number">4</div>
                <div class="section-header-text">CONCLUSION</div>
            </div>
            <div class="section-content">
                <div class="question-block">
                    <label class="question-label" for="conclusion">
                        Le test est-il concluant ? Pourquoi ?
                    </label>
                    <textarea id="conclusion" name="conclusion"
                        placeholder="Rédigez votre conclusion..." <?php echo $disabled; ?>><?php echo s($submission->conclusion ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Actions section -->
        <div class="export-section" style="margin-top: 40px; text-align: center;">
            <?php if ($canSubmit): ?>
                <button type="button" class="btn btn-primary btn-lg" id="submitButton" style="padding: 15px 40px; font-size: 18px; border-radius: 50px;">
                    📤 <?php echo get_string('submit', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <?php if ($canRevert): ?>
                <button type="button" class="btn btn-warning" id="revertButton">
                    ↩️ <?php echo get_string('revert_to_draft', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <button type="button" class="btn-export" id="exportPdfBtn" style="margin-left: 20px;">
                📄 <?php echo get_string('export_pdf', 'gestionprojet'); ?> (2 pages)
            </button>
            <p style="margin-top: 15px; color: #6c757d; font-size: 0.9em;">
                ℹ️ <?php echo get_string('export_pdf_notice', 'gestionprojet'); ?>
            </p>
        </div>
    </form>
</div>

<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>


// Call AMD module
$PAGE->requires->js_call_amd('mod_gestionprojet/step5', 'init', [[
    'cmid' => $cm->id,
    'step' => 5,
    'groupid' => $groupid,
    'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
    'isLocked' => $readonly,
    'strings' => [
        'confirm_submission' => get_string('confirm_submission', 'gestionprojet'),
        'confirm_revert' => get_string('confirm_revert', 'gestionprojet'),
        'export_pdf_coming_soon' => get_string('export_pdf_coming_soon', 'gestionprojet')
    ]
]]);
?>


<?php
echo $OUTPUT->footer();
?>
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 6: Rapport de Projet (Student group page)
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
    $PAGE->set_url('/mod/gestionprojet/pages/step6.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('step6', 'gestionprojet'));
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
    $groupid = gestionprojet_get_user_group($cm, $USER->id);
}

// If group submission is enabled, user must be in a group
if ($gestionprojet->group_submission && !$groupid) {
    throw new \moodle_exception('not_in_group', 'gestionprojet');
}

// Get or create submission
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'rapport');

// Check if submitted
$isSubmitted = $submission->status == 1;
$isLocked = $isSubmitted; // Lock if submitted
$canSubmit = $gestionprojet->enable_submission && !$isSubmitted;
$canRevert = has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;

// Autosave handled inline at bottom of file
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [[
//     'cmid' => $cm->id,
//     'step' => 6,
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

// Parse auteurs (stored as JSON array)
$auteurs = [];
if ($submission->auteurs) {
    $auteurs = json_decode($submission->auteurs, true) ?? [];
}
?>



<div class="step6-container">
    <!-- Navigation -->
    <div class="navigation-top">
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>🏠</span>
                <span><?php echo get_string('home', 'gestionprojet'); ?></span>
            </a>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/pages/step5.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>←</span>
                <span><?php echo get_string('previous', 'gestionprojet'); ?></span>
            </a>
        </div>
    </div>

    <!-- Header -->
    <div class="header-section">
        <h2>📋 RAPPORT DE PROJET</h2>
        <p>Technologie - Collège</p>
    </div>

    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- Info box -->
    <div class="info-box">
        <p><strong>💡 Ce document regroupe toutes les informations de votre projet</strong></p>
        <p>Remplissez tous les champs pour obtenir un rapport complet</p>
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

    <form id="rapportForm" method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Section 1: Informations Générales -->
        <div class="section">
            <h2 class="section-title">1. INFORMATIONS GÉNÉRALES</h2>

            <div class="form-group">
                <label for="titre_projet"><?php echo get_string('titre_projet', 'gestionprojet'); ?></label>
                <input type="text" id="titre_projet" name="titre_projet"
                    value="<?php echo s($submission->titre_projet ?? ''); ?>" placeholder="Nom de votre projet" <?php echo $disabled; ?>>
            </div>

            <div class="form-group">
                <label><?php echo get_string('membres_groupe', 'gestionprojet'); ?> :</label>
                <div id="membersContainer">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Section 2: Le Projet -->
        <div class="section">
            <h2 class="section-title">2. LE PROJET</h2>

            <div class="form-group">
                <label for="besoin_projet"><?php echo get_string('besoin_projet', 'gestionprojet'); ?></label>
                <textarea id="besoin_projet" name="besoin_projet"
                    placeholder="Décrivez le besoin auquel répond votre projet..." <?php echo $disabled; ?>><?php echo s($submission->besoin_projet ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="imperatifs"><?php echo get_string('imperatifs', 'gestionprojet'); ?></label>
                <textarea id="imperatifs" name="imperatifs"
                    placeholder="Listez les contraintes et impératifs du projet..." <?php echo $disabled; ?>><?php echo s($submission->imperatifs ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 3: Solutions Choisies -->
        <div class="section">
            <h2 class="section-title">3. SOLUTIONS CHOISIES</h2>

            <div class="form-group">
                <label for="solutions"><?php echo get_string('solutions', 'gestionprojet'); ?></label>
                <textarea id="solutions" name="solutions" placeholder="Décrivez les solutions retenues..." <?php echo $disabled; ?>><?php echo s($submission->solutions ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="justification"><?php echo get_string('justification', 'gestionprojet'); ?></label>
                <textarea id="justification" name="justification"
                    placeholder="Justifiez vos choix techniques et stratégiques..." <?php echo $disabled; ?>><?php echo s($submission->justification ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 4: Réalisation -->
        <div class="section">
            <h2 class="section-title">4. RÉALISATION</h2>

            <div class="form-group">
                <label for="realisation"><?php echo get_string('realisation', 'gestionprojet'); ?></label>
                <textarea id="realisation" name="realisation" placeholder="Comment avez-vous réalisé votre projet ?"
                    <?php echo $disabled; ?>><?php echo s($submission->realisation ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="difficultes"><?php echo get_string('difficultes', 'gestionprojet'); ?></label>
                <textarea id="difficultes" name="difficultes"
                    placeholder="Quelles difficultés avez-vous rencontrées et comment les avez-vous surmontées ?" <?php echo $disabled; ?>><?php echo s($submission->difficultes ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 5: Validation et Résultats -->
        <div class="section">
            <h2 class="section-title">5. VALIDATION ET RÉSULTATS</h2>

            <div class="form-group">
                <label for="validation"><?php echo get_string('validation', 'gestionprojet'); ?></label>
                <textarea id="validation" name="validation"
                    placeholder="Décrivez les résultats de vos tests et essais..." <?php echo $disabled; ?>><?php echo s($submission->validation ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="ameliorations"><?php echo get_string('ameliorations', 'gestionprojet'); ?></label>
                <textarea id="ameliorations" name="ameliorations"
                    placeholder="Quelles améliorations pourriez-vous apporter au projet ?" <?php echo $disabled; ?>><?php echo s($submission->ameliorations ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 6: Conclusion -->
        <div class="section">
            <h2 class="section-title">6. CONCLUSION</h2>

            <div class="form-group">
                <label for="bilan"><?php echo get_string('bilan', 'gestionprojet'); ?></label>
                <textarea id="bilan" name="bilan"
                    placeholder="Quel est votre bilan global du projet ? Qu'avez-vous appris ?" <?php echo $disabled; ?>><?php echo s($submission->bilan ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="perspectives"><?php echo get_string('perspectives', 'gestionprojet'); ?></label>
                <textarea id="perspectives" name="perspectives"
                    placeholder="Quelles sont les perspectives d'évolution du projet ?" <?php echo $disabled; ?>><?php echo s($submission->perspectives ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Actions section -->
        <div class="export-section" style="margin-top: 40px; text-align: center;">
            <?php if ($canSubmit): ?>
                <button type="button" class="btn btn-primary btn-lg" id="submitButton"
                    style="padding: 15px 40px; font-size: 18px; border-radius: 50px;">
                    📤 <?php echo get_string('submit', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <?php if ($canRevert): ?>
                <button type="button" class="btn btn-warning" id="revertButton">
                    ↩️ <?php echo get_string('revert_to_draft', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <button type="button" class="btn-export" id="exportPdfBtn" style="margin-left: 20px;">
                📄 <?php echo get_string('export_pdf', 'gestionprojet'); ?>
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
$PAGE->requires->js_call_amd('mod_gestionprojet/step6', 'init', [[
    'cmid' => $cm->id,
    'step' => 6,
    'groupid' => $groupid,
    'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
    'isLocked' => $readonly,
    'auteurs' => $auteurs,
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
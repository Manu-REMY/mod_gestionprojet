<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 4: Cahier des Charges Fonctionnel (Student group page)
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
    $PAGE->set_url('/mod/gestionprojet/pages/step4.php', ['id' => $cm->id]);
    $PAGE->set_title(get_string('step4', 'gestionprojet'));
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
$submission = gestionprojet_get_or_create_submission($gestionprojet, $groupid, $USER->id, 'cdcf');

// Check if submitted
$isSubmitted = $submission->status == 1;
$isLocked = $isSubmitted; // Lock if submitted
$canSubmit = $gestionprojet->enable_submission && !$isSubmitted;
$canRevert = has_capability('mod/gestionprojet:grade', $context) && $isSubmitted;

// Autosave handled inline at bottom of file
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [[
//     'cmid' => $cm->id,
//     'step' => 4,
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

// Parse interacteurs (stored as JSON array)
$interacteurs = [];
if ($submission->interacteurs_data) {
    $interacteurs = json_decode($submission->interacteurs_data, true) ?? [];
}

// Default interacteurs if empty
if (empty($interacteurs)) {
    $interacteurs = [
        ['name' => 'Interacteur 1', 'fcs' => [['value' => '', 'criteres' => [['critere' => '', 'niveau' => '', 'unite' => '']]]]],
        ['name' => 'Interacteur 2', 'fcs' => [['value' => '', 'criteres' => [['critere' => '', 'niveau' => '', 'unite' => '']]]]]
    ];
}
?>



<div class="step4-container">
    <!-- Navigation -->
    <div class="navigation-top">
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>🏠</span>
                <span><?php echo get_string('home', 'gestionprojet'); ?></span>
            </a>
        </div>
        <a href="<?php echo new moodle_url('/mod/gestionprojet/pages/step5.php', ['id' => $cm->id]); ?>"
            class="nav-button">
            <span><?php echo get_string('next', 'gestionprojet'); ?></span>
            <span>→</span>
        </a>
    </div>

    <!-- Header -->
    <div class="header-section">
        <h2>📋 CAHIER DES CHARGES FONCTIONNEL</h2>
        <p>Analyse fonctionnelle - Technologie</p>
    </div>

    <!-- Group info -->
    <div class="group-info">
        👥 <strong><?php echo get_string('your_group', 'gestionprojet'); ?>:</strong>
        <?php echo format_string($group->name); ?>
    </div>

    <!-- Description -->
    <div class="description">
        <h3>📖 Qu'est-ce qu'un Cahier des Charges Fonctionnel ?</h3>
        <p>Le Cahier des Charges Fonctionnel (CDCF) définit ce que doit faire le produit sans dire comment.</p>
        <p><strong>FP (Fonction Principale)</strong> : Relie le produit aux 2 premiers éléments du milieu extérieur</p>
        <p><strong>FC (Fonction Contrainte)</strong> : Relie le produit à 1 élément du milieu extérieur</p>
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

    <form id="cdcfForm" method="post" action="">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

        <!-- Project section -->
        <div class="project-section">
            <div class="project-name-container">
                <div class="project-name">
                    <label for="produit"><?php echo get_string('produit', 'gestionprojet'); ?></label>
                    <input type="text" id="produit" name="produit" value="<?php echo s($submission->produit ?? ''); ?>"
                        placeholder="Nom du produit" <?php echo $disabled; ?>>
                </div>

                <div class="fp-container">
                    <label class="fp-label">FP (Fonction Principale)</label>
                    <textarea id="fp" name="fp" class="fp-input"
                        placeholder="Décrivez la fonction principale du produit..." <?php echo $disabled; ?>><?php echo s($submission->fp ?? ''); ?></textarea>
                </div>
            </div>

            <div class="project-name">
                <label for="milieu"><?php echo get_string('milieu', 'gestionprojet'); ?></label>
                <input type="text" id="milieu" name="milieu" value="<?php echo s($submission->milieu ?? ''); ?>"
                    placeholder="Milieu d'utilisation" <?php echo $disabled; ?>>
            </div>
        </div>

        <!-- Diagram -->
        <div class="diagram-container">
            <h3 class="diagram-title">📊 Diagramme des Interacteurs</h3>
            <svg id="interactorDiagram" viewBox="0 0 800 500"></svg>
        </div>

        <!-- Interactors section -->
        <div class="interactors-section">
            <h3 class="section-title">⚙️ Interacteurs et Fonctions Contraintes</h3>

            <div id="interactorsContainer"></div>
            <?php if (!$isLocked): ?>
                <button type="button" class="btn-add" id="addInteractorBtn">+ Ajouter un interacteur</button>
            <?php endif; ?>
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

// Call AMD module
$PAGE->requires->js_call_amd('mod_gestionprojet/step4', 'init', [
    [
        'cmid' => $cm->id,
        'step' => 4,
        'groupid' => $groupid,
        'autosaveInterval' => $gestionprojet->autosave_interval * 1000,
        'isLocked' => $readonly,
        'interacteurs' => $interacteurs,
        'strings' => [
            'confirm_submission' => get_string('confirm_submission', 'gestionprojet'),
            'confirm_revert' => get_string('confirm_revert', 'gestionprojet'),
            'export_pdf_coming_soon' => get_string('export_pdf_coming_soon', 'gestionprojet')
        ]
    ]
]);
?>

<?php
echo $OUTPUT->footer();
?>
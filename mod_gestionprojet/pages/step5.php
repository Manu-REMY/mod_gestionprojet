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

<style>
    .step5-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Navigation */
    .navigation-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .nav-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        transition: all 0.3s;
        text-decoration: none;
    }

    .nav-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        color: white;
        text-decoration: none;
    }

    .nav-button-prev {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    }

    /* Header */
    .header-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
    }

    .header-title h2 {
        font-size: 1.8em;
        margin-bottom: 5px;
    }

    .header-subtitle {
        font-size: 0.95em;
        opacity: 0.9;
    }

    .header-logo {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5em;
    }

    /* Group info */
    .group-info {
        background: #d1ecf1;
        border: 2px solid #bee5eb;
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 30px;
        color: #0c5460;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .group-info strong {
        font-weight: 700;
    }

    /* Info section */
    .info-section {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 30px;
    }

    .section-title {
        color: #333;
        font-size: 1.1em;
        font-weight: 700;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }

    .info-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .info-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.9em;
    }

    .info-group input,
    .info-group textarea {
        width: 100%;
        padding: 10px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 0.95em;
        font-family: inherit;
        transition: border-color 0.3s;
    }

    .info-group input:focus,
    .info-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }

    /* Numbered sections */
    .numbered-section {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        margin-bottom: 30px;
        overflow: hidden;
    }

    .section-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px 25px;
        display: flex;
        align-items: center;
        gap: 15px;
        color: white;
    }

    .section-number {
        background: white;
        color: #667eea;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4em;
        font-weight: bold;
    }

    .section-header-text {
        font-size: 1.2em;
        font-weight: 600;
    }

    .section-content {
        padding: 25px;
    }

    .question-block {
        margin-bottom: 25px;
    }

    .question-block:last-child {
        margin-bottom: 0;
    }

    .question-label {
        color: #333;
        font-weight: 600;
        font-size: 0.95em;
        margin-bottom: 10px;
        display: block;
    }

    .question-block textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 0.9em;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
        transition: border-color 0.3s;
    }

    .question-block textarea:focus {
        outline: none;
        border-color: #667eea;
    }

    /* Precautions table */
    .precautions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .precautions-table td {
        border: 2px solid #ddd;
        padding: 10px;
        vertical-align: top;
        width: 33.33%;
    }

    .precautions-table textarea {
        width: 100%;
        height: 100%;
        border: none;
        padding: 5px;
        font-size: 0.9em;
        font-family: inherit;
        resize: none;
        min-height: 80px;
    }

    .precautions-table textarea:focus {
        outline: 1px solid #667eea;
    }

    /* Export button */
    .export-section {
        text-align: center;
        margin-top: 40px;
        padding: 30px;
        background: #f8f9fa;
        border-radius: 15px;
    }

    .btn-export {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 15px 40px;
        border-radius: 50px;
        font-size: 18px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        transition: all 0.3s;
    }

    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.6);
    }

    /* Grade display */
    .grade-display {
        background: #d4edda;
        border: 2px solid #c3e6cb;
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        color: #155724;
    }

    .grade-display strong {
        font-size: 1.1em;
    }

    .feedback-display {
        background: #fff3cd;
        border: 2px solid #ffeaa7;
        border-radius: 10px;
        padding: 15px 20px;
        margin-top: 15px;
        color: #856404;
    }

    .feedback-display h4 {
        margin: 0 0 10px 0;
        font-size: 1em;
    }

    .feedback-display p {
        margin: 0;
        white-space: pre-wrap;
    }

    @media (max-width: 768px) {
        .info-row {
            grid-template-columns: 1fr;
        }

        .header-section {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }

        .precautions-table {
            display: block;
        }

        .precautions-table tbody,
        .precautions-table tr,
        .precautions-table td {
            display: block;
            width: 100%;
        }

        .precautions-table td {
            margin-bottom: 10px;
        }
    }
</style>

<div class="step5-container">
    <!-- Navigation -->
<?php
    $nav_links = gestionprojet_get_navigation_links($gestionprojet, $cm->id, 'step5');
    ?>
    <div class="navigation-top">
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>🏠</span>
                <span><?php echo get_string('home', 'gestionprojet'); ?></span>
            </a>
            <?php if ($nav_links['prev']): ?>
            <a href="<?php echo $nav_links['prev']; ?>"
                class="nav-button nav-button-prev">
                <span>←</span>
                <span><?php echo get_string('previous', 'gestionprojet'); ?></span>
            </a>
            <?php endif; ?>
        </div>
        <?php if ($nav_links['next']): ?>
        <a href="<?php echo $nav_links['next']; ?>"
            class="nav-button">
            <span><?php echo get_string('next', 'gestionprojet'); ?></span>
            <span>→</span>
        </a>
        <?php endif; ?>
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

            <button type="button" class="btn-export" onclick="exportPDF()" style="margin-left: 20px;">
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

<script>
    // Wait for jQuery to be loaded
    // Wait for jQuery to be loaded
    // Wait for RequireJS and jQuery
    (function waitRequire() {
        if (typeof require === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(waitRequire, 50);
            return;
        }

        require(['jquery', 'mod_gestionprojet/autosave'], function ($, Autosave) {
            var cmid = <?php echo $cm->id; ?>;
            var step = 5;
            var autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
            var groupid = <?php echo $groupid; ?>;

            // Custom serialization for step 5
            var serializeData = function () {
                var formData = {};

                // Collect regular fields (text inputs, textareas, date)
                $('#essaiForm').find('input[type="text"], input[type="date"], textarea').each(function () {
                    if (this.name && !this.name.startsWith('precaution_')) {
                        formData[this.name] = this.value;
                    }
                });

                // Collect precautions as JSON array
                var precautions = [];
                for (var i = 1; i <= 6; i++) {
                    var input = document.getElementById('precaution_' + i);
                    if (input) {
                        precautions.push(input.value);
                    }
                }
                formData['precautions'] = JSON.stringify(precautions);

                return formData;
            };

            var isLocked = <?php echo $isLocked ? 'true' : 'false'; ?>;

            // Handle Submission
            $('#submitButton').on('click', function() {
                if (confirm('<?php echo get_string('confirm_submission', 'gestionprojet'); ?>')) {
                    $.ajax({
                        url: '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            action: 'submit',
                            sesskey: M.cfg.sesskey
                        },
                        success: function(response) {
                             var res = JSON.parse(response);
                             if (res.success) {
                                 window.location.reload();
                             } else {
                                 alert('Error submitting');
                             }
                        }
                    });
                }
            });

            // Handle Revert
            $('#revertButton').on('click', function() {
                if (confirm('<?php echo get_string('confirm_revert', 'gestionprojet'); ?>')) {
                    $.ajax({
                        url: '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            action: 'revert',
                            sesskey: M.cfg.sesskey
                        },
                        success: function(response) {
                             var res = JSON.parse(response);
                             if (res.success) {
                                 window.location.reload();
                             } else {
                                 alert('Error reverting');
                             }
                        }
                    });
                }
            });

            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid, // Note: Autosave might need update if groupid is 0 but we kept groupid var
                    interval: autosaveInterval,
                    formSelector: '#essaiForm',
                    serialize: serializeData
                });
            }
        });
    })();

    // PDF Export placeholder (will use TCPDF server-side in future)
    function exportPDF() {
        alert('<?php echo get_string('export_pdf_coming_soon', 'gestionprojet'); ?>');
        // TODO: Implement server-side PDF generation with TCPDF
    }
</script>

<?php
echo $OUTPUT->footer();
?>
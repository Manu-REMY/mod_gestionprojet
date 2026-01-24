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



<div class="step-container">
    <!-- Navigation -->
    <?php
    $nav_links = gestionprojet_get_navigation_links($gestionprojet, $cm->id, 'step6');
    ?>
    <div class="navigation-top">
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>"
                class="nav-button nav-button-prev">
                <span>🏠</span>
                <span><?php echo get_string('home', 'gestionprojet'); ?></span>
            </a>
            <?php if ($nav_links['prev']): ?>
                <a href="<?php echo $nav_links['prev']; ?>" class="nav-button nav-button-prev">
                    <span>←</span>
                    <span><?php echo get_string('previous', 'gestionprojet'); ?></span>
                </a>
            <?php endif; ?>
        </div>
        <?php if ($nav_links['next']): ?>
            <a href="<?php echo $nav_links['next']; ?>" class="nav-button">
                <span><?php echo get_string('next', 'gestionprojet'); ?></span>
                <span>→</span>
            </a>
        <?php endif; ?>
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
        <div class="report-section">
            <h2 class="report-section-title">1. INFORMATIONS GÉNÉRALES</h2>

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
        <div class="report-section">
            <h2 class="report-section-title">2. LE PROJET</h2>

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
        <div class="report-section">
            <h2 class="report-section-title">3. SOLUTIONS CHOISIES</h2>

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
        <div class="report-section">
            <h2 class="report-section-title">4. RÉALISATION</h2>

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
        <div class="report-section">
            <h2 class="report-section-title">5. VALIDATION ET RÉSULTATS</h2>

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
        <div class="report-section">
            <h2 class="report-section-title">6. CONCLUSION</h2>

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

            <button type="button" class="btn-export btn-export-margin" onclick="exportPDF()">
                📄 <?php echo get_string('export_pdf', 'gestionprojet'); ?>
            </button>
            <p class="export-notice">
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
    // Wait for RequireJS and jQuery
    (function waitRequire() {
        if (typeof require === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(waitRequire, 50);
            return;
        }

        require(['jquery', 'mod_gestionprojet/autosave'], function ($, Autosave) {
            var cmid = <?php echo $cm->id; ?>;
            var step = 6;
            var autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
            var groupid = <?php echo $groupid; ?>;

            // Custom serialization for step 6
            var serializeData = function () {
                var formData = {};

                // Collect regular fields (text inputs, textareas)
                $('#rapportForm').find('input[type="text"], textarea').each(function () {
                    if (this.name) {
                        formData[this.name] = this.value;
                    }
                });

                // Collect authors as JSON array
                // members is defined globally in the script below
                if (typeof members !== 'undefined') {
                    formData['auteurs'] = JSON.stringify(members.filter(function (m) {
                        return m.trim() !== '';
                    }));
                } else {
                    formData['auteurs'] = '[]';
                }

                return formData;
            };

            var isLocked = <?php echo $isLocked ? 'true' : 'false'; ?>;

            // Handle Submission
            $('#submitButton').on('click', function () {
                if (confirm('<?php echo get_string('confirm_submission', 'gestionprojet'); ?>')) {
                    $.ajax({
                        url: '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            groupid: groupid,
                            action: 'submit',
                            sesskey: M.cfg.sesskey
                        },
                        success: function (response) {
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
            $('#revertButton').on('click', function () {
                if (confirm('<?php echo get_string('confirm_revert', 'gestionprojet'); ?>')) {
                    $.ajax({
                        url: '<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            groupid: groupid,
                            action: 'revert',
                            sesskey: M.cfg.sesskey
                        },
                        success: function (response) {
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
                    formSelector: '#rapportForm',
                    serialize: serializeData
                });
            }

            // We need to trigger autosave when members change
            // The Autosave module listens for 'change input' on the form, so this should work automatically if not locked.
        });
    })();

    // Members management
    let members = <?php echo json_encode($auteurs); ?>;
    let isLocked = <?php echo $isLocked ? 'true' : 'false'; ?>;

    if (members.length === 0) {
        members = [''];
    }

    function renderMembers() {
        const container = document.getElementById('membersContainer');
        container.innerHTML = '';

        members.forEach((member, index) => {
            const memberGroup = document.createElement('div');
            memberGroup.className = 'member-group';

            const input = document.createElement('input');
            input.type = 'text';
            input.placeholder = 'Nom et prénom';
            input.value = member;
            if (isLocked) {
                input.disabled = true;
                input.readOnly = true;
            } else {
                input.onchange = (e) => {
                    members[index] = e.target.value;
                    // Trigger auto-save
                    const event = new Event('change', { bubbles: true });
                    document.getElementById('rapportForm').dispatchEvent(event);
                };
            }

            memberGroup.appendChild(input);

            if (!isLocked) {
                if (index === members.length - 1) {
                    const addBtn = document.createElement('button');
                    addBtn.type = 'button';
                    addBtn.className = 'btn-circle btn-add-circle';
                    addBtn.innerHTML = '+';
                    addBtn.title = 'Ajouter un membre';
                    addBtn.onclick = () => {
                        members.push('');
                        renderMembers();
                    };
                    memberGroup.appendChild(addBtn);
                } else {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn-circle btn-remove-circle';
                    removeBtn.innerHTML = '✕';
                    removeBtn.title = 'Retirer ce membre';
                    removeBtn.onclick = () => {
                        if (members.length > 1) {
                            members.splice(index, 1);
                            renderMembers();
                        }
                    };
                    memberGroup.appendChild(removeBtn);
                }
            }

            container.appendChild(memberGroup);
        });
    }

    // Custom data collection for auto-save (auteurs as JSON array)
    window.collectFormData = function () {
        const formData = {};
        const form = document.getElementById('rapportForm');

        // Regular fields (text inputs, textareas)
        form.querySelectorAll('input[type="text"], textarea').forEach(field => {
            if (field.name) {
                formData[field.name] = field.value;
            }
        });

        // Collect authors as JSON array
        formData['auteurs'] = JSON.stringify(members.filter(m => m.trim() !== ''));

        return formData;
    };

    // PDF Export placeholder (will use TCPDF server-side in future)
    function exportPDF() {
        alert('<?php echo get_string('export_pdf_coming_soon', 'gestionprojet'); ?>');
        // TODO: Implement server-side PDF generation with TCPDF
    }

    // Initialize members on page load
    document.addEventListener('DOMContentLoaded', function () {
        renderMembers();
    });
</script>

<?php
echo $OUTPUT->footer();
?>
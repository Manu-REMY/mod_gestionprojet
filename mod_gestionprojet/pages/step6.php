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
require_capability('mod/gestionprojet:submit', $context);

// Get user's group
$groupid = gestionprojet_get_user_group($cm->id, $USER->id);

if (!$groupid) {
    print_error('not_in_group', 'gestionprojet');
}

// Get or create submission
$submission = gestionprojet_get_or_create_submission($gestionprojet->id, $groupid, 'rapport');

// Autosave handled inline at bottom of file
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [[
//     'cmid' => $cm->id,
//     'step' => 6,
//     'interval' => $gestionprojet->autosaveinterval * 1000
// ]]);

echo $OUTPUT->header();

// Get group info
$group = groups_get_group($groupid);

// Parse auteurs (stored as JSON array)
$auteurs = [];
if ($submission->auteurs) {
    $auteurs = json_decode($submission->auteurs, true) ?? [];
}
?>

<style>
.step6-container {
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
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.header-section h2 {
    font-size: 2em;
    margin-bottom: 10px;
}

.header-section p {
    font-size: 1em;
    opacity: 0.9;
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

.info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    color: #1565c0;
}

.info-box p {
    margin: 5px 0;
    font-size: 14px;
}

/* Sections */
.section {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    border-left: 5px solid #667eea;
}

.section-title {
    color: #667eea;
    font-size: 1.3em;
    font-weight: 700;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 600;
    font-size: 15px;
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.3s;
}

.form-group input[type="text"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    background: #f0f3ff;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

/* Members list */
.member-group {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.member-group input {
    flex: 1;
}

.btn-add,
.btn-remove {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    font-size: 18px;
    font-weight: bold;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-add {
    background: #48bb78;
    color: white;
}

.btn-add:hover {
    background: #38a169;
    transform: scale(1.1);
}

.btn-remove {
    background: #dc3545;
    color: white;
    font-size: 16px;
}

.btn-remove:hover {
    background: #c82333;
    transform: scale(1.1);
}

/* Export section */
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
    .header-section {
        padding: 20px;
    }

    .section {
        padding: 15px;
    }
}
</style>

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
                       value="<?php echo s($submission->titre_projet ?? ''); ?>"
                       placeholder="Nom de votre projet">
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
                          placeholder="Décrivez le besoin auquel répond votre projet..."><?php echo s($submission->besoin_projet ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="imperatifs"><?php echo get_string('imperatifs', 'gestionprojet'); ?></label>
                <textarea id="imperatifs" name="imperatifs"
                          placeholder="Listez les contraintes et impératifs du projet..."><?php echo s($submission->imperatifs ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 3: Solutions Choisies -->
        <div class="section">
            <h2 class="section-title">3. SOLUTIONS CHOISIES</h2>

            <div class="form-group">
                <label for="solutions"><?php echo get_string('solutions', 'gestionprojet'); ?></label>
                <textarea id="solutions" name="solutions"
                          placeholder="Décrivez les solutions retenues..."><?php echo s($submission->solutions ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="justification"><?php echo get_string('justification', 'gestionprojet'); ?></label>
                <textarea id="justification" name="justification"
                          placeholder="Justifiez vos choix techniques et stratégiques..."><?php echo s($submission->justification ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 4: Réalisation -->
        <div class="section">
            <h2 class="section-title">4. RÉALISATION</h2>

            <div class="form-group">
                <label for="realisation"><?php echo get_string('realisation', 'gestionprojet'); ?></label>
                <textarea id="realisation" name="realisation"
                          placeholder="Comment avez-vous réalisé votre projet ?"><?php echo s($submission->realisation ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="difficultes"><?php echo get_string('difficultes', 'gestionprojet'); ?></label>
                <textarea id="difficultes" name="difficultes"
                          placeholder="Quelles difficultés avez-vous rencontrées et comment les avez-vous surmontées ?"><?php echo s($submission->difficultes ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 5: Validation et Résultats -->
        <div class="section">
            <h2 class="section-title">5. VALIDATION ET RÉSULTATS</h2>

            <div class="form-group">
                <label for="validation"><?php echo get_string('validation', 'gestionprojet'); ?></label>
                <textarea id="validation" name="validation"
                          placeholder="Décrivez les résultats de vos tests et essais..."><?php echo s($submission->validation ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="ameliorations"><?php echo get_string('ameliorations', 'gestionprojet'); ?></label>
                <textarea id="ameliorations" name="ameliorations"
                          placeholder="Quelles améliorations pourriez-vous apporter au projet ?"><?php echo s($submission->ameliorations ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Section 6: Conclusion -->
        <div class="section">
            <h2 class="section-title">6. CONCLUSION</h2>

            <div class="form-group">
                <label for="bilan"><?php echo get_string('bilan', 'gestionprojet'); ?></label>
                <textarea id="bilan" name="bilan"
                          placeholder="Quel est votre bilan global du projet ? Qu'avez-vous appris ?"><?php echo s($submission->bilan ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="perspectives"><?php echo get_string('perspectives', 'gestionprojet'); ?></label>
                <textarea id="perspectives" name="perspectives"
                          placeholder="Quelles sont les perspectives d'évolution du projet ?"><?php echo s($submission->perspectives ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Export section -->
        <div class="export-section">
            <button type="button" class="btn-export" onclick="exportPDF()">
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

<script>
// Wait for jQuery to be loaded
(function checkJQuery() {
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            const cmid = <?php echo $cm->id; ?>;
            const step = 6;
            const autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
            let autosaveTimer;

            // Autosave on input change
            $('#rapportForm input, #rapportForm textarea').on('input change', function() {
                clearTimeout(autosaveTimer);
                autosaveTimer = setTimeout(function() {
                    autosave();
                }, 2000);
            });

            // Autosave on blur
            $('#rapportForm input, #rapportForm textarea').on('blur', function() {
                autosave();
            });

            // Autosave when page loses focus
            $(window).on('blur', function() {
                autosave();
            });

            // Periodic autosave
            let periodicTimer = setInterval(function() {
                autosave();
            }, autosaveInterval);

            function collectFormData() {
                const formData = {};
                $('#rapportForm input, #rapportForm textarea').each(function() {
                    if (this.name && !this.name.includes('[]')) {
                        formData[this.name] = this.value;
                    }
                });
                formData['auteurs'] = JSON.stringify(members);
                return formData;
            }

            function autosave() {
                const formData = collectFormData();
                console.log('Autosave triggered (step6)', formData);

                $.ajax({
                    url: '<?php echo new moodle_url('/mod/gestionprojet/ajax/autosave.php'); ?>',
                    type: 'POST',
                    data: {
                        cmid: cmid,
                        step: step,
                        groupid: <?php echo $groupid; ?>,
                        data: JSON.stringify(formData)
                    },
                    success: function(response) {
                        console.log('Autosave response:', response);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                    }
                });
            }
        });
    } else {
        setTimeout(checkJQuery, 50);
    }
})();

// Members management
let members = <?php echo json_encode($auteurs); ?>;
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
        input.onchange = (e) => {
            members[index] = e.target.value;
            // Trigger auto-save
            const event = new Event('change', { bubbles: true });
            document.getElementById('rapportForm').dispatchEvent(event);
        };

        memberGroup.appendChild(input);

        if (index === members.length - 1) {
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'btn-add';
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
            removeBtn.className = 'btn-remove';
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

        container.appendChild(memberGroup);
    });
}

// Custom data collection for auto-save (auteurs as JSON array)
window.collectFormData = function() {
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
document.addEventListener('DOMContentLoaded', function() {
    renderMembers();
});
</script>

<?php
echo $OUTPUT->footer();
?>

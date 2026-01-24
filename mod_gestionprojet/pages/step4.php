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
    <?php
    $nav_links = gestionprojet_get_navigation_links($gestionprojet, $cm->id, 'step4');
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
                <button type="button" class="btn-add" onclick="addInteractor()">+ Ajouter un interacteur</button>
            <?php endif; ?>
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
    // Wait for jQuery to be loaded
    // Wait for RequireJS and jQuery
    (function waitRequire() {
        if (typeof require === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(waitRequire, 50);
            return;
        }

        require(['jquery', 'mod_gestionprojet/autosave'], function ($, Autosave) {
            var cmid = <?php echo $cm->id; ?>;
            var step = 4;
            var autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
            var groupid = <?php echo $groupid; ?>;

            // Custom serialization for step 4
            var serializeData = function () {
                var formData = {};

                // Collect standard fields (produit, milieu, fp)
                $('#cdcfForm').find('input, textarea').each(function () {
                    if (this.name && this.name !== 'sesskey') {
                        formData[this.name] = this.value;
                    }
                });

                // Add complex interacteurs data
                formData['interacteurs_data'] = JSON.stringify(interacteurs);

                return formData;
            };

            // Callback after save to ensure diagram is in sync (optional, but good practice)
            var onSave = function (response) {
                // console.log('Step 4 saved', response);
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
                    formSelector: '#cdcfForm',
                    serialize: serializeData,
                    onSave: onSave
                });
            }
        });
    })();

    // Interacteurs data
    let interacteurs = <?php echo json_encode($interacteurs); ?>;

    function renderInteractors() {
        const container = document.getElementById('interactorsContainer');
        container.innerHTML = '';

        interacteurs.forEach((interactor, iIndex) => {
            const item = document.createElement('div');
            item.className = 'interactor-item';

            const header = document.createElement('div');
            header.className = 'interactor-header';

            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.className = 'interactor-name-input';
            nameInput.value = interactor.name;
            nameInput.placeholder = 'Nom de l\'interacteur';
            nameInput.onchange = () => {
                interacteurs[iIndex].name = nameInput.value;
                updateDiagram();
            };
            header.appendChild(nameInput);

            if (iIndex >= 2) {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn-delete-interactor';
                deleteBtn.innerHTML = '🗑️ Supprimer';
                deleteBtn.onclick = () => {
                    interacteurs.splice(iIndex, 1);
                    renderInteractors();
                    updateDiagram();
                };
                header.appendChild(deleteBtn);
            }

            item.appendChild(header);

            // FC list
            const fcList = document.createElement('div');
            fcList.className = 'fc-list';

            interactor.fcs.forEach((fc, fcIndex) => {
                const fcItem = document.createElement('div');
                fcItem.className = 'fc-item';

                const fcHeader = document.createElement('div');
                fcHeader.className = 'fc-header';
                fcHeader.innerHTML = `<span class="fc-label">FC${fcIndex + 1}</span>`;
                fcItem.appendChild(fcHeader);

                const fcValueInput = document.createElement('input');
                fcValueInput.type = 'text';
                fcValueInput.className = 'fc-value-input';
                fcValueInput.value = fc.value;
                fcValueInput.placeholder = 'Description de la fonction contrainte';
                fcValueInput.onchange = () => {
                    interacteurs[iIndex].fcs[fcIndex].value = fcValueInput.value;
                    updateDiagram();
                };
                fcItem.appendChild(fcValueInput);

                // Criteres
                const criteresList = document.createElement('div');
                criteresList.className = 'criteres-list';

                fc.criteres.forEach((critere, cIndex) => {
                    const critereItem = document.createElement('div');
                    critereItem.className = 'critere-item';

                    const critereInput = document.createElement('input');
                    critereInput.type = 'text';
                    critereInput.className = 'critere-input';
                    critereInput.value = critere.critere;
                    critereInput.placeholder = 'Critère d\'appréciation';
                    critereInput.onchange = () => {
                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].critere = critereInput.value;
                    };

                    const niveauInput = document.createElement('input');
                    niveauInput.type = 'text';
                    niveauInput.className = 'critere-input';
                    niveauInput.value = critere.niveau;
                    niveauInput.placeholder = 'Niveau';
                    niveauInput.onchange = () => {
                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].niveau = niveauInput.value;
                    };

                    const uniteInput = document.createElement('input');
                    uniteInput.type = 'text';
                    uniteInput.className = 'critere-input';
                    uniteInput.value = critere.unite;
                    uniteInput.placeholder = 'Unité';
                    uniteInput.onchange = () => {
                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].unite = uniteInput.value;
                    };

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn-remove';
                    removeBtn.innerHTML = '✕';
                    removeBtn.onclick = () => {
                        if (fc.criteres.length > 1) {
                            interacteurs[iIndex].fcs[fcIndex].criteres.splice(cIndex, 1);
                            renderInteractors();
                        }
                    };

                    critereItem.appendChild(critereInput);
                    critereItem.appendChild(niveauInput);
                    critereItem.appendChild(uniteInput);
                    critereItem.appendChild(removeBtn);
                    criteresList.appendChild(critereItem);
                });

                fcItem.appendChild(criteresList);

                const addCritereBtn = document.createElement('button');
                addCritereBtn.type = 'button';
                addCritereBtn.className = 'btn-add';
                addCritereBtn.innerHTML = '+ Critère';
                addCritereBtn.onclick = () => {
                    interacteurs[iIndex].fcs[fcIndex].criteres.push({ critere: '', niveau: '', unite: '' });
                    renderInteractors();
                };
                fcItem.appendChild(addCritereBtn);

                fcList.appendChild(fcItem);
            });

            item.appendChild(fcList);

            const addFCBtn = document.createElement('button');
            addFCBtn.type = 'button';
            addFCBtn.className = 'btn-add';
            addFCBtn.innerHTML = '+ Fonction Contrainte';
            addFCBtn.onclick = () => {
                interacteurs[iIndex].fcs.push({ value: '', criteres: [{ critere: '', niveau: '', unite: '' }] });
                renderInteractors();
                updateDiagram();
            };
            item.appendChild(addFCBtn);

            container.appendChild(item);
        });
    }

    function addInteractor() {
        interacteurs.push({
            name: `Interacteur ${interacteurs.length + 1}`,
            fcs: [{ value: '', criteres: [{ critere: '', niveau: '', unite: '' }] }]
        });
        renderInteractors();
        updateDiagram();
    }

    function updateDiagram() {
        const svg = document.getElementById('interactorDiagram');
        const width = 800;
        const height = 500;
        svg.innerHTML = '';

        const centerX = width / 2;
        const centerY = height / 2;
        const productRadius = 60;

        // Draw interactors in circle
        const validInteractors = interacteurs.filter(i => i.name.trim() !== '');
        const angleStep = (2 * Math.PI) / validInteractors.length;

        validInteractors.forEach((interactor, index) => {
            const angle = (index * angleStep) - Math.PI / 2;
            const distance = 200;
            const x = centerX + distance * Math.cos(angle);
            const y = centerY + distance * Math.sin(angle);

            // Draw line to center
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x);
            line.setAttribute('y1', y);
            line.setAttribute('x2', centerX);
            line.setAttribute('y2', centerY);
            line.setAttribute('stroke', index < 2 ? '#667eea' : '#ff6b6b');
            line.setAttribute('stroke-width', index < 2 ? '3' : '2');
            svg.appendChild(line);

            // Draw interactor circle
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', x);
            circle.setAttribute('cy', y);
            circle.setAttribute('r', '40');
            circle.setAttribute('fill', '#f0f3ff');
            circle.setAttribute('stroke', '#667eea');
            circle.setAttribute('stroke-width', '2');
            svg.appendChild(circle);

            // Draw interactor name
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', x);
            text.setAttribute('y', y);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dominant-baseline', 'middle');
            text.setAttribute('font-size', '12');
            text.setAttribute('fill', '#333');
            text.textContent = interactor.name;
            svg.appendChild(text);

            // Draw FC label if exists
            if (index >= 2 && interactor.fcs[0]?.value) {
                const fcLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                fcLabel.setAttribute('x', (x + centerX) / 2);
                fcLabel.setAttribute('y', (y + centerY) / 2 - 10);
                fcLabel.setAttribute('text-anchor', 'middle');
                fcLabel.setAttribute('font-size', '11');
                fcLabel.setAttribute('fill', '#ff6b6b');
                fcLabel.setAttribute('font-weight', 'bold');
                fcLabel.textContent = 'FC' + (index - 1);
                svg.appendChild(fcLabel);
            }
        });

        // Draw product circle (on top)
        const productCircle = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
        productCircle.setAttribute('cx', centerX);
        productCircle.setAttribute('cy', centerY);
        productCircle.setAttribute('rx', productRadius * 1.5);
        productCircle.setAttribute('ry', productRadius);
        productCircle.setAttribute('fill', '#667eea');
        productCircle.setAttribute('stroke', '#764ba2');
        productCircle.setAttribute('stroke-width', '3');
        svg.appendChild(productCircle);

        // Draw product name
        const productName = document.getElementById('produit').value || 'Produit';
        const productText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        productText.setAttribute('x', centerX);
        productText.setAttribute('y', centerY);
        productText.setAttribute('text-anchor', 'middle');
        productText.setAttribute('dominant-baseline', 'middle');
        productText.setAttribute('font-size', '16');
        productText.setAttribute('font-weight', 'bold');
        productText.setAttribute('fill', 'white');
        productText.textContent = productName;
        svg.appendChild(productText);
    }

    // Custom data collection for auto-save
    window.collectFormData = function () {
        const formData = {};
        const form = document.getElementById('cdcfForm');

        // Regular fields
        form.querySelectorAll('input[type="text"], textarea').forEach(field => {
            if (field.name) {
                formData[field.name] = field.value;
            }
        });

        // Interacteurs as JSON
        formData['interacteurs_data'] = JSON.stringify(interacteurs);

        return formData;
    };

    function exportPDF() {
        alert('<?php echo get_string('export_pdf_coming_soon', 'gestionprojet'); ?>');
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        renderInteractors();
        updateDiagram();

        // Update diagram when product name changes
        document.getElementById('produit').addEventListener('change', updateDiagram);
    });
</script>

<?php
echo $OUTPUT->footer();
?>
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 4 Teacher Correction Model: Cahier des Charges Fonctionnel
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step4', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Get or create teacher model.
$model = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->produit = '';
    $model->milieu = '';
    $model->fp = '';
    $model->interacteurs_data = '';
    $model->ai_instructions = '';
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_cdcf_teacher', $model);
}

// Parse interacteurs.
$interacteurs = [];
if (!empty($model->interacteurs_data)) {
    $interacteurs = json_decode($model->interacteurs_data, true) ?? [];
}
if (empty($interacteurs)) {
    $interacteurs = [
        ['name' => 'Interacteur 1', 'fcs' => [['value' => '', 'criteres' => [['critere' => '', 'niveau' => '', 'unite' => '']]]]],
        ['name' => 'Interacteur 2', 'fcs' => [['value' => '', 'criteres' => [['critere' => '', 'niveau' => '', 'unite' => '']]]]]
    ];
}

echo $OUTPUT->header();
require_once(__DIR__ . '/teacher_model_styles.php');
?>

<style>
    /* Step 4 specific styles - Interactors */
    .interactor-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .interactor-header {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }
    .interactor-name-input {
        flex: 1;
        padding: 10px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        font-weight: 600;
    }
    .fc-item {
        background: white;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        border-left: 4px solid #17a2b8;
    }
    .fc-label {
        display: inline-block;
        background: #17a2b8;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
    }
    .fc-value-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 10px;
    }
    .criteres-list {
        margin-top: 10px;
    }
    .critere-item {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr auto;
        gap: 8px;
        margin-bottom: 8px;
    }
    .critere-input {
        padding: 6px 10px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        font-size: 13px;
    }
    .btn-add, .btn-remove, .btn-delete-interactor {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
    }
    .btn-add {
        background: #e9ecef;
        color: #495057;
        margin-top: 10px;
    }
    .btn-add:hover {
        background: #dee2e6;
    }
    .btn-remove {
        background: #dc3545;
        color: white;
        padding: 4px 10px;
    }
    .btn-delete-interactor {
        background: #ffc107;
        color: #212529;
    }
</style>

<div class="teacher-model-container">

    <div class="back-nav">
        <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>">
            &#8592; <?php echo get_string('correction_models', 'gestionprojet'); ?>
        </a>
    </div>

    <div class="teacher-model-header">
        <h2>&#128203; <?php echo get_string('step4', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <form id="teacherModelForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="4">

        <div class="model-form-section">
            <h3>&#128196; <?php echo get_string('step4', 'gestionprojet'); ?></h3>

            <div class="form-group">
                <label for="produit"><?php echo get_string('produit', 'gestionprojet'); ?></label>
                <input type="text" id="produit" name="produit" value="<?php echo s($model->produit ?? ''); ?>"
                       placeholder="Nom du produit attendu">
            </div>

            <div class="form-group">
                <label for="milieu"><?php echo get_string('milieu', 'gestionprojet'); ?></label>
                <input type="text" id="milieu" name="milieu" value="<?php echo s($model->milieu ?? ''); ?>"
                       placeholder="Milieu d'utilisation attendu">
            </div>

            <div class="form-group">
                <label for="fp"><?php echo get_string('fonction_principale', 'gestionprojet'); ?></label>
                <textarea id="fp" name="fp" placeholder="Fonction principale attendue..."><?php echo s($model->fp ?? ''); ?></textarea>
            </div>
        </div>

        <div class="model-form-section">
            <h3>&#9881; <?php echo get_string('interacteurs', 'gestionprojet'); ?></h3>
            <div id="interactorsContainer"></div>
            <button type="button" class="btn-add" onclick="addInteractor()">+ Ajouter un interacteur</button>
        </div>

        <?php
        $step = 4;
        require_once(__DIR__ . '/teacher_dates_section.php');
        ?>

        <div class="ai-instructions-section">
            <h3>&#129302; <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
            <textarea id="ai_instructions" name="ai_instructions"
                      placeholder="<?php echo get_string('ai_instructions_placeholder', 'gestionprojet'); ?>"><?php echo s($model->ai_instructions ?? ''); ?></textarea>
            <p class="ai-instructions-help">
                <?php echo get_string('ai_instructions_help', 'gestionprojet'); ?>
            </p>
        </div>

        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">
                &#128190; <?php echo get_string('save', 'gestionprojet'); ?>
            </button>
            <div id="saveStatus" class="save-status"></div>
        </div>
    </form>

</div>

<script>
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
        nameInput.onchange = () => { interacteurs[iIndex].name = nameInput.value; };
        header.appendChild(nameInput);

        if (iIndex >= 2) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn-delete-interactor';
            deleteBtn.innerHTML = '&#128465; Supprimer';
            deleteBtn.onclick = () => {
                interacteurs.splice(iIndex, 1);
                renderInteractors();
            };
            header.appendChild(deleteBtn);
        }

        item.appendChild(header);

        const fcList = document.createElement('div');
        fcList.className = 'fc-list';

        interactor.fcs.forEach((fc, fcIndex) => {
            const fcItem = document.createElement('div');
            fcItem.className = 'fc-item';

            const fcHeader = document.createElement('div');
            fcHeader.innerHTML = '<span class="fc-label">FC' + (fcIndex + 1) + '</span>';
            fcItem.appendChild(fcHeader);

            const fcValueInput = document.createElement('input');
            fcValueInput.type = 'text';
            fcValueInput.className = 'fc-value-input';
            fcValueInput.value = fc.value;
            fcValueInput.placeholder = 'Description de la fonction contrainte';
            fcValueInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].value = fcValueInput.value; };
            fcItem.appendChild(fcValueInput);

            const criteresList = document.createElement('div');
            criteresList.className = 'criteres-list';

            fc.criteres.forEach((critere, cIndex) => {
                const critereItem = document.createElement('div');
                critereItem.className = 'critere-item';

                const critereInput = document.createElement('input');
                critereInput.type = 'text';
                critereInput.className = 'critere-input';
                critereInput.value = critere.critere;
                critereInput.placeholder = 'Critere';
                critereInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].critere = critereInput.value; };

                const niveauInput = document.createElement('input');
                niveauInput.type = 'text';
                niveauInput.className = 'critere-input';
                niveauInput.value = critere.niveau;
                niveauInput.placeholder = 'Niveau';
                niveauInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].niveau = niveauInput.value; };

                const uniteInput = document.createElement('input');
                uniteInput.type = 'text';
                uniteInput.className = 'critere-input';
                uniteInput.value = critere.unite;
                uniteInput.placeholder = 'Unite';
                uniteInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].unite = uniteInput.value; };

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-remove';
                removeBtn.innerHTML = '&#10005;';
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
            addCritereBtn.innerHTML = '+ Critere';
            addCritereBtn.onclick = () => {
                interacteurs[iIndex].fcs[fcIndex].criteres.push({critere: '', niveau: '', unite: ''});
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
            interacteurs[iIndex].fcs.push({value: '', criteres: [{critere: '', niveau: '', unite: ''}]});
            renderInteractors();
        };
        item.appendChild(addFCBtn);

        container.appendChild(item);
    });
}

function addInteractor() {
    interacteurs.push({
        name: 'Interacteur ' + (interacteurs.length + 1),
        fcs: [{value: '', criteres: [{critere: '', niveau: '', unite: ''}]}]
    });
    renderInteractors();
}

// Wait for RequireJS
(function waitRequire() {
    if (typeof require === 'undefined') {
        setTimeout(waitRequire, 50);
        return;
    }

    require(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
        $(document).ready(function() {
            renderInteractors();

            var cmid = <?php echo $cm->id; ?>;
            var autosaveInterval = <?php echo ($gestionprojet->autosave_interval ?? 30) * 1000; ?>;

            // Custom serialization for step 4 teacher model
            var serializeData = function() {
                var dates = getDateValues();
                return {
                    produit: document.getElementById('produit').value,
                    milieu: document.getElementById('milieu').value,
                    fp: document.getElementById('fp').value,
                    interacteurs_data: JSON.stringify(interacteurs),
                    ai_instructions: document.getElementById('ai_instructions').value,
                    submission_date: dates.submission_date,
                    deadline_date: dates.deadline_date
                };
            };

            // Initialize Autosave for teacher mode
            Autosave.init({
                cmid: cmid,
                step: 4,
                groupid: 0,
                mode: 'teacher',
                interval: autosaveInterval,
                formSelector: '#teacherModelForm',
                serialize: serializeData
            });

            // Manual save button (in addition to autosave)
            document.getElementById('saveButton').addEventListener('click', function() {
                Autosave.save();
            });
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();

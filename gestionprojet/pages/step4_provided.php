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
 * Step 4 Teacher Consigne (Provided Document): Functional Specifications
 *
 * This page lets the teacher fill in the CDCF consigne (produit, milieu, fp,
 * interacteurs) that will be displayed read-only to students. It does NOT
 * contain AI instructions, submission dates, deadline dates, or a student
 * dashboard — those belong to the correction model page (step4_teacher.php).
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

// Read-only when the user lacks teacher-edit capability — students see the brief but cannot edit it.
$canedit = has_capability('mod/gestionprojet:configureteacherpages', $context);
$readonly = !$canedit;

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'provided']);
$PAGE->set_title(get_string('step4', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

// Get or create the provided consigne record.
$model = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->produit = '';
    $model->milieu = '';
    $model->fp = '';
    $model->interacteurs_data = '';
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_cdcf_provided', $model);
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

// Tabs: teacher gets consignes navigation (1, 3, 2, 4, 9); student gets work navigation (7, 4, 9, 5, 8, 6).
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 4, $canedit ? 'consignes' : 'student')
);
echo $OUTPUT->heading(get_string('step4', 'gestionprojet') . ' — ' . get_string('consigne', 'gestionprojet'));

require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher consigne steps (reuse teacher step navigation order, only for editors).
$stepnav = $canedit ? gestionprojet_get_teacher_step_navigation($gestionprojet, 4) : ['prev' => null, 'next' => null];
?>

<div class="teacher-model-container gp-consigne">

    <form id="teacherModelForm">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="mode" value="provided">

        <div class="model-form-section">
            <h3><?php echo icon::render('clipboard-list', 'sm', 'purple'); ?> <?php echo get_string('step4', 'gestionprojet'); ?></h3>

            <div class="form-group">
                <label for="produit"><?php echo get_string('produit', 'gestionprojet'); ?></label>
                <input type="text" id="produit" name="produit" value="<?php echo s($model->produit ?? ''); ?>"
                       placeholder="<?php echo get_string('produit_placeholder', 'gestionprojet'); ?>">
            </div>

            <div class="form-group">
                <label for="milieu"><?php echo get_string('milieu', 'gestionprojet'); ?></label>
                <input type="text" id="milieu" name="milieu" value="<?php echo s($model->milieu ?? ''); ?>"
                       placeholder="<?php echo get_string('milieu_placeholder', 'gestionprojet'); ?>">
            </div>

            <div class="form-group">
                <label for="fp"><?php echo get_string('fonction_principale', 'gestionprojet'); ?></label>
                <textarea id="fp" name="fp" placeholder="<?php echo get_string('fp_placeholder', 'gestionprojet'); ?>"><?php echo s($model->fp ?? ''); ?></textarea>
            </div>
        </div>

        <div class="model-form-section">
            <h3><?php echo icon::render('settings', 'sm', 'gray'); ?> <?php echo get_string('interacteurs', 'gestionprojet'); ?></h3>
            <div id="interactorsContainer"></div>
            <?php if (!$readonly): ?>
            <button type="button" class="btn-add" onclick="addInteractor()">+ <?php echo get_string('add_interactor', 'gestionprojet'); ?></button>
            <?php endif; ?>
        </div>

        <?php if (!$readonly): ?>
        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">
                <?php echo icon::render('save', 'sm', 'inherit'); ?> <?php echo get_string('save', 'gestionprojet'); ?>
            </button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'provided']); ?>" class="btn-nav btn-prev">
                <?php echo icon::render('chevron-left', 'sm', 'inherit'); ?> <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>" class="btn-nav btn-hub">
                <?php echo get_string('consigne', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'provided']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> <?php echo icon::render('chevron-right', 'sm', 'inherit'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>

</div>

<script>
let interacteurs = <?php echo json_encode($interacteurs); ?>;
var STR_INTERACTOR_NAME = <?php echo json_encode(get_string('interactor_name_placeholder', 'gestionprojet')); ?>;
var STR_DELETE_INTERACTOR = <?php echo json_encode(get_string('delete_interactor', 'gestionprojet')); ?>;
var STR_FC_DESC = <?php echo json_encode(get_string('fc_description_placeholder', 'gestionprojet')); ?>;
var STR_CRITERE = <?php echo json_encode(get_string('critere', 'gestionprojet')); ?>;
var STR_NIVEAU = <?php echo json_encode(get_string('niveau_attendu', 'gestionprojet')); ?>;
var STR_UNITE = <?php echo json_encode(get_string('unite', 'gestionprojet')); ?>;
var STR_ADD_CRITERE = <?php echo json_encode(get_string('add_criterion', 'gestionprojet')); ?>;
var STR_ADD_FC = <?php echo json_encode(get_string('add_constraint_function', 'gestionprojet')); ?>;
var STR_ADD_INTERACTOR = <?php echo json_encode(get_string('add_interactor', 'gestionprojet')); ?>;
var STR_INTERACTEUR = <?php echo json_encode(get_string('interacteurs', 'gestionprojet')); ?>;

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
        nameInput.placeholder = STR_INTERACTOR_NAME;
        nameInput.onchange = () => { interacteurs[iIndex].name = nameInput.value; };
        header.appendChild(nameInput);

        if (iIndex >= 2) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn-delete-interactor';
            deleteBtn.textContent = STR_DELETE_INTERACTOR;
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
            fcValueInput.placeholder = STR_FC_DESC;
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
                critereInput.placeholder = STR_CRITERE;
                critereInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].critere = critereInput.value; };

                const niveauInput = document.createElement('input');
                niveauInput.type = 'text';
                niveauInput.className = 'critere-input';
                niveauInput.value = critere.niveau;
                niveauInput.placeholder = STR_NIVEAU;
                niveauInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].niveau = niveauInput.value; };

                const uniteInput = document.createElement('input');
                uniteInput.type = 'text';
                uniteInput.className = 'critere-input';
                uniteInput.value = critere.unite;
                uniteInput.placeholder = STR_UNITE;
                uniteInput.onchange = () => { interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].unite = uniteInput.value; };

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-remove';
                removeBtn.textContent = '✕';
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
            addCritereBtn.innerHTML = '+ ' + STR_ADD_CRITERE;
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
        addFCBtn.innerHTML = '+ ' + STR_ADD_FC;
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
        name: STR_INTERACTEUR + ' ' + (interacteurs.length + 1),
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

            <?php if ($readonly): ?>
            // Read-only display for students: disable every input/textarea/button inside the form.
            $('#teacherModelForm').find('input, textarea, select, button').prop('disabled', true).attr('readonly', 'readonly');
            <?php else: ?>
            var cmid = <?php echo $cm->id; ?>;
            var autosaveInterval = <?php echo ($gestionprojet->autosave_interval ?? 30) * 1000; ?>;

            // Custom serialization for step 4 provided consigne (no ai_instructions, no dates).
            var serializeData = function() {
                return {
                    produit: document.getElementById('produit').value,
                    milieu: document.getElementById('milieu').value,
                    fp: document.getElementById('fp').value,
                    interacteurs_data: JSON.stringify(interacteurs)
                };
            };

            // Initialize Autosave for provided consigne mode.
            Autosave.init({
                cmid: cmid,
                step: 4,
                groupid: 0,
                mode: 'provided',
                interval: autosaveInterval,
                formSelector: '#teacherModelForm',
                serialize: serializeData
            });

            // Manual save button with redirect to hub.
            document.getElementById('saveButton').addEventListener('click', function() {
                var originalOnSave = Autosave.onSave;
                Autosave.onSave = function(response) {
                    if (originalOnSave) originalOnSave(response);
                    setTimeout(function() {
                        window.location.href = M.cfg.wwwroot + '/mod/gestionprojet/view.php?id=' + cmid;
                    }, 800);
                };
                Autosave.save();
            });
            <?php endif; ?>
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();

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
 * Step 4 Teacher Correction Model: Requirements specification
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step4', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Default AI instructions for CdCF (requirements specification).
$defaultaiinstructions = 'Rôle : Tu es un professeur de Technologie au collège expérimenté et bienveillant. Tu dois évaluer des élèves de niveau 3ème (14-15 ans) sur la rédaction d\'un Cahier des Charges Fonctionnel (CdCF). Tu disposes du modèle rempli par l\'enseignant ci-joint.

Contexte pédagogique : Les élèves doivent passer du "besoin" à la "fonction". Ils confondent souvent la fonction (ce que l\'objet doit faire) avec la solution technique (comment il est fait). Ton but est de corriger cette erreur fréquente tout en validant la structure du CdCF.

Tes critères d\'évaluation sont les suivants :

L\'Analyse Fonctionnelle (Diagramme Pieuvre / Fonctions)

Verbes : Chaque fonction doit commencer par un verbe à l\'infinitif.

Fonction Principale (FP) : Relie-t-elle bien l\'utilisateur et la matière d\'œuvre ?

Fonctions Contraintes (FC) : Sont-elles pertinentes (Énergie, Esthétique, Sécurité, Budget, Environnement) ?

Erreur fatale à surveiller : L\'élève ne doit pas citer de solution technique (Exemple : dire "Doit avoir des roues" est une erreur, il faut dire "Doit permettre de se déplacer").

3. Caractérisation (Critères et Niveaux)

Critères : Sont-ils observables ou mesurables ?

Niveaux : Y a-t-il une valeur cible (ex: "Moins de 20€", "Autonomie > 2h") ? L\'unité utilisée pour le niveau est-elle en concordance avec la fonction mesurée?

4. Forme et Orthographe

La syntaxe est-elle correcte et le vocabulaire technique précis ?

Format de ta réponse : Pour chaque élève, fournis une réponse structurée ainsi :

Note globale indicative (sur 20) ou appréciation générale (Acquis / En cours / Non acquis).

Points forts : Ce qui est bien réussi.

Tableau d\'analyse : Un tableau rapide vérifiant les 3 piliers (Besoin, Fonctions, Critères).

Conseils d\'amélioration : Explique comment transformer une solution en fonction si l\'élève s\'est trompé. Sois encourageant.';

// Get or create teacher model.
$model = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->produit = '';
    $model->milieu = '';
    $model->fp = '';
    $model->interacteurs_data = '';
    $model->ai_instructions = $defaultaiinstructions;
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
        ['name' => get_string('default_interactor', 'gestionprojet', 1), 'fcs' => [['value' => '', 'criteres' => [['critere' => '', 'niveau' => '', 'unite' => '']]]]],
        ['name' => get_string('default_interactor', 'gestionprojet', 2), 'fcs' => [['value' => '', 'criteres' => [['critere' => '', 'niveau' => '', 'unite' => '']]]]]
    ];
}

// Load AMD module for teacher model.
$PAGE->requires->js_call_amd('mod_gestionprojet/teacher_model', 'init', [[
    'cmid' => $cm->id,
    'step' => 4,
    'autosaveInterval' => ($gestionprojet->autosave_interval ?? 30) * 1000,
    'fields' => ['produit', 'milieu', 'fp', 'ai_instructions'],
    'interacteurs' => $interacteurs,
]]);

echo $OUTPUT->header();
require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher steps.
$stepnav = gestionprojet_get_teacher_step_navigation($gestionprojet, 4);
?>

<!-- Top navigation (before the dashboard) -->
<div class="step-navigation step-navigation-top" style="max-width: 1200px; margin: 0 auto 20px auto; padding: 0 20px;">
    <?php if ($stepnav['prev']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
        &#8592; <?php echo get_string('previous', 'gestionprojet'); ?>
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>

    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
        <?php echo get_string('correction_models', 'gestionprojet'); ?>
    </a>

    <?php if ($stepnav['next']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
        <?php echo get_string('next', 'gestionprojet'); ?> &#8594;
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>
</div>

<?php
// Render teacher dashboard for this step.
echo gestionprojet_render_step_dashboard($gestionprojet, 4, $context, $cm->id);
?>

<div class="teacher-model-container">

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
                       placeholder="<?php echo get_string('expected_product_name', 'gestionprojet'); ?>">
            </div>

            <div class="form-group">
                <label for="milieu"><?php echo get_string('milieu', 'gestionprojet'); ?></label>
                <input type="text" id="milieu" name="milieu" value="<?php echo s($model->milieu ?? ''); ?>"
                       placeholder="<?php echo get_string('expected_usage_env', 'gestionprojet'); ?>">
            </div>

            <div class="form-group">
                <label for="fp"><?php echo get_string('fonction_principale', 'gestionprojet'); ?></label>
                <textarea id="fp" name="fp" placeholder="<?php echo get_string('expected_main_function', 'gestionprojet'); ?>"><?php echo s($model->fp ?? ''); ?></textarea>
            </div>
        </div>

        <div class="model-form-section">
            <h3>&#9881; <?php echo get_string('interacteurs', 'gestionprojet'); ?></h3>
            <div id="interactorsContainer"></div>
            <button type="button" class="btn-add" id="addInteractorBtn">+ Ajouter un interacteur</button>
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

        <!-- Step navigation -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
                &#8592; <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
                <?php echo get_string('correction_models', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> &#8594;
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
    </form>

</div>


<?php
echo $OUTPUT->footer();

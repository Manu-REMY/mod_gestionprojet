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
 * Teacher correction models hub page.
 *
 * This page lists all available correction models for steps 4-8.
 * Teachers can define expected answers and AI correction instructions.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']);
$PAGE->set_title(format_string($gestionprojet->name) . ' - ' . get_string('correction_models', 'gestionprojet'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('correction_models', 'gestionprojet'));

// Get existing teacher correction models.
$step4_teacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step5_teacher = $DB->get_record('gestionprojet_essai_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step6_teacher = $DB->get_record('gestionprojet_rapport_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step7_teacher = $DB->get_record('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step8_teacher = $DB->get_record('gestionprojet_carnet_teacher', ['gestionprojetid' => $gestionprojet->id]);

// Define correction model cards.
$models = [
    7 => [
        'icon' => '&#129423;', // Rhino emoji code.
        'title' => get_string('step7', 'gestionprojet'),
        'desc' => get_string('step7_desc', 'gestionprojet'),
        'data' => $step7_teacher,
        'complete' => $step7_teacher && !empty($step7_teacher->aqui),
        'table' => 'besoin_eleve',
    ],
    4 => [
        'icon' => '&#128203;', // Clipboard emoji code.
        'title' => get_string('step4', 'gestionprojet'),
        'desc' => get_string('step4_desc', 'gestionprojet'),
        'data' => $step4_teacher,
        'complete' => $step4_teacher && !empty($step4_teacher->produit),
        'table' => 'cdcf',
    ],
    5 => [
        'icon' => '&#128300;', // Microscope emoji code.
        'title' => get_string('step5', 'gestionprojet'),
        'desc' => get_string('step5_desc', 'gestionprojet'),
        'data' => $step5_teacher,
        'complete' => $step5_teacher && !empty($step5_teacher->objectif),
        'table' => 'essai',
    ],
    8 => [
        'icon' => '&#128211;', // Notebook emoji code.
        'title' => get_string('step8', 'gestionprojet'),
        'desc' => get_string('step8_desc', 'gestionprojet'),
        'data' => $step8_teacher,
        'complete' => $step8_teacher && !empty($step8_teacher->tasks_data),
        'table' => 'carnet',
    ],
    6 => [
        'icon' => '&#128221;', // Memo emoji code.
        'title' => get_string('step6', 'gestionprojet'),
        'desc' => get_string('step6_desc', 'gestionprojet'),
        'data' => $step6_teacher,
        'complete' => $step6_teacher && !empty($step6_teacher->besoins),
        'table' => 'rapport',
    ],
];

// Filter out disabled steps.
foreach ($models as $stepnum => $model) {
    $field = 'enable_step' . $stepnum;
    if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
        unset($models[$stepnum]);
    }
}

?>

<div class="correction-models-hub">

    <div class="back-button">
        <a href="view.php?id=<?php echo $cm->id; ?>">
            &#8592; <?php echo get_string('home', 'gestionprojet'); ?>
        </a>
    </div>

    <div class="hub-description">
        <h3><?php echo get_string('correction_models_hub_title', 'gestionprojet'); ?></h3>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
        <?php if ($gestionprojet->ai_enabled): ?>
            <p style="margin-top: 10px;">
                <strong style="color: #28a745;">&#10003; <?php echo get_string('ai_evaluation_enabled', 'gestionprojet'); ?></strong>
            </p>
        <?php else: ?>
            <p style="margin-top: 10px;">
                <em style="color: #856404;"><?php echo get_string('ai_evaluation_disabled_hint', 'gestionprojet'); ?></em>
            </p>
        <?php endif; ?>
    </div>

    <?php if (empty($models)): ?>
        <div class="alert alert-warning">
            <?php echo get_string('no_student_steps_enabled', 'gestionprojet'); ?>
        </div>
    <?php else: ?>
        <div class="models-grid">
            <?php foreach ($models as $stepnum => $model): ?>
                <div class="model-card <?php echo $model['complete'] ? 'complete' : ''; ?>">

                    <?php if ($gestionprojet->ai_enabled): ?>
                        <span class="ai-status-badge enabled">IA</span>
                    <?php endif; ?>

                    <div class="card-icon"><?php echo $model['icon']; ?></div>
                    <h3 class="card-title"><?php echo $model['title']; ?></h3>
                    <p class="card-description"><?php echo $model['desc']; ?></p>

                    <?php if ($model['complete']): ?>
                        <div class="card-status complete">
                            &#10003; <?php echo get_string('correction_model_complete', 'gestionprojet'); ?>
                        </div>
                    <?php else: ?>
                        <div class="card-status incomplete">
                            &#9203; <?php echo get_string('correction_model_incomplete', 'gestionprojet'); ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Check if AI instructions are set.
                    $hasInstructions = $model['data'] && !empty($model['data']->ai_instructions);
                    if ($hasInstructions): ?>
                        <div class="card-status has-instructions">
                            &#129302; <?php echo get_string('ai_instructions_set', 'gestionprojet'); ?>
                        </div>
                    <?php endif; ?>

                    <a href="view.php?id=<?php echo $cm->id; ?>&step=<?php echo $stepnum; ?>&mode=teacher"
                       class="card-button">
                        <?php echo get_string('correction_model_configure', 'gestionprojet'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
echo $OUTPUT->footer();

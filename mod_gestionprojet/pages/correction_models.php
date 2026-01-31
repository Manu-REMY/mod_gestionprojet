<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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

<style>
    .correction-models-hub {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px 0;
    }

    .hub-description {
        background: linear-gradient(135deg, #e7f3ff 0%, #f0f7ff 100%);
        border-left: 4px solid #17a2b8;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
    }

    .hub-description h3 {
        color: #0056b3;
        margin: 0 0 10px;
    }

    .hub-description p {
        color: #555;
        margin: 0;
        line-height: 1.6;
    }

    .models-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }

    .model-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border-top: 5px solid #17a2b8;
        position: relative;
    }

    .model-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .model-card.complete {
        border-top-color: #28a745;
    }

    .model-card .card-icon {
        font-size: 48px;
        text-align: center;
        margin-bottom: 15px;
    }

    .model-card .card-title {
        font-size: 20px;
        font-weight: bold;
        color: #333;
        margin-bottom: 8px;
        text-align: center;
    }

    .model-card .card-description {
        color: #666;
        line-height: 1.5;
        margin-bottom: 15px;
        text-align: center;
        font-size: 14px;
    }

    .model-card .card-status {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 15px;
    }

    .model-card .card-status.complete {
        background: #d4edda;
        color: #155724;
    }

    .model-card .card-status.incomplete {
        background: #fff3cd;
        color: #856404;
    }

    .model-card .card-status.has-instructions {
        background: #e7f3ff;
        color: #0056b3;
        margin-top: 8px;
    }

    .model-card .card-button {
        display: block;
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        text-align: center;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }

    .model-card .card-button:hover {
        transform: scale(1.02);
        color: white;
        text-decoration: none;
        background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
    }

    .back-button {
        margin-bottom: 20px;
    }

    .back-button a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        background: #f8f9fa;
        color: #17a2b8;
        border: 2px solid #17a2b8;
    }

    .back-button a:hover {
        background: #17a2b8;
        color: white;
        text-decoration: none;
    }

    .ai-status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .ai-status-badge.enabled {
        background: #d4edda;
        color: #155724;
    }

    .ai-status-badge.disabled {
        background: #f8f9fa;
        color: #6c757d;
    }
</style>

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

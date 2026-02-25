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

use mod_gestionprojet\output\icon;

// Page setup.
$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']);
$PAGE->set_title(format_string($gestionprojet->name) . ' - ' . get_string('correction_models', 'gestionprojet'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('correction_models', 'gestionprojet'));

// Get existing teacher correction models.
$step4teacher = $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step5teacher = $DB->get_record('gestionprojet_essai_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step6teacher = $DB->get_record('gestionprojet_rapport_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step7teacher = $DB->get_record('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => $gestionprojet->id]);
$step8teacher = $DB->get_record('gestionprojet_carnet_teacher', ['gestionprojetid' => $gestionprojet->id]);

// Step icons using Lucide SVGs via icon helper.
$stepicons = [];
for ($i = 4; $i <= 8; $i++) {
    $stepicons[$i] = icon::render_step($i, 'xl', 'purple');
}

// Define correction model cards.
$modelsraw = [
    7 => [
        'data' => $step7teacher,
        'complete' => $step7teacher && !empty($step7teacher->aqui),
    ],
    4 => [
        'data' => $step4teacher,
        'complete' => $step4teacher && !empty($step4teacher->produit),
    ],
    5 => [
        'data' => $step5teacher,
        'complete' => $step5teacher && !empty($step5teacher->objectif),
    ],
    8 => [
        'data' => $step8teacher,
        'complete' => $step8teacher && !empty($step8teacher->tasks_data),
    ],
    6 => [
        'data' => $step6teacher,
        'complete' => $step6teacher && !empty($step6teacher->besoins),
    ],
];

// Filter out disabled steps.
foreach ($modelsraw as $stepnum => $model) {
    $field = 'enable_step' . $stepnum;
    if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
        unset($modelsraw[$stepnum]);
    }
}

// Count completion stats.
$totalmodels = count($modelsraw);
$completedmodels = 0;
foreach ($modelsraw as $model) {
    if ($model['complete']) {
        $completedmodels++;
    }
}
$progresspercent = $totalmodels > 0 ? round(($completedmodels / $totalmodels) * 100) : 0;

// Build template models array.
$models = [];
foreach ($modelsraw as $stepnum => $modeldata) {
    $hasinstructions = $modeldata['data'] && !empty($modeldata['data']->ai_instructions);
    $models[] = [
        'stepnum' => $stepnum,
        'icon' => $stepicons[$stepnum],
        'title' => get_string('step' . $stepnum, 'gestionprojet'),
        'description' => get_string('step' . $stepnum . '_desc', 'gestionprojet'),
        'iscomplete' => $modeldata['complete'],
        'hasinstructions' => $hasinstructions,
        'url' => 'view.php?id=' . $cm->id . '&step=' . $stepnum . '&mode=teacher',
    ];
}

// Build template context.
$templatecontext = [
    'cmid' => $cm->id,
    'aienabled' => !empty($gestionprojet->ai_enabled),
    'aiprovider' => ucfirst($gestionprojet->ai_provider ?? ''),
    'totalmodels' => $totalmodels,
    'completedmodels' => $completedmodels,
    'progresspercent' => $progresspercent,
    'hasmodels' => !empty($models),
    'models' => $models,
    // Icon template variables.
    'icon_chevron_left' => icon::render('chevron-left', 'sm', 'inherit'),
    'icon_home' => icon::render('home', 'sm', 'inherit'),
    'icon_check' => icon::render('check-circle', 'sm', 'green'),
    'icon_warning' => icon::render('alert-triangle', 'sm', 'orange'),
    'icon_incomplete' => icon::render('x-circle', 'sm', 'orange'),
    'icon_bot' => icon::render('bot', 'sm', 'purple'),
];

// Render the template using the renderer.
$renderer = $PAGE->get_renderer('mod_gestionprojet');
echo $renderer->render_correction_models($templatecontext);

echo $OUTPUT->footer();

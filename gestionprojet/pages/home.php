<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Home page content for gestionprojet.
 *
 * Builds the context data and renders the home Mustache template.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_gestionprojet\output\icon;

// Check if teacher pages are complete.
$teacherpagescomplete = gestionprojet_teacher_pages_complete($gestionprojet->id);

// Build context data for the template.
$templatecontext = [
    'cmid' => $cm->id,
    'isteacher' => $isteacher,
    'cangrade' => $cangrade,
    'teacherpagescomplete' => $teacherpagescomplete,
    'hasusergroup' => false,
    'nogrouperror' => false,
    'groupnotfounderror' => false,
];

// Step icons using Lucide SVGs via icon helper.
$stepicons = [];
for ($i = 1; $i <= 8; $i++) {
    $stepicons[$i] = icon::render_step($i, 'xl', 'purple');
}

if ($isteacher) {
    // Get teacher pages data.
    $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
    $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
    $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

    $teacherstepsraw = [
        1 => [
            'data' => $description,
            'complete' => $description && !empty($description->intitule),
        ],
        3 => [
            'data' => $planning,
            'complete' => $planning && !empty($planning->projectname),
        ],
        2 => [
            'data' => $besoin,
            'complete' => $besoin && !empty($besoin->aqui),
        ],
    ];

    $teachersteps = [];
    foreach ($teacherstepsraw as $stepnum => $stepdata) {
        $islocked = $stepdata['data'] && $stepdata['data']->locked;
        $teachersteps[] = [
            'stepnum' => $stepnum,
            'icon' => $stepicons[$stepnum],
            'title' => get_string('step' . $stepnum, 'gestionprojet'),
            'description' => get_string('step' . $stepnum . '_desc', 'gestionprojet'),
            'islocked' => $islocked,
            'iscomplete' => !$islocked && $stepdata['complete'],
            'url' => 'view.php?id=' . $cm->id . '&step=' . $stepnum,
            'buttonlabel' => $islocked ? get_string('unlock', 'gestionprojet') : get_string('configure', 'gestionprojet'),
        ];
    }
    $templatecontext['teachersteps'] = $teachersteps;

    // Grading steps.
    if ($cangrade && $teacherpagescomplete) {
        $studentstepsraw = [
            7 => get_string('step7', 'gestionprojet'),
            4 => get_string('step4', 'gestionprojet'),
            5 => get_string('step5', 'gestionprojet'),
            8 => get_string('step8', 'gestionprojet'),
            6 => get_string('step6', 'gestionprojet'),
        ];

        $gradingsteps = [];
        foreach ($studentstepsraw as $stepnum => $title) {
            $field = 'enable_step' . $stepnum;
            if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                continue;
            }
            $gradingsteps[] = [
                'stepnum' => $stepnum,
                'icon' => $stepicons[$stepnum],
                'title' => $title,
                'gradingurl' => $CFG->wwwroot . '/mod/gestionprojet/grading.php?id=' . $cm->id . '&step=' . $stepnum,
            ];
        }
        $templatecontext['gradingsteps'] = $gradingsteps;
    }

    // Dashboard data: submission counts per step.
    $studenttables = [
        7 => 'gestionprojet_besoin_eleve',
        4 => 'gestionprojet_cdcf',
        5 => 'gestionprojet_essai',
        8 => 'gestionprojet_carnet',
        6 => 'gestionprojet_rapport',
    ];

    // Count teacher config completion.
    $teachercomplete = 0;
    foreach ($teacherstepsraw as $stepdata) {
        if ($stepdata['complete']) {
            $teachercomplete++;
        }
    }
    $teachertotal = count($teacherstepsraw);

    // Count correction models completed.
    $modeltables = [
        4 => 'gestionprojet_cdcf_teacher',
        5 => 'gestionprojet_essai_teacher',
        6 => 'gestionprojet_rapport_teacher',
        7 => 'gestionprojet_besoin_eleve_teacher',
        8 => 'gestionprojet_carnet_teacher',
    ];
    $modelscomplete = 0;
    $modelstotal = 0;
    foreach ($modeltables as $mstep => $mtable) {
        $mfield = 'enable_step' . $mstep;
        if (isset($gestionprojet->$mfield) && !$gestionprojet->$mfield) {
            continue;
        }
        $modelstotal++;
        $mrecord = $DB->get_record($mtable, ['gestionprojetid' => $gestionprojet->id]);
        if ($mrecord && !empty($mrecord->ai_instructions)) {
            $modelscomplete++;
        }
    }

    // Count submissions and grades per student step.
    $dashboardsubmissions = [];
    $totalungraded = 0;
    foreach ($studenttables as $dstep => $dtable) {
        $dfield = 'enable_step' . $dstep;
        if (isset($gestionprojet->$dfield) && !$gestionprojet->$dfield) {
            continue;
        }
        $totalsubmitted = $DB->count_records_select(
            $dtable,
            'gestionprojetid = :gid AND status = 1',
            ['gid' => $gestionprojet->id]
        );
        $totalgraded = $DB->count_records_select(
            $dtable,
            'gestionprojetid = :gid AND grade IS NOT NULL',
            ['gid' => $gestionprojet->id]
        );
        $ungraded = max(0, $totalsubmitted - $totalgraded);
        $totalungraded += $ungraded;
        $dashboardsubmissions[] = [
            'stepnum' => $dstep,
            'stepname' => get_string('step' . $dstep, 'gestionprojet'),
            'submitted' => $totalsubmitted,
            'graded' => $totalgraded,
            'ungraded' => $ungraded,
            'hasungraded' => $ungraded > 0,
        ];
    }

    $templatecontext['dashboard'] = [
        'teachercomplete' => $teachercomplete,
        'teachertotal' => $teachertotal,
        'modelscomplete' => $modelscomplete,
        'modelstotal' => $modelstotal,
        'totalungraded' => $totalungraded,
        'hasungraded' => $totalungraded > 0,
        'submissions' => $dashboardsubmissions,
        'hassubmissions' => !empty($dashboardsubmissions),
    ];

} else {
    // Student section.
    if ($teacherpagescomplete && $usergroup == 0) {
        $templatecontext['nogrouperror'] = true;
    } else if ($teacherpagescomplete && $usergroup > 0) {
        // Safe retrieval of group info.
        $groupinfo = $DB->get_record('groups', ['id' => $usergroup]);

        if (!$groupinfo) {
            $templatecontext['groupnotfounderror'] = true;
            $templatecontext['groupnotfounderrorid'] = $usergroup;
        } else {
            $templatecontext['hasusergroup'] = true;
            $templatecontext['usergroupname'] = s($groupinfo->name);

            // Consultation steps (read-only for students).
            $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
            $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
            $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

            $consultationstepsraw = [
                1 => ['data' => $description, 'complete' => $description && !empty($description->intitule)],
                3 => ['data' => $planning, 'complete' => $planning && !empty($planning->projectname)],
                2 => ['data' => $besoin, 'complete' => $besoin && !empty($besoin->aqui)],
            ];

            $consultationsteps = [];
            foreach ($consultationstepsraw as $stepnum => $stepdata) {
                $field = 'enable_step' . $stepnum;
                if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                    continue;
                }
                $consultationsteps[] = [
                    'stepnum' => $stepnum,
                    'icon' => $stepicons[$stepnum],
                    'title' => get_string('step' . $stepnum, 'gestionprojet'),
                    'description' => get_string('step' . $stepnum . '_desc', 'gestionprojet'),
                    'url' => 'view.php?id=' . $cm->id . '&step=' . $stepnum,
                ];
            }
            $templatecontext['consultationsteps'] = $consultationsteps;

            // Student work steps.
            $cdcf = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'cdcf');
            $essai = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'essai');
            $rapport = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'rapport');
            $besoineleve = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'besoin_eleve');
            $carnet = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'carnet');

            $studentstepsraw = [
                7 => ['data' => $besoineleve, 'complete' => $besoineleve && !empty($besoineleve->aqui)],
                4 => ['data' => $cdcf, 'complete' => $cdcf && !empty($cdcf->produit)],
                5 => ['data' => $essai, 'complete' => $essai && !empty($essai->objectif)],
                8 => ['data' => $carnet, 'complete' => $carnet && !empty($carnet->tasks_data)],
                6 => ['data' => $rapport, 'complete' => $rapport && !empty($rapport->besoins)],
            ];

            $studentsteps = [];
            foreach ($studentstepsraw as $stepnum => $stepdata) {
                $field = 'enable_step' . $stepnum;
                if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                    continue;
                }
                $hasgrade = $stepdata['data'] && $stepdata['data']->grade !== null;
                $gradeformatted = '';
                if ($hasgrade) {
                    $gradeformatted = number_format($stepdata['data']->grade, 1) . ' / 20';
                }
                $studentsteps[] = [
                    'stepnum' => $stepnum,
                    'icon' => $stepicons[$stepnum],
                    'title' => get_string('step' . $stepnum, 'gestionprojet'),
                    'description' => get_string('step' . $stepnum . '_desc', 'gestionprojet'),
                    'iscomplete' => $stepdata['complete'],
                    'hasgrade' => $hasgrade,
                    'gradeformatted' => $gradeformatted,
                    'url' => 'view.php?id=' . $cm->id . '&step=' . $stepnum,
                ];
            }
            $templatecontext['studentsteps'] = $studentsteps;
        }
    }
}

// Add icon template variables for the mustache template.
$templatecontext['icon_lock'] = icon::render('lock', 'sm', 'red');
$templatecontext['icon_unlock'] = icon::render('lock-open', 'sm', 'green');
$templatecontext['icon_check'] = icon::render('check-circle', 'sm', 'green');
$templatecontext['icon_incomplete'] = icon::render('x-circle', 'sm', 'orange');
$templatecontext['icon_correction'] = icon::render('file-text', 'xl', 'purple');
$templatecontext['icon_bot'] = icon::render('bot', 'sm', 'purple');
$templatecontext['icon_pencil'] = icon::render('pencil', 'md', 'purple');
$templatecontext['icon_warning'] = icon::render('alert-triangle', 'sm', 'orange');
$templatecontext['icon_award'] = icon::render('award', 'md', 'purple');
$templatecontext['icon_error'] = icon::render('x-circle', 'sm', 'red');
$templatecontext['icon_users'] = icon::render('users', 'sm', 'blue');
$templatecontext['icon_eye'] = icon::render('eye', 'sm', 'gray');
$templatecontext['icon_bar_chart'] = icon::render('bar-chart-3', 'md', 'purple');
$templatecontext['icon_clipboard'] = icon::render('clipboard-list', 'md', 'purple');
$templatecontext['icon_home'] = icon::render('home', 'sm', 'inherit');
$templatecontext['icon_chevron_left'] = icon::render('chevron-left', 'sm', 'inherit');
$templatecontext['icon_settings'] = icon::render('settings', 'sm', 'purple');
$templatecontext['icon_file_text'] = icon::render('file-text', 'sm', 'purple');
$templatecontext['icon_bar_chart_sm'] = icon::render('bar-chart-3', 'sm', 'purple');

// Render the template using the renderer.
$renderer = $PAGE->get_renderer('mod_gestionprojet');
echo $renderer->render_home($templatecontext);

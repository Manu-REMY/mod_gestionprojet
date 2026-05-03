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
    // Build the Gantt dashboard for the teacher home view.
    // Pedagogical column order: [1, 3, 2, 7, 4, 5, 8, 6].
    $ganttorder = [1, 3, 2, 7, 4, 5, 8, 6];
    $teacherdocsteps = [1, 2, 3];
    $studentsteps = [4, 5, 6, 7, 8];

    // Map step number to its data source for "is filled" computation.
    $teacherdocs = [
        1 => $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]),
        2 => $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]),
        3 => $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]),
    ];
    $teachermodels = [
        4 => $DB->get_record('gestionprojet_cdcf_teacher', ['gestionprojetid' => $gestionprojet->id]),
        5 => $DB->get_record('gestionprojet_essai_teacher', ['gestionprojetid' => $gestionprojet->id]),
        6 => $DB->get_record('gestionprojet_rapport_teacher', ['gestionprojetid' => $gestionprojet->id]),
        7 => $DB->get_record('gestionprojet_besoin_eleve_teacher', ['gestionprojetid' => $gestionprojet->id]),
        8 => $DB->get_record('gestionprojet_carnet_teacher', ['gestionprojetid' => $gestionprojet->id]),
    ];
    $studenttables = [
        4 => 'gestionprojet_cdcf',
        5 => 'gestionprojet_essai',
        6 => 'gestionprojet_rapport',
        7 => 'gestionprojet_besoin_eleve',
        8 => 'gestionprojet_carnet',
    ];

    // Helper closures for cell completion logic.
    $teacherdocfilled = function($stepnum) use ($teacherdocs) {
        $rec = $teacherdocs[$stepnum] ?? null;
        if (!$rec) {
            return false;
        }
        if ($stepnum === 1) {
            return !empty($rec->intitule);
        }
        if ($stepnum === 2) {
            return !empty($rec->aqui);
        }
        if ($stepnum === 3) {
            return !empty($rec->projectname);
        }
        return false;
    };
    $teachermodelfilled = function($stepnum) use ($teachermodels, $gestionprojet) {
        $rec = $teachermodels[$stepnum] ?? null;
        if (!$rec) {
            return false;
        }
        // For step 4 in provided mode, completion is based on `produit` rather than `ai_instructions`.
        if ($stepnum === 4 && (int)$gestionprojet->enable_step4 === 2) {
            return !empty($rec->produit);
        }
        return !empty($rec->ai_instructions);
    };

    // Build column headers and cells for each row.
    $ganttcolumns = [];
    $rowdocs = [];
    $rowmodels = [];
    $rowstudent = [];

    $totalconfigured = 0;
    $totalconfigtargets = 0;
    $totalungraded = 0;

    foreach ($ganttorder as $stepnum) {
        $field = 'enable_step' . $stepnum;
        $enableval = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
        $isenabled = ($enableval !== 0);
        $isteacherdocstep = in_array($stepnum, $teacherdocsteps, true);
        $isstudentstep = in_array($stepnum, $studentsteps, true);

        // Column header — shows checkbox only for teacher doc steps (independent control).
        $ganttcolumns[] = [
            'stepnum' => $stepnum,
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'icon' => \mod_gestionprojet\output\icon::render_step($stepnum, 'sm', 'inherit'),
        ];

        // Row 1 cells — only fill for teacher doc steps.
        if ($isteacherdocstep) {
            $iscomplete = $teacherdocfilled($stepnum);
            if ($isenabled) {
                $totalconfigtargets++;
                if ($iscomplete) {
                    $totalconfigured++;
                }
            }
            $rowdocs[] = [
                'stepnum' => $stepnum,
                'isfilled' => true,
                'isenabled' => $isenabled,
                'iscomplete' => $iscomplete,
                'haschexkbox' => true,
                'name' => get_string('step' . $stepnum, 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
            ];
        } else {
            $rowdocs[] = ['isfilled' => false];
        }

        // Row 2 cells — only fill for student steps. Checkbox is here (shared with row 3).
        if ($isstudentstep) {
            $iscomplete = $teachermodelfilled($stepnum);
            $isprovided = ($stepnum === 4 && $enableval === 2);
            if ($isenabled) {
                $totalconfigtargets++;
                if ($iscomplete) {
                    $totalconfigured++;
                }
            }
            $rowmodels[] = [
                'stepnum' => $stepnum,
                'isfilled' => true,
                'isenabled' => $isenabled,
                'iscomplete' => $iscomplete,
                'isprovided' => $isprovided,
                'hascheckbox' => true,
                'name' => get_string('step' . $stepnum, 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum, 'mode' => 'teacher']))->out(false),
            ];
        } else {
            $rowmodels[] = ['isfilled' => false];
        }

        // Row 3 cells — only fill for student steps. No checkbox here (linked via row 2).
        if ($isstudentstep) {
            $table = $studenttables[$stepnum];
            $totalsubmitted = $DB->count_records_select(
                $table,
                'gestionprojetid = :gid AND status = 1',
                ['gid' => $gestionprojet->id]
            );
            $totalgraded = $DB->count_records_select(
                $table,
                'gestionprojetid = :gid AND grade IS NOT NULL',
                ['gid' => $gestionprojet->id]
            );
            $ungraded = max(0, $totalsubmitted - $totalgraded);
            if ($isenabled) {
                $totalungraded += $ungraded;
            }
            $rowstudent[] = [
                'stepnum' => $stepnum,
                'isfilled' => true,
                'isenabled' => $isenabled,
                'submitted' => $totalsubmitted,
                'graded' => $totalgraded,
                'ungraded' => $ungraded,
                'hasungraded' => $ungraded > 0,
                'name' => get_string('step' . $stepnum, 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
            ];
        } else {
            $rowstudent[] = ['isfilled' => false];
        }
    }

    $templatecontext['gantt'] = [
        'columns' => $ganttcolumns,
        'rowdocs' => $rowdocs,
        'rowmodels' => $rowmodels,
        'rowstudent' => $rowstudent,
        'cmid' => $cm->id,
        'sesskey' => sesskey(),
        'summary' => [
            'configured' => $totalconfigured,
            'total' => $totalconfigtargets,
            'ungraded' => $totalungraded,
            'hasungraded' => $totalungraded > 0,
        ],
    ];

    // Load the gantt AMD module for interactivity.
    $PAGE->requires->js_call_amd('mod_gestionprojet/gantt', 'init');

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

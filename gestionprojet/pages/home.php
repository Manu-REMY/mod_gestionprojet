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
for ($i = 1; $i <= 9; $i++) {
    $stepicons[$i] = icon::render_step($i, 'xl', 'purple');
}

if ($isteacher) {
    // Build the Gantt dashboard for the teacher home view.
    // 7-column layout: steps 2 and 7 are merged into a single column.
    $teacherdocsteps = [1, 2, 3];
    $studentsteps = [4, 5, 6, 7, 8, 9];

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
        9 => $DB->get_record('gestionprojet_fast_teacher', ['gestionprojetid' => $gestionprojet->id]),
    ];
    $studenttables = [
        4 => 'gestionprojet_cdcf',
        5 => 'gestionprojet_essai',
        6 => 'gestionprojet_rapport',
        7 => 'gestionprojet_besoin_eleve',
        8 => 'gestionprojet_carnet',
        9 => 'gestionprojet_fast',
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

    $ganttcolumndefs = gestionprojet_get_gantt_column_defs();

    foreach ($ganttcolumndefs as $coldef) {
        $stepnum = $coldef['stepnum'];
        $mergedwith = $coldef['mergedwith'];
        $field = 'enable_step' . $stepnum;
        $enableval = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
        $isenabled = ($enableval !== 0);
        $isteacherdocstep = in_array($stepnum, $teacherdocsteps, true);

        // Column header — uses the primary step's identity (for merged columns, primary is step 2).
        $ganttcolumns[] = [
            'stepnum' => $stepnum,
            'name' => get_string('step' . $stepnum, 'gestionprojet'),
            'icon' => \mod_gestionprojet\output\icon::render_step($stepnum, 'sm', 'inherit'),
        ];

        // Row 1 cells — fill for teacher doc steps (1, 2, 3) and for step 4 in provided mode.
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
                'flag' => 'enable',
                'name' => get_string('step' . $stepnum, 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
            ];
        } else if ($stepnum === 4) {
            // Special case: CDCF row 1 cell controls step4_provided (teacher-provided consigne).
            $providedval = isset($gestionprojet->step4_provided) ? (int)$gestionprojet->step4_provided : 0;
            $providedenabled = ($providedval === 1);
            $providedrec = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
            $providedcomplete = $providedrec && !empty($providedrec->produit);
            if ($providedenabled) {
                $totalconfigtargets++;
                if ($providedcomplete) {
                    $totalconfigured++;
                }
            }
            $rowdocs[] = [
                'stepnum' => 4,
                'isfilled' => true,
                'isenabled' => $providedenabled,
                'iscomplete' => $providedcomplete,
                'flag' => 'provided',
                'name' => get_string('step4', 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'provided']))->out(false),
            ];
        } else if ($stepnum === 9) {
            // Special case: FAST row 1 cell controls step9_provided (teacher-provided consigne).
            $providedval = isset($gestionprojet->step9_provided) ? (int)$gestionprojet->step9_provided : 0;
            $providedenabled = ($providedval === 1);
            $providedrec = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);
            $providedcomplete = false;
            if ($providedrec && !empty($providedrec->data_json)) {
                $decoded = json_decode($providedrec->data_json, true);
                $providedcomplete = is_array($decoded) && !empty($decoded['fonctions']);
            }
            if ($providedenabled) {
                $totalconfigtargets++;
                if ($providedcomplete) {
                    $totalconfigured++;
                }
            }
            $rowdocs[] = [
                'stepnum' => 9,
                'isfilled' => true,
                'isenabled' => $providedenabled,
                'iscomplete' => $providedcomplete,
                'flag' => 'provided',
                'name' => get_string('step9', 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'provided']))->out(false),
            ];
        } else {
            $rowdocs[] = ['isfilled' => false];
        }

        // Determine which step provides rows 2 and 3 (correction model + student activity).
        // For merged columns, the secondary step (mergedwith) drives rows 2-3.
        $secondarystepnum = $mergedwith !== null ? $mergedwith : $stepnum;
        $secondaryfield = 'enable_step' . $secondarystepnum;
        $secondaryenableval = isset($gestionprojet->$secondaryfield) ? (int)$gestionprojet->$secondaryfield : 1;
        $secondaryisenabled = ($secondaryenableval !== 0);
        $secondaryisstudentstep = in_array($secondarystepnum, $studentsteps, true);

        // Row 2 cells — filled when the secondary step is a student step.
        if ($secondaryisstudentstep) {
            $iscomplete = $teachermodelfilled($secondarystepnum);
            $isprovided = ($secondarystepnum === 4 && $secondaryenableval === 2);
            if ($secondaryisenabled) {
                $totalconfigtargets++;
                if ($iscomplete) {
                    $totalconfigured++;
                }
            }
            $rowmodels[] = [
                'stepnum' => $secondarystepnum,
                'isfilled' => true,
                'isenabled' => $secondaryisenabled,
                'iscomplete' => $iscomplete,
                'isprovided' => $isprovided,
                'hascheckbox' => true,
                'name' => get_string('step' . $secondarystepnum, 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $secondarystepnum, 'mode' => 'teacher']))->out(false),
            ];
        } else {
            $rowmodels[] = ['isfilled' => false];
        }

        // Row 3 cells — filled when the secondary step is a student step.
        if ($secondaryisstudentstep) {
            $table = $studenttables[$secondarystepnum];
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
            if ($secondaryisenabled) {
                $totalungraded += $ungraded;
            }
            $rowstudent[] = [
                'stepnum' => $secondarystepnum,
                'isfilled' => true,
                'isenabled' => $secondaryisenabled,
                'submitted' => $totalsubmitted,
                'graded' => $totalgraded,
                'ungraded' => $ungraded,
                'hasungraded' => $ungraded > 0,
                'name' => get_string('step' . $secondarystepnum, 'gestionprojet'),
                'url' => (new \moodle_url('/mod/gestionprojet/grading.php', ['id' => $cm->id, 'step' => $secondarystepnum]))->out(false),
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

            // Consultation row data sources.
            $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
            $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
            $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);
            $cdcfprovided = $DB->get_record('gestionprojet_cdcf_provided', ['gestionprojetid' => $gestionprojet->id]);
            $fastprovided = $DB->get_record('gestionprojet_fast_provided', ['gestionprojetid' => $gestionprojet->id]);

            // Student work data sources.
            $cdcf = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'cdcf');
            $essai = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'essai');
            $rapport = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'rapport');
            $besoineleve = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'besoin_eleve');
            $carnet = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'carnet');
            $fast = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'fast');

            // Helper: column "is enabled" check (enable_stepX != 0).
            $stepenabled = function($stepnum) use ($gestionprojet) {
                $field = 'enable_step' . $stepnum;
                $val = isset($gestionprojet->$field) ? (int)$gestionprojet->$field : 1;
                return $val !== 0;
            };

            // Helper: completion check for consultation steps (1, 2, 3).
            $consultcomplete = function($stepnum) use ($description, $besoin, $planning) {
                if ($stepnum === 1) {
                    return $description && !empty($description->intitule);
                }
                if ($stepnum === 2) {
                    return $besoin && !empty($besoin->aqui);
                }
                if ($stepnum === 3) {
                    return $planning && !empty($planning->projectname);
                }
                return false;
            };

            // Helper: provided-brief completion (steps 4 and 9).
            $providedcomplete = function($stepnum) use ($cdcfprovided, $fastprovided) {
                if ($stepnum === 4) {
                    return $cdcfprovided && !empty($cdcfprovided->produit);
                }
                if ($stepnum === 9) {
                    if (!$fastprovided || empty($fastprovided->data_json)) {
                        return false;
                    }
                    $decoded = json_decode($fastprovided->data_json, true);
                    return is_array($decoded) && !empty($decoded['fonctions']);
                }
                return false;
            };

            // Helper: student-work completion + grade for one step.
            $workdata = function($stepnum) use ($cdcf, $essai, $rapport, $besoineleve, $carnet, $fast) {
                $rec = null;
                $complete = false;
                switch ($stepnum) {
                    case 4: $rec = $cdcf; $complete = $rec && !empty($rec->produit); break;
                    case 5: $rec = $essai; $complete = $rec && !empty($rec->objectif); break;
                    case 6: $rec = $rapport; $complete = $rec && !empty($rec->besoins); break;
                    case 7: $rec = $besoineleve; $complete = $rec && !empty($rec->aqui); break;
                    case 8: $rec = $carnet; $complete = $rec && !empty($rec->tasks_data); break;
                    case 9:
                        $rec = $fast;
                        if ($rec && !empty($rec->data_json)) {
                            $decoded = json_decode($rec->data_json, true);
                            $complete = is_array($decoded) && !empty($decoded['fonctions']);
                        }
                        break;
                }
                $grade = ($rec && $rec->grade !== null) ? (float)$rec->grade : null;
                return ['complete' => $complete, 'grade' => $grade];
            };

            // Build Gantt columns and cells.
            $ganttcolumndefs = gestionprojet_get_gantt_column_defs();
            $ganttcolumns = [];
            $rowconsult = [];
            $rowwork = [];
            $totaldone = 0;
            $totalwork = 0;

            foreach ($ganttcolumndefs as $coldef) {
                $stepnum = $coldef['stepnum'];
                $mergedwith = $coldef['mergedwith'];

                // Column header (uses primary step identity).
                $ganttcolumns[] = [
                    'stepnum' => $stepnum,
                    'name' => get_string('step' . $stepnum, 'gestionprojet'),
                    'icon' => icon::render_step($stepnum, 'sm', 'inherit'),
                ];

                // Row 1 — consultation cell.
                if (in_array($stepnum, [1, 2, 3], true)) {
                    $rowconsult[] = gestionprojet_build_student_gantt_cell([
                        'isfilled' => true,
                        'role' => 'consult',
                        'isenabled' => $stepenabled($stepnum),
                        'iscomplete' => $consultcomplete($stepnum),
                        'name' => get_string('step' . $stepnum, 'gestionprojet'),
                        'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnum]))->out(false),
                        'isprovided' => false,
                    ]);
                } else if ($stepnum === 4) {
                    $providedflag = isset($gestionprojet->step4_provided) ? (int)$gestionprojet->step4_provided : 0;
                    if ($providedflag === 1) {
                        $rowconsult[] = gestionprojet_build_student_gantt_cell([
                            'isfilled' => true,
                            'role' => 'consult',
                            'isenabled' => $stepenabled(4),
                            'iscomplete' => $providedcomplete(4),
                            'name' => get_string('step4', 'gestionprojet'),
                            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 4, 'mode' => 'provided']))->out(false),
                            'isprovided' => true,
                        ]);
                    } else {
                        $rowconsult[] = ['isfilled' => false];
                    }
                } else if ($stepnum === 9) {
                    $providedflag = isset($gestionprojet->step9_provided) ? (int)$gestionprojet->step9_provided : 0;
                    if ($providedflag === 1) {
                        $rowconsult[] = gestionprojet_build_student_gantt_cell([
                            'isfilled' => true,
                            'role' => 'consult',
                            'isenabled' => $stepenabled(9),
                            'iscomplete' => $providedcomplete(9),
                            'name' => get_string('step9', 'gestionprojet'),
                            'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 9, 'mode' => 'provided']))->out(false),
                            'isprovided' => true,
                        ]);
                    } else {
                        $rowconsult[] = ['isfilled' => false];
                    }
                } else {
                    $rowconsult[] = ['isfilled' => false];
                }

                // Row 2 — student work cell. Use mergedwith if set (column 3 → step 7).
                $workstep = $mergedwith !== null ? $mergedwith : $stepnum;
                if (in_array($workstep, [4, 5, 6, 7, 8, 9], true)) {
                    $work = $workdata($workstep);
                    $isenabled = $stepenabled($workstep);
                    if ($isenabled) {
                        $totalwork++;
                        if ($work['complete']) {
                            $totaldone++;
                        }
                    }
                    $rowwork[] = gestionprojet_build_student_gantt_cell([
                        'isfilled' => true,
                        'role' => 'work',
                        'isenabled' => $isenabled,
                        'iscomplete' => $work['complete'],
                        'name' => get_string('step' . $workstep, 'gestionprojet'),
                        'url' => (new \moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $workstep]))->out(false),
                        'grade' => $work['grade'],
                    ]);
                } else {
                    $rowwork[] = ['isfilled' => false];
                }
            }

            $templatecontext['gantt_student'] = [
                'columns' => $ganttcolumns,
                'rowconsult' => $rowconsult,
                'rowwork' => $rowwork,
                'cmid' => $cm->id,
                'summary' => [
                    'done' => $totaldone,
                    'total' => $totalwork,
                    'allcomplete' => $totalwork > 0 && $totaldone === $totalwork,
                ],
            ];
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

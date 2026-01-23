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
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Check if teacher pages are complete
$teacherpagescomplete = gestionprojet_teacher_pages_complete($gestionprojet->id);

?>



<div class="gestionprojet-home">

    <?php if ($isteacher): ?>
        <!-- Section Enseignant -->
        <div class="section-header">
            <h2>📋 <?php echo get_string('navigation_teacher', 'gestionprojet'); ?></h2>
            <p><?php echo get_string('step1_desc', 'gestionprojet') . ', ' .
                get_string('step2_desc', 'gestionprojet') . ', ' .
                get_string('step3_desc', 'gestionprojet'); ?></p>
        </div>

        <div class="gestionprojet-cards">
            <?php
            // Get teacher pages data
            $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
            $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
            $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

            $steps = [
                1 => [
                    'icon' => '📋',
                    'title' => get_string('step1', 'gestionprojet'),
                    'desc' => get_string('step1_desc', 'gestionprojet'),
                    'data' => $description,
                    'complete' => $description && !empty($description->intitule)
                ],
                3 => [
                    'icon' => '📅',
                    'title' => get_string('step3', 'gestionprojet'),
                    'desc' => get_string('step3_desc', 'gestionprojet'),
                    'data' => $planning,
                    'complete' => $planning && !empty($planning->projectname)
                ],
                2 => [
                    'icon' => '🦏',
                    'title' => get_string('step2', 'gestionprojet'),
                    'desc' => get_string('step2_desc', 'gestionprojet'),
                    'data' => $besoin,
                    'complete' => $besoin && !empty($besoin->aqui)
                ]
            ];

            foreach ($steps as $stepnum => $step):
                $islocked = $step['data'] && $step['data']->locked;
                ?>
                <div class="gestionprojet-card teacher <?php echo $islocked ? 'locked' : ''; ?>">
                    <div class="card-icon"><?php echo $step['icon']; ?></div>
                    <h3 class="card-title"><?php echo $step['title']; ?></h3>
                    <p class="card-description"><?php echo $step['desc']; ?></p>

                    <?php if ($islocked): ?>
                        <div class="card-status locked">
                            🔒 <?php echo get_string('locked', 'gestionprojet'); ?>
                        </div>
                    <?php elseif ($step['complete']): ?>
                        <div class="card-status complete">
                            ✓ Complété
                        </div>
                    <?php else: ?>
                        <div class="card-status incomplete">
                            ⏳ À compléter
                        </div>
                    <?php endif; ?>

                    <a href="view.php?id=<?php echo $cm->id; ?>&step=<?php echo $stepnum; ?>" class="card-button">
                        <?php echo $islocked ? get_string('unlock', 'gestionprojet') : 'Configurer'; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Section Correction -->
        <?php if ($cangrade): ?>
            <div class="grading-section">
                <div class="section-header">
                    <h2>✏️ <?php echo get_string('navigation_grading', 'gestionprojet'); ?></h2>
                    <p><?php echo get_string('grading_description', 'gestionprojet'); ?></p>
                </div>

                <?php if (!$teacherpagescomplete): ?>
                    <div class="alert alert-warning">
                        ⚠️ <?php echo get_string('must_complete_teacher_pages', 'gestionprojet'); ?>
                    </div>
                <?php else: ?>
                    <div class="grading-cards">
                        <?php
                        $studentsteps = [
                            7 => ['icon' => '🦏', 'title' => get_string('step7', 'gestionprojet')],
                            4 => ['icon' => '📋', 'title' => get_string('step4', 'gestionprojet')],
                            5 => ['icon' => '🔬', 'title' => get_string('step5', 'gestionprojet')],
                            6 => ['icon' => '📝', 'title' => get_string('step6', 'gestionprojet')]
                        ];

                        // Filter enabled steps
                        foreach ($studentsteps as $k => $v) {
                            $field = 'enable_step' . $k;
                            if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                                unset($studentsteps[$k]);
                            }
                        }

                        foreach ($studentsteps as $stepnum => $step):
                            ?>
                            <div class="grading-card">
                                <div class="card-icon"><?php echo $step['icon']; ?></div>
                                <h4><?php echo $step['title']; ?></h4>
                                <a href="<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/grading.php?id=<?php echo $cm->id; ?>&step=<?php echo $stepnum; ?>"
                                    class="btn btn-primary">
                                    Corriger
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Section Élève -->
        <div class="section-header">
            <h2>🎯 <?php echo get_string('navigation_student', 'gestionprojet'); ?></h2>
            <p><?php echo get_string('student_description', 'gestionprojet'); ?></p>
        </div>

        <?php if (!$teacherpagescomplete): ?>
            <div class="alert alert-warning">
                ⚠️ <?php echo get_string('must_complete_teacher_pages', 'gestionprojet'); ?>
            </div>
        <?php elseif ($usergroup == 0): ?>
            <div class="alert alert-danger">
                ❌ <?php echo get_string('no_groups', 'gestionprojet'); ?>
            </div>
        <?php else: ?>
            <?php
            // Safe retrieval of group info
            $groupinfo = null;
            if ($usergroup > 0) {
                $groupinfo = $DB->get_record('groups', ['id' => $usergroup]);
            }

            // If group not found despite ID existing, fallback to no groups error
            if (!$groupinfo) {
                ?>
                <div class="alert alert-danger">
                    <?php echo get_string('group_not_found_error', 'gestionprojet', $usergroup); ?>
                </div>
            <?php } else { ?>
                <div class="alert alert-info">
                    <?php echo get_string('working_in_group_info', 'gestionprojet'); ?>
                    <strong><?php echo $groupinfo->name; ?></strong>
                </div>

                <div class="gestionprojet-cards">
                    <?php
                    // Consultation steps (read-only for students)
                    $description = $DB->get_record('gestionprojet_description', ['gestionprojetid' => $gestionprojet->id]);
                    $besoin = $DB->get_record('gestionprojet_besoin', ['gestionprojetid' => $gestionprojet->id]);
                    $planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

                    $consultationsteps = [
                        1 => [
                            'icon' => '📋',
                            'title' => get_string('step1', 'gestionprojet'),
                            'desc' => get_string('step1_desc', 'gestionprojet'),
                            'data' => $description,
                            'complete' => $description && !empty($description->intitule)
                        ],
                        3 => [
                            'icon' => '📅',
                            'title' => get_string('step3', 'gestionprojet'),
                            'desc' => get_string('step3_desc', 'gestionprojet'),
                            'data' => $planning,
                            'complete' => $planning && !empty($planning->projectname)
                        ],
                        2 => [
                            'icon' => '🦏',
                            'title' => get_string('step2', 'gestionprojet'),
                            'desc' => get_string('step2_desc', 'gestionprojet'),
                            'data' => $besoin,
                            'complete' => $besoin && !empty($besoin->aqui)
                        ]
                    ];

                    // Filter enabled steps
                    foreach ($consultationsteps as $k => $v) {
                        $field = 'enable_step' . $k;
                        if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                            unset($consultationsteps[$k]);
                        }
                    }

                    foreach ($consultationsteps as $stepnum => $step):
                        ?>
                        <div class="gestionprojet-card student" style="border-top-color: #667eea; opacity: 0.9;">
                            <div class="card-icon"><?php echo $step['icon']; ?></div>
                            <h3 class="card-title"><?php echo $step['title']; ?></h3>
                            <p class="card-description"><?php echo $step['desc']; ?></p>

                            <div class="card-status locked" style="background: #e2e8f0; color: #4a5568;">
                                👁️ <?php echo get_string('consultation_mode', 'gestionprojet'); ?>
                            </div>

                            <a href="view.php?id=<?php echo $cm->id; ?>&step=<?php echo $stepnum; ?>" class="card-button"
                                style="background: #667eea;">
                                <?php echo get_string('consult_button', 'gestionprojet'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>

                    <?php
                    // Get student submissions
                    $cdcf = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'cdcf');
                    $essai = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'essai');
                    $rapport = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'rapport');
                    $besoin_eleve = gestionprojet_get_or_create_submission($gestionprojet, $usergroup, $USER->id, 'besoin_eleve');

                    $steps = [
                        7 => [
                            'icon' => '🦏',
                            'title' => get_string('step7', 'gestionprojet'),
                            'desc' => get_string('step7_desc', 'gestionprojet'),
                            'data' => $besoin_eleve,
                            'complete' => $besoin_eleve && !empty($besoin_eleve->aqui)
                        ],
                        4 => [
                            'icon' => '📋',
                            'title' => get_string('step4', 'gestionprojet'),
                            'desc' => get_string('step4_desc', 'gestionprojet'),
                            'data' => $cdcf,
                            'complete' => $cdcf && !empty($cdcf->produit)
                        ],
                        5 => [
                            'icon' => '🔬',
                            'title' => get_string('step5', 'gestionprojet'),
                            'desc' => get_string('step5_desc', 'gestionprojet'),
                            'data' => $essai,
                            'complete' => $essai && !empty($essai->objectif)
                        ],
                        6 => [
                            'icon' => '📝',
                            'title' => get_string('step6', 'gestionprojet'),
                            'desc' => get_string('step6_desc', 'gestionprojet'),
                            'data' => $rapport,
                            'complete' => $rapport && !empty($rapport->besoins)
                        ]
                    ];

                    // Filter enabled steps
                    foreach ($steps as $k => $v) {
                        $field = 'enable_step' . $k;
                        if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                            unset($steps[$k]);
                        }
                    }

                    foreach ($steps as $stepnum => $step):
                        ?>
                        <div class="gestionprojet-card student">
                            <div class="card-icon"><?php echo $step['icon']; ?></div>
                            <h3 class="card-title"><?php echo $step['title']; ?></h3>
                            <p class="card-description"><?php echo $step['desc']; ?></p>

                            <?php if ($step['complete']): ?>
                                <div class="card-status complete">
                                    ✓ Complété
                                </div>
                            <?php else: ?>
                                <div class="card-status incomplete">
                                    ⏳ À compléter
                                </div>
                            <?php endif; ?>

                            <?php if ($step['data'] && $step['data']->grade !== null): ?>
                                <div class="card-status">
                                    Note: <?php echo number_format($step['data']->grade, 1); ?> / 20
                                </div>
                            <?php endif; ?>

                            <a href="view.php?id=<?php echo $cm->id; ?>&step=<?php echo $stepnum; ?>" class="card-button">
                                <?php echo get_string('work_button', 'gestionprojet'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php } ?>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php
// Note: Autosave JavaScript is now inline in each step file
// Commented out to avoid "No define call" error
// $PAGE->requires->js_call_amd('mod_gestionprojet/autosave', 'init', [
//     'cmid' => $cm->id,
//     'interval' => $gestionprojet->autosave_interval * 1000
// ]);
?>
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

<style>
    .gestionprojet-home {
        max-width: 1400px;
        margin: 0 auto;
    }

    .gestionprojet-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
        margin: 30px 0;
    }

    .gestionprojet-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border-top: 5px solid;
        position: relative;
    }

    .gestionprojet-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .gestionprojet-card.teacher {
        border-top-color: #667eea;
    }

    .gestionprojet-card.student {
        border-top-color: #48bb78;
    }

    .gestionprojet-card.locked {
        opacity: 0.7;
    }

    .card-icon {
        font-size: 48px;
        text-align: center;
        margin-bottom: 15px;
    }

    .card-title {
        font-size: 22px;
        font-weight: bold;
        color: #333;
        margin-bottom: 10px;
        text-align: center;
    }

    .card-description {
        color: #666;
        line-height: 1.6;
        margin-bottom: 15px;
        text-align: center;
    }

    .card-status {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 15px;
    }

    .card-status.complete {
        background: #d4edda;
        color: #155724;
    }

    .card-status.incomplete {
        background: #fff3cd;
        color: #856404;
    }

    .card-status.locked {
        background: #f8d7da;
        color: #721c24;
    }

    .card-button {
        display: block;
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-align: center;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
    }

    .card-button:hover {
        transform: scale(1.02);
        color: white;
        text-decoration: none;
    }

    .card-button:disabled,
    .card-button.disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .section-header {
        margin: 40px 0 20px;
        padding-bottom: 10px;
        border-bottom: 3px solid #667eea;
    }

    .section-header h2 {
        color: #667eea;
        font-size: 28px;
        margin: 0;
    }

    .section-header p {
        color: #666;
        margin: 5px 0 0;
    }

    .grading-section {
        margin-top: 40px;
        padding: 25px;
        background: #f8f9fa;
        border-radius: 12px;
    }

    .grading-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .grading-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .grading-card h4 {
        color: #667eea;
        margin-bottom: 15px;
    }

    .alert-warning {
        margin: 20px 0;
    }
</style>

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
                    <p>Corrigez les travaux des groupes par étape</p>
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
            <p>Complétez votre projet en 3 étapes</p>
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
                    ❌ Erreur : Groupe introuvable (ID: <?php echo $usergroup; ?>)
                </div>
            <?php } else { ?>
                <div class="alert alert-info">
                    👥 Vous travaillez en groupe: <strong><?php echo $groupinfo->name; ?></strong>
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
                                👁️ Consultation
                            </div>

                            <a href="view.php?id=<?php echo $cm->id; ?>&step=<?php echo $stepnum; ?>" class="card-button"
                                style="background: #667eea;">
                                Consulter
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
                                Travailler
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

?>
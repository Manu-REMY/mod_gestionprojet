<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Grading interface with step-by-step navigation.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$step = required_param('step', PARAM_INT); // Step number (4, 5, or 6)
$groupid = optional_param('groupid', 0, PARAM_INT); // Current group being graded

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);



require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/gestionprojet:grade', $context);

// Check if we are in individual submission mode
$isGroupSubmission = $gestionprojet->group_submission;

// Handling navigation items (Groups or Users)
$navItems = [];
$navId = 0; // Current item ID (groupid or userid)

if ($isGroupSubmission) {
    // Get all groups
    $groups = groups_get_all_groups($course->id);
    if (empty($groups)) {
        // If no groups, create a virtual group for "All participants"
        $groups = [0 => (object) ['id' => 0, 'name' => get_string('allparticipants')]];
    }

    // Convert to simplified array for navigation
    foreach ($groups as $g) {
        $navItems[$g->id] = (object) ['id' => $g->id, 'name' => $g->name];
    }

    // Determine current ID
    $groupid = optional_param('groupid', 0, PARAM_INT);
    if (!$groupid && !empty($navItems)) {
        $groupid = array_key_first($navItems);
    }
    $navId = $groupid;
    $userid = 0; // Not relevant for group submission

} else {
    // Get all enrolled users who can submit
    // Equivalent to get_enrolled_users with capability check
    $context = context_module::instance($cm->id);
    $users = get_enrolled_users($context, 'mod/gestionprojet:submit');

    foreach ($users as $u) {
        $navItems[$u->id] = (object) ['id' => $u->id, 'name' => fullname($u)];
    }

    if (empty($navItems)) {
        $navItems[0] = (object) ['id' => 0, 'name' => get_string('nousers', 'gestionprojet')];
    }

    // Determine current ID
    $userid = optional_param('userid', 0, PARAM_INT);
    if (!$userid && !empty($navItems)) {
        $userid = array_key_first($navItems);
    }
    $navId = $userid;
    $groupid = 0; // Not used as primary key, but might be useful for context

    // Try to find group for this user if needed
    if ($userid) {
        $groupid = gestionprojet_get_user_group($cm, $userid);
    }
}

// Navigation logic
$allIds = array_keys($navItems);
$currentindex = array_search($navId, $allIds);

// Get previous and next IDs
$prevId = ($currentindex > 0) ? $allIds[$currentindex - 1] : null;
$nextId = ($currentindex < count($allIds) - 1) ? $allIds[$currentindex + 1] : null;

// Handle form submission
if (optional_param('savegrading', false, PARAM_BOOL) && confirm_sesskey()) {
    $grade = optional_param('grade', null, PARAM_FLOAT);
    $feedback = optional_param('feedback', '', PARAM_RAW);

    $table = '';
    switch ($step) {
        case 4:
            $table = 'gestionprojet_cdcf';
            break;
        case 5:
            $table = 'gestionprojet_essai';
            break;
        case 6:
            $table = 'gestionprojet_rapport';
            break;
        case 7:
            $table = 'gestionprojet_besoin_eleve';
            break;
        case 8:
            $table = 'gestionprojet_carnet';
            break;
    }

    if ($table) {
        // Construct search params based on submission mode
        $params = ['gestionprojetid' => $gestionprojet->id];
        if ($isGroupSubmission) {
            $params['groupid'] = $groupid;
            // Ensure unique constraint logic matches lib.php
        } else {
            $params['userid'] = $userid;
        }

        $record = $DB->get_record($table, $params);

        if ($record) {
            $record->grade = $grade;
            $record->feedback = $feedback;
            $record->timemodified = time();
            $DB->update_record($table, $record);

            // Update gradebook
            gestionprojet_update_grades($gestionprojet, ($isGroupSubmission ? 0 : $userid));

            // Redirect parameters
            $redirectParams = ['id' => $id, 'step' => $step];
            if ($isGroupSubmission) {
                $redirectParams['groupid'] = $nextId ?: $groupid;
            } else {
                $redirectParams['userid'] = $nextId ?: $userid;
            }

            redirect(new moodle_url('/mod/gestionprojet/grading.php', $redirectParams), get_string('grading_save', 'gestionprojet'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

// Page setup
$PAGE->set_url('/mod/gestionprojet/grading.php', ['id' => $cm->id, 'step' => $step, 'groupid' => $groupid]);
$PAGE->set_title(get_string('grading_navigation', 'gestionprojet'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

// Display step selector and group navigation

?>



<div class="grading-navigation">
    <div class="grading-nav-top">
        <div class="step-selector">
            <?php
            $steps = [
                7 => ['icon' => '🦏', 'name' => 'Expression Besoin'],
                4 => ['icon' => '📋', 'name' => 'CDCF'],
                5 => ['icon' => '🔬', 'name' => 'Essai'],
                8 => ['icon' => '📓', 'name' => 'Carnet de bord'],
                6 => ['icon' => '📝', 'name' => 'Rapport']
            ];

            // Filter enabled steps
            foreach ($steps as $k => $stepinfo) {
                $field = 'enable_step' . $k;
                if (isset($gestionprojet->$field) && !$gestionprojet->$field) {
                    unset($steps[$k]);
                }
            }

            // Ensure current step is valid/enabled, otherwise redirect to first available
            if (!array_key_exists($step, $steps) && !empty($steps)) {
                $firststep = array_key_first($steps);
                redirect(new moodle_url('/mod/gestionprojet/grading.php', [
                    'id' => $id,
                    'step' => $firststep,
                    'groupid' => $groupid
                ]));
            }

            foreach ($steps as $stepnum => $stepinfo):
                $active = ($stepnum == $step) ? 'active' : '';
                $url = new moodle_url('/mod/gestionprojet/grading.php', [
                    'id' => $id,
                    'step' => $stepnum,
                    'groupid' => $groupid
                ]);
                ?>
                <a href="<?php echo $url; ?>" class="step-btn <?php echo $active; ?>">
                    <?php echo $stepinfo['icon'] . ' ' . $stepinfo['name']; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="group-navigation">
            <?php
            $paramName = $isGroupSubmission ? 'groupid' : 'userid';
            ?>
            <?php if ($prevId !== null): ?>
                <a href="?id=<?php echo $id; ?>&step=<?php echo $step; ?>&<?php echo $paramName; ?>=<?php echo $prevId; ?>"
                    class="group-nav-btn">
                    ← <?php echo get_string('grading_previous', 'gestionprojet'); ?>
                </a>
            <?php else: ?>
                <button class="group-nav-btn" disabled>
                    ← <?php echo get_string('grading_previous', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <div class="group-info">
                👥 <?php echo $navItems[$navId]->name; ?>
                (<?php echo ($currentindex + 1) . '/' . count($navItems); ?>)
            </div>

            <?php if ($nextId !== null): ?>
                <a href="?id=<?php echo $id; ?>&step=<?php echo $step; ?>&<?php echo $paramName; ?>=<?php echo $nextId; ?>"
                    class="group-nav-btn">
                    <?php echo get_string('grading_next', 'gestionprojet'); ?> →
                </a>
            <?php else: ?>
                <button class="group-nav-btn" disabled>
                    <?php echo get_string('grading_next', 'gestionprojet'); ?> →
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="context-indicator">
        ℹ️ <strong><?php echo get_string('grading_context_maintained', 'gestionprojet'); ?></strong>
        - Vous corrigez l'étape "<?php echo $steps[$step]['name']; ?>" pour tous les groupes.
    </div>
</div>

<?php

// Get submission based on step
$submission = null;
$tablename = '';

// Construct search params based on submission mode
$params = ['gestionprojetid' => $gestionprojet->id];
if ($isGroupSubmission) {
    $params['groupid'] = $groupid;
} else {
    $params['userid'] = $userid;
}

switch ($step) {
    case 1:
        $tablename = 'gestionprojet_description';
        // Description is always by teacher (no group or user distinction in retrieval, locked by teacher)
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id
        ]);
        break;
    case 2:
        $tablename = 'gestionprojet_besoin';
        // Besoin is always by teacher
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id
        ]);
        break;
    case 3:
        $tablename = 'gestionprojet_planning';
        // Planning is always by teacher
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id
        ]);
        break;
    case 4:
        $tablename = 'gestionprojet_cdcf';
        // Student submission (group or individual)
        $submission = $DB->get_record($tablename, $params);
        break;
    case 5:
        $tablename = 'gestionprojet_essai';
        // Student submission (group or individual)
        $submission = $DB->get_record($tablename, $params);
        break;
    case 6:
        $tablename = 'gestionprojet_rapport';
        // Student submission (group or individual)
        $submission = $DB->get_record($tablename, $params);
        break;
    case 7:
        $tablename = 'gestionprojet_besoin_eleve';
        // Student submission (group or individual)
        $submission = $DB->get_record($tablename, $params);
        break;
    case 8:
        $tablename = 'gestionprojet_carnet';
        // Student submission (group or individual)
        $submission = $DB->get_record($tablename, $params);
        break;
}

if (!$submission): ?>
    <div class="submission-content">
        <div class="no-submission">
            <h3>❌ <?php echo get_string('no_submission', 'gestionprojet'); ?></h3>
            <p>Le groupe n'a pas encore commencé cette étape.</p>
        </div>
    </div>
<?php else: ?>
    <div class="submission-content">
        <h2><?php echo $steps[$step]['icon'] . ' ' . get_string('step' . $step, 'gestionprojet'); ?></h2>
        <hr>

        <?php
        // Display submission content based on step
        if ($step == 1): // Description
            ?>
            <div class="field-display">
                <h4><?php echo get_string('intitule', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->intitule ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('niveau', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->niveau ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('support', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->support ?? '', FORMAT_HTML); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('duree', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->duree ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('besoin', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->besoin ?? '', FORMAT_HTML); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('production', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->production ?? '', FORMAT_HTML); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('outils', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->outils ?? '', FORMAT_HTML); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('evaluation', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->evaluation ?? '', FORMAT_HTML); ?></p>
            </div>

        <?php elseif ($step == 2): // Besoin ?>
            <div class="field-display">
                <h4><?php echo get_string('aqui', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->aqui ?? '', FORMAT_PLAIN); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('surquoi', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->surquoi ?? '', FORMAT_PLAIN); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('dansquelbut', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->dansquelbut ?? '', FORMAT_PLAIN); ?></p>
            </div>

        <?php elseif ($step == 3): // Planning ?>
            <div class="field-display">
                <h4><?php echo get_string('projectname', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->projectname ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('startdate', 'gestionprojet'); ?> -
                    <?php echo get_string('enddate', 'gestionprojet'); ?>
                </h4>
                <p>
                    <?php
                    if ($submission->startdate) {
                        echo userdate($submission->startdate, get_string('strftimedaydate'));
                    }
                    ?>
                    -
                    <?php
                    if ($submission->enddate) {
                        echo userdate($submission->enddate, get_string('strftimedaydate'));
                    }
                    ?>
                </p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('vacationzone', 'gestionprojet'); ?></h4>
                <p><?php echo $submission->vacationzone ? get_string('vacationzone_' . strtolower($submission->vacationzone), 'gestionprojet') : get_string('vacationzone_none', 'gestionprojet'); ?>
                </p>
            </div>

        <?php elseif ($step == 4): // CDCF
            // Parse interacteurs data
            $interacteursData = [];
            if ($submission->interacteurs_data) {
                $interacteursData = json_decode($submission->interacteurs_data, true) ?? [];
            }
            ?>
            <div class="field-display">
                <h4><?php echo get_string('produit', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->produit ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('milieu', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->milieu ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('fonction_principale', 'gestionprojet'); ?> (FP)</h4>
                <p><?php echo s($submission->fp ?? ''); ?></p>
            </div>

            <?php if (!empty($interacteursData)): ?>
                <div class="field-display">
                    <h4><?php echo get_string('interacteurs', 'gestionprojet'); ?></h4>
                    <?php foreach ($interacteursData as $idx => $interacteur): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            <strong><?php echo s($interacteur['name'] ?? 'Interacteur ' . ($idx + 1)); ?></strong>
                            <?php if (!empty($interacteur['fcs'])): ?>
                                <ul style="margin-top: 5px; padding-left: 20px;">
                                    <?php foreach ($interacteur['fcs'] as $fcIdx => $fc): ?>
                                        <?php if (!empty($fc['value'])): ?>
                                            <li><strong>FC<?php echo ($fcIdx + 1); ?>:</strong> <?php echo s($fc['value']); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($step == 5): // Essai ?>
            <div class="field-display">
                <h4><?php echo get_string('nom_essai', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->nom_essai ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('date', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->date_essai ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('fonction_service', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->fonction_service ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('niveaux_reussite', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->niveaux_reussite ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('etapes_protocole', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->etapes_protocole ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('materiel_outils', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->materiel_outils ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('resultats_obtenus', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->resultats_obtenus ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('conclusion', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->conclusion ?? ''); ?></p>
            </div>

        <?php elseif ($step == 6): // Rapport ?>
            <?php
            $auteurs = [];
            if ($submission->auteurs) {
                $auteurs = json_decode($submission->auteurs, true) ?? [];
            }
            ?>
            <div class="field-display">
                <h4><?php echo get_string('titre_projet', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->titre_projet ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('auteurs', 'gestionprojet'); ?></h4>
                <p><?php echo !empty($auteurs) ? s(implode(', ', $auteurs)) : 'Non renseigné'; ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('besoin_projet', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->besoin_projet ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('imperatifs', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->imperatifs ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('solutions', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->solutions ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('justification', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->justification ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('realisation', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->realisation ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('difficultes', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->difficultes ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('validation', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->validation ?? ''); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('bilan', 'gestionprojet'); ?></h4>
                <p><?php echo s($submission->bilan ?? ''); ?></p>
            </div>
        <?php elseif ($step == 7): // Besoin Eleve ?>
            <div class="field-display">
                <h4><?php echo get_string('aqui', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->aqui ?? '', FORMAT_PLAIN); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('surquoi', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->surquoi ?? '', FORMAT_PLAIN); ?></p>
            </div>

            <div class="field-display">
                <h4><?php echo get_string('dansquelbut', 'gestionprojet'); ?></h4>
                <p><?php echo format_text($submission->dansquelbut ?? '', FORMAT_PLAIN); ?></p>
            </div>
        <?php elseif ($step == 8): // Carnet de bord ?>
            <?php
            $tasks_data = [];
            if ($submission->tasks_data) {
                $tasks_data = json_decode($submission->tasks_data, true) ?? [];
            }
            ?>
            <div class="field-display">
                <h4><?php echo get_string('step8', 'gestionprojet'); ?></h4>

                <?php if (empty($tasks_data)): ?>
                    <p><em>Aucune entrée dans le carnet de bord.</em></p>
                <?php else: ?>
                    <style>
                        .grading-logbook-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                        }

                        .grading-logbook-table th,
                        .grading-logbook-table td {
                            border: 1px solid #ddd;
                            padding: 8px;
                            text-align: left;
                        }

                        .grading-logbook-table th {
                            background-color: #f2f2f2;
                        }

                        .status-badge {
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 0.9em;
                            display: inline-block;
                        }

                        .status-ahead {
                            background-color: #d1fae5;
                            color: #065f46;
                        }

                        .status-ontime {
                            background-color: #fee2e2;
                            color: #991b1b;
                        }

                        /* Wait, ontime usually green? ontime -> blue/gray? late -> red */
                        /* Fixing colors based on intent */
                        .status-ontime-fixed {
                            background-color: #dbeafe;
                            color: #1e40af;
                        }

                        .status-late {
                            background-color: #fee2e2;
                            color: #991b1b;
                        }
                    </style>
                    <table class="grading-logbook-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Tâches du jour</th>
                                <th>Tâches à venir</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks_data as $task): ?>
                                <?php
                                $statusClass = '';
                                $statusLabel = '';
                                if (($task['status'] ?? '') === 'ahead') {
                                    $statusClass = 'status-ahead';
                                    $statusLabel = 'En avance';
                                } elseif (($task['status'] ?? '') === 'ontime') {
                                    $statusClass = 'status-ontime-fixed';
                                    $statusLabel = 'À l\'heure';
                                } elseif (($task['status'] ?? '') === 'late') {
                                    $statusClass = 'status-late';
                                    $statusLabel = 'En retard';
                                }
                                ?>
                                <tr>
                                    <td><?php echo s($task['date'] ?? ''); ?></td>
                                    <td><?php echo s($task['tasks_today'] ?? ''); ?></td>
                                    <td><?php echo s($task['tasks_future'] ?? ''); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo s($statusLabel); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        // ============================================
        // AI Evaluation Section (Phase 4)
        // ============================================
        // Only show for student steps (4-8) when AI is enabled
        if (in_array($step, [4, 5, 6, 7, 8]) && !empty($gestionprojet->ai_enabled)):
            require_once(__DIR__ . '/classes/ai_evaluator.php');
            require_once(__DIR__ . '/classes/ai_response_parser.php');

            $aievaluation = \mod_gestionprojet\ai_evaluator::get_evaluation(
                $gestionprojet->id,
                $step,
                $submission->id
            );
        ?>
        <div class="ai-evaluation-section" style="margin: 20px 0; padding: 15px; background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%); border-radius: 8px; border: 1px solid #c2d6ff;">
            <h3 style="margin-top: 0; color: #1a56db; display: flex; align-items: center; gap: 10px;">
                🤖 <?php echo get_string('ai_evaluation_section', 'gestionprojet'); ?>
            </h3>

            <?php if (!$aievaluation): ?>
                <!-- No evaluation yet -->
                <div style="padding: 15px; background: #fff; border-radius: 6px; text-align: center;">
                    <p style="color: #6b7280; margin-bottom: 15px;">
                        <?php echo get_string('no_ai_evaluation', 'gestionprojet'); ?>
                    </p>
                    <?php if ($submission->is_submitted): ?>
                        <button type="button" class="btn btn-primary" id="btn-trigger-ai-eval"
                            data-cmid="<?php echo $cm->id; ?>"
                            data-step="<?php echo $step; ?>"
                            data-submissionid="<?php echo $submission->id; ?>">
                            🚀 <?php echo get_string('trigger_ai_evaluation', 'gestionprojet'); ?>
                        </button>
                    <?php else: ?>
                        <p style="color: #9ca3af; font-style: italic;">
                            (La soumission doit être validée pour lancer l'évaluation IA)
                        </p>
                    <?php endif; ?>
                </div>

            <?php elseif ($aievaluation->status === 'pending' || $aievaluation->status === 'processing'): ?>
                <!-- Evaluation in progress -->
                <div style="padding: 15px; background: #fff; border-radius: 6px; text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <div class="spinner-border text-primary" role="status" style="width: 24px; height: 24px;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span style="color: #1a56db; font-weight: 500;">
                            <?php echo get_string('ai_evaluating', 'gestionprojet'); ?>
                        </span>
                    </div>
                    <p style="color: #6b7280; margin-top: 10px; font-size: 0.9em;">
                        Statut: <?php echo $aievaluation->status; ?> | ID: <?php echo $aievaluation->id; ?>
                    </p>
                </div>
                <script>
                    // Auto-refresh to check status
                    setTimeout(function() { location.reload(); }, 10000);
                </script>

            <?php elseif ($aievaluation->status === 'failed'): ?>
                <!-- Evaluation failed -->
                <div style="padding: 15px; background: #fef2f2; border-radius: 6px; border: 1px solid #fecaca;">
                    <div style="display: flex; align-items: center; gap: 10px; color: #dc2626;">
                        <span style="font-size: 1.5em;">⚠️</span>
                        <strong><?php echo get_string('ai_evaluation_failed', 'gestionprojet'); ?></strong>
                    </div>
                    <?php if ($aievaluation->error_message): ?>
                        <p style="color: #991b1b; margin: 10px 0; padding: 10px; background: #fff; border-radius: 4px; font-family: monospace; font-size: 0.85em;">
                            <?php echo s($aievaluation->error_message); ?>
                        </p>
                    <?php endif; ?>
                    <button type="button" class="btn btn-warning" id="btn-retry-ai-eval"
                        data-cmid="<?php echo $cm->id; ?>"
                        data-evaluationid="<?php echo $aievaluation->id; ?>">
                        🔄 <?php echo get_string('retry_evaluation', 'gestionprojet'); ?>
                    </button>
                </div>

            <?php elseif ($aievaluation->status === 'completed' || $aievaluation->status === 'applied'): ?>
                <!-- Evaluation completed - show results -->
                <?php
                $parser = new \mod_gestionprojet\ai_response_parser();
                $result = new \stdClass();
                $result->grade = $aievaluation->parsed_grade;
                $result->max_grade = 20;
                $result->feedback = $aievaluation->parsed_feedback;
                $result->criteria = json_decode($aievaluation->criteria_json, true) ?? [];
                $result->keywords_found = json_decode($aievaluation->keywords_found, true) ?? [];
                $result->keywords_missing = json_decode($aievaluation->keywords_missing, true) ?? [];
                $result->suggestions = json_decode($aievaluation->suggestions, true) ?? [];
                ?>

                <?php if ($aievaluation->status === 'applied'): ?>
                    <div style="padding: 8px 12px; background: #d1fae5; border-radius: 4px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <span>✅</span>
                        <span style="color: #065f46;">
                            <?php echo get_string('ai_evaluation_applied', 'gestionprojet'); ?>
                            <?php if ($aievaluation->applied_at): ?>
                                (<?php echo userdate($aievaluation->applied_at, get_string('strftimedatetime')); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- AI Grade Display -->
                <div style="display: grid; grid-template-columns: auto 1fr; gap: 20px; padding: 15px; background: #fff; border-radius: 6px; margin-bottom: 15px;">
                    <div style="text-align: center; padding: 15px; background: linear-gradient(135deg, #1a56db 0%, #3b82f6 100%); border-radius: 8px; color: white; min-width: 100px;">
                        <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($aievaluation->parsed_grade, 1); ?></div>
                        <div style="font-size: 0.85em; opacity: 0.9;">/20</div>
                        <div style="font-size: 0.75em; margin-top: 5px;"><?php echo get_string('ai_grade', 'gestionprojet'); ?></div>
                    </div>
                    <div>
                        <h4 style="margin-top: 0; color: #374151;"><?php echo get_string('ai_feedback', 'gestionprojet'); ?></h4>
                        <p style="color: #4b5563; line-height: 1.6;"><?php echo nl2br(s($aievaluation->parsed_feedback ?? '')); ?></p>
                    </div>
                </div>

                <!-- Criteria details -->
                <?php if (!empty($result->criteria)): ?>
                <div style="padding: 15px; background: #fff; border-radius: 6px; margin-bottom: 15px;">
                    <h4 style="margin-top: 0; color: #374151;"><?php echo get_string('ai_criteria', 'gestionprojet'); ?></h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($result->criteria as $criterion): ?>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9fafb; border-radius: 4px;">
                                <div style="min-width: 60px; text-align: center; padding: 5px 10px; background: <?php echo ($criterion['score'] ?? 0) >= ($criterion['max'] ?? 5) * 0.7 ? '#d1fae5' : (($criterion['score'] ?? 0) >= ($criterion['max'] ?? 5) * 0.4 ? '#fef3c7' : '#fee2e2'); ?>; border-radius: 4px; font-weight: bold;">
                                    <?php echo ($criterion['score'] ?? 0); ?>/<?php echo ($criterion['max'] ?? 5); ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong><?php echo s($criterion['name'] ?? ''); ?></strong>
                                    <?php if (!empty($criterion['comment'])): ?>
                                        <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 0.9em;"><?php echo s($criterion['comment']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Keywords found/missing -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <?php if (!empty($result->keywords_found)): ?>
                    <div style="padding: 15px; background: #d1fae5; border-radius: 6px;">
                        <h4 style="margin-top: 0; color: #065f46; font-size: 0.95em;"><?php echo get_string('ai_keywords_found', 'gestionprojet'); ?></h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                            <?php foreach ($result->keywords_found as $keyword): ?>
                                <span style="padding: 4px 8px; background: #a7f3d0; border-radius: 4px; font-size: 0.85em; color: #065f46;">✓ <?php echo s($keyword); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($result->keywords_missing)): ?>
                    <div style="padding: 15px; background: #fee2e2; border-radius: 6px;">
                        <h4 style="margin-top: 0; color: #991b1b; font-size: 0.95em;"><?php echo get_string('ai_keywords_missing', 'gestionprojet'); ?></h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                            <?php foreach ($result->keywords_missing as $keyword): ?>
                                <span style="padding: 4px 8px; background: #fecaca; border-radius: 4px; font-size: 0.85em; color: #991b1b;">✗ <?php echo s($keyword); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Suggestions -->
                <?php if (!empty($result->suggestions)): ?>
                <div style="padding: 15px; background: #fff; border-radius: 6px; margin-bottom: 15px;">
                    <h4 style="margin-top: 0; color: #374151;"><?php echo get_string('ai_suggestions', 'gestionprojet'); ?></h4>
                    <ul style="margin: 0; padding-left: 20px; color: #4b5563;">
                        <?php foreach ($result->suggestions as $suggestion): ?>
                            <li style="margin-bottom: 5px;"><?php echo s($suggestion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Token usage info -->
                <?php
                $tokensUsed = ($aievaluation->prompt_tokens ?? 0) + ($aievaluation->completion_tokens ?? 0);
                if ($tokensUsed > 0):
                ?>
                <div style="text-align: right; color: #9ca3af; font-size: 0.8em; margin-bottom: 15px;">
                    <?php echo get_string('ai_tokens_used', 'gestionprojet'); ?>: <?php echo number_format($tokensUsed); ?>
                    (<?php echo s($aievaluation->provider); ?>/<?php echo s($aievaluation->model); ?>)
                </div>
                <?php endif; ?>

                <!-- Action buttons -->
                <?php if ($aievaluation->status !== 'applied'): ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-success" id="btn-apply-ai-grade"
                        data-cmid="<?php echo $cm->id; ?>"
                        data-evaluationid="<?php echo $aievaluation->id; ?>"
                        data-grade="<?php echo $aievaluation->parsed_grade; ?>"
                        data-feedback="<?php echo htmlspecialchars($aievaluation->parsed_feedback ?? '', ENT_QUOTES); ?>">
                        ✅ <?php echo get_string('apply_ai_grade', 'gestionprojet'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btn-apply-modified"
                        onclick="document.getElementById('grade').value = '<?php echo $aievaluation->parsed_grade; ?>'; document.getElementById('feedback').value = <?php echo json_encode($aievaluation->parsed_feedback ?? ''); ?>; document.getElementById('grade').focus();">
                        ✏️ <?php echo get_string('apply_with_modifications', 'gestionprojet'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-retry-ai-eval"
                        data-cmid="<?php echo $cm->id; ?>"
                        data-evaluationid="<?php echo $aievaluation->id; ?>">
                        🔄 <?php echo get_string('retry_evaluation', 'gestionprojet'); ?>
                    </button>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Trigger AI evaluation
            var triggerBtn = document.getElementById('btn-trigger-ai-eval');
            if (triggerBtn) {
                triggerBtn.addEventListener('click', function() {
                    var btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '⏳ En cours...';

                    fetch('<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/evaluate.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'id=' + btn.dataset.cmid + '&step=' + btn.dataset.step + '&submissionid=' + btn.dataset.submissionid + '&sesskey=<?php echo sesskey(); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Erreur: ' + (data.message || 'Échec du déclenchement'));
                            btn.disabled = false;
                            btn.innerHTML = '🚀 <?php echo get_string('trigger_ai_evaluation', 'gestionprojet'); ?>';
                        }
                    })
                    .catch(err => {
                        alert('Erreur réseau');
                        btn.disabled = false;
                        btn.innerHTML = '🚀 <?php echo get_string('trigger_ai_evaluation', 'gestionprojet'); ?>';
                    });
                });
            }

            // Apply AI grade
            var applyBtn = document.getElementById('btn-apply-ai-grade');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    var btn = this;
                    btn.disabled = true;

                    fetch('<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/apply_ai_grade.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'id=' + btn.dataset.cmid + '&evaluationid=' + btn.dataset.evaluationid + '&sesskey=<?php echo sesskey(); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Erreur: ' + (data.message || 'Échec de l\'application'));
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        alert('Erreur réseau');
                        btn.disabled = false;
                    });
                });
            }

            // Retry AI evaluation
            var retryBtn = document.getElementById('btn-retry-ai-eval');
            if (retryBtn) {
                retryBtn.addEventListener('click', function() {
                    var btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '⏳ En cours...';

                    fetch('<?php echo $CFG->wwwroot; ?>/mod/gestionprojet/ajax/evaluate.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'id=<?php echo $cm->id; ?>&step=<?php echo $step; ?>&submissionid=<?php echo $submission->id; ?>&retry=1&sesskey=<?php echo sesskey(); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Erreur: ' + (data.message || 'Échec'));
                            btn.disabled = false;
                            btn.innerHTML = '🔄 <?php echo get_string('retry_evaluation', 'gestionprojet'); ?>';
                        }
                    })
                    .catch(err => {
                        alert('Erreur réseau');
                        btn.disabled = false;
                        btn.innerHTML = '🔄 <?php echo get_string('retry_evaluation', 'gestionprojet'); ?>';
                    });
                });
            }
        });
        </script>
        <?php endif; ?>

        <!-- Grading Form -->
        <div class="grading-form">
            <h3>✏️ Évaluation</h3>
            <form method="post" action="">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                <input type="hidden" name="savegrading" value="1">

                <div class="form-group">
                    <label for="grade"><?php echo get_string('grading_grade', 'gestionprojet'); ?></label>
                    <input type="number" name="grade" id="grade" min="0" max="20" step="0.5"
                        value="<?php echo $submission->grade ?? ''; ?>" placeholder="Note sur 20">
                </div>

                <div class="form-group">
                    <label for="feedback"><?php echo get_string('grading_feedback', 'gestionprojet'); ?></label>
                    <textarea name="feedback" id="feedback"
                        placeholder="Vos commentaires pour le groupe..."><?php echo s($submission->feedback ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-save-grade">
                    💾 <?php echo get_string('grading_save', 'gestionprojet'); ?>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
?>?>
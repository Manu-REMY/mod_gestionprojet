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

// Get all groups
$groups = groups_get_all_groups($course->id);

if (empty($groups)) {
    // If no groups, create a virtual group for "All participants"
    $groups = [0 => (object) ['id' => 0, 'name' => get_string('allparticipants')]];
}

// If no group specified, use first group
if (!$groupid) {
    $groupid = array_key_first($groups);
}

// Get group submissions
$allgroups = array_values($groups);
$currentindex = array_search($groupid, array_column($allgroups, 'id'));

// Get previous and next group IDs
$prevgroupid = ($currentindex > 0) ? $allgroups[$currentindex - 1]->id : null;
$nextgroupid = ($currentindex < count($allgroups) - 1) ? $allgroups[$currentindex + 1]->id : null;

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
    }

    if ($table) {
        $record = $DB->get_record($table, [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $groupid
        ]);

        if ($record) {
            $record->grade = $grade;
            $record->feedback = $feedback;
            $record->timemodified = time();
            $DB->update_record($table, $record);

            // Update gradebook
            gestionprojet_update_grades($gestionprojet);

            redirect(new moodle_url('/mod/gestionprojet/grading.php', [
                'id' => $id,
                'step' => $step,
                'groupid' => $nextgroupid ?: $groupid
            ]), get_string('grading_save', 'gestionprojet'), null, \core\output\notification::NOTIFY_SUCCESS);
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

<style>
    .grading-navigation {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .grading-nav-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .step-selector {
        display: flex;
        gap: 10px;
    }

    .step-btn {
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        border: 2px solid #dee2e6;
        background: white;
        color: #495057;
    }

    .step-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }

    .step-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        text-decoration: none;
    }

    .group-navigation {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .group-nav-btn {
        padding: 8px 16px;
        border-radius: 8px;
        background: white;
        border: 2px solid #667eea;
        color: #667eea;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }

    .group-nav-btn:hover:not(:disabled) {
        background: #667eea;
        color: white;
        text-decoration: none;
    }

    .group-nav-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .group-info {
        font-size: 18px;
        font-weight: bold;
        color: #333;
        padding: 8px 16px;
        background: white;
        border-radius: 8px;
        border: 2px solid #dee2e6;
    }

    .submission-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .grading-form {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 12px;
        margin-top: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        font-size: 16px;
    }

    .form-group textarea {
        min-height: 120px;
        resize: vertical;
    }

    .btn-save-grade {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-save-grade:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    }

    .context-indicator {
        background: #d1ecf1;
        border: 2px solid #bee5eb;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        color: #0c5460;
    }

    .no-submission {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .field-display {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .field-display h4 {
        margin: 0 0 10px 0;
        color: #667eea;
        font-size: 16px;
    }

    .field-display p {
        margin: 0;
        color: #333;
        white-space: pre-wrap;
    }
</style>

<div class="grading-navigation">
    <div class="grading-nav-top">
        <div class="step-selector">
            <?php
            $steps = [
                4 => ['icon' => '📋', 'name' => 'CDCF'],
                5 => ['icon' => '🔬', 'name' => 'Essai'],
                6 => ['icon' => '📝', 'name' => 'Rapport']
            ];

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
            <?php if ($prevgroupid): ?>
                <a href="?id=<?php echo $id; ?>&step=<?php echo $step; ?>&groupid=<?php echo $prevgroupid; ?>"
                    class="group-nav-btn">
                    ← <?php echo get_string('grading_previous', 'gestionprojet'); ?>
                </a>
            <?php else: ?>
                <button class="group-nav-btn" disabled>
                    ← <?php echo get_string('grading_previous', 'gestionprojet'); ?>
                </button>
            <?php endif; ?>

            <div class="group-info">
                👥 <?php echo $groups[$groupid]->name; ?>
                (<?php echo ($currentindex + 1) . '/' . count($groups); ?>)
            </div>

            <?php if ($nextgroupid): ?>
                <a href="?id=<?php echo $id; ?>&step=<?php echo $step; ?>&groupid=<?php echo $nextgroupid; ?>"
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

switch ($step) {
    case 1:
        $tablename = 'gestionprojet_description';
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id
        ]);
        break;
    case 2:
        $tablename = 'gestionprojet_besoin';
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id
        ]);
        break;
    case 3:
        $tablename = 'gestionprojet_planning';
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id
        ]);
        break;
    case 4:
        $tablename = 'gestionprojet_cdcf';
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $groupid
        ]);
        break;
    case 5:
        $tablename = 'gestionprojet_essai';
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $groupid
        ]);
        break;
    case 6:
        $tablename = 'gestionprojet_rapport';
        $submission = $DB->get_record($tablename, [
            'gestionprojetid' => $gestionprojet->id,
            'groupid' => $groupid
        ]);
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
?>
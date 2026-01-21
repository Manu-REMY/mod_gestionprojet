<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Grading page for teacher.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$step = required_param('step', PARAM_INT); // Step to grade (4, 5, or 6)

$cm = get_coursemodule_from_id('gestionprojet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/gestionprojet:grade', $context);

// Page setup
$url = new moodle_url('/mod/gestionprojet/pages/grading.php', ['id' => $id, 'step' => $step]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('grading', 'gestionprojet'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Get titles based on step
$steptitles = [
    4 => get_string('step4', 'gestionprojet'),
    5 => get_string('step5', 'gestionprojet'),
    6 => get_string('step6', 'gestionprojet')
];
$steptitle = $steptitles[$step] ?? 'Unknown Step';

echo $OUTPUT->header();
?>

<style>
    .grading-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .grading-header {
        margin-bottom: 30px;
        border-bottom: 2px solid #ddd;
        padding-bottom: 15px;
    }

    .group-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 20px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .group-name {
        font-size: 1.2rem;
        font-weight: bold;
        color: #333;
    }

    .submission-status {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .status-draft {
        background: #fee;
        color: #844;
    }

    .status-submitted {
        background: #efe;
        color: #060;
    }

    .grading-form {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 6px;
        margin-top: 15px;
    }

    .form-row {
        margin-bottom: 15px;
    }

    .form-row label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .btn-view {
        background: #667eea;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
    }

    .btn-view:hover {
        opacity: 0.9;
        color: white;
        text-decoration: none;
    }

    .btn-save {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-save:hover {
        background: #218838;
    }
</style>

<div class="grading-container">
    <div class="grading-header">
        <h2>✏️ Correction :
            <?php echo $steptitle; ?>
        </h2>
        <a href="home.php?id=<?php echo $cm->id; ?>">← Retour à l'accueil</a>
    </div>

    <?php
    // Get all groups
    $groups = groups_get_all_groups($course->id, 0, $cm->groupingid);
    if (empty($groups)) {
        echo $OUTPUT->notification(get_string('no_groups', 'gestionprojet'), 'warning');
    } else {
        foreach ($groups as $group) {
            // Get submission
            $tablename = '';
            if ($step == 4)
                $tablename = 'gestionprojet_cdcf';
            elseif ($step == 5)
                $tablename = 'gestionprojet_essai';
            elseif ($step == 6)
                $tablename = 'gestionprojet_rapport';

            $submission = $DB->get_record($tablename, ['gestionprojetid' => $gestionprojet->id, 'groupid' => $group->id]);

            $isSubmitted = $submission && $submission->status == 1;
            $statusLabel = $isSubmitted ? 'Soumis' : 'Brouillon';
            $statusClass = $isSubmitted ? 'status-submitted' : 'status-draft';
            $currentGrade = $submission->grade ?? '';
            $currentFeedback = $submission->feedback ?? '';

            // View link passes groupid parameter (step pages updated to handle this)
            $viewLink = "step{$step}.php?id={$cm->id}&groupid={$group->id}";
            ?>
            <div class="group-card" id="group-<?php echo $group->id; ?>">
                <div class="group-header">
                    <div class="group-name">
                        <?php echo format_string($group->name); ?>
                    </div>
                    <div class="submission-status <?php echo $statusClass; ?>">
                        <?php echo $statusLabel; ?>
                        <?php if ($isSubmitted)
                            echo ' - ' . userdate($submission->timesubmitted); ?>
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <a href="<?php echo $viewLink; ?>" target="_blank" class="btn-view">
                        👁️ Voir le travail
                    </a>
                </div>

                <div class="grading-form">
                    <form onsubmit="saveGrade(event, <?php echo $group->id; ?>)">
                        <div class="form-row">
                            <label>Note sur 20</label>
                            <input type="number" step="0.5" min="0" max="20" name="grade" class="form-control"
                                style="width: 100px;" value="<?php echo $currentGrade; ?>">
                        </div>
                        <div class="form-row">
                            <label>Feedback</label>
                            <textarea name="feedback" class="form-control"
                                rows="3"><?php echo s($currentFeedback); ?></textarea>
                        </div>
                        <button type="submit" class="btn-save">Enregistrer la note</button>
                    </form>
                </div>
            </div>
            <?php
        }
    }
    ?>
</div>

<script>
    function saveGrade(e, groupid) {
        e.preventDefault();
        var form = e.target;
        var grade = form.grade.value;
        var feedback = form.feedback.value;
        var btn = form.querySelector('button');
        var originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = 'Enregistrement...';

        // Using fetch for simplicity or M.core_ajax if available, but fetch checks credentials?
        // Using jQuery as moodle usually loads it.

        require(['jquery'], function ($) {
            $.ajax({
                url: '../ajax/grade.php',
                type: 'POST',
                data: {
                    id: <?php echo $cm->id; ?>,
                step: <?php echo $step; ?>,
                    groupid: groupid,
                        grade: grade,
                            feedback: feedback,
                                sesskey: M.cfg.sesskey
        },
            success: function (res) {
                if (res.success) {
                    btn.innerHTML = '✅ Enregistré';
                    setTimeout(function () {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }, 2000);
                } else {
                    alert('Erreur: ' + res.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            },
            error: function () {
                alert('Erreur technique');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    });
}
</script>

<?php
echo $OUTPUT->footer();
?>
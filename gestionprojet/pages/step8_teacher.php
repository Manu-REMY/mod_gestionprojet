<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Step 8 Teacher Correction Model: Logbook
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$PAGE->set_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => 8, 'mode' => 'teacher']);
$PAGE->set_title(get_string('step8', 'gestionprojet') . ' - ' . get_string('correction_models', 'gestionprojet'));

// Get or create teacher model.
$model = $DB->get_record('gestionprojet_carnet_teacher', ['gestionprojetid' => $gestionprojet->id]);
if (!$model) {
    $model = new stdClass();
    $model->gestionprojetid = $gestionprojet->id;
    $model->timecreated = time();
    $model->timemodified = time();
    $model->id = $DB->insert_record('gestionprojet_carnet_teacher', $model);
    $model = $DB->get_record('gestionprojet_carnet_teacher', ['id' => $model->id]);
}

// Parse tasks data.
$tasks = [];
if (!empty($model->tasks_data)) {
    $tasks = json_decode($model->tasks_data, true) ?? [];
}
if (empty($tasks)) {
    $tasks = [
        ['date' => '', 'tasks_today' => '', 'tasks_future' => '', 'status' => 'ontime']
    ];
}

// Load AMD module for teacher model.
$PAGE->requires->js_call_amd('mod_gestionprojet/teacher_model', 'init', [[
    'cmid' => $cm->id,
    'step' => 8,
    'autosaveInterval' => ($gestionprojet->autosave_interval ?? 30) * 1000,
    'fields' => ['ai_instructions'],
    'tasks' => $tasks,
    'strings' => [
        'logbook_status_ahead' => get_string('logbook_status_ahead', 'gestionprojet'),
        'logbook_status_ontime' => get_string('logbook_status_ontime', 'gestionprojet'),
        'logbook_status_late' => get_string('logbook_status_late', 'gestionprojet'),
        'logbook_tasks_today' => get_string('logbook_tasks_today', 'gestionprojet'),
        'logbook_tasks_future' => get_string('logbook_tasks_future', 'gestionprojet'),
    ],
]]);

echo $OUTPUT->header();
require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher steps.
$stepnav = gestionprojet_get_teacher_step_navigation($gestionprojet, 8);
?>

<!-- Top navigation (before the dashboard) -->
<div class="step-navigation step-navigation-top" style="max-width: 1200px; margin: 0 auto 20px auto; padding: 0 20px;">
    <?php if ($stepnav['prev']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
        &#8592; <?php echo get_string('previous', 'gestionprojet'); ?>
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>

    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
        <?php echo get_string('correction_models', 'gestionprojet'); ?>
    </a>

    <?php if ($stepnav['next']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
        <?php echo get_string('next', 'gestionprojet'); ?> &#8594;
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>
</div>

<?php
// Render teacher dashboard for this step.
echo gestionprojet_render_step_dashboard($gestionprojet, 8, $context, $cm->id);
?>

<div class="teacher-model-container">

    <div class="teacher-model-header">
        <h2>&#128211; <?php echo get_string('step8', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <form id="teacherModelForm">
        <input type="hidden" name="step" value="8">

        <div class="model-form-section">
            <h3>&#128211; <?php echo get_string('step8', 'gestionprojet'); ?></h3>
            <p style="color: #666; margin-bottom: 20px;">
                Definissez ici un exemple de carnet de bord attendu. L'IA utilisera ce modele pour evaluer les productions des eleves.
            </p>

            <div id="logbookContainer"></div>
            <button type="button" class="btn-add-entry" id="addEntryBtn">+ <?php echo get_string('logbook_add_line', 'gestionprojet'); ?></button>
        </div>

        <?php
        $step = 8;
        require_once(__DIR__ . '/teacher_dates_section.php');
        ?>

        <div class="ai-instructions-section">
            <h3>&#129302; <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
            <textarea id="ai_instructions" name="ai_instructions"
                      placeholder="<?php echo get_string('ai_instructions_placeholder', 'gestionprojet'); ?>"><?php echo s($model->ai_instructions ?? ''); ?></textarea>
            <p class="ai-instructions-help"><?php echo get_string('ai_instructions_help', 'gestionprojet'); ?></p>
        </div>

        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton">&#128190; <?php echo get_string('save', 'gestionprojet'); ?></button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
                &#8592; <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
                <?php echo get_string('correction_models', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> &#8594;
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
    </form>
</div>


<?php
echo $OUTPUT->footer();

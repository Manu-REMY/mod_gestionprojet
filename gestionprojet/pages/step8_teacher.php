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

use mod_gestionprojet\output\icon;

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

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'mod_gestionprojet/step_tabs',
    gestionprojet_build_step_tabs($gestionprojet, $cm->id, 8, 'correction')
);

echo $OUTPUT->heading(
    get_string('step8', 'gestionprojet')
    . ' <span class="gp-correction-badge">' . get_string('correction_model_badge', 'gestionprojet') . '</span>',
    2
);

require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher steps.
$stepnav = gestionprojet_get_teacher_step_navigation($gestionprojet, 8);
?>

<?php
// Render teacher dashboard for this step.
echo gestionprojet_render_step_dashboard($gestionprojet, 8, $context, $cm->id);
?>

<div class="teacher-model-container gp-correction">

    <div class="teacher-model-header">
        <h2><?php echo icon::render('book-open', 'sm', 'purple'); ?> <?php echo get_string('step8', 'gestionprojet'); ?> - <?php echo get_string('correction_models', 'gestionprojet'); ?></h2>
        <p><?php echo get_string('correction_models_hub_desc', 'gestionprojet'); ?></p>
    </div>

    <?php if ($gestionprojet->ai_enabled): ?>
        <div class="ai-status-indicator enabled">
            <?php echo icon::render('check-circle', 'sm', 'green'); ?> <?php echo get_string('ai_evaluation_enabled', 'gestionprojet'); ?>
            (<?php echo ucfirst($gestionprojet->ai_provider); ?>)
        </div>
    <?php else: ?>
        <div class="ai-status-indicator disabled">
            <?php echo icon::render('alert-triangle', 'sm', 'orange'); ?> <?php echo get_string('ai_evaluation_disabled_hint', 'gestionprojet'); ?>
        </div>
    <?php endif; ?>

    <form id="teacherModelForm">
        <input type="hidden" name="step" value="8">

        <div class="model-form-section">
            <h3><?php echo icon::render('book-open', 'sm', 'purple'); ?> <?php echo get_string('step8', 'gestionprojet'); ?></h3>
            <p style="color: #666; margin-bottom: 20px;">
                <?php echo get_string('logbook_model_desc', 'gestionprojet'); ?>
            </p>

            <div id="logbookContainer"></div>
            <button type="button" id="addEntryBtn" class="btn-add-entry">+ <?php echo get_string('logbook_add_line', 'gestionprojet'); ?></button>
        </div>

        <?php
        $step = 8;
        require_once(__DIR__ . '/teacher_dates_section.php');
        ?>

        <div class="ai-instructions-section">
            <h3><?php echo icon::render('bot', 'sm', 'purple'); ?> <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
            <div class="ai-instructions-actions" id="aiInstructionsActions"></div>
            <textarea id="ai_instructions" name="ai_instructions"
                      placeholder="<?php echo get_string('ai_instructions_placeholder', 'gestionprojet'); ?>"><?php echo s($model->ai_instructions ?? ''); ?></textarea>
            <p class="ai-instructions-help"><?php echo get_string('ai_instructions_help', 'gestionprojet'); ?></p>
        </div>

        <div class="save-section">
            <button type="button" class="btn-save" id="saveButton"><?php echo icon::render('save', 'sm', 'inherit'); ?> <?php echo get_string('save', 'gestionprojet'); ?></button>
            <div id="saveStatus" class="save-status"></div>
        </div>

        <!-- Step navigation -->
        <div class="step-navigation">
            <?php if ($stepnav['prev']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
                <?php echo icon::render('chevron-left', 'sm', 'inherit'); ?> <?php echo get_string('previous', 'gestionprojet'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]); ?>" class="btn-nav btn-hub">
                <?php echo get_string('correction_models', 'gestionprojet'); ?>
            </a>

            <?php if ($stepnav['next']): ?>
            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['next'], 'mode' => 'teacher']); ?>" class="btn-nav btn-next">
                <?php echo get_string('next', 'gestionprojet'); ?> <?php echo icon::render('chevron-right', 'sm', 'inherit'); ?>
            </a>
            <?php else: ?>
            <div class="nav-spacer"></div>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php
$PAGE->requires->js_call_amd('mod_gestionprojet/step8_teacher_init', 'init', [[
    'cmid' => (int)$cm->id,
    'autosaveInterval' => (int)($gestionprojet->autosave_interval ?? 30) * 1000,
    'tasks' => $tasks,
    'aiEnabled' => (bool)$gestionprojet->ai_enabled,
    'defaultText' => get_string('ai_instructions_default_step8', 'gestionprojet'),
]]);

echo $OUTPUT->footer();

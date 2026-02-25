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
require_once(__DIR__ . '/teacher_model_styles.php');

// Get navigation for teacher steps.
$stepnav = gestionprojet_get_teacher_step_navigation($gestionprojet, 8);
?>

<!-- Top navigation (before the dashboard) -->
<div class="step-navigation step-navigation-top" style="max-width: 1200px; margin: 0 auto 20px auto; padding: 0 20px;">
    <?php if ($stepnav['prev']): ?>
    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'step' => $stepnav['prev'], 'mode' => 'teacher']); ?>" class="btn-nav btn-prev">
        <?php echo icon::render('chevron-left', 'sm', 'inherit'); ?> <?php echo get_string('previous', 'gestionprojet'); ?>
    </a>
    <?php else: ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>

    <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
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

<?php
// Render teacher dashboard for this step.
echo gestionprojet_render_step_dashboard($gestionprojet, 8, $context, $cm->id);
?>

<div class="teacher-model-container">

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
            <button type="button" class="btn-add-entry" onclick="addEntry()">+ <?php echo get_string('logbook_add_line', 'gestionprojet'); ?></button>
        </div>

        <?php
        $step = 8;
        require_once(__DIR__ . '/teacher_dates_section.php');
        ?>

        <div class="ai-instructions-section">
            <h3><?php echo icon::render('bot', 'sm', 'purple'); ?> <?php echo get_string('ai_instructions', 'gestionprojet'); ?></h3>
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

            <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>" class="btn-nav btn-hub">
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

<script>
let tasks = <?php echo json_encode($tasks); ?>;

function renderEntries() {
    const container = document.getElementById('logbookContainer');
    container.innerHTML = '';

    tasks.forEach((task, index) => {
        const entry = document.createElement('div');
        entry.className = 'logbook-entry';

        entry.innerHTML = `
            <div class="logbook-entry-header">
                <input type="date" value="${task.date || ''}" onchange="tasks[${index}].date = this.value">
                <select onchange="tasks[${index}].status = this.value">
                    <option value="ahead" ${task.status === 'ahead' ? 'selected' : ''}><?php echo get_string('logbook_status_ahead', 'gestionprojet'); ?></option>
                    <option value="ontime" ${task.status === 'ontime' ? 'selected' : ''}><?php echo get_string('logbook_status_ontime', 'gestionprojet'); ?></option>
                    <option value="late" ${task.status === 'late' ? 'selected' : ''}><?php echo get_string('logbook_status_late', 'gestionprojet'); ?></option>
                </select>
                ${tasks.length > 1 ? `<button type="button" class="btn-remove-entry" onclick="removeEntry(${index})">\u2715</button>` : ''}
            </div>
            <div class="logbook-row">
                <div>
                    <label style="font-weight:600; margin-bottom:5px; display:block;"><?php echo get_string('logbook_tasks_today', 'gestionprojet'); ?></label>
                    <textarea onchange="tasks[${index}].tasks_today = this.value">${task.tasks_today || ''}</textarea>
                </div>
                <div>
                    <label style="font-weight:600; margin-bottom:5px; display:block;"><?php echo get_string('logbook_tasks_future', 'gestionprojet'); ?></label>
                    <textarea onchange="tasks[${index}].tasks_future = this.value">${task.tasks_future || ''}</textarea>
                </div>
            </div>
        `;

        container.appendChild(entry);
    });
}

function addEntry() {
    tasks.push({date: '', tasks_today: '', tasks_future: '', status: 'ontime'});
    renderEntries();
}

function removeEntry(index) {
    if (tasks.length > 1) {
        tasks.splice(index, 1);
        renderEntries();
    }
}

// Wait for RequireJS
(function waitRequire() {
    if (typeof require === 'undefined') {
        setTimeout(waitRequire, 50);
        return;
    }

    require(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
        $(document).ready(function() {
            renderEntries();

            var cmid = <?php echo $cm->id; ?>;
            var autosaveInterval = <?php echo ($gestionprojet->autosave_interval ?? 30) * 1000; ?>;

            // Custom serialization for step 8 teacher model
            var serializeData = function() {
                var dates = getDateValues();
                return {
                    tasks_data: JSON.stringify(tasks),
                    ai_instructions: document.getElementById('ai_instructions').value,
                    submission_date: dates.submission_date,
                    deadline_date: dates.deadline_date
                };
            };

            // Initialize Autosave for teacher mode
            Autosave.init({
                cmid: cmid,
                step: 8,
                groupid: 0,
                mode: 'teacher',
                interval: autosaveInterval,
                formSelector: '#teacherModelForm',
                serialize: serializeData
            });

            // Manual save button with redirect to hub
            document.getElementById('saveButton').addEventListener('click', function() {
                var originalOnSave = Autosave.onSave;
                Autosave.onSave = function(response) {
                    if (originalOnSave) originalOnSave(response);
                    setTimeout(function() {
                        window.location.href = M.cfg.wwwroot + '/mod/gestionprojet/view.php?id=' + cmid + '&page=correctionmodels';
                    }, 800);
                };
                Autosave.save();
            });
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();

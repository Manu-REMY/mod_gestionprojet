<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 8 Teacher Correction Model: Carnet de bord
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

echo $OUTPUT->header();
require_once(__DIR__ . '/teacher_model_styles.php');
?>

<style>
    .logbook-entry {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid #17a2b8;
    }
    .logbook-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .logbook-entry-header input[type="date"] {
        padding: 8px 12px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
    }
    .logbook-entry-header select {
        padding: 8px 12px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
    }
    .logbook-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 10px;
    }
    .logbook-row textarea {
        width: 100%;
        min-height: 80px;
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        resize: vertical;
    }
    .btn-remove-entry {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    .btn-add-entry {
        background: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        margin-top: 15px;
    }
</style>

<div class="teacher-model-container">

    <div class="back-nav">
        <a href="<?php echo new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id, 'page' => 'correctionmodels']); ?>">
            &#8592; <?php echo get_string('correction_models', 'gestionprojet'); ?>
        </a>
    </div>

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
            <button type="button" class="btn-add-entry" onclick="addEntry()">+ <?php echo get_string('logbook_add_line', 'gestionprojet'); ?></button>
        </div>

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
                ${tasks.length > 1 ? `<button type="button" class="btn-remove-entry" onclick="removeEntry(${index})">&#128465;</button>` : ''}
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
                return {
                    tasks_data: JSON.stringify(tasks),
                    ai_instructions: document.getElementById('ai_instructions').value
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

            // Manual save button
            document.getElementById('saveButton').addEventListener('click', function() {
                Autosave.save();
            });
        });
    });
})();
</script>

<?php
echo $OUTPUT->footer();

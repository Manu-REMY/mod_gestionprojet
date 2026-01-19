<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 3: Planification - Teacher configuration page
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Check if this file is included by view.php or accessed directly
if (!defined('MOODLE_INTERNAL')) {
    // Standalone mode - requires config
    require_once(__DIR__ . '/../../../config.php');
    require_once(__DIR__ . '/../lib.php');

    $cmid = required_param('cmid', PARAM_INT);
    $cm = get_coursemodule_from_id('gestionprojet', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $gestionprojet = $DB->get_record('gestionprojet', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);
    require_capability('mod/gestionprojet:configureteacherpages', $context);

    $PAGE->set_url(new moodle_url('/mod/gestionprojet/pages/step3.php', ['cmid' => $cm->id]));
    $PAGE->set_title(get_string('step3', 'gestionprojet'));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_context($context);
} else {
    // Included mode - variables already set by view.php
    require_capability('mod/gestionprojet:configureteacherpages', $context);
}

// Get existing data
$planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

// Display grade and feedback if available (for teachers viewing their own work)
$showGrade = false;

echo $OUTPUT->header();

// Navigation buttons
echo '<div class="navigation-container" style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">';
echo '<div>';
echo '<a href="../view.php?id=' . $cm->id . '" class="btn btn-secondary">← ' . get_string('back') . '</a>';
echo '</div>';
echo '<div>';
echo '<a href="../view.php?id=' . $cm->id . '" class="btn btn-primary">' . get_string('finish') . ' ✓</a>';
echo '</div>';
echo '</div>';

echo '<h2>📋 ' . get_string('step3', 'gestionprojet') . '</h2>';

// Description
echo '<div class="alert alert-info">';
echo '<p>' . get_string('planning_description', 'gestionprojet') . '</p>';
echo '</div>';

if ($showGrade && isset($planning->grade)): ?>
    <div class="alert alert-success">
        <h4><?php echo get_string('grade'); ?>: <?php echo number_format($planning->grade, 2); ?>/20</h4>
        <?php if (!empty($planning->feedback)): ?>
            <p><strong><?php echo get_string('feedback'); ?>:</strong><br><?php echo format_text($planning->feedback, FORMAT_HTML); ?></p>
        <?php endif; ?>
    </div>
<?php endif;

// Lock status (teacher can lock their own configuration)
$locked = $planning ? $planning->locked : 0;
?>

<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0;"><?php echo get_string('project_planning', 'gestionprojet'); ?></h4>
            <div>
                <label class="switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
                    <input type="checkbox" id="lockToggle" <?php echo $locked ? 'checked' : ''; ?>>
                    <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: 0.4s; border-radius: 34px;"></span>
                </label>
                <span style="margin-left: 10px;"><?php echo get_string('lock_page', 'gestionprojet'); ?></span>
            </div>
        </div>

        <form id="planningForm">
            <div class="form-group mb-3">
                <label for="projectname"><?php echo get_string('projectname', 'gestionprojet'); ?></label>
                <input type="text" class="form-control" id="projectname" name="projectname"
                    value="<?php echo $planning ? s($planning->projectname) : ''; ?>"
                    <?php echo $locked ? 'readonly' : ''; ?>>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="startdate"><?php echo get_string('startdate', 'gestionprojet'); ?></label>
                        <input type="date" class="form-control" id="startdate" name="startdate"
                            value="<?php echo $planning && $planning->startdate ? date('Y-m-d', $planning->startdate) : ''; ?>"
                            <?php echo $locked ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="enddate"><?php echo get_string('enddate', 'gestionprojet'); ?></label>
                        <input type="date" class="form-control" id="enddate" name="enddate"
                            value="<?php echo $planning && $planning->enddate ? date('Y-m-d', $planning->enddate) : ''; ?>"
                            <?php echo $locked ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="vacationzone"><?php echo get_string('vacationzone', 'gestionprojet'); ?></label>
                        <select class="form-control" id="vacationzone" name="vacationzone" <?php echo $locked ? 'disabled' : ''; ?>>
                            <option value="" <?php echo !$planning || !$planning->vacationzone ? 'selected' : ''; ?>><?php echo get_string('vacationzone_none', 'gestionprojet'); ?></option>
                            <option value="A" <?php echo $planning && $planning->vacationzone === 'A' ? 'selected' : ''; ?>><?php echo get_string('vacationzone_a', 'gestionprojet'); ?></option>
                            <option value="B" <?php echo $planning && $planning->vacationzone === 'B' ? 'selected' : ''; ?>><?php echo get_string('vacationzone_b', 'gestionprojet'); ?></option>
                            <option value="C" <?php echo $planning && $planning->vacationzone === 'C' ? 'selected' : ''; ?>><?php echo get_string('vacationzone_c', 'gestionprojet'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <h5 class="mt-4 mb-3"><?php echo get_string('task_durations', 'gestionprojet'); ?></h5>
            <p class="text-muted small"><?php echo get_string('hours_per_week_info', 'gestionprojet'); ?></p>

            <?php
            $taskColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
            for ($i = 1; $i <= 5; $i++):
                $fieldName = 'task' . $i . '_hours';
                $value = $planning ? $planning->$fieldName : 0;
            ?>
                <div class="task-input mb-3" style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="width: 30px; height: 30px; background-color: <?php echo $taskColors[$i-1]; ?>; border-radius: 5px; margin-right: 15px;"></div>
                    <div style="flex: 1; font-weight: 500;"><?php echo get_string('task' . $i, 'gestionprojet'); ?></div>
                    <input type="number" class="form-control" id="task<?php echo $i; ?>_hours" name="task<?php echo $i; ?>_hours"
                        min="0" step="0.5" value="<?php echo $value; ?>" style="width: 100px;"
                        <?php echo $locked ? 'readonly' : ''; ?>>
                    <span class="ml-2"><?php echo get_string('hours', 'gestionprojet'); ?></span>
                </div>
            <?php endfor; ?>

            <div class="mt-4 p-3" style="background: #f8f9fa; border-radius: 10px;">
                <h5 class="mb-3"><?php echo get_string('timeline_preview', 'gestionprojet'); ?></h5>
                <div id="timelineContainer" style="background: #e9ecef; height: 60px; border-radius: 10px; position: relative;">
                    <svg id="timelineSVG" style="width: 100%; height: 60px;"></svg>
                </div>
                <div id="totalInfo" class="mt-2 text-muted small"></div>
            </div>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>

<style>
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider:before {
    position: absolute;
    content: "🔓";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

input:checked + .slider {
    background-color: #667eea;
}

input:checked + .slider:before {
    transform: translateX(26px);
    content: "🔒";
}
</style>

<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>

<script>
// Wait for jQuery to be loaded
(function checkJQuery() {
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
    const cmid = <?php echo $cm->id; ?>;
    const step = 3;
    const autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
    let autosaveTimer;
    let isLocked = <?php echo $locked ? 'true' : 'false'; ?>;

    const taskColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
    const HOURS_PER_WEEK = 1.5;

    // Lock toggle
    $('#lockToggle').on('change', function() {
        isLocked = this.checked;
        updateFormLockState();
        triggerAutosave();
    });

    function updateFormLockState() {
        if (isLocked) {
            $('#planningForm input, #planningForm select').attr('readonly', true).attr('disabled', true);
        } else {
            $('#planningForm input, #planningForm select').attr('readonly', false).attr('disabled', false);
        }
    }

    // Update timeline when values change
    $('#planningForm input, #planningForm select').on('input change', function() {
        updateTimeline();
        triggerAutosave();
    });

    function triggerAutosave() {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            autosave();
        }, autosaveInterval);
    }

    // Collect form data
    window.collectFormData = function() {
        const formData = {};

        formData['projectname'] = $('#projectname').val();

        const startDate = $('#startdate').val();
        const endDate = $('#enddate').val();
        formData['startdate'] = startDate ? new Date(startDate).getTime() / 1000 : 0;
        formData['enddate'] = endDate ? new Date(endDate).getTime() / 1000 : 0;
        formData['vacationzone'] = $('#vacationzone').val();

        for (let i = 1; i <= 5; i++) {
            formData['task' + i + '_hours'] = parseFloat($('#task' + i + '_hours').val()) || 0;
        }

        formData['locked'] = isLocked ? 1 : 0;

        return formData;
    };

    function autosave() {
        const formData = collectFormData();

        $('#autosaveIndicator').html('<i class="fa fa-spinner fa-spin"></i> <?php echo get_string('autosaving', 'gestionprojet'); ?>');

        $.ajax({
            url: '<?php echo new moodle_url('/mod/gestionprojet/ajax/autosave.php'); ?>',
            type: 'POST',
            data: {
                cmid: cmid,
                step: step,
                data: JSON.stringify(formData)
            },
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    $('#autosaveIndicator').html('<i class="fa fa-check text-success"></i> <?php echo get_string('autosaved', 'gestionprojet'); ?>');
                } else {
                    $('#autosaveIndicator').html('<i class="fa fa-exclamation-triangle text-warning"></i> ' + result.message);
                }
                setTimeout(function() {
                    $('#autosaveIndicator').html('');
                }, 3000);
            },
            error: function() {
                $('#autosaveIndicator').html('<i class="fa fa-exclamation-triangle text-danger"></i> <?php echo get_string('autosave_failed', 'gestionprojet'); ?>');
            }
        });
    }

    function updateTimeline() {
        const svg = document.getElementById('timelineSVG');
        svg.innerHTML = '';

        const startDate = $('#startdate').val();
        const endDate = $('#enddate').val();

        if (!startDate || !endDate) {
            $('#totalInfo').html('Veuillez sélectionner les dates de début et de fin');
            return;
        }

        const start = new Date(startDate);
        const end = new Date(endDate);

        if (end <= start) {
            $('#totalInfo').html('La date de fin doit être après la date de début');
            return;
        }

        // Calculate total hours and weeks
        let totalHours = 0;
        const taskHours = [];
        for (let i = 1; i <= 5; i++) {
            const hours = parseFloat($('#task' + i + '_hours').val()) || 0;
            taskHours.push(hours);
            totalHours += hours;
        }

        const totalWeeks = Math.ceil(totalHours / HOURS_PER_WEEK);

        // Display total info
        $('#totalInfo').html(`Total: ${totalHours.toFixed(1)}h sur ${totalWeeks} semaines (${HOURS_PER_WEEK}h/semaine)`);

        // Draw timeline
        const svgWidth = svg.clientWidth;
        let currentX = 0;

        taskHours.forEach((hours, index) => {
            if (hours > 0) {
                const weeks = hours / HOURS_PER_WEEK;
                const width = (weeks / totalWeeks) * svgWidth;

                const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                rect.setAttribute('x', currentX);
                rect.setAttribute('y', 0);
                rect.setAttribute('width', width);
                rect.setAttribute('height', 60);
                rect.setAttribute('fill', taskColors[index]);
                svg.appendChild(rect);

                if (width > 40) {
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', currentX + width / 2);
                    text.setAttribute('y', 35);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('fill', 'white');
                    text.setAttribute('font-weight', 'bold');
                    text.setAttribute('font-size', '14');
                    text.textContent = hours.toFixed(1) + 'h';
                    svg.appendChild(text);
                }

                currentX += width;
            }
        });
    }

    // Initial timeline render
    updateTimeline();
        });
    } else {
        setTimeout(checkJQuery, 50);
    }
})();
</script>

<?php
echo $OUTPUT->footer();

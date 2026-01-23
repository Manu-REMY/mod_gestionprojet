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
    $PAGE->set_context($context);

    // Check capability for standalone
    $isteacher = has_capability('mod/gestionprojet:configureteacherpages', $context);
    if (!$isteacher) {
        require_capability('mod/gestionprojet:view', $context);
    } else {
        require_capability('mod/gestionprojet:configureteacherpages', $context);
    }
} else {
    // Included mode - variables already set by view.php
    // Check capability
    $isteacher = has_capability('mod/gestionprojet:configureteacherpages', $context);
    if (!$isteacher) {
        require_capability('mod/gestionprojet:view', $context);
    } else {
        require_capability('mod/gestionprojet:configureteacherpages', $context);
    }
}

$readonly = !$isteacher;

// Get existing data
$planning = $DB->get_record('gestionprojet_planning', ['gestionprojetid' => $gestionprojet->id]);

// Display grade and feedback if available (for teachers viewing their own work)
$showGrade = false;

echo $OUTPUT->header();

// Navigation buttons
echo '<div class="navigation-container" style="display: flex; justify-content: space-between; margin-bottom: 20px; gap: 15px;">';
echo '<div>';
echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="btn btn-secondary">← ' . get_string('back') . '</a>';
echo '</div>';
echo '<div>';
echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="btn btn-primary">' . get_string('finish') . ' ✓</a>';
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
            <p><strong><?php echo get_string('feedback'); ?>:</strong><br><?php echo format_text($planning->feedback, FORMAT_HTML); ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif;

// Lock status (teacher can lock their own configuration)
// Lock status removed (always unlocked)
$locked = 0;
?>

<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0;"><?php echo get_string('project_planning', 'gestionprojet'); ?></h4>
            <!-- Lock toggle removed -->

        </div>

        <form id="planningForm">
            <div class="form-group mb-3">
                <label for="projectname"><?php echo get_string('projectname', 'gestionprojet'); ?></label>
                <input type="text" class="form-control" id="projectname" name="projectname"
                    value="<?php echo $planning ? s($planning->projectname) : ''; ?>" <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="startdate"><?php echo get_string('startdate', 'gestionprojet'); ?></label>
                        <input type="date" class="form-control" id="startdate" name="startdate"
                            value="<?php echo $planning && $planning->startdate ? date('Y-m-d', $planning->startdate) : ''; ?>"
                            <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="enddate"><?php echo get_string('enddate', 'gestionprojet'); ?></label>
                        <input type="date" class="form-control" id="enddate" name="enddate"
                            value="<?php echo $planning && $planning->enddate ? date('Y-m-d', $planning->enddate) : ''; ?>"
                            <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="vacationzone"><?php echo get_string('vacationzone', 'gestionprojet'); ?></label>
                        <select class="form-control" id="vacationzone" name="vacationzone" <?php echo ($locked || $readonly) ? 'disabled' : ''; ?>>
                            <option value="" <?php echo !$planning || !$planning->vacationzone ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_none', 'gestionprojet'); ?>
                            </option>
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
                <div class="task-input mb-3"
                    style="display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div
                        style="width: 30px; height: 30px; background-color: <?php echo $taskColors[$i - 1]; ?>; border-radius: 5px; margin-right: 15px;">
                    </div>
                    <div style="flex: 1; font-weight: 500;"><?php echo get_string('task' . $i, 'gestionprojet'); ?></div>
                    <input type="number" class="form-control" id="task<?php echo $i; ?>_hours"
                        name="task<?php echo $i; ?>_hours" min="0" step="0.5" value="<?php echo $value; ?>"
                        style="width: 100px;" <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    <span class="ml-2"><?php echo get_string('hours', 'gestionprojet'); ?></span>
                </div>
            <?php endfor; ?>

            <div class="mt-4 p-3" style="background: #f8f9fa; border-radius: 10px;">
                <h5 class="mb-3"><?php echo get_string('timeline_preview', 'gestionprojet'); ?></h5>
                <div id="timelineContainer"
                    style="background: #e9ecef; height: 60px; border-radius: 10px; position: relative;">
                    <svg id="timelineSVG" style="width: 100%; height: 60px;"></svg>
                </div>
                <div id="totalInfo" class="mt-2 text-muted small"></div>
            </div>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>



<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>

<script>
    // Wait for jQuery to be loaded
    // Wait for RequireJS and jQuery
    (function waitRequire() {
        if (typeof require === 'undefined' || typeof jQuery === 'undefined') {
            setTimeout(waitRequire, 50);
            return;
        }

        require(['mod_gestionprojet/autosave'], function (Autosave) {
            jQuery(document).ready(function ($) {
                var cmid = <?php echo $cm->id; ?>;
                var step = 3;
                var autosaveInterval = <?php echo $gestionprojet->autosave_interval * 1000; ?>;
                var isLocked = <?php echo $locked ? 'true' : 'false'; ?>;

                var taskColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
                var HOURS_PER_WEEK = 1.5;

                // Lock toggle logic removed


                // Update timeline when values change
                $('#planningForm input, #planningForm select').on('input change', function () {
                    updateTimeline();
                });

                // Custom serialization for step 3
                var serializeData = function () {
                    var formData = {};

                    formData['projectname'] = $('#projectname').val();

                    var startDate = $('#startdate').val();
                    var endDate = $('#enddate').val();
                    formData['startdate'] = startDate ? new Date(startDate).getTime() / 1000 : 0;
                    formData['enddate'] = endDate ? new Date(endDate).getTime() / 1000 : 0;
                    formData['vacationzone'] = $('#vacationzone').val();

                    for (var i = 1; i <= 5; i++) {
                        formData['task' + i + '_hours'] = parseFloat($('#task' + i + '_hours').val()) || 0;
                    }

                    formData['locked'] = 0; // Always unlocked


                    return formData;
                };

                // Initialize Autosave if not readonly
                <?php if (!$readonly): ?>
                    Autosave.init({
                        cmid: cmid,
                        step: step,
                        groupid: 0,
                        interval: autosaveInterval,
                        formSelector: '#planningForm',
                        serialize: serializeData
                    });
                <?php endif; ?>

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
        });
    })();
</script>

<?php
echo $OUTPUT->footer();

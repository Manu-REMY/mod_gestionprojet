<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Step 3: Planning - Teacher configuration page
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
$nav_links = gestionprojet_get_navigation_links($gestionprojet, $cm->id, 'step3');

echo '<div class="navigation-container-flex">';
echo '<div class="nav-group">';
echo '<a href="' . new moodle_url('/mod/gestionprojet/view.php', ['id' => $cm->id]) . '" class="nav-button nav-button-prev"><span>🏠</span><span>' . get_string('home', 'gestionprojet') . '</span></a>';
if ($nav_links['prev']) {
    echo '<a href="' . $nav_links['prev'] . '" class="nav-button nav-button-prev"><span>←</span><span>' . get_string('previous', 'gestionprojet') . '</span></a>';
}
echo '</div>';

echo '<div>';
if ($nav_links['next']) {
    echo '<a href="' . $nav_links['next'] . '" class="nav-button"><span>' . get_string('next', 'gestionprojet') . '</span><span>→</span></a>';
}
echo '</div>';
echo '</div>';
echo '<h2>📋 ' . get_string('step3', 'gestionprojet') . '</h2>';

// Description
echo '<div class="alert alert-info">';
echo '<p>' . get_string('planning_description', 'gestionprojet') . '</p>';
echo '</div>';

if ($showGrade && isset($planning->grade)): ?>
    <div class="alert alert-success">
        <h4>
            <?php echo get_string('grade'); ?>:
            <?php echo number_format($planning->grade, 2); ?>/20
        </h4>
        <?php if (!empty($planning->feedback)): ?>
            <p><strong>
                    <?php echo get_string('feedback'); ?>:
                </strong><br>
                <?php echo format_text($planning->feedback, FORMAT_HTML); ?>
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
        <div class="card-header-flex">
            <h4>
                <?php echo get_string('project_planning', 'gestionprojet'); ?>
            </h4>
        </div>

        <form id="planningForm">
            <div class="form-group mb-3">
                <label for="projectname">
                    <?php echo get_string('projectname', 'gestionprojet'); ?>
                </label>
                <input type="text" class="form-control" id="projectname" name="projectname"
                    value="<?php echo $planning ? s($planning->projectname) : ''; ?>" <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="startdate">
                            <?php echo get_string('startdate', 'gestionprojet'); ?>
                        </label>
                        <input type="date" class="form-control" id="startdate" name="startdate"
                            value="<?php echo $planning && $planning->startdate ? date('Y-m-d', $planning->startdate) : ''; ?>"
                            <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="enddate">
                            <?php echo get_string('enddate', 'gestionprojet'); ?>
                        </label>
                        <input type="date" class="form-control" id="enddate" name="enddate"
                            value="<?php echo $planning && $planning->enddate ? date('Y-m-d', $planning->enddate) : ''; ?>"
                            <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="vacationzone">
                            <?php echo get_string('vacationzone', 'gestionprojet'); ?>
                        </label>
                        <select class="form-control" id="vacationzone" name="vacationzone" <?php echo ($locked || $readonly) ? 'disabled' : ''; ?>>
                            <option value="" <?php echo !$planning || !$planning->vacationzone ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_none', 'gestionprojet'); ?>
                            </option>
                            <option value="A" <?php echo $planning && $planning->vacationzone === 'A' ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_a', 'gestionprojet'); ?>
                            </option>
                            <option value="B" <?php echo $planning && $planning->vacationzone === 'B' ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_b', 'gestionprojet'); ?>
                            </option>
                            <option value="C" <?php echo $planning && $planning->vacationzone === 'C' ? 'selected' : ''; ?>>
                                <?php echo get_string('vacationzone_c', 'gestionprojet'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="timeline-preview-section mt-4 mb-4">
                <h5 class="mb-3">
                    <?php echo get_string('timeline_preview', 'gestionprojet'); ?>
                </h5>
                <div id="timelineContainer" class="timeline-box">
                    <svg id="timelineSVG" class="timeline-svg"></svg>
                </div>
                <div id="milestonesContainer" class="milestones-container"></div>
                <div id="totalInfo" class="total-info-box"></div>
                <div id="vacationInfo" class="vacation-info"></div>
            </div>

            <h5 class="mt-4 mb-3">
                <?php echo get_string('task_durations', 'gestionprojet'); ?>
            </h5>
            <p class="text-muted small">
                <?php echo get_string('hours_per_week_info', 'gestionprojet'); ?>
            </p>

            <?php
            $taskColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
            for ($i = 1; $i <= 5; $i++):
                $fieldName = 'task' . $i . '_hours';
                $value = $planning ? $planning->$fieldName : 0;
                ?>
                <div class="task-input-item">
                    <div class="task-color-indicator"
                        style="background-color: <?php echo $taskColors[$i - 1]; ?>;">
                    </div>
                    <div class="task-label">
                        <?php echo get_string('task' . $i, 'gestionprojet'); ?>
                    </div>
                    <input type="number" class="form-control task-hours-input" id="task<?php echo $i; ?>_hours"
                        name="task<?php echo $i; ?>_hours" min="0" step="0.5" value="<?php echo $value; ?>"
                        <?php echo ($locked || $readonly) ? 'readonly' : ''; ?>>
                    <span class="ml-2">
                        <?php echo get_string('hours', 'gestionprojet'); ?>
                    </span>
                </div>
            <?php endfor; ?>
        </form>

        <div id="autosaveIndicator" class="text-muted small" style="margin-top: 10px;"></div>
    </div>
</div>

<?php
// Ensure jQuery is loaded
$PAGE->requires->jquery();
?>

<script>
    // Localized strings for timeline JS
    var TIMELINE_STRINGS = {
        errorEndBeforeStart: <?php echo json_encode(get_string('timeline_error_end_before_start', 'gestionprojet')); ?>,
        defineDurations: <?php echo json_encode(get_string('timeline_define_durations', 'gestionprojet')); ?>,
        startdateLabel: <?php echo json_encode(get_string('startdate', 'gestionprojet')); ?>,
        enddateLabel: <?php echo json_encode(get_string('enddate', 'gestionprojet')); ?>,
        totalHours: <?php echo json_encode(get_string('total_hours', 'gestionprojet')); ?>,
        hours: <?php echo json_encode(get_string('hours', 'gestionprojet')); ?>,
        hoursPerWeekInfo: <?php echo json_encode(get_string('hours_per_week_info', 'gestionprojet')); ?>,
        workingWeeks: <?php echo json_encode(get_string('working_weeks', 'gestionprojet')); ?>,
        hoursAvailable: <?php echo json_encode(get_string('timeline_hours_available', 'gestionprojet')); ?>,
        hoursOverCapacity: <?php echo json_encode(get_string('timeline_hours_over_capacity', 'gestionprojet')); ?>,
        vacationPeriods: <?php echo json_encode(get_string('timeline_vacation_periods', 'gestionprojet')); ?>
    };

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
                var schoolHolidays = [];

                // Fetch school holidays from French government API
                async function fetchSchoolHolidays(zone) {
                    if (!zone) {
                        schoolHolidays = [];
                        updateTimeline();
                        return;
                    }

                    try {
                        // API query for 2025-2027 period
                        const response = await fetch(`https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=zones%20like%20%22%25${zone}%25%22%20AND%20(start_date%20>=%20%222025-01-01%22%20AND%20start_date%20<=%20%222027-12-31%22)&limit=100`);
                        const data = await response.json();

                        if (data.error_code) {
                            console.error('API Error:', data.message);
                            schoolHolidays = [];
                            updateTimeline();
                            return;
                        }

                        schoolHolidays = data.results
                            .filter(record => {
                                const isVacation = record.description && record.description.toLowerCase().includes('vacances');
                                if (!isVacation) return false;

                                if (!record.zones) return false;
                                const zonesStr = typeof record.zones === 'string' ? record.zones : record.zones.join(' ');
                                const hasZone = zonesStr.toLowerCase().includes(zone.toLowerCase()) ||
                                               zonesStr.toLowerCase().includes('zone ' + zone.toLowerCase()) ||
                                               zonesStr.includes(zone);
                                return hasZone;
                            })
                            .map(record => ({
                                description: record.description,
                                start: new Date(record.start_date),
                                end: new Date(record.end_date)
                            }))
                            .sort((a, b) => a.start - b.start);

                        // Deduplicate holidays with same start/end dates
                        const uniqueHolidays = [];
                        schoolHolidays.forEach(holiday => {
                            const isDuplicate = uniqueHolidays.some(h =>
                                h.start.getTime() === holiday.start.getTime() &&
                                h.end.getTime() === holiday.end.getTime() &&
                                h.description === holiday.description
                            );
                            if (!isDuplicate) {
                                uniqueHolidays.push(holiday);
                            }
                        });
                        schoolHolidays = uniqueHolidays;

                        updateTimeline();
                    } catch (error) {
                        console.error('Error fetching holidays:', error);
                        schoolHolidays = [];
                        updateTimeline();
                    }
                }

                // Calculate working weeks excluding holidays
                function calculateWorkingWeeks(startDate, endDate) {
                    if (!startDate || !endDate || startDate >= endDate) {
                        return 0;
                    }

                    let totalDays = 0;
                    let currentDate = new Date(startDate);

                    while (currentDate <= endDate) {
                        const isHoliday = schoolHolidays.some(holiday =>
                            currentDate >= holiday.start && currentDate <= holiday.end
                        );

                        if (!isHoliday) {
                            totalDays++;
                        }

                        currentDate.setDate(currentDate.getDate() + 1);
                    }

                    return totalDays / 7;
                }

                function calculateTotalHours() {
                    const startDate = new Date($('#startdate').val());
                    const endDate = new Date($('#enddate').val());

                    if (!startDate || !endDate || isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || startDate >= endDate) {
                        return 0;
                    }

                    const weeks = calculateWorkingWeeks(startDate, endDate);
                    return weeks * HOURS_PER_WEEK;
                }

                // Add working days excluding holidays
                function addWorkingDays(startDate, daysToAdd) {
                    let currentDate = new Date(startDate);
                    let daysAdded = 0;

                    while (daysAdded < daysToAdd) {
                        currentDate.setDate(currentDate.getDate() + 1);

                        const isHoliday = schoolHolidays.some(holiday =>
                            currentDate >= holiday.start && currentDate <= holiday.end
                        );

                        if (!isHoliday) {
                            daysAdded++;
                        }
                    }

                    return currentDate;
                }

                function initializeTaskDurations() {
                    const totalHours = calculateTotalHours();
                    if (totalHours <= 0) return;

                    // Check if all tasks are at 0 (initial state)
                    let currentTotal = 0;
                    for (let i = 1; i <= 5; i++) {
                        currentTotal += parseFloat($('#task' + i + '_hours').val()) || 0;
                    }

                    // Only auto-distribute if all tasks are at 0
                    if (currentTotal === 0) {
                        const hoursPerTask = totalHours / 5;
                        for (let i = 1; i <= 5; i++) {
                            $('#task' + i + '_hours').val(hoursPerTask.toFixed(1));
                        }
                    }

                    updateTimeline();
                }

                function updateTimeline() {
                    const svg = document.getElementById('timelineSVG');
                    const container = document.getElementById('timelineContainer');
                    const milestonesContainer = document.getElementById('milestonesContainer');
                    svg.innerHTML = '';
                    milestonesContainer.innerHTML = '';

                    // Remove existing overlays
                    container.querySelectorAll('.vacation-overlay, .current-day-marker').forEach(el => el.remove());

                    const startDateVal = $('#startdate').val();
                    const endDateVal = $('#enddate').val();

                    if (!startDateVal || !endDateVal) {
                        $('#totalInfo').html('<strong><?php echo get_string('startdate', 'gestionprojet'); ?> / <?php echo get_string('enddate', 'gestionprojet'); ?></strong>');
                        return;
                    }

                    const start = new Date(startDateVal);
                    const end = new Date(endDateVal);

                    if (end <= start) {
                        $('#totalInfo').html('<span class="text-danger">' + TIMELINE_STRINGS.errorEndBeforeStart + '</span>');
                        return;
                    }

                    // Calculate total hours
                    let totalHours = 0;
                    const taskHours = [];
                    for (let i = 1; i <= 5; i++) {
                        const hours = parseFloat($('#task' + i + '_hours').val()) || 0;
                        taskHours.push(hours);
                        totalHours += hours;
                    }

                    if (totalHours === 0) {
                        $('#totalInfo').html('<span class="text-muted">' + TIMELINE_STRINGS.defineDurations + '</span>');
                        return;
                    }

                    const svgWidth = svg.clientWidth || container.clientWidth;
                    const totalProjectDays = (end - start) / (1000 * 60 * 60 * 24);

                    // Draw timeline segments accounting for holidays
                    let currentDate = new Date(start);
                    const allSegments = [];

                    taskHours.forEach((hours, taskIndex) => {
                        if (hours <= 0) return;

                        const taskWeeks = hours / HOURS_PER_WEEK;
                        const taskDays = taskWeeks * 7;
                        let remainingDays = taskDays;

                        while (remainingDays > 0) {
                            // Find next holiday
                            let nextHoliday = null;
                            let daysUntilHoliday = Infinity;

                            schoolHolidays.forEach(holiday => {
                                if (holiday.start > currentDate) {
                                    const daysToHoliday = (holiday.start - currentDate) / (1000 * 60 * 60 * 24);
                                    if (daysToHoliday < daysUntilHoliday) {
                                        daysUntilHoliday = daysToHoliday;
                                        nextHoliday = holiday;
                                    }
                                }
                            });

                            // Check if currently in holiday
                            const currentHoliday = schoolHolidays.find(h =>
                                currentDate >= h.start && currentDate <= h.end
                            );

                            if (currentHoliday) {
                                currentDate = new Date(currentHoliday.end);
                                currentDate.setDate(currentDate.getDate() + 1);
                                continue;
                            }

                            let segmentDays;
                            if (nextHoliday && daysUntilHoliday < remainingDays) {
                                segmentDays = daysUntilHoliday;
                            } else {
                                segmentDays = remainingDays;
                            }

                            const segmentStart = new Date(currentDate);
                            currentDate.setDate(currentDate.getDate() + segmentDays);
                            const segmentEnd = new Date(currentDate);

                            const segmentStartDays = (segmentStart - start) / (1000 * 60 * 60 * 24);
                            const segmentPercent = (segmentStartDays / totalProjectDays) * 100;
                            const segmentWidth = (segmentDays / totalProjectDays) * 100;

                            allSegments.push({
                                taskIndex: taskIndex,
                                left: segmentPercent,
                                width: segmentWidth,
                                hours: (segmentDays / taskDays) * hours
                            });

                            remainingDays -= segmentDays;

                            if (nextHoliday && daysUntilHoliday <= segmentDays) {
                                currentDate = new Date(nextHoliday.end);
                                currentDate.setDate(currentDate.getDate() + 1);
                            }
                        }
                    });

                    // Sort and draw segments
                    allSegments.sort((a, b) => a.left - b.left);

                    allSegments.forEach(segment => {
                        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                        rect.setAttribute('x', (segment.left / 100) * svgWidth);
                        rect.setAttribute('y', 0);
                        rect.setAttribute('width', (segment.width / 100) * svgWidth);
                        rect.setAttribute('height', 60);
                        rect.setAttribute('fill', taskColors[segment.taskIndex]);
                        svg.appendChild(rect);

                        // Add text if segment is wide enough
                        if ((segment.width / 100) * svgWidth > 40) {
                            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                            text.setAttribute('x', ((segment.left + segment.width / 2) / 100) * svgWidth);
                            text.setAttribute('y', 35);
                            text.setAttribute('text-anchor', 'middle');
                            text.setAttribute('fill', 'white');
                            text.setAttribute('font-weight', 'bold');
                            text.setAttribute('font-size', '12');
                            text.textContent = segment.hours.toFixed(1) + 'h';
                            svg.appendChild(text);
                        }
                    });

                    // Draw vacation overlays
                    schoolHolidays.forEach(holiday => {
                        if (holiday.start <= end && holiday.end >= start) {
                            const holidayStart = holiday.start > start ? holiday.start : start;
                            const holidayEnd = holiday.end < end ? holiday.end : end;

                            const daysFromStart = (holidayStart - start) / (1000 * 60 * 60 * 24);
                            const holidayDuration = (holidayEnd - holidayStart) / (1000 * 60 * 60 * 24);

                            const leftPercent = (daysFromStart / totalProjectDays) * 100;
                            const widthPercent = (holidayDuration / totalProjectDays) * 100;

                            const vacationOverlay = document.createElement('div');
                            vacationOverlay.className = 'vacation-overlay';
                            vacationOverlay.style.left = leftPercent + '%';
                            vacationOverlay.style.width = widthPercent + '%';
                            vacationOverlay.title = holiday.description;
                            container.appendChild(vacationOverlay);
                        }
                    });

                    // Draw current day marker
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (today >= start && today <= end) {
                        const daysFromStart = (today - start) / (1000 * 60 * 60 * 24);
                        const todayPercent = (daysFromStart / totalProjectDays) * 100;

                        const todayMarker = document.createElement('div');
                        todayMarker.className = 'current-day-marker';
                        todayMarker.style.left = todayPercent + '%';

                        const day = String(today.getDate()).padStart(2, '0');
                        const month = String(today.getMonth() + 1).padStart(2, '0');
                        const year = today.getFullYear();
                        todayMarker.setAttribute('data-date', `${day}/${month}/${year}`);
                        container.appendChild(todayMarker);
                    }

                    // Draw milestones
                    let cumulativeHours = 0;
                    taskHours.forEach((hours, index) => {
                        if (hours > 0) {
                            cumulativeHours += hours;

                            const weeksNeeded = cumulativeHours / HOURS_PER_WEEK;
                            const daysToAdd = Math.round(weeksNeeded * 7);
                            const milestoneDate = addWorkingDays(start, daysToAdd);

                            const daysFromStart = (milestoneDate - start) / (1000 * 60 * 60 * 24);
                            const milestonePercent = (daysFromStart / totalProjectDays) * 100;

                            const dateStr = `${String(milestoneDate.getDate()).padStart(2, '0')}/${String(milestoneDate.getMonth() + 1).padStart(2, '0')}`;

                            const marker = document.createElement('div');
                            marker.className = 'milestone-marker';
                            marker.style.left = milestonePercent + '%';
                            marker.textContent = dateStr;
                            milestonesContainer.appendChild(marker);
                        }
                    });

                    // Update total info
                    const totalWeeks = (totalHours / HOURS_PER_WEEK).toFixed(1);
                    const availableHours = calculateTotalHours();
                    let infoHTML = '<strong>' + TIMELINE_STRINGS.totalHours + ': ' + totalHours.toFixed(1) + ' ' + TIMELINE_STRINGS.hours + '</strong> (' + totalWeeks + ' ' + TIMELINE_STRINGS.workingWeeks + ' - ' + TIMELINE_STRINGS.hoursPerWeekInfo + ')';

                    if (availableHours > 0 && Math.abs(totalHours - availableHours) > 0.5) {
                        const diff = (availableHours - totalHours).toFixed(1);
                        if (diff > 0) {
                            infoHTML += '<br><span class="text-info">' + TIMELINE_STRINGS.hoursAvailable.replace('{$a}', diff) + '</span>';
                        } else {
                            infoHTML += '<br><span class="text-warning">' + TIMELINE_STRINGS.hoursOverCapacity.replace('{$a}', Math.abs(diff)) + '</span>';
                        }
                    }

                    $('#totalInfo').html(infoHTML);

                    // Update vacation info
                    if (schoolHolidays.length > 0) {
                        const relevantHolidays = schoolHolidays.filter(h => h.start <= end && h.end >= start);
                        if (relevantHolidays.length > 0) {
                            let vacationHTML = '📅 ' + TIMELINE_STRINGS.vacationPeriods.replace('{$a}', relevantHolidays.length) + ' ';
                            vacationHTML += relevantHolidays.map(h => {
                                const startStr = h.start.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' });
                                const endStr = h.end.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' });
                                return `<span>${h.description.replace('Vacances ', '')} (${startStr}-${endStr})</span>`;
                            }).join(' ');
                            $('#vacationInfo').html(vacationHTML);
                        } else {
                            $('#vacationInfo').html('');
                        }
                    } else {
                        $('#vacationInfo').html('');
                    }
                }

                // Event listeners
                $('#planningForm input, #planningForm select').on('input change', function () {
                    updateTimeline();
                });

                $('#startdate, #enddate').on('change', function() {
                    initializeTaskDurations();
                });

                $('#vacationzone').on('change', async function() {
                    const zone = this.value;
                    await fetchSchoolHolidays(zone);
                    initializeTaskDurations();
                });

                // Auto-adjust other tasks when one is modified
                $('.task-hours-input').on('input', function() {
                    const changedTaskId = $(this).attr('id');
                    const newValue = parseFloat($(this).val()) || 0;
                    const totalHours = calculateTotalHours();

                    if (totalHours <= 0) {
                        updateTimeline();
                        return;
                    }

                    let currentTotal = 0;
                    const currentValues = {};
                    for (let i = 1; i <= 5; i++) {
                        const val = parseFloat($('#task' + i + '_hours').val()) || 0;
                        currentValues['task' + i + '_hours'] = val;
                        currentTotal += val;
                    }

                    if (currentTotal > totalHours) {
                        const excess = currentTotal - totalHours;
                        const otherTasksTotal = currentTotal - newValue;

                        if (otherTasksTotal > 0) {
                            for (let i = 1; i <= 5; i++) {
                                const id = 'task' + i + '_hours';
                                if (id !== changedTaskId) {
                                    const currentVal = currentValues[id];
                                    const reduction = (currentVal / otherTasksTotal) * excess;
                                    const newVal = Math.max(0, currentVal - reduction);
                                    $('#' + id).val(newVal.toFixed(1));
                                }
                            }
                        }
                    }

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

                    formData['locked'] = 0;

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

                // Initial load
                const initialZone = $('#vacationzone').val();
                if (initialZone) {
                    fetchSchoolHolidays(initialZone);
                } else {
                    updateTimeline();
                }
            });
        });
    })();
</script>

<?php
echo $OUTPUT->footer();

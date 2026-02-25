/*
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @package    mod_gestionprojet
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Step 3 - Planning / Timeline module.
 *
 * Handles the Gantt-style timeline rendering, school holidays API
 * integration, task hour distribution and autosave for step 3.
 *
 * @module     mod_gestionprojet/step3
 */
define(['jquery', 'mod_gestionprojet/autosave', 'core/notification'], function($, Autosave, Notification) {
    return {
        /**
         * Initialise step 3.
         *
         * @param {Object} config
         * @param {Number} config.cmid            Course-module id.
         * @param {Number} config.autosaveInterval Autosave interval in ms.
         * @param {Boolean} config.isLocked        Whether the form is locked.
         * @param {Boolean} config.readonly        Whether the form is readonly.
         * @param {String}  config.holidaysApiUrl  Base URL for the holidays API.
         * @param {Object}  config.strings         Pre-resolved language strings.
         */
        init: function(config) {
            var cmid = config.cmid;
            var step = 3;
            var autosaveInterval = config.autosaveInterval;
            var isReadonly = config.readonly;

            var taskColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];
            var HOURS_PER_WEEK = 1.5;
            var schoolHolidays = [];
            var STRINGS = config.strings || {};
            var holidaysApiUrl = config.holidaysApiUrl ||
                'https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records';

            // ---------------------------------------------------------------
            // School holidays API
            // ---------------------------------------------------------------

            /**
             * Fetch school holidays from the French government open data API.
             *
             * @param {String} zone  Vacation zone (A, B or C). Empty = clear.
             * @return {Promise}
             */
            async function fetchSchoolHolidays(zone) {
                if (!zone) {
                    schoolHolidays = [];
                    updateTimeline();
                    return;
                }

                try {
                    var url = holidaysApiUrl +
                        '?where=zones%20like%20%22%25' + zone +
                        '%25%22%20AND%20(start_date%20>=%20%222025-01-01' +
                        '%22%20AND%20start_date%20<=%20%222027-12-31%22)&limit=100';

                    var response = await fetch(url);
                    var data = await response.json();

                    if (data.error_code) {
                        // eslint-disable-next-line no-console
                        console.error('API Error:', data.message);
                        schoolHolidays = [];
                        updateTimeline();
                        return;
                    }

                    schoolHolidays = data.results
                        .filter(function(record) {
                            var isVacation = record.description &&
                                record.description.toLowerCase().includes('vacances');
                            if (!isVacation) {
                                return false;
                            }
                            if (!record.zones) {
                                return false;
                            }
                            var zonesStr = typeof record.zones === 'string'
                                ? record.zones
                                : record.zones.join(' ');
                            return zonesStr.toLowerCase().includes(zone.toLowerCase()) ||
                                zonesStr.toLowerCase().includes('zone ' + zone.toLowerCase()) ||
                                zonesStr.includes(zone);
                        })
                        .map(function(record) {
                            return {
                                description: record.description,
                                start: new Date(record.start_date),
                                end: new Date(record.end_date)
                            };
                        })
                        .sort(function(a, b) {
                            return a.start - b.start;
                        });

                    // Deduplicate holidays with identical start/end/description.
                    var uniqueHolidays = [];
                    schoolHolidays.forEach(function(holiday) {
                        var isDuplicate = uniqueHolidays.some(function(h) {
                            return h.start.getTime() === holiday.start.getTime() &&
                                h.end.getTime() === holiday.end.getTime() &&
                                h.description === holiday.description;
                        });
                        if (!isDuplicate) {
                            uniqueHolidays.push(holiday);
                        }
                    });
                    schoolHolidays = uniqueHolidays;

                    updateTimeline();
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error('Error fetching holidays:', error);
                    schoolHolidays = [];
                    updateTimeline();
                }
            }

            // ---------------------------------------------------------------
            // Working-week helpers
            // ---------------------------------------------------------------

            /**
             * Calculate the number of working weeks between two dates,
             * excluding school holiday periods.
             *
             * @param {Date} startDate
             * @param {Date} endDate
             * @return {Number}
             */
            function calculateWorkingWeeks(startDate, endDate) {
                if (!startDate || !endDate || startDate >= endDate) {
                    return 0;
                }

                var totalDays = 0;
                var currentDate = new Date(startDate);

                while (currentDate <= endDate) {
                    var isHoliday = schoolHolidays.some(function(holiday) {
                        return currentDate >= holiday.start && currentDate <= holiday.end;
                    });

                    if (!isHoliday) {
                        totalDays++;
                    }

                    currentDate.setDate(currentDate.getDate() + 1);
                }

                return totalDays / 7;
            }

            /**
             * Calculate total available hours for the project based on
             * dates and HOURS_PER_WEEK.
             *
             * @return {Number}
             */
            function calculateTotalHours() {
                var startDate = new Date($('#startdate').val());
                var endDate = new Date($('#enddate').val());

                if (!startDate || !endDate ||
                    isNaN(startDate.getTime()) || isNaN(endDate.getTime()) ||
                    startDate >= endDate) {
                    return 0;
                }

                var weeks = calculateWorkingWeeks(startDate, endDate);
                return weeks * HOURS_PER_WEEK;
            }

            /**
             * Add a number of working days (skipping holidays) from a start date.
             *
             * @param {Date}   startDate
             * @param {Number} daysToAdd
             * @return {Date}
             */
            function addWorkingDays(startDate, daysToAdd) {
                var currentDate = new Date(startDate);
                var daysAdded = 0;

                while (daysAdded < daysToAdd) {
                    currentDate.setDate(currentDate.getDate() + 1);

                    var isHoliday = schoolHolidays.some(function(holiday) {
                        return currentDate >= holiday.start && currentDate <= holiday.end;
                    });

                    if (!isHoliday) {
                        daysAdded++;
                    }
                }

                return currentDate;
            }

            // ---------------------------------------------------------------
            // Task duration initialisation
            // ---------------------------------------------------------------

            /**
             * If all task hour inputs are zero, auto-distribute the
             * available hours equally among the 5 tasks.
             */
            function initializeTaskDurations() {
                var totalHours = calculateTotalHours();
                if (totalHours <= 0) {
                    return;
                }

                var currentTotal = 0;
                var i;
                for (i = 1; i <= 5; i++) {
                    currentTotal += parseFloat($('#task' + i + '_hours').val()) || 0;
                }

                if (currentTotal === 0) {
                    var hoursPerTask = totalHours / 5;
                    for (i = 1; i <= 5; i++) {
                        $('#task' + i + '_hours').val(hoursPerTask.toFixed(1));
                    }
                }

                updateTimeline();
            }

            // ---------------------------------------------------------------
            // Timeline rendering
            // ---------------------------------------------------------------

            /**
             * Helper to safely set element text content via jQuery.
             *
             * @param {String} selector jQuery selector.
             * @param {String} text     Plain text to set.
             */
            function setTextContent(selector, text) {
                $(selector).text(text);
            }

            /**
             * Build safe DOM content for the total info box.
             *
             * @param {String} type  'select_dates' | 'end_after_start' | 'define_durations' | 'info'
             * @param {Object} [data] Extra data for the 'info' type.
             */
            function renderTotalInfo(type, data) {
                var el = document.getElementById('totalInfo');
                el.textContent = '';

                if (type === 'select_dates') {
                    var strong = document.createElement('strong');
                    strong.textContent = STRINGS.step3_select_dates || 'Select a start date and an end date';
                    el.appendChild(strong);
                    return;
                }

                if (type === 'end_after_start') {
                    var span = document.createElement('span');
                    span.className = 'text-danger';
                    span.textContent = STRINGS.step3_end_after_start || 'The end date must be after the start date';
                    el.appendChild(span);
                    return;
                }

                if (type === 'define_durations') {
                    var mutedSpan = document.createElement('span');
                    mutedSpan.className = 'text-muted';
                    mutedSpan.textContent = STRINGS.step3_define_durations || 'Set the task durations';
                    el.appendChild(mutedSpan);
                    return;
                }

                // type === 'info'
                var strongInfo = document.createElement('strong');
                var totalStr = (STRINGS.step3_total_hours || 'Total: {hours} hours ({weeks} weeks at {hpw}h/week)')
                    .replace('{hours}', data.totalHours)
                    .replace('{weeks}', data.totalWeeks)
                    .replace('{hpw}', data.hpw);
                strongInfo.textContent = totalStr;
                el.appendChild(strongInfo);

                if (data.diff !== null) {
                    el.appendChild(document.createElement('br'));
                    var diffSpan = document.createElement('span');
                    if (data.diff > 0) {
                        diffSpan.className = 'text-info';
                        diffSpan.textContent = (STRINGS.step3_hours_available || '{a}h available')
                            .replace('{a}', data.diff);
                    } else {
                        diffSpan.className = 'text-warning';
                        diffSpan.textContent = (STRINGS.step3_hours_exceeded || '{a}h above capacity')
                            .replace('{a}', data.absDiff);
                    }
                    el.appendChild(diffSpan);
                }
            }

            /**
             * Build safe DOM content for the vacation info line.
             *
             * @param {Array}  holidays Relevant holiday objects.
             * @param {String} prefix   Vacation prefix to strip from description.
             */
            function renderVacationInfo(holidays, prefix) {
                var el = document.getElementById('vacationInfo');
                el.textContent = '';

                if (!holidays || holidays.length === 0) {
                    return;
                }

                var headerText = '\uD83D\uDCC5 ' +
                    (STRINGS.step3_vacation_periods || '{a} vacation period(s):')
                        .replace('{a}', holidays.length) + ' ';
                el.appendChild(document.createTextNode(headerText));

                holidays.forEach(function(h) {
                    var startStr = h.start.toLocaleDateString('fr-FR', {day: '2-digit', month: '2-digit'});
                    var endStr = h.end.toLocaleDateString('fr-FR', {day: '2-digit', month: '2-digit'});
                    var span = document.createElement('span');
                    span.textContent = h.description.replace(prefix, '') +
                        ' (' + startStr + '-' + endStr + ')';
                    el.appendChild(span);
                    el.appendChild(document.createTextNode(' '));
                });
            }

            /**
             * Re-draw the SVG timeline, vacation overlays, current-day
             * marker, milestone markers and info text.
             */
            function updateTimeline() {
                var svg = document.getElementById('timelineSVG');
                var container = document.getElementById('timelineContainer');
                var milestonesContainer = document.getElementById('milestonesContainer');
                svg.textContent = '';
                milestonesContainer.textContent = '';

                // Remove existing dynamic overlays.
                container.querySelectorAll('.vacation-overlay, .current-day-marker').forEach(function(el) {
                    el.remove();
                });

                var startDateVal = $('#startdate').val();
                var endDateVal = $('#enddate').val();

                if (!startDateVal || !endDateVal) {
                    renderTotalInfo('select_dates');
                    return;
                }

                var start = new Date(startDateVal);
                var end = new Date(endDateVal);

                if (end <= start) {
                    renderTotalInfo('end_after_start');
                    return;
                }

                // Collect task hours.
                var totalHours = 0;
                var taskHours = [];
                var i;
                for (i = 1; i <= 5; i++) {
                    var hours = parseFloat($('#task' + i + '_hours').val()) || 0;
                    taskHours.push(hours);
                    totalHours += hours;
                }

                if (totalHours === 0) {
                    renderTotalInfo('define_durations');
                    return;
                }

                var svgWidth = svg.clientWidth || container.clientWidth;
                var totalProjectDays = (end - start) / (1000 * 60 * 60 * 24);

                // Build timeline segments, splitting around holidays.
                var currentDate = new Date(start);
                var allSegments = [];

                taskHours.forEach(function(taskHoursVal, taskIndex) {
                    if (taskHoursVal <= 0) {
                        return;
                    }

                    var taskWeeks = taskHoursVal / HOURS_PER_WEEK;
                    var taskDays = taskWeeks * 7;
                    var remainingDays = taskDays;

                    while (remainingDays > 0) {
                        // Find next holiday.
                        var nextHoliday = null;
                        var daysUntilHoliday = Infinity;

                        schoolHolidays.forEach(function(holiday) {
                            if (holiday.start > currentDate) {
                                var daysToHoliday = (holiday.start - currentDate) / (1000 * 60 * 60 * 24);
                                if (daysToHoliday < daysUntilHoliday) {
                                    daysUntilHoliday = daysToHoliday;
                                    nextHoliday = holiday;
                                }
                            }
                        });

                        // Check if currently inside a holiday.
                        var currentHoliday = schoolHolidays.find(function(h) {
                            return currentDate >= h.start && currentDate <= h.end;
                        });

                        if (currentHoliday) {
                            currentDate = new Date(currentHoliday.end);
                            currentDate.setDate(currentDate.getDate() + 1);
                            continue;
                        }

                        var segmentDays;
                        if (nextHoliday && daysUntilHoliday < remainingDays) {
                            segmentDays = daysUntilHoliday;
                        } else {
                            segmentDays = remainingDays;
                        }

                        var segmentStart = new Date(currentDate);
                        currentDate.setDate(currentDate.getDate() + segmentDays);

                        var segmentStartDays = (segmentStart - start) / (1000 * 60 * 60 * 24);
                        var segmentPercent = (segmentStartDays / totalProjectDays) * 100;
                        var segmentWidth = (segmentDays / totalProjectDays) * 100;

                        allSegments.push({
                            taskIndex: taskIndex,
                            left: segmentPercent,
                            width: segmentWidth,
                            hours: (segmentDays / taskDays) * taskHoursVal
                        });

                        remainingDays -= segmentDays;

                        if (nextHoliday && daysUntilHoliday <= segmentDays) {
                            currentDate = new Date(nextHoliday.end);
                            currentDate.setDate(currentDate.getDate() + 1);
                        }
                    }
                });

                // Sort segments left-to-right and draw.
                allSegments.sort(function(a, b) {
                    return a.left - b.left;
                });

                allSegments.forEach(function(segment) {
                    var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    rect.setAttribute('x', (segment.left / 100) * svgWidth);
                    rect.setAttribute('y', 0);
                    rect.setAttribute('width', (segment.width / 100) * svgWidth);
                    rect.setAttribute('height', 60);
                    rect.setAttribute('fill', taskColors[segment.taskIndex]);
                    svg.appendChild(rect);

                    // Label if segment is wide enough.
                    if ((segment.width / 100) * svgWidth > 40) {
                        var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
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

                // Draw vacation overlays.
                schoolHolidays.forEach(function(holiday) {
                    if (holiday.start <= end && holiday.end >= start) {
                        var holidayStart = holiday.start > start ? holiday.start : start;
                        var holidayEnd = holiday.end < end ? holiday.end : end;

                        var daysFromStart = (holidayStart - start) / (1000 * 60 * 60 * 24);
                        var holidayDuration = (holidayEnd - holidayStart) / (1000 * 60 * 60 * 24);

                        var leftPercent = (daysFromStart / totalProjectDays) * 100;
                        var widthPercent = (holidayDuration / totalProjectDays) * 100;

                        var vacationOverlay = document.createElement('div');
                        vacationOverlay.className = 'vacation-overlay';
                        vacationOverlay.style.left = leftPercent + '%';
                        vacationOverlay.style.width = widthPercent + '%';
                        vacationOverlay.title = holiday.description;
                        container.appendChild(vacationOverlay);
                    }
                });

                // Current day marker.
                var today = new Date();
                today.setHours(0, 0, 0, 0);

                if (today >= start && today <= end) {
                    var daysFromStartToday = (today - start) / (1000 * 60 * 60 * 24);
                    var todayPercent = (daysFromStartToday / totalProjectDays) * 100;

                    var todayMarker = document.createElement('div');
                    todayMarker.className = 'current-day-marker';
                    todayMarker.style.left = todayPercent + '%';

                    var day = String(today.getDate()).padStart(2, '0');
                    var month = String(today.getMonth() + 1).padStart(2, '0');
                    var year = today.getFullYear();
                    todayMarker.setAttribute('data-date', day + '/' + month + '/' + year);
                    container.appendChild(todayMarker);
                }

                // Milestone markers.
                var cumulativeHours = 0;
                taskHours.forEach(function(taskH) {
                    if (taskH > 0) {
                        cumulativeHours += taskH;

                        var weeksNeeded = cumulativeHours / HOURS_PER_WEEK;
                        var daysToAddM = Math.round(weeksNeeded * 7);
                        var milestoneDate = addWorkingDays(start, daysToAddM);

                        var daysFromStartM = (milestoneDate - start) / (1000 * 60 * 60 * 24);
                        var milestonePercent = (daysFromStartM / totalProjectDays) * 100;

                        var dateStr = String(milestoneDate.getDate()).padStart(2, '0') + '/' +
                            String(milestoneDate.getMonth() + 1).padStart(2, '0');

                        var marker = document.createElement('div');
                        marker.className = 'milestone-marker';
                        marker.style.left = milestonePercent + '%';
                        marker.textContent = dateStr;
                        milestonesContainer.appendChild(marker);
                    }
                });

                // Total info.
                var totalWeeks = (totalHours / HOURS_PER_WEEK).toFixed(1);
                var availableHours = calculateTotalHours();

                var infoData = {
                    totalHours: totalHours.toFixed(1),
                    totalWeeks: totalWeeks,
                    hpw: HOURS_PER_WEEK,
                    diff: null,
                    absDiff: null
                };

                if (availableHours > 0 && Math.abs(totalHours - availableHours) > 0.5) {
                    var diff = (availableHours - totalHours).toFixed(1);
                    infoData.diff = parseFloat(diff);
                    infoData.absDiff = Math.abs(parseFloat(diff)).toFixed(1);
                }

                renderTotalInfo('info', infoData);

                // Vacation info.
                var relevantHolidays = schoolHolidays.filter(function(h) {
                    return h.start <= end && h.end >= start;
                });
                var vacPrefix = STRINGS.step3_vacation_prefix || 'Holidays ';
                renderVacationInfo(relevantHolidays, vacPrefix);
            }

            // ---------------------------------------------------------------
            // Event listeners
            // ---------------------------------------------------------------

            $('#planningForm input, #planningForm select').on('input change', function() {
                updateTimeline();
            });

            $('#startdate, #enddate').on('change', function() {
                initializeTaskDurations();
            });

            $('#vacationzone').on('change', async function() {
                var zone = this.value;
                await fetchSchoolHolidays(zone);
                initializeTaskDurations();
            });

            // Auto-adjust other tasks when one is modified.
            $('.task-hours-input').on('input', function() {
                var changedTaskId = $(this).attr('id');
                var newValue = parseFloat($(this).val()) || 0;
                var totalHoursAvail = calculateTotalHours();

                if (totalHoursAvail <= 0) {
                    updateTimeline();
                    return;
                }

                var currentTotal = 0;
                var currentValues = {};
                var j;
                for (j = 1; j <= 5; j++) {
                    var val = parseFloat($('#task' + j + '_hours').val()) || 0;
                    currentValues['task' + j + '_hours'] = val;
                    currentTotal += val;
                }

                if (currentTotal > totalHoursAvail) {
                    var excess = currentTotal - totalHoursAvail;
                    var otherTasksTotal = currentTotal - newValue;

                    if (otherTasksTotal > 0) {
                        for (j = 1; j <= 5; j++) {
                            var id = 'task' + j + '_hours';
                            if (id !== changedTaskId) {
                                var currentVal = currentValues[id];
                                var reduction = (currentVal / otherTasksTotal) * excess;
                                var newVal = Math.max(0, currentVal - reduction);
                                $('#' + id).val(newVal.toFixed(1));
                            }
                        }
                    }
                }

                updateTimeline();
            });

            // ---------------------------------------------------------------
            // Custom serialization for autosave
            // ---------------------------------------------------------------

            var serializeData = function() {
                var formData = {};

                formData.projectname = $('#projectname').val();

                var startDate = $('#startdate').val();
                var endDate = $('#enddate').val();
                formData.startdate = startDate ? new Date(startDate).getTime() / 1000 : 0;
                formData.enddate = endDate ? new Date(endDate).getTime() / 1000 : 0;
                formData.vacationzone = $('#vacationzone').val();

                var k;
                for (k = 1; k <= 5; k++) {
                    formData['task' + k + '_hours'] = parseFloat($('#task' + k + '_hours').val()) || 0;
                }

                formData.locked = 0;

                return formData;
            };

            // ---------------------------------------------------------------
            // Initialise autosave (when not readonly)
            // ---------------------------------------------------------------

            if (!isReadonly) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: 0,
                    interval: autosaveInterval,
                    formSelector: '#planningForm',
                    serialize: serializeData
                });
            }

            // ---------------------------------------------------------------
            // Initial load
            // ---------------------------------------------------------------

            var initialZone = $('#vacationzone').val();
            if (initialZone) {
                fetchSchoolHolidays(initialZone);
            } else {
                updateTimeline();
            }
        }
    };
});

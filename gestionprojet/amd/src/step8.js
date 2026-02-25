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
 * Step 8: Carnet de bord (Student logbook) AMD module.
 *
 * @module     mod_gestionprojet/step8
 */
define(['jquery', 'mod_gestionprojet/autosave', 'core/notification'], function($, Autosave, Notification) {
    return {
        init: function(config) {
            var cmid = config.cmid;
            var step = 8;
            var autosaveInterval = config.autosaveInterval;
            var groupid = config.groupid;
            var isLocked = config.isLocked;
            var STRINGS = config.strings || {};

            // Logbook entries data (passed from PHP as JSON).
            var tasksData = config.tasksData || [];

            /**
             * Clear all child nodes from an element safely.
             * @param {HTMLElement} element The element to clear.
             */
            function clearChildren(element) {
                while (element.firstChild) {
                    element.removeChild(element.firstChild);
                }
            }

            /**
             * Render all logbook entries into the table body.
             */
            function renderLogEntries() {
                var tbody = document.getElementById('logbookTableBody');
                if (!tbody) {
                    return;
                }
                clearChildren(tbody);

                tasksData.forEach(function(entry, index) {
                    var tr = document.createElement('tr');

                    // Date Cell.
                    var tdDate = document.createElement('td');
                    var dateInput = document.createElement('input');
                    dateInput.type = 'date';
                    dateInput.className = 'form-control';
                    dateInput.value = entry.date;
                    dateInput.disabled = isLocked;
                    dateInput.onchange = function(e) {
                        tasksData[index].date = e.target.value;
                    };
                    tdDate.appendChild(dateInput);
                    tr.appendChild(tdDate);

                    // Today Tasks Cell.
                    var tdToday = document.createElement('td');
                    var todayInput = document.createElement('textarea');
                    todayInput.className = 'form-control';
                    todayInput.rows = 3;
                    todayInput.value = entry.tasks_today;
                    todayInput.disabled = isLocked;
                    todayInput.onchange = function(e) {
                        tasksData[index].tasks_today = e.target.value;
                    };
                    tdToday.appendChild(todayInput);
                    tr.appendChild(tdToday);

                    // Future Tasks Cell.
                    var tdFuture = document.createElement('td');
                    var futureInput = document.createElement('textarea');
                    futureInput.className = 'form-control';
                    futureInput.rows = 3;
                    futureInput.value = entry.tasks_future;
                    futureInput.disabled = isLocked;
                    futureInput.onchange = function(e) {
                        tasksData[index].tasks_future = e.target.value;
                    };
                    tdFuture.appendChild(futureInput);
                    tr.appendChild(tdFuture);

                    // Status Cell.
                    var tdStatus = document.createElement('td');
                    var statusDiv = document.createElement('div');
                    statusDiv.className = 'status-radios';

                    var statuses = [
                        {id: 'ahead', label: STRINGS.status_ahead || 'Ahead'},
                        {id: 'ontime', label: STRINGS.status_ontime || 'On time'},
                        {id: 'late', label: STRINGS.status_late || 'Late'}
                    ];

                    statuses.forEach(function(status) {
                        var label = document.createElement('label');
                        label.className = 'status-radio-label';

                        var input = document.createElement('input');
                        input.type = 'radio';
                        input.name = 'status_' + index;
                        input.value = status.id;
                        input.checked = entry.status === status.id;
                        input.disabled = isLocked;
                        input.onchange = function() {
                            tasksData[index].status = status.id;
                        };

                        label.appendChild(input);
                        label.appendChild(document.createTextNode(' ' + status.label));
                        statusDiv.appendChild(label);
                    });
                    tdStatus.appendChild(statusDiv);
                    tr.appendChild(tdStatus);

                    // Actions Cell.
                    var tdAction = document.createElement('td');
                    if (!isLocked && tasksData.length > 1) {
                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'btn-remove-line';
                        removeBtn.textContent = '\uD83D\uDDD1\uFE0F';
                        removeBtn.title = STRINGS.remove_line || 'Remove line';
                        removeBtn.onclick = function() {
                            if (confirm(STRINGS.confirm_remove_line || 'Remove this line?')) {
                                tasksData.splice(index, 1);
                                renderLogEntries();
                            }
                        };
                        tdAction.appendChild(removeBtn);
                    }
                    tr.appendChild(tdAction);

                    tbody.appendChild(tr);
                });
            }

            /**
             * Add a new logbook entry with today's date.
             */
            function addLogEntry() {
                var today = new Date().toISOString().split('T')[0];
                tasksData.push({
                    date: today,
                    tasks_today: '',
                    tasks_future: '',
                    status: 'ontime'
                });
                renderLogEntries();
            }

            /**
             * Export the current logbook as PDF.
             */
            function exportPDF() {
                window.location.href = M.cfg.wwwroot + '/mod/gestionprojet/export_pdf.php?id=' + cmid + '&groupid=' + groupid;
            }

            // Custom serialization for step 8 - serialize tasksData as JSON.
            var serializeData = function() {
                var formData = {};
                formData.tasks_data = JSON.stringify(tasksData);
                return formData;
            };

            // Handle Submission.
            $('#submitButton').on('click', function() {
                if (confirm(STRINGS.confirm_submission)) {
                    $.ajax({
                        url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            groupid: groupid,
                            action: 'submit',
                            sesskey: M.cfg.sesskey
                        },
                        success: function(response) {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                Notification.alert('Error', 'Error submitting');
                            }
                        }
                    });
                }
            });

            // Handle Revert.
            $('#revertButton').on('click', function() {
                if (confirm(STRINGS.confirm_revert)) {
                    $.ajax({
                        url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit.php',
                        method: 'POST',
                        data: {
                            id: cmid,
                            step: step,
                            groupid: groupid,
                            action: 'revert',
                            sesskey: M.cfg.sesskey
                        },
                        success: function(response) {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                Notification.alert('Error', 'Error reverting');
                            }
                        }
                    });
                }
            });

            // Handle Add Entry button.
            $('#addLogEntryButton').on('click', function() {
                addLogEntry();
            });

            // Handle Export PDF button.
            $('#exportPdfButton').on('click', function() {
                exportPDF();
            });

            // Initialize Autosave if not locked.
            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#carnetForm',
                    serialize: serializeData
                });
            }

            // Initial render of logbook entries.
            renderLogEntries();
        }
    };
});

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared AMD module for teacher correction model pages (steps 4-8).
 *
 * Handles autosave, date helpers, interactors rendering (step 4),
 * logbook entries rendering (step 8), and save-with-redirect.
 *
 * @module     mod_gestionprojet/teacher_model
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {

    // ---- Private state ----
    var interacteurs = [];
    var tasks = [];
    var config = {};

    // ---- Date helpers (moved from teacher_dates_section.php) ----

    /**
     * Convert a date input string (YYYY-MM-DD) to a Unix timestamp.
     *
     * @param {string} dateStr The date string.
     * @return {number|null} Unix timestamp or null.
     */
    function dateToTimestamp(dateStr) {
        if (!dateStr) {
            return null;
        }
        var date = new Date(dateStr);
        return Math.floor(date.getTime() / 1000);
    }

    /**
     * Read submission_date and deadline_date inputs and return timestamps.
     *
     * @return {Object} Object with submission_date and deadline_date.
     */
    function getDateValues() {
        var submissionEl = document.getElementById('submission_date');
        var deadlineEl = document.getElementById('deadline_date');
        return {
            submission_date: submissionEl ? dateToTimestamp(submissionEl.value) : null,
            deadline_date: deadlineEl ? dateToTimestamp(deadlineEl.value) : null
        };
    }

    // ---- Interactors rendering (step 4) ----

    /**
     * Create a text element with the given tag, class and text content.
     *
     * @param {string} tag HTML tag name.
     * @param {string} className CSS class(es).
     * @param {string} text Text content.
     * @return {HTMLElement} The created element.
     */
    function createTextElement(tag, className, text) {
        var el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        el.textContent = text;
        return el;
    }

    /**
     * Render all interactors into the container.
     */
    function renderInteractors() {
        var container = document.getElementById('interactorsContainer');
        if (!container) {
            return;
        }
        container.textContent = '';

        interacteurs.forEach(function(interactor, iIndex) {
            var item = document.createElement('div');
            item.className = 'interactor-item';

            var header = document.createElement('div');
            header.className = 'interactor-header';

            var nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.className = 'interactor-name-input';
            nameInput.value = interactor.name;
            nameInput.placeholder = 'Nom de l\'interacteur';
            nameInput.onchange = function() {
                interacteurs[iIndex].name = nameInput.value;
            };
            header.appendChild(nameInput);

            if (iIndex >= 2) {
                var deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn-delete-interactor';
                deleteBtn.textContent = '\uD83D\uDDD1 Supprimer';
                deleteBtn.onclick = function() {
                    interacteurs.splice(iIndex, 1);
                    renderInteractors();
                };
                header.appendChild(deleteBtn);
            }

            item.appendChild(header);

            var fcList = document.createElement('div');
            fcList.className = 'fc-list';

            interactor.fcs.forEach(function(fc, fcIndex) {
                var fcItem = document.createElement('div');
                fcItem.className = 'fc-item';

                var fcHeader = document.createElement('div');
                fcHeader.appendChild(createTextElement('span', 'fc-label', 'FC' + (fcIndex + 1)));
                fcItem.appendChild(fcHeader);

                var fcValueInput = document.createElement('input');
                fcValueInput.type = 'text';
                fcValueInput.className = 'fc-value-input';
                fcValueInput.value = fc.value;
                fcValueInput.placeholder = 'Description de la fonction contrainte';
                fcValueInput.onchange = function() {
                    interacteurs[iIndex].fcs[fcIndex].value = fcValueInput.value;
                };
                fcItem.appendChild(fcValueInput);

                var criteresList = document.createElement('div');
                criteresList.className = 'criteres-list';

                fc.criteres.forEach(function(critere, cIndex) {
                    var critereItem = document.createElement('div');
                    critereItem.className = 'critere-item';

                    var critereInput = document.createElement('input');
                    critereInput.type = 'text';
                    critereInput.className = 'critere-input';
                    critereInput.value = critere.critere;
                    critereInput.placeholder = 'Critere';
                    critereInput.onchange = function() {
                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].critere = critereInput.value;
                    };

                    var niveauInput = document.createElement('input');
                    niveauInput.type = 'text';
                    niveauInput.className = 'critere-input';
                    niveauInput.value = critere.niveau;
                    niveauInput.placeholder = 'Niveau';
                    niveauInput.onchange = function() {
                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].niveau = niveauInput.value;
                    };

                    var uniteInput = document.createElement('input');
                    uniteInput.type = 'text';
                    uniteInput.className = 'critere-input';
                    uniteInput.value = critere.unite;
                    uniteInput.placeholder = 'Unite';
                    uniteInput.onchange = function() {
                        interacteurs[iIndex].fcs[fcIndex].criteres[cIndex].unite = uniteInput.value;
                    };

                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn-remove';
                    removeBtn.textContent = '\u2715';
                    removeBtn.onclick = function() {
                        if (fc.criteres.length > 1) {
                            interacteurs[iIndex].fcs[fcIndex].criteres.splice(cIndex, 1);
                            renderInteractors();
                        }
                    };

                    critereItem.appendChild(critereInput);
                    critereItem.appendChild(niveauInput);
                    critereItem.appendChild(uniteInput);
                    critereItem.appendChild(removeBtn);
                    criteresList.appendChild(critereItem);
                });

                fcItem.appendChild(criteresList);

                var addCritereBtn = document.createElement('button');
                addCritereBtn.type = 'button';
                addCritereBtn.className = 'btn-add';
                addCritereBtn.textContent = '+ Critere';
                addCritereBtn.onclick = function() {
                    interacteurs[iIndex].fcs[fcIndex].criteres.push({critere: '', niveau: '', unite: ''});
                    renderInteractors();
                };
                fcItem.appendChild(addCritereBtn);

                fcList.appendChild(fcItem);
            });

            item.appendChild(fcList);

            var addFCBtn = document.createElement('button');
            addFCBtn.type = 'button';
            addFCBtn.className = 'btn-add';
            addFCBtn.textContent = '+ Fonction Contrainte';
            addFCBtn.onclick = function() {
                interacteurs[iIndex].fcs.push({value: '', criteres: [{critere: '', niveau: '', unite: ''}]});
                renderInteractors();
            };
            item.appendChild(addFCBtn);

            container.appendChild(item);
        });
    }

    /**
     * Add a new interactor.
     */
    function addInteractor() {
        interacteurs.push({
            name: 'Interacteur ' + (interacteurs.length + 1),
            fcs: [{value: '', criteres: [{critere: '', niveau: '', unite: ''}]}]
        });
        renderInteractors();
    }

    // ---- Logbook entries rendering (step 8) ----

    /**
     * Render all logbook entries into the container.
     */
    function renderEntries() {
        var container = document.getElementById('logbookContainer');
        if (!container) {
            return;
        }
        container.textContent = '';

        var strings = config.strings || {};
        var statusAhead = strings.logbook_status_ahead || 'En avance';
        var statusOntime = strings.logbook_status_ontime || 'Dans les temps';
        var statusLate = strings.logbook_status_late || 'En retard';
        var tasksToday = strings.logbook_tasks_today || "Taches d'aujourd'hui";
        var tasksFuture = strings.logbook_tasks_future || 'Taches futures';

        tasks.forEach(function(task, index) {
            var entry = document.createElement('div');
            entry.className = 'logbook-entry';

            // Header row.
            var headerDiv = document.createElement('div');
            headerDiv.className = 'logbook-entry-header';

            var dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.value = task.date || '';
            dateInput.setAttribute('data-index', index);
            dateInput.onchange = function() {
                tasks[index].date = this.value;
            };
            headerDiv.appendChild(dateInput);

            var statusSelect = document.createElement('select');
            statusSelect.setAttribute('data-index', index);
            statusSelect.onchange = function() {
                tasks[index].status = this.value;
            };

            var optAhead = document.createElement('option');
            optAhead.value = 'ahead';
            optAhead.textContent = statusAhead;
            if (task.status === 'ahead') {
                optAhead.selected = true;
            }
            statusSelect.appendChild(optAhead);

            var optOntime = document.createElement('option');
            optOntime.value = 'ontime';
            optOntime.textContent = statusOntime;
            if (task.status === 'ontime') {
                optOntime.selected = true;
            }
            statusSelect.appendChild(optOntime);

            var optLate = document.createElement('option');
            optLate.value = 'late';
            optLate.textContent = statusLate;
            if (task.status === 'late') {
                optLate.selected = true;
            }
            statusSelect.appendChild(optLate);

            headerDiv.appendChild(statusSelect);

            if (tasks.length > 1) {
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-remove-entry';
                removeBtn.textContent = '\uD83D\uDDD1';
                removeBtn.setAttribute('data-index', index);
                removeBtn.onclick = function() {
                    var idx = parseInt(this.getAttribute('data-index'), 10);
                    if (tasks.length > 1) {
                        tasks.splice(idx, 1);
                        renderEntries();
                    }
                };
                headerDiv.appendChild(removeBtn);
            }

            entry.appendChild(headerDiv);

            // Content row.
            var rowDiv = document.createElement('div');
            rowDiv.className = 'logbook-row';

            var todayDiv = document.createElement('div');
            var todayLabel = document.createElement('label');
            todayLabel.style.fontWeight = '600';
            todayLabel.style.marginBottom = '5px';
            todayLabel.style.display = 'block';
            todayLabel.textContent = tasksToday;
            todayDiv.appendChild(todayLabel);

            var todayTextarea = document.createElement('textarea');
            todayTextarea.value = task.tasks_today || '';
            todayTextarea.setAttribute('data-index', index);
            todayTextarea.onchange = function() {
                tasks[index].tasks_today = this.value;
            };
            todayDiv.appendChild(todayTextarea);
            rowDiv.appendChild(todayDiv);

            var futureDiv = document.createElement('div');
            var futureLabel = document.createElement('label');
            futureLabel.style.fontWeight = '600';
            futureLabel.style.marginBottom = '5px';
            futureLabel.style.display = 'block';
            futureLabel.textContent = tasksFuture;
            futureDiv.appendChild(futureLabel);

            var futureTextarea = document.createElement('textarea');
            futureTextarea.value = task.tasks_future || '';
            futureTextarea.setAttribute('data-index', index);
            futureTextarea.onchange = function() {
                tasks[index].tasks_future = this.value;
            };
            futureDiv.appendChild(futureTextarea);
            rowDiv.appendChild(futureDiv);

            entry.appendChild(rowDiv);
            container.appendChild(entry);
        });
    }

    /**
     * Add a new logbook entry.
     */
    function addEntry() {
        tasks.push({date: '', tasks_today: '', tasks_future: '', status: 'ontime'});
        renderEntries();
    }

    // ---- Serialization ----

    /**
     * Build the data object for autosave from config.fields + dates + optional JSON blobs.
     *
     * @return {Object} Serialized form data.
     */
    function serializeData() {
        var data = {};
        var dates = getDateValues();
        var fields = config.fields || [];
        var i, el;

        // Collect simple fields by ID.
        for (i = 0; i < fields.length; i++) {
            el = document.getElementById(fields[i]);
            if (el) {
                data[fields[i]] = el.value;
            }
        }

        // Append interacteurs JSON if present.
        if (config.interacteurs) {
            data.interacteurs_data = JSON.stringify(interacteurs);
        }

        // Append tasks JSON if present.
        if (config.tasks) {
            data.tasks_data = JSON.stringify(tasks);
        }

        // Append date values.
        data.submission_date = dates.submission_date;
        data.deadline_date = dates.deadline_date;

        return data;
    }

    // ---- Public API ----

    return {
        /**
         * Initialise the teacher model page.
         *
         * @param {Object} cfg Configuration object with:
         *   - cmid {number}          Course module ID
         *   - step {number}          Step number (4-8)
         *   - autosaveInterval {number} Autosave interval in ms
         *   - fields {string[]}      Array of field IDs to serialize
         *   - interacteurs {Array}   (optional) Initial interactors data for step 4
         *   - tasks {Array}          (optional) Initial logbook entries for step 8
         *   - strings {Object}       (optional) Localised strings for step 8 labels
         */
        init: function(cfg) {
            config = cfg || {};
            var cmid = config.cmid;
            var step = config.step;
            var autosaveInterval = config.autosaveInterval || 30000;

            // Initialise interactors if provided (step 4).
            if (config.interacteurs) {
                interacteurs = config.interacteurs;
                renderInteractors();

                // Bind add-interactor button.
                var addInteractorBtn = document.getElementById('addInteractorBtn');
                if (addInteractorBtn) {
                    addInteractorBtn.addEventListener('click', function() {
                        addInteractor();
                    });
                }
            }

            // Initialise logbook entries if provided (step 8).
            if (config.tasks) {
                tasks = config.tasks;
                renderEntries();

                // Bind add-entry button.
                var addEntryBtn = document.getElementById('addEntryBtn');
                if (addEntryBtn) {
                    addEntryBtn.addEventListener('click', function() {
                        addEntry();
                    });
                }
            }

            // Initialise autosave.
            Autosave.init({
                cmid: cmid,
                step: step,
                groupid: 0,
                mode: 'teacher',
                interval: autosaveInterval,
                formSelector: '#teacherModelForm',
                serialize: serializeData
            });

            // Save button: save then redirect to correction models hub.
            var saveButton = document.getElementById('saveButton');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    var originalOnSave = Autosave.onSave;
                    Autosave.onSave = function(response) {
                        if (originalOnSave) {
                            originalOnSave(response);
                        }
                        setTimeout(function() {
                            window.location.href = M.cfg.wwwroot +
                                '/mod/gestionprojet/view.php?id=' + cmid + '&page=correctionmodels';
                        }, 800);
                    };
                    Autosave.save();
                });
            }
        }
    };
});

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
 * Glue module for the step 8 teacher correction model page.
 *
 * Hosts the logbook entry list (tasks_data) and wires both autosave and the
 * AI instructions buttons. Replaces the legacy inline script that lived in
 * pages/step8_teacher.php.
 *
 * @module     mod_gestionprojet/step8_teacher_init
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_gestionprojet/teacher_model',
    'mod_gestionprojet/generate_ai_instructions',
    'mod_gestionprojet/autosave'
], function(TeacherModel, GenerateAi, Autosave) {

    /**
     * Read the current logbook entries from the rendered DOM. Mirrors the
     * structure produced by teacher_model.renderEntries().
     *
     * @return {Array<Object>}
     */
    function readTasksFromDom() {
        var tasks = [];
        var entries = document.querySelectorAll('#logbookContainer .logbook-entry');
        entries.forEach(function(entry) {
            var dateEl = entry.querySelector('input[type="date"]');
            var statusEl = entry.querySelector('select');
            var textareas = entry.querySelectorAll('textarea');
            tasks.push({
                date: dateEl ? dateEl.value : '',
                status: statusEl ? statusEl.value : 'ontime',
                tasks_today: textareas[0] ? textareas[0].value : '',
                tasks_future: textareas[1] ? textareas[1].value : ''
            });
        });
        return tasks;
    }

    /**
     * Heuristic: any non-empty string field across any entry means non-empty.
     *
     * @param {Array<Object>} tasks
     * @return {boolean}
     */
    function isTasksEmpty(tasks) {
        for (var i = 0; i < tasks.length; i++) {
            var t = tasks[i];
            for (var k in t) {
                if (Object.prototype.hasOwnProperty.call(t, k)
                        && typeof t[k] === 'string' && t[k].trim() !== '') {
                    return false;
                }
            }
        }
        return true;
    }

    return {
        /**
         * @param {Object} cfg
         * @param {number} cfg.cmid
         * @param {number} cfg.autosaveInterval
         * @param {Array} cfg.tasks Initial logbook entries.
         * @param {boolean} cfg.aiEnabled
         * @param {string} cfg.defaultText
         */
        init: function(cfg) {
            TeacherModel.init({
                cmid: cfg.cmid,
                step: 8,
                autosaveInterval: cfg.autosaveInterval,
                fields: ['ai_instructions'],
                tasks: cfg.tasks || []
            });

            GenerateAi.init({
                cmid: cfg.cmid,
                step: 8,
                aiEnabled: cfg.aiEnabled,
                defaultText: cfg.defaultText,
                containerSelector: '#aiInstructionsActions',
                textareaSelector: '#ai_instructions',
                getModelData: function() {
                    return {tasks_data: JSON.stringify(readTasksFromDom())};
                },
                isModelEmpty: function() {
                    return isTasksEmpty(readTasksFromDom());
                },
                onUpdated: function() {
                    Autosave.save();
                }
            });
        }
    };
});

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
 * Glue module for teacher correction model pages (steps 5-8).
 *
 * Wraps teacher_model + generate_ai_instructions so that PHP can wire both
 * via a single js_call_amd. Replaces the legacy inline scripts that used to
 * live in pages/step{5,6,7,8}_teacher.php.
 *
 * @module     mod_gestionprojet/teacher_step_init
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'mod_gestionprojet/teacher_model',
    'mod_gestionprojet/generate_ai_instructions',
    'mod_gestionprojet/autosave'
], function(TeacherModel, GenerateAi, Autosave) {

    /**
     * Build a getModelData callback that reads values from a list of input ids.
     *
     * @param {string[]} ids
     * @return {Function}
     */
    function buildGetModelData(ids) {
        return function() {
            var data = {};
            for (var i = 0; i < ids.length; i++) {
                var el = document.getElementById(ids[i]);
                data[ids[i]] = el ? el.value : '';
            }
            return data;
        };
    }

    /**
     * Build an isModelEmpty callback over the same id list.
     *
     * @param {string[]} ids
     * @return {Function}
     */
    function buildIsModelEmpty(ids) {
        return function() {
            for (var i = 0; i < ids.length; i++) {
                var el = document.getElementById(ids[i]);
                if (el && el.value && String(el.value).trim() !== '') {
                    return false;
                }
            }
            return true;
        };
    }

    return {
        /**
         * Init both autosave (via teacher_model) and AI buttons for a teacher page.
         *
         * @param {Object} cfg
         * @param {number} cfg.cmid
         * @param {number} cfg.step               Step number (5-8).
         * @param {number} cfg.autosaveInterval   Interval in ms.
         * @param {string[]} cfg.fields           Field ids serialized for autosave.
         * @param {string[]} cfg.aiFields         Field ids exposed to AI generator.
         * @param {boolean} cfg.aiEnabled
         * @param {string} cfg.defaultText        Localised default ai_instructions text.
         * @param {string} [cfg.aiContainerSelector]   Defaults to '#aiInstructionsActions'.
         * @param {string} [cfg.aiTextareaSelector]    Defaults to '#ai_instructions'.
         */
        init: function(cfg) {
            TeacherModel.init({
                cmid: cfg.cmid,
                step: cfg.step,
                autosaveInterval: cfg.autosaveInterval,
                fields: cfg.fields
            });

            GenerateAi.init({
                cmid: cfg.cmid,
                step: cfg.step,
                aiEnabled: cfg.aiEnabled,
                defaultText: cfg.defaultText,
                containerSelector: cfg.aiContainerSelector || '#aiInstructionsActions',
                textareaSelector: cfg.aiTextareaSelector || '#ai_instructions',
                getModelData: buildGetModelData(cfg.aiFields || []),
                isModelEmpty: buildIsModelEmpty(cfg.aiFields || []),
                onUpdated: function() {
                    Autosave.save();
                }
            });
        }
    };
});

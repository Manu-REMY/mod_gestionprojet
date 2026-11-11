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
 * AI instructions buttons of the step 9 (FAST) correction model page (moved out of the page, v2.13.7).
 *
 * @module     mod_gestionprojet/fast_ai_instructions
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_gestionprojet/generate_ai_instructions'], function(GenerateAi) {
    'use strict';

    /**
     * Whether the FAST model holds nothing yet.
     *
     * @param {string} json Content of the hidden FAST data field
     * @returns {boolean}
     */
    function isEmptyFast(json) {
        if (!json) {
            return true;
        }
        try {
            var obj = JSON.parse(json);
            if (!obj || typeof obj !== 'object') {
                return true;
            }
            if (Array.isArray(obj)) {
                return obj.length === 0;
            }
            // FAST data: an object of arrays and objects; empty when none of them holds anything
            // (flags such as populatedFromCdcf: true do not count, as before).
            return !Object.keys(obj).some(function(k) {
                var v = obj[k];
                return v && (Array.isArray(v) ? v.length > 0 : Object.keys(v).length > 0);
            });
        } catch (e) {
            return false;
        }
    }

    return {
        /**
         * Wire the "Generate" and "Default" buttons of the AI instructions.
         *
         * @param {Object} cfg cmid, aiEnabled, defaultText
         */
        init: function(cfg) {
            var dataId = 'fast-data-' + cfg.cmid;
            var textareaId = 'fast-ai-' + cfg.cmid;
            GenerateAi.init({
                cmid: cfg.cmid,
                step: 9,
                aiEnabled: cfg.aiEnabled,
                defaultText: cfg.defaultText,
                containerSelector: '#aiInstructionsActions-' + cfg.cmid,
                textareaSelector: '#' + textareaId,
                getModelData: function() {
                    var input = document.getElementById(dataId);
                    return {data_json: input ? input.value : ''};
                },
                isModelEmpty: function() {
                    var input = document.getElementById(dataId);
                    return isEmptyFast(input ? input.value : '');
                },
                onUpdated: function() {
                    // fast_editor saves the AI instructions on an input event of this textarea.
                    var ta = document.getElementById(textareaId);
                    if (ta) {
                        ta.dispatchEvent(new Event('input', {bubbles: true}));
                    }
                }
            });
        }
    };
});

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
 * Generate AI correction instructions buttons for teacher correction models.
 *
 * @module     mod_gestionprojet/generate_ai_instructions
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/notification'], function($, Str, Notification) {

    function fetchStrings() {
        return Str.get_strings([
            {key: 'ai_instructions_btn_default',       component: 'mod_gestionprojet'},
            {key: 'ai_instructions_btn_generate',      component: 'mod_gestionprojet'},
            {key: 'ai_instructions_btn_generating',    component: 'mod_gestionprojet'},
            {key: 'ai_instructions_tooltip_empty',     component: 'mod_gestionprojet'},
            {key: 'ai_instructions_tooltip_disabled',  component: 'mod_gestionprojet'},
            {key: 'ai_instructions_confirm_replace',   component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_generic',     component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_disabled',    component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_no_provider', component: 'mod_gestionprojet'},
            {key: 'ai_instructions_error_model_empty', component: 'mod_gestionprojet'},
            {key: 'ai_instructions_success',           component: 'mod_gestionprojet'}
        ]).then(function(values) {
            return {
                btnDefault:        values[0],
                btnGenerate:       values[1],
                btnGenerating:     values[2],
                tooltipEmpty:      values[3],
                tooltipDisabled:   values[4],
                confirmReplace:    values[5],
                errorGeneric:      values[6],
                errorDisabled:     values[7],
                errorNoProvider:   values[8],
                errorModelEmpty:   values[9],
                success:           values[10]
            };
        });
    }

    function errorMessage(strings, code) {
        if (code === 'ai_disabled')  { return strings.errorDisabled; }
        if (code === 'no_provider')  { return strings.errorNoProvider; }
        if (code === 'model_empty')  { return strings.errorModelEmpty; }
        return strings.errorGeneric;
    }

    /**
     * Read and parse JSON from a hidden input identified by its DOM id.
     *
     * @param {string} fieldId
     * @returns {Object|null} Parsed object, or null on missing/invalid input.
     */
    function readJsonField(fieldId) {
        var el = document.getElementById(fieldId);
        if (!el) { return null; }
        var raw = (el.value || '').trim();
        if (raw === '') { return null; }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    /**
     * Default emptiness check for a CDCF-style payload read from a hidden
     * JSON field: empty when there are neither service functions nor
     * constraints declared.
     *
     * @param {Object|null} obj
     * @returns {boolean}
     */
    function defaultIsCdcfEmpty(obj) {
        if (!obj || typeof obj !== 'object') { return true; }
        var fs = Array.isArray(obj.fonctionsService) ? obj.fonctionsService : [];
        var co = Array.isArray(obj.contraintes) ? obj.contraintes : [];
        return fs.length === 0 && co.length === 0;
    }

    return {
        /**
         * Initialise the two buttons for one teacher page.
         *
         * Two ways to expose model data to the AI generator:
         *   1. Pass `modelDataField` (id of a hidden input whose value is JSON).
         *      The whole parsed object is sent as `model_data`. Emptiness is
         *      derived heuristically (no service functions and no constraints).
         *   2. Pass `getModelData` / `isModelEmpty` callbacks (legacy API,
         *      still used by step5..step9 teacher pages).
         *
         * If both are provided, `modelDataField` takes precedence.
         *
         * @param {Object} cfg
         * @param {number} cfg.cmid                Course module id.
         * @param {number} cfg.step                Step number (4-9).
         * @param {string} cfg.defaultText         Localised default ai_instructions text.
         * @param {boolean} cfg.aiEnabled          Whether AI is enabled at activity level.
         * @param {string} cfg.containerSelector   CSS selector of the buttons container.
         * @param {string} cfg.textareaSelector    CSS selector of the ai_instructions textarea.
         * @param {string} [cfg.modelDataField]    DOM id of a hidden input holding model JSON.
         * @param {Function} [cfg.getModelData]    Legacy: returns the current model fields object.
         * @param {Function} [cfg.isModelEmpty]    Legacy: returns true if all model fields are empty.
         * @param {Function} [cfg.onUpdated]       Optional callback after textarea is filled.
         */
        init: function(cfg) {
            // Resolve the model-data accessor depending on which API the caller
            // provided. `modelDataField` (new) wins over legacy callbacks.
            var getModelData;
            var isModelEmpty;
            if (cfg.modelDataField) {
                getModelData = function() {
                    return readJsonField(cfg.modelDataField) || {};
                };
                isModelEmpty = function() {
                    return defaultIsCdcfEmpty(readJsonField(cfg.modelDataField));
                };
            } else {
                // Legacy callbacks: bind `this` to cfg so internal references
                // such as `this.getModelData()` keep resolving.
                getModelData = typeof cfg.getModelData === 'function'
                    ? cfg.getModelData.bind(cfg) : function() { return {}; };
                isModelEmpty = typeof cfg.isModelEmpty === 'function'
                    ? cfg.isModelEmpty.bind(cfg) : function() { return true; };
            }

            fetchStrings().then(function(strings) {
                var $container = $(cfg.containerSelector);
                var $textarea  = $(cfg.textareaSelector);

                if ($container.length === 0 || $textarea.length === 0) {
                    return;
                }

                var $btnDefault = $('<button type="button" class="btn btn-secondary btn-ai-default">')
                    .text(strings.btnDefault);

                var $btnGenerate = $('<button type="button" class="btn btn-primary btn-ai-generate">')
                    .text(strings.btnGenerate);

                $container.empty().append($btnDefault).append(' ').append($btnGenerate);

                $btnDefault.on('click', function() {
                    if ($textarea.val().trim() !== '' && !window.confirm(strings.confirmReplace)) {
                        return;
                    }
                    $textarea.val(cfg.defaultText).trigger('change').trigger('input');
                    if (typeof cfg.onUpdated === 'function') { cfg.onUpdated(); }
                });

                function refreshGenerateState() {
                    if (!cfg.aiEnabled) {
                        $btnGenerate.prop('disabled', true).attr('title', strings.tooltipDisabled);
                        return;
                    }
                    if (isModelEmpty()) {
                        $btnGenerate.prop('disabled', true).attr('title', strings.tooltipEmpty);
                    } else {
                        $btnGenerate.prop('disabled', false).removeAttr('title');
                    }
                }
                refreshGenerateState();
                var formEventNs = 'input.aiGen' + cfg.step + ' change.aiGen' + cfg.step;
                $container.closest('form').off(formEventNs).on(formEventNs, refreshGenerateState);

                $btnGenerate.on('click', function() {
                    var $btn = $(this);
                    // Disable immediately to prevent rapid double-clicks during the confirm prompt.
                    $btn.prop('disabled', true);

                    if ($textarea.val().trim() !== '' && !window.confirm(strings.confirmReplace)) {
                        refreshGenerateState();
                        return;
                    }

                    var originalLabel = $btn.text();
                    $btn.text(strings.btnGenerating);

                    $.ajax({
                        url:  M.cfg.wwwroot + '/mod/gestionprojet/ajax/generate_ai_instructions.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            id: cfg.cmid,
                            step: cfg.step,
                            sesskey: M.cfg.sesskey,
                            model_data: JSON.stringify(getModelData())
                        }
                    }).done(function(resp) {
                        if (resp && resp.success) {
                            $textarea.val(resp.instructions).trigger('change').trigger('input');
                            if (typeof cfg.onUpdated === 'function') { cfg.onUpdated(); }
                            Notification.addNotification({message: strings.success, type: 'success'});
                        } else {
                            Notification.addNotification({
                                message: errorMessage(strings, resp && resp.error),
                                type: 'error'
                            });
                        }
                    }).fail(function(jqXHR) {
                        // On HTTP 400 the response body is still JSON with an error code.
                        var resp = jqXHR && jqXHR.responseJSON;
                        Notification.addNotification({
                            message: errorMessage(strings, resp && resp.error),
                            type: 'error'
                        });
                    }).always(function() {
                        $btn.text(originalLabel);
                        refreshGenerateState();
                    });
                });
            }).catch(Notification.exception);
        }
    };
});

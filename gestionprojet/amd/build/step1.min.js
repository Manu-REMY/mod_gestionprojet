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
 * Step 1: Fiche Descriptive du Projet
 *
 * Handles autosave with custom serialization for competences checkboxes,
 * readonly mode for students, and PDF export.
 *
 * @module     mod_gestionprojet/step1
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'mod_gestionprojet/autosave', 'core/notification'], function($, Autosave, Notification) {
    return {
        /**
         * Initialize step 1 module.
         *
         * @param {Object} config Configuration object passed from PHP.
         * @param {number} config.cmid Course module ID.
         * @param {number} config.step Step number (always 1).
         * @param {number} config.groupid Group ID (0 for teacher pages).
         * @param {number} config.autosaveInterval Autosave interval in milliseconds.
         * @param {boolean} config.isReadonly Whether the form is readonly (student view).
         * @param {Object} config.strings Localised strings.
         */
        init: function(config) {
            var cmid = config.cmid;
            var step = config.step;
            var groupid = config.groupid;
            var autosaveInterval = config.autosaveInterval;
            var isReadonly = config.isReadonly;
            var STRINGS = config.strings || {};

            /**
             * Custom serialization for step 1 form data.
             * Handles regular fields plus competences checkbox array.
             *
             * @return {Object} Serialized form data.
             */
            var serializeData = function() {
                var formData = {};
                var form = document.getElementById('descriptionForm');

                // Collect regular fields.
                form.querySelectorAll('input[type="text"], select, textarea').forEach(function(field) {
                    if (field.name && !field.name.includes('[]')) {
                        formData[field.name] = field.value;
                    }
                });

                // Collect competences as JSON array.
                var competences = [];
                form.querySelectorAll('input[name="competences[]"]:checked').forEach(function(cb) {
                    competences.push(cb.value);
                });
                formData['competences'] = JSON.stringify(competences);

                return formData;
            };

            // Initialize autosave for editable mode.
            if (!isReadonly) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#descriptionForm',
                    serialize: serializeData
                });
            }

            // Lock form elements if readonly (student view).
            if (isReadonly) {
                $('#descriptionForm input, #descriptionForm select, #descriptionForm textarea')
                    .prop('disabled', true);
            }

            // Export PDF button handler.
            $('#exportPdfBtn').on('click', function() {
                alert(STRINGS.export_pdf_coming_soon || 'Export PDF sera implement\u00e9 avec TCPDF c\u00f4t\u00e9 serveur');
                window.location.href = M.cfg.wwwroot + '/mod/gestionprojet/export_pdf.php?id=' + cmid + '&step=1';
            });
        }
    };
});

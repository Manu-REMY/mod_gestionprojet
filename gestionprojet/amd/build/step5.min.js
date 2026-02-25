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

define(['jquery', 'mod_gestionprojet/autosave', 'core/str'], function ($, Autosave, Str) {
    return {
        init: function (config) {
            var cmid = config.cmid;
            var step = config.step;
            var groupid = config.groupid;
            var autosaveInterval = config.autosaveInterval;
            var isLocked = config.isLocked;
            var STRINGS = config.strings || {};

            // Submit / Revert
            $('#submitButton').on('click', function () {
                if (confirm(STRINGS.confirm_submission)) {
                    submitAction('submit');
                }
            });

            $('#revertButton').on('click', function () {
                if (confirm(STRINGS.confirm_revert)) {
                    submitAction('revert');
                }
            });

            $('#exportPdfBtn').on('click', function () {
                alert(STRINGS.export_pdf_coming_soon || 'Export PDF coming soon');
            });

            function submitAction(action) {
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit.php',
                    method: 'POST',
                    data: {
                        id: cmid,
                        step: step,
                        action: action,
                        sesskey: M.cfg.sesskey
                    },
                    success: function (response) {
                        try {
                            var res = JSON.parse(response);
                            if (res.success) {
                                window.location.reload();
                            } else {
                                alert('Error: ' + (res.message || 'Unknown error'));
                            }
                        } catch (e) {
                            console.error('Submission error', e);
                        }
                    }
                });
            }

            // Custom serialization
            var serializeData = function () {
                var formData = {};

                // Collect regular fields (text inputs, textareas, date)
                $('#essaiForm').find('input[type="text"], input[type="date"], textarea').each(function () {
                    if (this.name && !this.name.startsWith('precaution_')) {
                        formData[this.name] = this.value;
                    }
                });

                // Collect precautions as JSON array
                var precautions = [];
                for (var i = 1; i <= 6; i++) {
                    var input = document.getElementById('precaution_' + i);
                    if (input) {
                        precautions.push(input.value);
                    }
                }
                formData['precautions'] = JSON.stringify(precautions);

                return formData;
            };

            // Autosave
            if (!isLocked) {
                Autosave.init({
                    cmid: cmid,
                    step: step,
                    groupid: groupid,
                    interval: autosaveInterval,
                    formSelector: '#essaiForm',
                    serialize: serializeData
                });
            }
        }
    };
});

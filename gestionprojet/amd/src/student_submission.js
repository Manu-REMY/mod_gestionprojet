// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student submission handling for gestionprojet.
 *
 * @module     mod_gestionprojet/student_submission
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    var studentSubmission = {
        cmid: 0,
        step: 0,
        groupid: 0,
        strings: {},

        /**
         * Initialize student submission.
         *
         * @param {Object} config Configuration object
         * @param {number} config.cmid Course module ID
         * @param {number} config.step Step number
         * @param {number} config.groupid Group ID (0 for individual)
         * @param {Object} config.strings Localised strings
         */
        init: function(config) {
            this.cmid = config.cmid;
            this.step = config.step;
            this.groupid = config.groupid || 0;
            this.strings = config.strings || {};

            this._bindEvents();
        },

        /**
         * Bind click event to the submit button.
         *
         * @private
         */
        _bindEvents: function() {
            var self = this;
            $('#submitStepBtn').on('click', function() {
                self._submitStep();
            });
        },

        /**
         * Set the button content safely using DOM methods.
         *
         * @param {HTMLElement} btn The button element
         * @param {string} icon The icon character
         * @param {string} text The button label text
         * @private
         */
        _setButtonContent: function(btn, icon, text) {
            while (btn.firstChild) {
                btn.removeChild(btn.firstChild);
            }
            var span = document.createElement('span');
            span.textContent = icon;
            btn.appendChild(span);
            btn.appendChild(document.createTextNode(' ' + text));
        },

        /**
         * Handle step submission.
         *
         * @private
         */
        _submitStep: function() {
            var self = this;

            if (!confirm(self.strings.confirm_submit)) {
                return;
            }

            var btn = document.getElementById('submitStepBtn');
            btn.disabled = true;
            // Hourglass icon + submitting text.
            self._setButtonContent(btn, '\u231B', self.strings.submitting);

            var formData = new FormData();
            formData.append('cmid', self.cmid);
            formData.append('step', self.step);
            formData.append('action', 'submit');
            formData.append('groupid', self.groupid);
            formData.append('sesskey', M.cfg.sesskey);

            fetch(M.cfg.wwwroot + '/mod/gestionprojet/ajax/submit_step.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    alert(self.strings.submissionsuccess);
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    // Outbox icon + submit text.
                    self._setButtonContent(btn, '\uD83D\uDCE4', self.strings.submit_step);
                    alert(data.message || self.strings.submissionerror);
                }
            })
            .catch(function(error) {
                btn.disabled = false;
                // Outbox icon + submit text.
                self._setButtonContent(btn, '\uD83D\uDCE4', self.strings.submit_step);
                alert(self.strings.submissionerror);
                window.console.error('Error:', error);
            });
        }
    };

    return {
        /**
         * Initialize student submission module.
         *
         * @param {Object} config Configuration object
         */
        init: function(config) {
            studentSubmission.init(config);
        }
    };
});

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
 * Grading interface module for the teacher grading page.
 *
 * Handles:
 * - Auto-refresh when AI evaluation is pending/processing
 * - AI progress initialization and evaluation button handlers
 * - Unlock submission functionality
 * - Bulk reevaluate functionality
 * - Apply-with-modifications button (pre-fills grade form)
 *
 * @module     mod_gestionprojet/grading
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'mod_gestionprojet/ai_progress', 'mod_gestionprojet/notifications'], function($, AIProgress, Notifications) {

    /**
     * Helper to set a button into its loading state (spinner + text).
     *
     * @param {HTMLElement} btn The button element
     * @param {String} text The loading text to display
     */
    var setButtonLoading = function(btn, text) {
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');
        while (btn.firstChild) {
            btn.removeChild(btn.firstChild);
        }
        btn.appendChild(spinner);
        btn.appendChild(document.createTextNode(' ' + text));
    };

    /**
     * Helper to reset a button to its default state with an emoji prefix.
     *
     * @param {HTMLElement} btn The button element
     * @param {String} emoji The emoji prefix
     * @param {String} label The button label text
     */
    var resetButton = function(btn, emoji, label) {
        while (btn.firstChild) {
            btn.removeChild(btn.firstChild);
        }
        btn.appendChild(document.createTextNode(emoji + ' ' + label));
    };

    return {
        /**
         * Initialize the grading page functionality.
         *
         * @param {Object} config Configuration object passed from PHP
         * @param {Number} config.cmid Course module ID
         * @param {Number} config.step Current step number
         * @param {Number} config.submissionId Submission ID (0 if none)
         * @param {Boolean} config.aiEnabled Whether AI evaluation is enabled
         * @param {String} config.aiStatus AI evaluation status (empty if no evaluation)
         * @param {Number} config.parsedGrade Parsed AI grade for apply-with-modifications
         * @param {String} config.parsedFeedback Parsed AI feedback for apply-with-modifications
         * @param {String} config.confirmUnlockMsg Confirmation message for unlocking submission
         * @param {String} config.confirmBulkMsg Confirmation message for bulk reevaluate
         * @param {String} config.errorMsg Generic error message
         * @param {String} config.networkErrorMsg Network error message
         * @param {String} config.unlockBtnLabel Label for unlock button
         * @param {String} config.bulkBtnLabel Label for bulk reevaluate button
         * @param {String} config.bulkProcessingLabel Label shown during bulk processing
         */
        init: function(config) {
            var cmid = config.cmid;
            var step = config.step;
            var submissionId = config.submissionId || 0;

            // Initialize notifications.
            Notifications.init();

            // Initialize AI progress.
            AIProgress.init({
                cmid: cmid,
                step: step,
                submissionid: submissionId,
                containerSelector: '#ai-progress-container'
            });

            // Script Block 1: Auto-refresh when AI evaluation is pending or processing.
            if (config.aiStatus === 'pending' || config.aiStatus === 'processing') {
                setTimeout(function() {
                    location.reload();
                }, 10000);
            }

            // Script Block 2: AI evaluation button event handlers.
            this._bindAIButtons(cmid, step, submissionId);

            // Apply-with-modifications button (replaces inline onclick).
            this._bindApplyModified(config.parsedGrade, config.parsedFeedback);

            // Script Block 3: Unlock submission and bulk reevaluate.
            this._bindUnlockButton(config);
            this._bindBulkReevaluateButton(config);
        },

        /**
         * Bind AI evaluation button event handlers.
         *
         * @param {Number} cmid Course module ID
         * @param {Number} step Step number
         * @param {Number} submissionId Submission ID
         * @private
         */
        _bindAIButtons: function(cmid, step, submissionId) {
            // Trigger AI evaluation button.
            var triggerBtn = document.getElementById('btn-trigger-ai-eval');
            if (triggerBtn) {
                $(triggerBtn).on('click', function() {
                    var btn = $(this);
                    AIProgress.triggerEvaluation(
                        parseInt(btn.data('cmid'), 10),
                        parseInt(btn.data('step'), 10),
                        parseInt(btn.data('submissionid'), 10)
                    );
                });
            }

            // Apply AI grade button.
            var applyBtn = document.getElementById('btn-apply-ai-grade');
            if (applyBtn) {
                $(applyBtn).on('click', function() {
                    var btn = $(this);
                    AIProgress.applyGrade(
                        parseInt(btn.data('cmid'), 10),
                        parseInt(btn.data('evaluationid'), 10)
                    );
                });
            }

            // Retry AI evaluation button.
            var retryBtn = document.getElementById('btn-retry-ai-eval');
            if (retryBtn) {
                $(retryBtn).on('click', function() {
                    AIProgress.retryEvaluation(
                        cmid,
                        step,
                        submissionId
                    );
                });
            }
        },

        /**
         * Bind the apply-with-modifications button to pre-fill the grading form.
         *
         * @param {Number} parsedGrade The AI-parsed grade
         * @param {String} parsedFeedback The AI-parsed feedback
         * @private
         */
        _bindApplyModified: function(parsedGrade, parsedFeedback) {
            var applyModifiedBtn = document.getElementById('btn-apply-modified');
            if (applyModifiedBtn) {
                $(applyModifiedBtn).on('click', function() {
                    var gradeInput = document.getElementById('grade');
                    var feedbackInput = document.getElementById('feedback');
                    if (gradeInput) {
                        gradeInput.value = parsedGrade;
                    }
                    if (feedbackInput) {
                        feedbackInput.value = parsedFeedback;
                    }
                    if (gradeInput) {
                        gradeInput.focus();
                    }
                });
            }
        },

        /**
         * Bind the unlock submission button.
         *
         * @param {Object} config Configuration object
         * @private
         */
        _bindUnlockButton: function(config) {
            var unlockBtn = document.getElementById('btn-unlock-submission');
            if (!unlockBtn) {
                return;
            }

            $(unlockBtn).on('click', function() {
                if (!confirm(config.confirmUnlockMsg)) {
                    return;
                }

                var btn = this;
                btn.disabled = true;
                setButtonLoading(btn, '...');

                var formData = new FormData();
                formData.append('cmid', btn.dataset.cmid);
                formData.append('step', btn.dataset.step);
                formData.append('action', 'unlock');
                formData.append('groupid', btn.dataset.groupid);
                formData.append('userid', btn.dataset.userid);
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
                        location.reload();
                    } else {
                        alert(data.message || config.errorMsg);
                        btn.disabled = false;
                        resetButton(btn, '\uD83D\uDD13', config.unlockBtnLabel);
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert(config.networkErrorMsg);
                    btn.disabled = false;
                    resetButton(btn, '\uD83D\uDD13', config.unlockBtnLabel);
                });
            });
        },

        /**
         * Bind the bulk reevaluate button.
         *
         * @param {Object} config Configuration object
         * @private
         */
        _bindBulkReevaluateButton: function(config) {
            var bulkReevalBtn = document.getElementById('btn-bulk-reevaluate');
            if (!bulkReevalBtn) {
                return;
            }

            $(bulkReevalBtn).on('click', function() {
                if (!confirm(config.confirmBulkMsg)) {
                    return;
                }

                var btn = this;
                btn.disabled = true;
                setButtonLoading(btn, config.bulkProcessingLabel);

                var formData = new FormData();
                formData.append('id', btn.dataset.cmid);
                formData.append('step', btn.dataset.step);
                formData.append('sesskey', M.cfg.sesskey);

                fetch(M.cfg.wwwroot + '/mod/gestionprojet/ajax/bulk_reevaluate.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || config.errorMsg);
                        btn.disabled = false;
                        resetButton(btn, '\uD83D\uDD04', config.bulkBtnLabel);
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert(config.networkErrorMsg);
                    btn.disabled = false;
                    resetButton(btn, '\uD83D\uDD04', config.bulkBtnLabel);
                });
            });
        }
    };
});

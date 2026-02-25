// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AI Progress indicators and evaluation status management.
 *
 * @module     mod_gestionprojet/ai_progress
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    var AIProgress = {
        cmid: 0,
        step: 0,
        submissionid: 0,
        evaluationid: 0,
        pollInterval: 5000, // 5 seconds
        pollTimer: null,
        maxPolls: 60, // Max 5 minutes of polling
        pollCount: 0,
        containerSelector: '#ai-progress-container',
        onComplete: null,
        onError: null,

        /**
         * Initialize AI progress tracking.
         *
         * @param {Object} config Configuration object
         */
        init: function(config) {
            this.cmid = config.cmid;
            this.step = config.step || 0;
            this.submissionid = config.submissionid || 0;
            this.evaluationid = config.evaluationid || 0;
            this.pollInterval = config.pollInterval || 5000;
            this.containerSelector = config.containerSelector || '#ai-progress-container';
            this.onComplete = config.onComplete || null;
            this.onError = config.onError || null;

            // Initialize UI components
            this.createProgressUI();

            // Bind event handlers
            this.bindEvents();
        },

        /**
         * Create the progress UI elements.
         */
        createProgressUI: function() {
            var container = $(this.containerSelector);
            if (!container.length) {
                return;
            }

            // Progress overlay template
            var progressHtml = '<div id="ai-progress-overlay" style="display: none;">' +
                '<div class="ai-progress-modal">' +
                '<div class="ai-progress-header">' +
                '<span class="ai-progress-icon">🤖</span>' +
                '<span class="ai-progress-title">Évaluation IA en cours</span>' +
                '</div>' +
                '<div class="ai-progress-body">' +
                '<div class="ai-progress-spinner"></div>' +
                '<div class="ai-progress-status">Initialisation...</div>' +
                '<div class="ai-progress-bar-container">' +
                '<div class="ai-progress-bar"></div>' +
                '</div>' +
                '<div class="ai-progress-details"></div>' +
                '</div>' +
                '</div>' +
                '</div>';

            container.append(progressHtml);
            this.injectStyles();
        },

        /**
         * Inject CSS styles for the progress UI.
         */
        injectStyles: function() {
            if ($('#ai-progress-styles').length) {
                return;
            }

            var styles = '<style id="ai-progress-styles">' +
                '#ai-progress-overlay {' +
                '  position: fixed; top: 0; left: 0; width: 100%; height: 100%;' +
                '  background: rgba(0, 0, 0, 0.5); z-index: 10000;' +
                '  display: flex; align-items: center; justify-content: center;' +
                '}' +
                '.ai-progress-modal {' +
                '  background: white; border-radius: 12px; padding: 30px;' +
                '  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);' +
                '  min-width: 400px; max-width: 90%; text-align: center;' +
                '}' +
                '.ai-progress-header {' +
                '  display: flex; align-items: center; justify-content: center;' +
                '  gap: 12px; margin-bottom: 20px;' +
                '}' +
                '.ai-progress-icon {' +
                '  font-size: 2em;' +
                '}' +
                '.ai-progress-title {' +
                '  font-size: 1.25em; font-weight: 600; color: #1a56db;' +
                '}' +
                '.ai-progress-spinner {' +
                '  width: 50px; height: 50px; margin: 20px auto;' +
                '  border: 4px solid #e5e7eb; border-top-color: #1a56db;' +
                '  border-radius: 50%; animation: ai-spin 1s linear infinite;' +
                '}' +
                '@keyframes ai-spin {' +
                '  to { transform: rotate(360deg); }' +
                '}' +
                '.ai-progress-status {' +
                '  font-size: 1.1em; color: #374151; margin: 15px 0;' +
                '}' +
                '.ai-progress-bar-container {' +
                '  background: #e5e7eb; border-radius: 10px; height: 8px;' +
                '  overflow: hidden; margin: 20px 0;' +
                '}' +
                '.ai-progress-bar {' +
                '  background: linear-gradient(90deg, #1a56db, #3b82f6);' +
                '  height: 100%; width: 0%; transition: width 0.5s ease;' +
                '  border-radius: 10px;' +
                '}' +
                '.ai-progress-details {' +
                '  font-size: 0.85em; color: #6b7280; margin-top: 10px;' +
                '}' +
                '.ai-progress-inline {' +
                '  display: inline-flex; align-items: center; gap: 8px;' +
                '  padding: 8px 16px; background: #f0f4ff; border-radius: 8px;' +
                '  border: 1px solid #c2d6ff;' +
                '}' +
                '.ai-progress-inline .spinner-small {' +
                '  width: 16px; height: 16px; border: 2px solid #e5e7eb;' +
                '  border-top-color: #1a56db; border-radius: 50%;' +
                '  animation: ai-spin 1s linear infinite;' +
                '}' +
                '.ai-status-badge {' +
                '  display: inline-flex; align-items: center; gap: 6px;' +
                '  padding: 6px 12px; border-radius: 6px; font-size: 0.9em;' +
                '}' +
                '.ai-status-pending { background: #fef3c7; color: #92400e; }' +
                '.ai-status-processing { background: #dbeafe; color: #1e40af; }' +
                '.ai-status-completed { background: #d1fae5; color: #065f46; }' +
                '.ai-status-failed { background: #fee2e2; color: #991b1b; }' +
                '.ai-status-applied { background: #d1fae5; color: #065f46; border: 2px solid #10b981; }' +
                '</style>';

            $('head').append(styles);
        },

        /**
         * Bind event handlers for AI evaluation buttons.
         */
        bindEvents: function() {
            var self = this;

            // Trigger evaluation button
            $(document).on('click', '.btn-trigger-ai-eval', function(e) {
                e.preventDefault();
                var btn = $(this);
                self.triggerEvaluation(
                    btn.data('cmid'),
                    btn.data('step'),
                    btn.data('submissionid')
                );
            });

            // Apply AI grade button
            $(document).on('click', '.btn-apply-ai-grade', function(e) {
                e.preventDefault();
                var btn = $(this);
                self.applyGrade(
                    btn.data('cmid'),
                    btn.data('evaluationid')
                );
            });

            // Retry evaluation button
            $(document).on('click', '.btn-retry-ai-eval', function(e) {
                e.preventDefault();
                var btn = $(this);
                self.retryEvaluation(
                    btn.data('cmid'),
                    btn.data('step'),
                    btn.data('submissionid')
                );
            });
        },

        /**
         * Show the progress overlay.
         *
         * @param {String} status Initial status message
         */
        showProgress: function(status) {
            $('#ai-progress-overlay').fadeIn(200);
            this.updateStatus(status || 'Initialisation...');
            this.updateProgressBar(10);
        },

        /**
         * Hide the progress overlay.
         */
        hideProgress: function() {
            $('#ai-progress-overlay').fadeOut(200);
            this.stopPolling();
        },

        /**
         * Update the status message.
         *
         * @param {String} status Status message
         */
        updateStatus: function(status) {
            $('.ai-progress-status').text(status);
        },

        /**
         * Update the progress bar.
         *
         * @param {Number} percent Progress percentage (0-100)
         */
        updateProgressBar: function(percent) {
            $('.ai-progress-bar').css('width', Math.min(percent, 100) + '%');
        },

        /**
         * Update the details section.
         *
         * @param {String} details Details text
         */
        updateDetails: function(details) {
            $('.ai-progress-details').html(details);
        },

        /**
         * Trigger a new AI evaluation.
         *
         * @param {Number} cmid Course module ID
         * @param {Number} step Step number
         * @param {Number} submissionid Submission ID
         */
        triggerEvaluation: function(cmid, step, submissionid) {
            var self = this;

            this.cmid = cmid;
            this.step = step;
            this.submissionid = submissionid;

            this.showProgress('Envoi de la demande d\'évaluation...');
            this.updateProgressBar(20);

            Ajax.call([{
                methodname: 'mod_gestionprojet_evaluate',
                args: {
                    cmid: cmid,
                    step: step,
                    submissionid: submissionid
                }
            }])[0].done(function(response) {
                if (response.success) {
                    self.evaluationid = response.evaluationid;
                    self.updateStatus('Évaluation en cours...');
                    self.updateProgressBar(30);
                    self.startPolling();
                } else {
                    self.showError(response.message || 'Erreur lors du déclenchement');
                }
            }).fail(function(ex) {
                self.showError(ex.message || 'Erreur de connexion au serveur');
            });
        },

        /**
         * Retry a failed evaluation.
         *
         * @param {Number} cmid Course module ID
         * @param {Number} step Step number
         * @param {Number} submissionid Submission ID
         */
        retryEvaluation: function(cmid, step, submissionid) {
            this.triggerEvaluation(cmid, step, submissionid);
        },

        /**
         * Start polling for evaluation status.
         */
        startPolling: function() {
            var self = this;
            this.pollCount = 0;

            this.pollTimer = setInterval(function() {
                self.checkStatus();
            }, this.pollInterval);
        },

        /**
         * Stop polling.
         */
        stopPolling: function() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        /**
         * Check evaluation status.
         */
        checkStatus: function() {
            var self = this;
            this.pollCount++;

            // Progress bar animation based on poll count
            var progress = Math.min(30 + (this.pollCount * 2), 90);
            this.updateProgressBar(progress);

            if (this.pollCount >= this.maxPolls) {
                this.stopPolling();
                this.showError('Délai d\'attente dépassé. Veuillez réessayer.');
                return;
            }

            var args = {
                cmid: this.cmid,
                evaluationid: 0,
                step: 0,
                submissionid: 0
            };

            if (this.evaluationid) {
                args.evaluationid = this.evaluationid;
            } else {
                args.step = this.step;
                args.submissionid = this.submissionid;
            }

            Ajax.call([{
                methodname: 'mod_gestionprojet_get_evaluation_status',
                args: args
            }])[0].done(function(response) {
                if (response.success && response.has_evaluation) {
                    self.handleStatusUpdate(response);
                } else if (!response.success) {
                    self.showError(response.error_message || response.status_label || 'Erreur de récupération du statut');
                }
            }).fail(function() {
                // Don't stop polling on network errors, just log
                console.warn('Network error while polling AI status');
            });
        },

        /**
         * Handle status update from polling.
         *
         * @param {Object} response Status response
         */
        handleStatusUpdate: function(response) {
            var status = response.status;

            this.updateStatus(response.status_label || status);

            if (status === 'completed' || status === 'applied') {
                this.updateProgressBar(100);
                this.stopPolling();

                setTimeout(function() {
                    location.reload();
                }, 500);

            } else if (status === 'failed') {
                this.stopPolling();
                this.showError(response.error_message || 'L\'évaluation a échoué');

            } else {
                // Still processing
                this.updateDetails('Temps écoulé: ' + Math.round(this.pollCount * this.pollInterval / 1000) + 's');
            }
        },

        /**
         * Show error message.
         *
         * @param {String} message Error message
         */
        showError: function(message) {
            this.hideProgress();

            Notification.addNotification({
                message: message,
                type: 'error'
            });

            if (this.onError) {
                this.onError(message);
            }
        },

        /**
         * Apply AI grade to submission.
         *
         * @param {Number} cmid Course module ID
         * @param {Number} evaluationid Evaluation ID
         */
        applyGrade: function(cmid, evaluationid) {
            var self = this;

            this.showProgress('Application de la note...');
            this.updateProgressBar(50);

            // Collect visibility options from checkboxes.
            var showFeedback = $('#show_feedback').is(':checked') ? 1 : 0;
            var showCriteria = $('#show_criteria').is(':checked') ? 1 : 0;
            var showKeywordsFound = $('#show_keywords_found').is(':checked') ? 1 : 0;
            var showKeywordsMissing = $('#show_keywords_missing').is(':checked') ? 1 : 0;
            var showSuggestions = $('#show_suggestions').is(':checked') ? 1 : 0;

            Ajax.call([{
                methodname: 'mod_gestionprojet_apply_ai_grade',
                args: {
                    cmid: cmid,
                    evaluationid: evaluationid,
                    show_feedback: showFeedback,
                    show_criteria: showCriteria,
                    show_keywords_found: showKeywordsFound,
                    show_keywords_missing: showKeywordsMissing,
                    show_suggestions: showSuggestions
                }
            }])[0].done(function(response) {
                if (response.success) {
                    self.updateStatus('Note appliquée !');
                    self.updateProgressBar(100);

                    Notification.addNotification({
                        message: 'La note IA a été appliquée avec succès',
                        type: 'success'
                    });

                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    self.showError(response.message || 'Erreur lors de l\'application');
                }
            }).fail(function(ex) {
                self.showError(ex.message || 'Erreur de connexion au serveur');
            });
        },

        /**
         * Get status badge HTML.
         *
         * @param {String} status Status code
         * @return {String} HTML badge
         */
        getStatusBadge: function(status) {
            var icons = {
                'pending': '⏳',
                'processing': '🔄',
                'completed': '✅',
                'failed': '❌',
                'applied': '✓'
            };

            var labels = {
                'pending': 'En attente',
                'processing': 'En cours',
                'completed': 'Terminé',
                'failed': 'Échoué',
                'applied': 'Appliqué'
            };

            var icon = icons[status] || '❓';
            var label = labels[status] || status;

            return '<span class="ai-status-badge ai-status-' + status + '">' +
                icon + ' ' + label + '</span>';
        },

        /**
         * Create inline progress indicator.
         *
         * @param {String} containerId Container element ID
         * @return {Object} Inline progress controller
         */
        createInlineProgress: function(containerId) {
            var container = $('#' + containerId);
            if (!container.length) {
                return null;
            }

            var html = '<div class="ai-progress-inline">' +
                '<div class="spinner-small"></div>' +
                '<span class="inline-status">Évaluation en cours...</span>' +
                '</div>';

            container.html(html);

            return {
                update: function(text) {
                    container.find('.inline-status').text(text);
                },
                complete: function(html) {
                    container.html(html);
                },
                error: function(message) {
                    container.html('<span class="ai-status-badge ai-status-failed">❌ ' + message + '</span>');
                }
            };
        }
    };

    return AIProgress;
});

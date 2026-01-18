// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Autosave functionality for gestionprojet.
 *
 * @module     mod_gestionprojet/autosave
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    var autosave = {
        cmid: 0,
        step: 0,
        groupid: 0,
        interval: 30000,
        timer: null,
        formSelector: null,
        statusElement: null,
        isDirty: false,

        /**
         * Initialize autosave.
         *
         * @param {Object} config Configuration object
         */
        init: function(config) {
            this.cmid = config.cmid;
            this.step = config.step || 0;
            this.groupid = config.groupid || 0;
            this.interval = config.interval || 30000;
            this.formSelector = config.formSelector || 'form';

            // Create status indicator
            this.createStatusIndicator();

            // Monitor form changes
            this.monitorChanges();

            // Start autosave timer
            this.startTimer();

            // Save on page unload
            this.setupBeforeUnload();
        },

        /**
         * Create visual status indicator.
         */
        createStatusIndicator: function() {
            var statusHtml = '<div id="autosave-status" style="' +
                'position: fixed; top: 60px; right: 20px; z-index: 9999; ' +
                'padding: 10px 20px; border-radius: 8px; ' +
                'background: #f8f9fa; border: 2px solid #dee2e6; ' +
                'box-shadow: 0 4px 12px rgba(0,0,0,0.1); ' +
                'display: none; transition: all 0.3s;">' +
                '<span id="autosave-icon">💾</span> ' +
                '<span id="autosave-text">Sauvegarde automatique...</span>' +
                '</div>';

            $('body').append(statusHtml);
            this.statusElement = $('#autosave-status');
        },

        /**
         * Monitor form changes.
         */
        monitorChanges: function() {
            var self = this;

            $(document).on('change input', this.formSelector + ' input, ' +
                          this.formSelector + ' textarea, ' +
                          this.formSelector + ' select', function() {
                self.isDirty = true;
                self.showStatus('unsaved');
            });
        },

        /**
         * Start autosave timer.
         */
        startTimer: function() {
            var self = this;

            this.timer = setInterval(function() {
                if (self.isDirty) {
                    self.save();
                }
            }, this.interval);
        },

        /**
         * Stop autosave timer.
         */
        stopTimer: function() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        /**
         * Show status message.
         *
         * @param {String} type Status type (saving, saved, unsaved, error)
         */
        showStatus: function(type) {
            var icon = '💾';
            var text = 'Sauvegarde automatique...';
            var bgColor = '#f8f9fa';
            var borderColor = '#dee2e6';

            switch (type) {
                case 'saving':
                    icon = '⏳';
                    text = 'Sauvegarde en cours...';
                    bgColor = '#fff3cd';
                    borderColor = '#ffc107';
                    break;
                case 'saved':
                    icon = '✓';
                    text = 'Sauvegardé';
                    bgColor = '#d4edda';
                    borderColor = '#28a745';
                    break;
                case 'unsaved':
                    icon = '📝';
                    text = 'Modifications non sauvegardées';
                    bgColor = '#fff3cd';
                    borderColor = '#ffc107';
                    break;
                case 'error':
                    icon = '⚠️';
                    text = 'Erreur de sauvegarde';
                    bgColor = '#f8d7da';
                    borderColor = '#dc3545';
                    break;
            }

            $('#autosave-icon').text(icon);
            $('#autosave-text').text(text);
            this.statusElement.css({
                'background': bgColor,
                'border-color': borderColor,
                'display': 'block'
            });

            // Auto-hide after 3 seconds for saved/error states
            if (type === 'saved' || type === 'error') {
                setTimeout(() => {
                    this.statusElement.fadeOut();
                }, 3000);
            }
        },

        /**
         * Save form data.
         */
        save: function() {
            var self = this;

            if (!this.step) {
                return; // No step specified
            }

            this.showStatus('saving');

            // Collect form data
            var formData = {};
            $(this.formSelector).find('input, textarea, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var type = $field.attr('type');

                if (!name) {
                    return;
                }

                if (type === 'checkbox') {
                    formData[name] = $field.is(':checked') ? 1 : 0;
                } else if (type === 'radio') {
                    if ($field.is(':checked')) {
                        formData[name] = $field.val();
                    }
                } else {
                    formData[name] = $field.val();
                }
            });

            // Make AJAX request
            $.ajax({
                url: M.cfg.wwwroot + '/mod/gestionprojet/ajax/autosave.php',
                method: 'POST',
                data: {
                    cmid: this.cmid,
                    step: this.step,
                    groupid: this.groupid,
                    data: JSON.stringify(formData),
                    sesskey: M.cfg.sesskey
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.isDirty = false;
                        self.showStatus('saved');
                    } else {
                        self.showStatus('error');
                        Notification.addNotification({
                            message: response.message || 'Erreur de sauvegarde',
                            type: 'error'
                        });
                    }
                },
                error: function() {
                    self.showStatus('error');
                    Notification.addNotification({
                        message: 'Erreur de connexion',
                        type: 'error'
                    });
                }
            });
        },

        /**
         * Setup before unload warning.
         */
        setupBeforeUnload: function() {
            var self = this;

            $(window).on('beforeunload', function() {
                if (self.isDirty) {
                    // Save immediately
                    self.save();
                    return 'Vous avez des modifications non sauvegardées.';
                }
            });
        }
    };

    return autosave;
});

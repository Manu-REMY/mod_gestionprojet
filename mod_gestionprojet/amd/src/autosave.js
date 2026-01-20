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

define(['jquery', 'core/ajax', 'core/notification'], function ($, Ajax, Notification) {

    var autosave = {
        cmid: 0,
        step: 0,
        groupid: 0,
        interval: 30000,
        timer: null,
        formSelector: null,
        statusElement: null,
        isDirty: false,
        serialize: null, // Custom serialization function
        onSave: null,   // Custom callback after save

        /**
         * Initialize autosave.
         *
         * @param {Object} config Configuration object
         */
        init: function (config) {
            this.cmid = config.cmid;
            this.step = config.step || 0;
            this.groupid = config.groupid || 0;
            this.interval = config.interval || 30000;
            this.formSelector = config.formSelector || 'form';

            // Custom functions
            if (typeof config.serialize === 'function') {
                this.serialize = config.serialize;
            }
            if (typeof config.onSave === 'function') {
                this.onSave = config.onSave;
            }

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
        createStatusIndicator: function () {
            // Remove existing indicator if any
            $('#autosave-status').remove();

            var statusHtml = '<div id="autosave-status" style="' +
                'position: fixed; top: 60px; right: 20px; z-index: 9999; ' +
                'padding: 10px 20px; border-radius: 8px; ' +
                'background: #f8f9fa; border: 2px solid #dee2e6; ' +
                'box-shadow: 0 4px 12px rgba(0,0,0,0.1); ' +
                'display: none; transition: all 0.3s; font-family: -apple-system, system-ui, BlinkMacSystemFont, sans-serif;">' +
                '<span id="autosave-icon" style="margin-right: 8px;">💾</span> ' +
                '<span id="autosave-text">Sauvegarde automatique...</span>' +
                '</div>';

            $('body').append(statusHtml);
            this.statusElement = $('#autosave-status');
        },

        /**
         * Monitor form changes.
         */
        monitorChanges: function () {
            var self = this;

            $(document).on('change input', this.formSelector + ' input, ' +
                this.formSelector + ' textarea, ' +
                this.formSelector + ' select', function () {
                    self.isDirty = true;
                    // Don't show "unsaved" status immediately for every keystroke, 
                    // just mark as dirty. The saving status will be enough feedback.
                });
        },

        /**
         * Start autosave timer.
         */
        startTimer: function () {
            var self = this;

            this.timer = setInterval(function () {
                if (self.isDirty) {
                    self.save();
                }
            }, this.interval);
        },

        /**
         * Stop autosave timer.
         */
        stopTimer: function () {
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
        showStatus: function (type) {
            var icon = '💾';
            var text = 'Sauvegarde automatique...';
            var bgColor = '#f8f9fa';
            var borderColor = '#dee2e6';
            var textColor = '#212529';

            switch (type) {
                case 'saving':
                    icon = '⏳';
                    text = 'Sauvegarde en cours...';
                    bgColor = '#fff3cd';
                    borderColor = '#ffeeba';
                    textColor = '#856404';
                    break;
                case 'saved':
                    icon = '✓';
                    text = 'Sauvegardé';
                    bgColor = '#d4edda';
                    borderColor = '#c3e6cb';
                    textColor = '#155724';
                    break;
                case 'error':
                    icon = '⚠️';
                    text = 'Erreur de sauvegarde';
                    bgColor = '#f8d7da';
                    borderColor = '#f5c6cb';
                    textColor = '#721c24';
                    break;
            }

            $('#autosave-icon').text(icon);
            $('#autosave-text').text(text);
            this.statusElement.css({
                'background': bgColor,
                'border-color': borderColor,
                'color': textColor,
                'display': 'flex',
                'align-items': 'center'
            });

            // Auto-hide after 3 seconds for saved/error states
            if (type === 'saved') {
                setTimeout(function () {
                    $('#autosave-status').fadeOut();
                }, 3000);
            }
        },

        /**
         * Save form data.
         */
        save: function () {
            var self = this;

            if (!this.step) {
                return; // No step specified
            }

            this.showStatus('saving');

            // Collect form data
            var formData = {};

            if (this.serialize) {
                // Use custom serialization
                formData = this.serialize();
            } else {
                // Default serialization
                $(this.formSelector).find('input, textarea, select').each(function () {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var type = $field.attr('type');

                    if (!name) {
                        return;
                    }

                    // Handle array inputs (e.g., name="competences[]")
                    if (name.endsWith('[]')) {
                        return; // Skip arrays in default serializer, needs custom
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
            }

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
                success: function (response) {
                    if (response.success) {
                        self.isDirty = false;
                        self.showStatus('saved');

                        // Callback if defined
                        if (self.onSave) {
                            self.onSave(response);
                        }
                    } else {
                        self.showStatus('error');
                        // Only show notification for actual errors, not just failed status updates
                        if (response.message) {
                            console.error('Autosave error:', response.message);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    self.showStatus('error');
                    console.error('Autosave connection error:', status, error);
                }
            });
        },

        /**
         * Setup before unload warning.
         */
        setupBeforeUnload: function () {
            var self = this;

            $(window).on('beforeunload', function () {
                if (self.isDirty) {
                    // Try to save synchronously if possible, or just warn
                    // Modern browsers don't allow sync XHR in beforeunload often
                    self.save();
                    return 'Vous avez des modifications non sauvegardées.';
                }
            });
        }
    };

    return autosave;
});

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

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'mod_gestionprojet/toast'], function ($, Ajax, Notification, Str, Toast) {

    var autosave = {
        cmid: 0,
        step: 0,
        groupid: 0,
        mode: '', // 'teacher' for correction models
        interval: 30000,
        debounceDelay: 2000, // Delay after input before saving
        debounceTimer: null,
        timer: null,
        formSelector: null,
        statusElement: null,
        isDirty: false,
        serialize: null, // Custom serialization function
        onSave: null,   // Custom callback after save
        strings: {}, // Loaded language strings

        /**
         * Initialize autosave.
         *
         * @param {Object} config Configuration object
         */
        init: function (config) {
            this.cmid = config.cmid;
            this.step = config.step || 0;
            this.groupid = config.groupid || 0;
            this.mode = config.mode || '';
            this.interval = config.interval || 30000;
            this.formSelector = config.formSelector || 'form';

            // Custom functions
            if (typeof config.serialize === 'function') {
                this.serialize = config.serialize;
            }
            if (typeof config.onSave === 'function') {
                this.onSave = config.onSave;
            }

            // Load language strings then set up the UI.
            var self = this;
            Str.get_strings([
                {key: 'autosave_status_default', component: 'mod_gestionprojet'},
                {key: 'autosave_status_saving', component: 'mod_gestionprojet'},
                {key: 'autosave_status_saved', component: 'mod_gestionprojet'},
                {key: 'autosave_status_error', component: 'mod_gestionprojet'},
                {key: 'autosave_unsaved_changes', component: 'mod_gestionprojet'},
            ]).then(function (results) {
                self.strings = {
                    defaultText: results[0],
                    saving: results[1],
                    saved: results[2],
                    error: results[3],
                    unsavedChanges: results[4],
                };

                // Create status indicator
                self.createStatusIndicator();

                // Monitor form changes
                self.monitorChanges();

                // Start autosave timer
                self.startTimer();

                // Save on page unload
                self.setupBeforeUnload();

                return;
            }).catch(Notification.exception);
        },

        /**
         * Create visual status indicator.
         * Uses the toast notification system instead of inline-styled elements.
         */
        createStatusIndicator: function () {
            // Toast system handles its own container.
            // No manual DOM creation needed.
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

                    // Debounce save
                    clearTimeout(self.debounceTimer);
                    self.debounceTimer = setTimeout(function () {
                        self.save();
                    }, self.debounceDelay);
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
         * Show status message using the toast notification system.
         *
         * @param {String} type Status type (saving, saved, unsaved, error)
         */
        showStatus: function (type) {
            switch (type) {
                case 'saving':
                    // Brief info toast for saving state.
                    Toast.info(this.strings.saving || 'Saving...', 2000);
                    break;
                case 'saved':
                    Toast.success(this.strings.saved || 'Saved', 2000);
                    break;
                case 'error':
                    Toast.error(this.strings.error || 'Save error', 5000);
                    break;
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

            // Clear any pending debounce timer since we are saving now
            clearTimeout(this.debounceTimer);

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

            // Build request args matching the external service parameters.
            var args = {
                cmid: this.cmid,
                step: this.step,
                data: JSON.stringify(formData),
                groupid: this.groupid,
                mode: this.mode || ''
            };

            // Call the registered Moodle external service.
            Ajax.call([{
                methodname: 'mod_gestionprojet_autosave',
                args: args
            }])[0].done(function(response) {
                if (response.success) {
                    self.isDirty = false;
                    self.showStatus('saved');

                    // Callback if defined.
                    if (self.onSave) {
                        self.onSave(response);
                    }
                } else {
                    self.showStatus('error');
                    // Only show notification for actual errors, not just failed status updates.
                    if (response.message) {
                        console.error('Autosave error:', response.message);
                    }
                }
            }).fail(function(ex) {
                self.showStatus('error');
                console.error('Autosave connection error:', ex.message || ex);
            });
        },

        /**
         * Setup before unload warning.
         */
        setupBeforeUnload: function () {
            var self = this;

            $(window).on('beforeunload', function () {
                if (self.isDirty) {
                    // Try to save synchronously if possible, or just warn.
                    // Modern browsers don't allow sync XHR in beforeunload often.
                    self.save();
                    return self.strings.unsavedChanges || 'You have unsaved changes.';
                }
            });
        }
    };

    return autosave;
});

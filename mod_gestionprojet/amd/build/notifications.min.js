// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Toast notifications system for gestionprojet.
 *
 * @module     mod_gestionprojet/notifications
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    var Notifications = {
        containerId: 'gp-toast-container',
        defaultDuration: 5000,
        maxToasts: 5,
        activeToasts: [],

        /**
         * Initialize the notifications system.
         *
         * @param {Object} config Optional configuration
         */
        init: function(config) {
            config = config || {};
            this.defaultDuration = config.duration || 5000;
            this.maxToasts = config.maxToasts || 5;

            this.createContainer();
            this.injectStyles();
        },

        /**
         * Create the toast container.
         */
        createContainer: function() {
            if ($('#' + this.containerId).length) {
                return;
            }

            var container = $('<div>')
                .attr('id', this.containerId)
                .css({
                    'position': 'fixed',
                    'top': '20px',
                    'right': '20px',
                    'z-index': '9999',
                    'display': 'flex',
                    'flex-direction': 'column',
                    'gap': '10px',
                    'max-width': '400px',
                    'pointer-events': 'none'
                });

            $('body').append(container);
        },

        /**
         * Inject CSS styles.
         */
        injectStyles: function() {
            if ($('#gp-toast-styles').length) {
                return;
            }

            var styles = '<style id="gp-toast-styles">' +
                '.gp-toast {' +
                '  display: flex; align-items: flex-start; gap: 12px;' +
                '  padding: 14px 18px; border-radius: 10px;' +
                '  background: white; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);' +
                '  transform: translateX(120%); opacity: 0;' +
                '  transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);' +
                '  pointer-events: auto; cursor: pointer;' +
                '  border-left: 4px solid #6b7280;' +
                '}' +
                '.gp-toast.show {' +
                '  transform: translateX(0); opacity: 1;' +
                '}' +
                '.gp-toast.hiding {' +
                '  transform: translateX(120%); opacity: 0;' +
                '}' +
                '.gp-toast-success { border-left-color: #10b981; }' +
                '.gp-toast-error { border-left-color: #ef4444; }' +
                '.gp-toast-warning { border-left-color: #f59e0b; }' +
                '.gp-toast-info { border-left-color: #3b82f6; }' +
                '.gp-toast-ai { border-left-color: #8b5cf6; }' +
                '.gp-toast-icon {' +
                '  font-size: 1.3em; flex-shrink: 0;' +
                '}' +
                '.gp-toast-content {' +
                '  flex: 1; min-width: 0;' +
                '}' +
                '.gp-toast-title {' +
                '  font-weight: 600; color: #1f2937; margin-bottom: 2px;' +
                '}' +
                '.gp-toast-message {' +
                '  color: #6b7280; font-size: 0.9em; line-height: 1.4;' +
                '}' +
                '.gp-toast-close {' +
                '  background: none; border: none; padding: 0;' +
                '  color: #9ca3af; cursor: pointer; font-size: 1.2em;' +
                '  line-height: 1; opacity: 0.7; transition: opacity 0.2s;' +
                '}' +
                '.gp-toast-close:hover { opacity: 1; }' +
                '.gp-toast-progress {' +
                '  position: absolute; bottom: 0; left: 0; right: 0;' +
                '  height: 3px; background: rgba(0, 0, 0, 0.1);' +
                '  border-radius: 0 0 10px 10px; overflow: hidden;' +
                '}' +
                '.gp-toast-progress-bar {' +
                '  height: 100%; background: currentColor; opacity: 0.3;' +
                '  animation: gp-toast-progress linear forwards;' +
                '}' +
                '@keyframes gp-toast-progress {' +
                '  from { width: 100%; }' +
                '  to { width: 0%; }' +
                '}' +
                '</style>';

            $('head').append(styles);
        },

        /**
         * Show a toast notification.
         *
         * @param {Object} options Toast options
         * @return {Object} Toast instance for chaining
         */
        show: function(options) {
            var self = this;
            options = $.extend({
                type: 'info',
                title: '',
                message: '',
                duration: this.defaultDuration,
                closable: true,
                onClick: null
            }, options);

            // Limit active toasts
            while (this.activeToasts.length >= this.maxToasts) {
                this.dismiss(this.activeToasts[0].id);
            }

            var icons = {
                'success': '✓',
                'error': '✕',
                'warning': '⚠',
                'info': 'ℹ',
                'ai': '🤖'
            };

            var id = 'toast-' + Date.now();
            var icon = icons[options.type] || icons.info;

            var toast = $('<div>')
                .attr('id', id)
                .addClass('gp-toast gp-toast-' + options.type)
                .css('position', 'relative');

            var content = '<span class="gp-toast-icon">' + icon + '</span>' +
                '<div class="gp-toast-content">';

            if (options.title) {
                content += '<div class="gp-toast-title">' + this.escapeHtml(options.title) + '</div>';
            }

            content += '<div class="gp-toast-message">' + this.escapeHtml(options.message) + '</div>';
            content += '</div>';

            if (options.closable) {
                content += '<button class="gp-toast-close" aria-label="Fermer">&times;</button>';
            }

            if (options.duration > 0) {
                content += '<div class="gp-toast-progress">' +
                    '<div class="gp-toast-progress-bar" style="animation-duration: ' + options.duration + 'ms;"></div>' +
                    '</div>';
            }

            toast.html(content);

            // Event handlers
            toast.find('.gp-toast-close').on('click', function(e) {
                e.stopPropagation();
                self.dismiss(id);
            });

            if (options.onClick) {
                toast.on('click', function() {
                    options.onClick();
                    self.dismiss(id);
                });
            } else {
                toast.on('click', function() {
                    self.dismiss(id);
                });
            }

            // Add to container
            $('#' + this.containerId).append(toast);

            // Track active toast
            var toastObj = {
                id: id,
                element: toast,
                timer: null
            };
            this.activeToasts.push(toastObj);

            // Animate in
            setTimeout(function() {
                toast.addClass('show');
            }, 10);

            // Auto dismiss
            if (options.duration > 0) {
                toastObj.timer = setTimeout(function() {
                    self.dismiss(id);
                }, options.duration);
            }

            return {
                id: id,
                dismiss: function() {
                    self.dismiss(id);
                }
            };
        },

        /**
         * Dismiss a toast.
         *
         * @param {String} id Toast ID
         */
        dismiss: function(id) {
            var index = -1;
            for (var i = 0; i < this.activeToasts.length; i++) {
                if (this.activeToasts[i].id === id) {
                    index = i;
                    break;
                }
            }

            if (index === -1) {
                return;
            }

            var toastObj = this.activeToasts[index];

            if (toastObj.timer) {
                clearTimeout(toastObj.timer);
            }

            toastObj.element.addClass('hiding');

            setTimeout(function() {
                toastObj.element.remove();
            }, 300);

            this.activeToasts.splice(index, 1);
        },

        /**
         * Dismiss all toasts.
         */
        dismissAll: function() {
            var self = this;
            var ids = this.activeToasts.map(function(t) { return t.id; });
            ids.forEach(function(id) {
                self.dismiss(id);
            });
        },

        /**
         * Show success toast.
         *
         * @param {String} message Message
         * @param {String} title Optional title
         * @return {Object} Toast instance
         */
        success: function(message, title) {
            return this.show({
                type: 'success',
                title: title || 'Succès',
                message: message
            });
        },

        /**
         * Show error toast.
         *
         * @param {String} message Message
         * @param {String} title Optional title
         * @return {Object} Toast instance
         */
        error: function(message, title) {
            return this.show({
                type: 'error',
                title: title || 'Erreur',
                message: message,
                duration: 8000 // Longer for errors
            });
        },

        /**
         * Show warning toast.
         *
         * @param {String} message Message
         * @param {String} title Optional title
         * @return {Object} Toast instance
         */
        warning: function(message, title) {
            return this.show({
                type: 'warning',
                title: title || 'Attention',
                message: message
            });
        },

        /**
         * Show info toast.
         *
         * @param {String} message Message
         * @param {String} title Optional title
         * @return {Object} Toast instance
         */
        info: function(message, title) {
            return this.show({
                type: 'info',
                title: title || '',
                message: message
            });
        },

        /**
         * Show AI-specific toast.
         *
         * @param {String} message Message
         * @param {String} title Optional title
         * @return {Object} Toast instance
         */
        ai: function(message, title) {
            return this.show({
                type: 'ai',
                title: title || 'Évaluation IA',
                message: message
            });
        },

        /**
         * Show AI evaluation complete notification.
         *
         * @param {Number} grade Grade received
         * @param {String} stepName Step name
         */
        aiComplete: function(grade, stepName) {
            return this.show({
                type: 'ai',
                title: 'Évaluation terminée',
                message: stepName + ' - Note: ' + grade + '/20',
                duration: 10000
            });
        },

        /**
         * Escape HTML to prevent XSS.
         *
         * @param {String} str Input string
         * @return {String} Escaped string
         */
        escapeHtml: function(str) {
            if (!str) {
                return '';
            }
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    return Notifications;
});

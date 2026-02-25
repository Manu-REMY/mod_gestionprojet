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
 * Toast notification system for mod_gestionprojet.
 *
 * Provides lightweight, auto-dismissing toast notifications
 * for feedback on save, AI operations, and other actions.
 *
 * @module     mod_gestionprojet/toast
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var container = null;

    /**
     * Get or create the toast container element.
     *
     * @return {HTMLElement} The toast container.
     */
    function getContainer() {
        if (container && document.body.contains(container)) {
            return container;
        }

        container = document.createElement('div');
        container.className = 'gp-toast-container';
        document.body.appendChild(container);
        return container;
    }

    /**
     * Show a toast notification.
     *
     * @param {String} message The message to display.
     * @param {String} type The toast type: success, info, warning, error.
     * @param {Number} duration Duration in ms before auto-dismiss. 0 = no auto-dismiss.
     */
    function show(message, type, duration) {
        type = type || 'info';
        duration = (typeof duration !== 'undefined') ? duration : 4000;

        var toastContainer = getContainer();

        // Create toast element.
        var toast = document.createElement('div');
        toast.className = 'gp-toast gp-toast-' + type;

        // Icon based on type.
        var iconMap = {
            'success': '\u2713',
            'info': '\u2139',
            'warning': '\u26A0',
            'error': '\u2717'
        };

        // Icon span.
        var iconSpan = document.createElement('span');
        iconSpan.className = 'gp-toast-icon';
        iconSpan.textContent = iconMap[type] || iconMap.info;
        toast.appendChild(iconSpan);

        // Message span (safe text insertion).
        var msgSpan = document.createElement('span');
        msgSpan.className = 'gp-toast-message';
        msgSpan.textContent = message;
        toast.appendChild(msgSpan);

        // Close button.
        var closeBtn = document.createElement('button');
        closeBtn.className = 'gp-toast-close';
        closeBtn.textContent = '\u00D7';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.addEventListener('click', function() {
            dismiss(toast);
        });
        toast.appendChild(closeBtn);

        // Append and animate in.
        toastContainer.appendChild(toast);

        // Force reflow to trigger animation.
        toast.offsetHeight; // eslint-disable-line no-unused-expressions
        toast.classList.add('gp-toast-visible');

        // Auto-dismiss.
        if (duration > 0) {
            setTimeout(function() {
                dismiss(toast);
            }, duration);
        }

        return toast;
    }

    /**
     * Dismiss a toast with animation.
     *
     * @param {HTMLElement} toast The toast element to dismiss.
     */
    function dismiss(toast) {
        if (!toast || toast.classList.contains('gp-toast-exiting')) {
            return;
        }

        toast.classList.add('gp-toast-exiting');
        toast.classList.remove('gp-toast-visible');

        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    return {
        /**
         * Show a toast notification.
         *
         * @param {String} message The message text.
         * @param {String} type Toast type: success, info, warning, error.
         * @param {Number} duration Auto-dismiss duration in ms (0 = persistent).
         * @return {HTMLElement} The toast element.
         */
        show: show,

        /**
         * Show a success toast.
         *
         * @param {String} message The message text.
         * @param {Number} duration Auto-dismiss duration in ms.
         * @return {HTMLElement} The toast element.
         */
        success: function(message, duration) {
            return show(message, 'success', duration || 3000);
        },

        /**
         * Show an info toast.
         *
         * @param {String} message The message text.
         * @param {Number} duration Auto-dismiss duration in ms.
         * @return {HTMLElement} The toast element.
         */
        info: function(message, duration) {
            return show(message, 'info', duration || 4000);
        },

        /**
         * Show a warning toast.
         *
         * @param {String} message The message text.
         * @param {Number} duration Auto-dismiss duration in ms.
         * @return {HTMLElement} The toast element.
         */
        warning: function(message, duration) {
            return show(message, 'warning', duration || 5000);
        },

        /**
         * Show an error toast.
         *
         * @param {String} message The message text.
         * @param {Number} duration Auto-dismiss duration in ms.
         * @return {HTMLElement} The toast element.
         */
        error: function(message, duration) {
            return show(message, 'error', duration || 6000);
        }
    };
});

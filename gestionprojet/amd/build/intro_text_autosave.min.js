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
 * Generic autosave for the teacher intro_text Atto editor on consigne pages.
 *
 * Watches #intro_text (textarea backing the Atto editor) for changes and posts
 * to the mod_gestionprojet_autosave webservice with mode=provided. Used on
 * step 9 (FAST) where the existing fast_editor module does not serialize the
 * intro_text textarea. Steps 4 and 5 already cover intro_text via their
 * own form-serialization flows (cdcf_bootstrap, essai_provided).
 *
 * @module     mod_gestionprojet/intro_text_autosave
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Initialise the autosave loop.
     *
     * @param {Object} cfg
     * @param {number} cfg.cmid Course module ID.
     * @param {number} cfg.step Step number (4, 5 or 9).
     * @param {number} [cfg.autosaveMs] Debounce delay in milliseconds (default 30000).
     */
    function init(cfg) {
        var textarea = document.getElementById('intro_text');
        if (!textarea) {
            return;
        }

        var debounceMs = cfg.autosaveMs || 30000;
        var timer = null;
        var lastSent = textarea.value;

        function sendNow() {
            var current = textarea.value;
            if (current === lastSent) {
                return;
            }
            lastSent = current;
            Ajax.call([{
                methodname: 'mod_gestionprojet_autosave',
                args: {
                    cmid: cfg.cmid,
                    step: cfg.step,
                    data: JSON.stringify({intro_text: current}),
                    groupid: 0,
                    mode: 'provided'
                }
            }])[0].catch(Notification.exception);
        }

        function schedule() {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(sendNow, debounceMs);
        }

        // Atto syncs to the underlying textarea via 'change' event; 'input'
        // fires when the user types directly into the source view.
        textarea.addEventListener('change', schedule);
        textarea.addEventListener('input', schedule);

        // Best-effort flush on page unload.
        window.addEventListener('beforeunload', sendNow);
    }

    return { init: init };
});

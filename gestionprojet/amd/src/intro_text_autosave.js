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
 * Generic autosave for the teacher intro_text rich-text editor on consigne pages.
 *
 * Polls the underlying #intro_text textarea every cfg.autosaveMs and posts to
 * the mod_gestionprojet_autosave webservice with mode=provided when the value
 * changes. Used on step 9 (FAST) where the existing fast_editor module does
 * not serialize the intro_text textarea.
 *
 * Polling is required because Atto and TinyMCE edit inside an iframe and do
 * not dispatch native 'input'/'change' events on the underlying textarea while
 * the user is typing — they only sync the textarea value programmatically on
 * blur or on their own internal cadence, and programmatic value changes do not
 * fire DOM events.
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
     * @param {number} [cfg.autosaveMs] Polling interval in milliseconds (default 10000).
     */
    function init(cfg) {
        var textarea = document.getElementById('intro_text');
        if (!textarea) {
            return;
        }

        var pollMs = cfg.autosaveMs || 10000;
        var lastSent = textarea.value;

        function readEditorValue() {
            // TinyMCE 6 stores its instance under window.tinymce; force a sync
            // back to the underlying textarea before reading it.
            if (typeof window.tinymce !== 'undefined' && window.tinymce.get) {
                var ed = window.tinymce.get('intro_text');
                if (ed) {
                    ed.save();
                }
            }
            return textarea.value;
        }

        function sendNow() {
            var current = readEditorValue();
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

        // Periodic polling — survives iframe-edited content and programmatic syncs.
        setInterval(sendNow, pollMs);

        // Best-effort flush on page unload.
        window.addEventListener('beforeunload', sendNow);
    }

    return { init: init };
});

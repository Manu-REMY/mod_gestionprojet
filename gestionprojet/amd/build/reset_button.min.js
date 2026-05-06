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
 * Reset-to-provided button handler.
 *
 * Generic across steps that support reset (4 / 5 / 9). The button must have
 * id="resetButton" on the page. Behaviour is preserved verbatim from the
 * v2.9.0 implementation that previously lived in cdcf_bootstrap.js: opens a
 * Bootstrap SAVE_CANCEL modal, POSTs FormData to the reset endpoint via fetch,
 * reloads on success, alerts on failure.
 *
 * Lang strings are passed pre-resolved from PHP via cfg.resetLang to avoid an
 * extra core/str round-trip when the modal opens.
 *
 * Expected cfg shape:
 *   {
 *       cmid:     int,
 *       step:     int,
 *       groupid:  int,
 *       sesskey:  string,
 *       resetUrl: string,
 *       resetLang: {
 *           modalTitle:   string,
 *           modalBody:    string,
 *           modalConfirm: string,
 *           genericError: string
 *       }
 *   }
 *
 * @module     mod_gestionprojet/reset_button
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/modal_factory',
    'core/modal_events',
    'core/notification'
], function(ModalFactory, ModalEvents, Notification) {
    'use strict';

    /**
     * Escape a string for safe injection inside the modal body HTML.
     *
     * @param {string} s
     * @returns {string}
     */
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Bind the reset modal flow to the #resetButton element, if present.
     *
     * @param {Object} cfg See module-level docblock.
     */
    function init(cfg) {
        var resetBtn = document.getElementById('resetButton');
        if (!resetBtn || !cfg || !cfg.resetUrl) {
            return;
        }

        resetBtn.addEventListener('click', function() {
            var lang = cfg.resetLang || {};
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: lang.modalTitle,
                body: '<p>' + escapeHtml(lang.modalBody) + '</p>',
                large: false
            }).then(function(modal) {
                modal.setSaveButtonText(lang.modalConfirm);

                modal.getRoot().on(ModalEvents.save, function(e) {
                    e.preventDefault();
                    var saveBtn = modal.getRoot().find('[data-action="save"]');
                    saveBtn.prop('disabled', true);

                    var fd = new FormData();
                    fd.append('id', cfg.cmid);
                    fd.append('step', cfg.step);
                    fd.append('groupid', cfg.groupid || 0);
                    fd.append('sesskey', cfg.sesskey);

                    fetch(cfg.resetUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    }).then(function(r) {
                        return r.json().then(function(j) { return { ok: r.ok, body: j }; });
                    }).then(function(res) {
                        modal.hide();
                        if (res.ok && res.body.success) {
                            window.location.reload();
                        } else {
                            window.alert((res.body && res.body.message) || lang.genericError);
                            saveBtn.prop('disabled', false);
                        }
                    }).catch(function() {
                        modal.hide();
                        window.alert(lang.genericError);
                    });
                });

                modal.show();
                return modal;
            }).catch(Notification.exception);
        });
    }

    return { init: init };
});

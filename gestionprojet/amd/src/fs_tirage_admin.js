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
 * Consigne page of step 4: the teacher draws again the FS of a copy not started, or gives a draw to a started copy
 * (request 42).
 *
 * @module     mod_gestionprojet/fs_tirage_admin
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {
    'use strict';

    return {
        /**
         * Wire the buttons of the draw table.
         *
         * @param {Object} cfg cmid, confirmRedraw, confirmApply (questions asked first), ok (label of the confirm button)
         */
        init: function(cfg) {
            document.addEventListener('click', function(event) {
                var button = event.target.closest ? event.target.closest('.gp-fs-tirage-action') : null;
                if (!button || button.disabled) {
                    return;
                }
                var question = button.dataset.action === 'appliquer' ? cfg.confirmApply : cfg.confirmRedraw;
                Notification.saveCancelPromise('', question, cfg.ok)
                    .then(function() {
                        button.disabled = true;
                        var body = new URLSearchParams();
                        body.set('id', cfg.cmid);
                        body.set('copyid', button.dataset.copyid);
                        body.set('action', button.dataset.action);
                        body.set('sesskey', window.M.cfg.sesskey);
                        return fetch(window.M.cfg.wwwroot + '/mod/gestionprojet/ajax/fs_tirage.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: body
                        });
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (!data || !data.success) {
                            throw new Error((data && data.error) || 'error');
                        }
                        // Fresh page: some browsers serve a cached copy after a change made through AJAX.
                        var url = new URL(window.location.href);
                        url.searchParams.set('_', String(Date.now()));
                        window.location.href = url.toString();
                        return null;
                    })
                    .catch(function(error) {
                        button.disabled = false;
                        if (error && error.message) {
                            Notification.exception(error);
                        }
                    });
            });
        }
    };
});

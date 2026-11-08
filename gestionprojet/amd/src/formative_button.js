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
 * Formative feedback of the student step pages (v2.11.4).
 *
 * Ask: the copy on screen is saved first (Autosave and the registered savers), then the feedback is asked for and
 * the page reloads. Waiting: the status is polled; when the feedback is ready the page does NOT reload by itself
 * (the student keeps working): a button saves the copy and then shows the feedback.
 *
 * @module     mod_gestionprojet/formative_button
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'mod_gestionprojet/autosave', 'mod_gestionprojet/save_hooks'],
function(Ajax, Notification, Autosave, SaveHooks) {
    'use strict';

    var POLL_MS = 5000;

    /**
     * Save every editor of the page and wait (a failed save does not block).
     *
     * @returns {Promise}
     */
    function saveCopy() {
        var saved = Autosave.save();
        return Promise.all([
            Promise.resolve(saved).catch(function() { return null; }),
            SaveHooks.flushAll(),
        ]);
    }

    /**
     * Load the page again from the server (cache-buster: some browsers serve a cached copy).
     */
    function reloadFresh() {
        var url = new URL(window.location.href);
        url.searchParams.set('_', String(Date.now()));
        window.location.href = url.toString();
    }

    /**
     * Follow a running feedback; when it ends, offer a button instead of reloading by itself.
     *
     * @param {Object} cfg cmid, pendingId, statusUrl, bannerId, strings {ready, show, error}
     */
    function follow(cfg) {
        var banner = document.getElementById(cfg.bannerId);
        if (!banner) {
            return;
        }
        var timer = setInterval(function() {
            var sesskey = window.M && window.M.cfg ? window.M.cfg.sesskey : '';
            var url = cfg.statusUrl + '?id=' + encodeURIComponent(cfg.cmid) + '&evaluationid='
                + encodeURIComponent(cfg.pendingId) + '&sesskey=' + encodeURIComponent(sesskey);
            fetch(url, {credentials: 'same-origin'})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.success || (data.status !== 'completed' && data.status !== 'failed')) {
                        return;
                    }
                    clearInterval(timer);
                    var label = banner.querySelector('.ai-progress-label') || banner;
                    label.textContent = data.status === 'completed' ? cfg.strings.ready : cfg.strings.error;
                    var show = document.createElement('button');
                    show.type = 'button';
                    show.className = 'btn btn-primary btn-sm gp-formative-show';
                    show.textContent = cfg.strings.show;
                    show.addEventListener('click', function() {
                        show.disabled = true;
                        saveCopy().then(reloadFresh);
                    });
                    banner.appendChild(show);
                })
                .catch(function() {
                    // Silent: retry on the next tick.
                });
        }, POLL_MS);
    }

    /**
     * Wire the ask button, or follow a running feedback.
     *
     * @param {Object} cfg cmid, step, groupid, buttonId; or pendingId, statusUrl, bannerId, strings
     */
    function init(cfg) {
        if (cfg.pendingId) {
            follow(cfg);
            return;
        }
        var button = document.getElementById(cfg.buttonId);
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            button.disabled = true;
            // Save the copy on screen first: the feedback is about what the student sees.
            saveCopy()
                .then(function() {
                    return Ajax.call([{
                        methodname: 'mod_gestionprojet_request_formative',
                        args: {cmid: cfg.cmid, step: cfg.step, groupid: cfg.groupid},
                    }])[0];
                })
                .then(function() {
                    reloadFresh();
                    return null;
                })
                .catch(function(error) {
                    button.disabled = false;
                    Notification.exception(error);
                });
        });
    }

    return {init: init};
});

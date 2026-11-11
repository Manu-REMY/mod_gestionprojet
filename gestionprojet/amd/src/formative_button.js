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
 * the page reloads. Waiting: a spinner turns and the status is polled; when the feedback is ready it replaces the
 * banner by itself (v2.12.2), without reloading: the student keeps typing and loses nothing. The ask button comes
 * back when another feedback can be asked for.
 *
 * While waiting, a window in the middle of the screen says so (request 36, v2.13.4): spinner and words. The student
 * may close it and keep working; when the feedback is ready the window says so and shows it on click.
 *
 * @module     mod_gestionprojet/formative_button
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/templates', 'mod_gestionprojet/autosave',
    'mod_gestionprojet/save_hooks'],
function(Ajax, Notification, Templates, Autosave, SaveHooks) {
    'use strict';

    var POLL_MS = 5000;

    /** @type {?Object} The waiting window: overlay, title, detail, button; null when closed. */
    var modal = null;

    /**
     * Close the waiting window.
     */
    function closeWait() {
        if (!modal) {
            return;
        }
        document.removeEventListener('keydown', modal.onKey);
        if (modal.overlay.parentNode) {
            modal.overlay.parentNode.removeChild(modal.overlay);
        }
        modal = null;
    }

    /**
     * Put a label and an action on the window button.
     *
     * @param {string} label
     * @param {Function} action
     */
    function setButton(label, action) {
        modal.button.textContent = label;
        modal.button.onclick = action;
        modal.button.focus();
    }

    /**
     * Open the waiting window (once): spinner, words, and a button to keep working.
     *
     * @param {Object} strings pending, waitDetail, keepWorking
     */
    function openWait(strings) {
        if (modal || !strings) {
            return;
        }
        var overlay = document.createElement('div');
        overlay.className = 'gp-formative-modal';
        var box = document.createElement('div');
        box.className = 'gp-formative-modal-box';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-labelledby', 'gpFormativeModalTitle');
        var spinner = document.createElement('span');
        spinner.className = 'gp-formative-modal-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        var text = document.createElement('div');
        text.className = 'gp-formative-modal-text';
        text.setAttribute('aria-live', 'polite');
        var title = document.createElement('p');
        title.className = 'gp-formative-modal-title';
        title.id = 'gpFormativeModalTitle';
        title.textContent = strings.pending;
        var detail = document.createElement('p');
        detail.className = 'gp-formative-modal-detail';
        detail.textContent = strings.waitDetail;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary';
        text.appendChild(title);
        text.appendChild(detail);
        box.appendChild(spinner);
        box.appendChild(text);
        box.appendChild(button);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        modal = {overlay: overlay, box: box, spinner: spinner, title: title, detail: detail, button: button,
            onKey: function(event) {
                if (event.key === 'Escape') {
                    closeWait();
                }
            }};
        document.addEventListener('keydown', modal.onKey);
        setButton(strings.keepWorking, closeWait);
    }

    /**
     * The feedback is ready: say so in the window (if still open), show the feedback on click.
     *
     * @param {Object} strings ready, show
     * @param {HTMLElement} live Region holding the feedback
     */
    function showReady(strings, live) {
        if (!modal) {
            return;
        }
        modal.box.classList.add('gp-formative-modal-ready');
        modal.spinner.textContent = '✓';
        modal.title.textContent = strings.ready;
        modal.detail.textContent = '';
        setButton(strings.show, function() {
            closeWait();
            live.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    }

    /**
     * The feedback failed: say so in the window (if still open), with a close button.
     *
     * @param {Object} strings error, close
     */
    function showFailed(strings) {
        if (!modal) {
            return;
        }
        modal.box.classList.add('gp-formative-modal-failed');
        modal.spinner.textContent = '!';
        modal.title.textContent = strings.error;
        modal.detail.textContent = '';
        setButton(strings.close, closeWait);
    }

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
     * Ask for a feedback when the button is clicked: save the copy, call the web service, reload.
     *
     * @param {HTMLElement} button
     * @param {Object} cfg cmid, step, groupid, strings
     */
    function wireAsk(button, cfg) {
        button.addEventListener('click', function() {
            button.disabled = true;
            openWait(cfg.strings);
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
                    closeWait();
                    button.disabled = false;
                    Notification.exception(error);
                });
        });
    }

    /**
     * Put the ask button back in the panel (and in the narrow-screen bar), or say none is left.
     *
     * @param {Object} data ask_label or none_left from the status endpoint
     * @param {Object} cfg cmid, step, groupid
     */
    function restoreAsk(data, cfg) {
        var actions = document.querySelector('.gp-progress-actions');
        if (!actions || document.getElementById('gpFormativeButton')) {
            return;
        }
        if (data.ask_label) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary';
            button.id = 'gpFormativeButton';
            button.textContent = data.ask_label;
            actions.insertBefore(button, actions.firstChild);
            wireAsk(button, cfg);
            var bar = document.querySelector('.gp-progress-bottombar-status');
            if (bar && bar.parentNode) {
                var forward = document.createElement('button');
                forward.type = 'button';
                forward.className = 'btn btn-outline-primary';
                forward.textContent = data.ask_label;
                forward.addEventListener('click', function() {
                    if (!button.disabled) {
                        button.click();
                    }
                });
                bar.parentNode.insertBefore(forward, bar.nextSibling);
            }
        } else if (data.none_left) {
            var none = document.createElement('div');
            none.className = 'gp-formative-none';
            none.textContent = data.none_left;
            actions.insertBefore(none, actions.firstChild);
        }
    }

    /**
     * Follow a running feedback; when it is ready, show it in place of the banner (no click, no reload).
     *
     * @param {Object} cfg cmid, step, groupid, pendingId, statusUrl, bannerId, strings
     */
    function follow(cfg) {
        var live = document.getElementById(cfg.bannerId);
        if (!live) {
            return;
        }
        openWait(cfg.strings);
        // Polls may overlap on a slow server: the finished feedback is handled once.
        var done = false;
        var timer = setInterval(function() {
            var sesskey = window.M && window.M.cfg ? window.M.cfg.sesskey : '';
            var url = cfg.statusUrl + '?id=' + encodeURIComponent(cfg.cmid) + '&evaluationid='
                + encodeURIComponent(cfg.pendingId) + '&sesskey=' + encodeURIComponent(sesskey);
            fetch(url, {credentials: 'same-origin'})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (done || !data || !data.success || (data.status !== 'completed' && data.status !== 'failed')) {
                        return null;
                    }
                    done = true;
                    clearInterval(timer);
                    restoreAsk(data, cfg);
                    if (data.status === 'completed' && data.feedback_html) {
                        // Server-rendered and escaped (the same view as after a reload).
                        return Templates.replaceNodeContents(live, data.feedback_html, '').then(function() {
                            showReady(cfg.strings, live);
                            return null;
                        });
                    }
                    showFailed(cfg.strings);
                    var banner = live.querySelector('.ai-progress-banner');
                    if (banner) {
                        banner.className = banner.className.replace(/\bstatus-\S+/g, '') + ' status-failed';
                    }
                    var label = live.querySelector('.ai-progress-label') || live;
                    label.textContent = cfg.strings.error;
                    return null;
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
        if (button) {
            wireAsk(button, cfg);
        }
    }

    return {init: init};
});

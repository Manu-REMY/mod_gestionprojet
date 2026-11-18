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
 * Formative feedback of the student step pages (v2.11.4, window v2.17.0).
 *
 * Ask: the copy on screen is saved first (Autosave and the registered savers), then the feedback is asked for and
 * the page reloads. Waiting: the status is polled; when the feedback is ready it replaces the banner of the panel by
 * itself (v2.12.2), without reloading: nothing typed is lost. The ask button comes back when another feedback can be
 * asked for.
 *
 * The feedback window (v2.17.0, as mod_redaction D-086): while waiting, a window in the middle of the screen that
 * cannot be closed shows a timer and the true steps (copy sent; the AI corrects, waiting in line or in progress; the
 * feedback shows). When the feedback is ready, the window shows it: level reached, what works, what is missing, next
 * steps, language. From SAT the final submission is put forward. A failure says the feedback is not counted.
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

    /** @type {number} After this wait (ms) the student may leave the waiting window; the polling goes on. */
    var LEAVE_AFTER_MS = 180000;

    /** @type {?Object} The window: overlay, box, timer, closable, returnFocus, onKey, step2; null when closed. */
    var modal = null;

    /**
     * Create an element with a class and a text.
     *
     * @param {string} tag
     * @param {string} className
     * @param {string} [text]
     * @returns {HTMLElement}
     */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    /**
     * Stop the timer of the window.
     */
    function stopTimer() {
        if (modal && modal.timer) {
            clearInterval(modal.timer);
            modal.timer = null;
        }
    }

    /**
     * Close the window and give the focus back where the student was.
     */
    function closeModal() {
        if (!modal) {
            return;
        }
        stopTimer();
        document.removeEventListener('keydown', modal.onKey);
        if (modal.overlay.parentNode) {
            modal.overlay.parentNode.removeChild(modal.overlay);
        }
        var back = modal.returnFocus;
        modal = null;
        if (back && document.body.contains(back) && typeof back.focus === 'function') {
            back.focus();
        }
    }

    /**
     * Keyboard in the window: Escape closes it only when closable; Tab stays among its controls.
     *
     * @param {KeyboardEvent} event
     */
    function onKey(event) {
        if (!modal) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            if (modal.closable) {
                closeModal();
            }
        } else if (event.key === 'Tab') {
            var controls = Array.prototype.slice.call(modal.box.querySelectorAll('button, a[href]'));
            if (!controls.length) {
                event.preventDefault();
                modal.box.focus();
                return;
            }
            var index = controls.indexOf(document.activeElement);
            if (event.shiftKey && index <= 0) {
                event.preventDefault();
                controls[controls.length - 1].focus();
            } else if (!event.shiftKey && (index === -1 || index === controls.length - 1)) {
                event.preventDefault();
                controls[0].focus();
            }
        }
    }

    /**
     * Words of the second step for a status: waiting in line, or in progress.
     *
     * @param {Object} strings queue, processing
     * @param {string} status pending or processing
     * @returns {string}
     */
    function stepWords(strings, status) {
        return status === 'processing' ? strings.processing : strings.queue;
    }

    /**
     * Format seconds as m:ss.
     *
     * @param {number} seconds
     * @returns {string}
     */
    function clock(seconds) {
        var s = Math.max(0, Math.floor(seconds));
        var rest = s % 60;
        return Math.floor(s / 60) + ':' + (rest < 10 ? '0' : '') + rest;
    }

    /**
     * Open the waiting window (once): timer, true steps, words of patience. It cannot be closed.
     *
     * @param {Object} strings pending, sent, queue, processing, show, patience, elapsed
     * @param {string} status pending or processing
     * @param {number} elapsed Seconds since the feedback was asked for.
     */
    function openWait(strings, status, elapsed) {
        if (modal || !strings) {
            return;
        }
        var overlay = el('div', 'gp-formative-modal');
        var box = el('div', 'gp-formative-modal-box gp-retour-box');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-labelledby', 'gpRetourTitle');
        box.setAttribute('tabindex', '-1');

        var ring = el('div', 'gp-retour-ring');
        var spinner = el('span', 'gp-formative-modal-spinner');
        spinner.setAttribute('aria-hidden', 'true');
        var time = el('span', 'gp-retour-timer', clock(elapsed));
        time.setAttribute('aria-label', strings.elapsed);
        ring.appendChild(spinner);
        ring.appendChild(time);

        var title = el('p', 'gp-formative-modal-title', strings.pending);
        title.id = 'gpRetourTitle';
        var steps = el('ol', 'gp-retour-steps');
        steps.setAttribute('aria-live', 'polite');
        var step1 = el('li', 'gp-retour-step gp-retour-step-done');
        step1.appendChild(el('span', 'gp-retour-step-mark', '✓'));
        step1.appendChild(el('span', '', strings.sent));
        var step2 = el('li', 'gp-retour-step gp-retour-step-current');
        step2.appendChild(el('span', 'gp-retour-step-mark', '●'));
        var step2text = el('span', '', stepWords(strings, status));
        step2.appendChild(step2text);
        var step3 = el('li', 'gp-retour-step gp-retour-step-todo');
        step3.appendChild(el('span', 'gp-retour-step-mark', '○'));
        step3.appendChild(el('span', '', strings.show));
        steps.appendChild(step1);
        steps.appendChild(step2);
        steps.appendChild(step3);

        box.appendChild(ring);
        box.appendChild(title);
        box.appendChild(steps);
        box.appendChild(el('p', 'gp-formative-modal-detail', strings.patience));
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        var start = Date.now() - 1000 * Math.max(0, elapsed || 0);
        modal = {overlay: overlay, box: box, closable: false, returnFocus: document.activeElement, onKey: onKey,
            step2text: step2text, timer: setInterval(function() {
                time.textContent = clock((Date.now() - start) / 1000);
            }, 1000)};
        document.addEventListener('keydown', onKey);
        box.focus();
    }

    /**
     * Show the status of the correction in the second step (waiting in line, in progress).
     *
     * @param {Object} strings queue, processing
     * @param {string} status
     */
    function setStatus(strings, status) {
        if (modal && modal.step2text && (status === 'pending' || status === 'processing')) {
            modal.step2text.textContent = stepWords(strings, status);
        }
    }

    /**
     * Empty the window and make it closable, with a title and a close cross.
     *
     * @param {Object} strings title, close
     * @param {string} titleText
     * @returns {HTMLElement} The box, ready for its content.
     */
    function resetBox(strings, titleText) {
        stopTimer();
        var box = modal.box;
        while (box.firstChild) {
            box.removeChild(box.firstChild);
        }
        box.classList.add('gp-retour-result');
        var head = el('div', 'gp-retour-head');
        var title = el('h2', 'gp-retour-title', titleText);
        title.id = 'gpRetourTitle';
        var cross = el('button', 'gp-retour-cross', '×');
        cross.type = 'button';
        cross.setAttribute('aria-label', strings.close);
        cross.addEventListener('click', closeModal);
        head.appendChild(title);
        head.appendChild(cross);
        box.appendChild(head);
        modal.closable = true;
        modal.step2text = null;
        return box;
    }

    /**
     * Add a button to the actions of the window.
     *
     * @param {HTMLElement} actions
     * @param {string} label
     * @param {string} className
     * @param {Function} action
     * @returns {HTMLElement}
     */
    function addAction(actions, label, className, action) {
        var button = el('button', 'btn ' + className, label);
        button.type = 'button';
        button.addEventListener('click', action);
        actions.appendChild(button);
        return button;
    }

    /**
     * A long wait (cron late, queue full): let the student go back to the copy; the feedback still shows in the panel.
     *
     * @param {Object} strings back
     */
    function allowLeave(strings) {
        if (!modal || modal.closable) {
            return;
        }
        modal.closable = true;
        var actions = el('div', 'gp-retour-actions');
        modal.box.appendChild(actions);
        addAction(actions, strings.back, 'btn-outline-primary', closeModal);
    }

    /**
     * The feedback is ready: show it in the window, with the actions it calls for.
     *
     * From SAT (propose_submit): "Submit my final version" put forward, and "Improve again" while feedbacks are left.
     * Otherwise: "Back to my copy".
     *
     * @param {Object} strings title, back, submit, close
     * @param {Object} data feedback_html, propose_submit, improve_label from the status endpoint
     * @returns {null}
     */
    function showResult(strings, data) {
        if (!modal) {
            return null;
        }
        var box = resetBox(strings, strings.title);
        var body = el('div', 'gp-retour-body');
        box.appendChild(body);
        var actions = el('div', 'gp-retour-actions');
        box.appendChild(actions);
        var first;
        if (data.propose_submit && document.getElementById('submitButton')) {
            if (data.improve_label) {
                addAction(actions, data.improve_label, 'btn-outline-primary', closeModal);
            }
            first = addAction(actions, strings.submit, 'btn-primary', function() {
                closeModal();
                var submit = document.getElementById('submitButton');
                if (submit && !submit.disabled) {
                    submit.click();
                }
            });
        } else {
            first = addAction(actions, strings.back, 'btn-primary', closeModal);
        }
        // Server-rendered and escaped (the same view as the panel). replaceNodeContents is synchronous and returns
        // the new nodes, not a promise.
        Templates.replaceNodeContents(body, data.feedback_html, '');
        first.focus();
        return null;
    }

    /**
     * The feedback failed: say so in the window (not counted, ask again), with a button back to the copy.
     *
     * @param {Object} strings failed, back, close
     * @param {string} [message] Words to show instead of strings.failed.
     */
    function showFailed(strings, message) {
        if (!modal) {
            return;
        }
        var box = resetBox(strings, '');
        box.classList.add('gp-formative-modal-failed');
        var line = el('p', 'gp-retour-failed');
        line.appendChild(el('span', 'gp-formative-modal-spinner', '!'));
        line.appendChild(el('span', '', message || strings.failed));
        box.appendChild(line);
        var actions = el('div', 'gp-retour-actions');
        box.appendChild(actions);
        addAction(actions, strings.back, 'btn-primary', closeModal).focus();
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
            openWait(cfg.strings, 'pending', 0);
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
                    closeModal();
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
     * Follow a running feedback; when it is ready, show it in the window and in place of the banner of the panel
     * (no reload).
     *
     * @param {Object} cfg cmid, step, groupid, pendingId, pendingStatus, elapsed, statusUrl, bannerId, strings
     */
    function follow(cfg) {
        var live = document.getElementById(cfg.bannerId);
        if (!live) {
            return;
        }
        openWait(cfg.strings, cfg.pendingStatus || 'pending', cfg.elapsed || 0);
        // Polls may overlap on a slow server: the finished feedback is handled once.
        var done = false;
        var leaveAt = Date.now() + Math.max(0, LEAVE_AFTER_MS - 1000 * (cfg.elapsed || 0));
        var timer = setInterval(function() {
            if (Date.now() >= leaveAt) {
                allowLeave(cfg.strings);
            }
            var sesskey = window.M && window.M.cfg ? window.M.cfg.sesskey : '';
            var url = cfg.statusUrl + '?id=' + encodeURIComponent(cfg.cmid) + '&evaluationid='
                + encodeURIComponent(cfg.pendingId) + '&sesskey=' + encodeURIComponent(sesskey);
            fetch(url, {credentials: 'same-origin'})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (done || !data || !data.success) {
                        return null;
                    }
                    // The evaluation was deleted (by a teacher): nothing will come, say so instead of waiting forever.
                    var gone = data.has_evaluation === false;
                    if (!gone && data.status !== 'completed' && data.status !== 'failed') {
                        setStatus(cfg.strings, data.status);
                        return null;
                    }
                    done = true;
                    clearInterval(timer);
                    restoreAsk(data, cfg);
                    if (data.status === 'completed' && data.feedback_html) {
                        // Server-rendered and escaped (the same view as after a reload). replaceNodeContents returns
                        // the new nodes, not a promise: a .then on it threw and the window stayed on "in preparation".
                        return Promise.resolve().then(function() {
                            Templates.replaceNodeContents(live, data.feedback_html, '');
                            return showResult(cfg.strings, data);
                        }).catch(function() {
                            // Shown after a reload anyway; the window must not stay on "in preparation".
                            showFailed(cfg.strings, cfg.strings.error);
                        });
                    }
                    showFailed(cfg.strings);
                    var banner = live.querySelector('.ai-progress-banner');
                    if (banner) {
                        banner.className = banner.className.replace(/\bstatus-\S+/g, '') + ' status-failed';
                    }
                    var label = live.querySelector('.ai-progress-label') || live;
                    label.textContent = cfg.strings.failed;
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
     * @param {Object} cfg cmid, step, groupid, buttonId, strings; or pendingId, pendingStatus, elapsed, statusUrl,
     *     bannerId, strings
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

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
 * "Ask for a feedback" button of the student step pages (formative feedback, v2.11.4).
 *
 * The copy shown is saved first (Autosave.save()), then the feedback is asked for, then the page reloads.
 *
 * @module     mod_gestionprojet/formative_button
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'mod_gestionprojet/autosave'], function(Ajax, Notification, Autosave) {
    'use strict';

    /**
     * Wire the button.
     *
     * @param {Object} cfg cmid, step, groupid, buttonId
     */
    function init(cfg) {
        var button = document.getElementById(cfg.buttonId);
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            button.disabled = true;
            // Save the copy on screen first: the feedback is about what the student sees.
            var saved = Autosave.save();
            Promise.resolve(saved).catch(function() { return null; })
                .then(function() {
                    return Ajax.call([{
                        methodname: 'mod_gestionprojet_request_formative',
                        args: {cmid: cfg.cmid, step: cfg.step, groupid: cfg.groupid},
                    }])[0];
                })
                .then(function() {
                    var url = new URL(window.location.href);
                    url.searchParams.set('_', String(Date.now()));
                    window.location.href = url.toString();
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

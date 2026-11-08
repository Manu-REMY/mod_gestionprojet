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
 * Step 4 level buttons ("Faire valider le niveau SAT"), v2.11.2.
 *
 * The copy shown is saved first, then the level is asked for, in the same batch (run in order on the server):
 * what the student sees is what the AI judges.
 *
 * @module     mod_gestionprojet/palier_buttons
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Reload the page from the server (cache-buster: some browsers serve a cached copy on reload).
     */
    function reload() {
        var url = new URL(window.location.href);
        url.searchParams.set('_', String(Date.now()));
        window.location.href = url.toString();
    }

    /**
     * Wire the level buttons.
     *
     * @param {Object} cfg cmid, groupid, dataFieldId
     */
    function init(cfg) {
        var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-gp-palier]'));
        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                buttons.forEach(function(b) { b.disabled = true; });
                var field = document.getElementById(cfg.dataFieldId);
                var calls = [];
                if (field) {
                    calls.push({
                        methodname: 'mod_gestionprojet_autosave',
                        args: {cmid: cfg.cmid, step: 4, groupid: cfg.groupid, mode: '',
                            data: JSON.stringify({'interacteurs_data': field.value})},
                    });
                }
                calls.push({
                    methodname: 'mod_gestionprojet_request_palier',
                    args: {cmid: cfg.cmid, groupid: cfg.groupid, palier: button.getAttribute('data-gp-palier')},
                });
                var promises = Ajax.call(calls);
                promises[promises.length - 1].then(reload).catch(function(error) {
                    buttons.forEach(function(b) { b.disabled = false; });
                    Notification.exception(error);
                });
            });
        });
    }

    return {init: init};
});

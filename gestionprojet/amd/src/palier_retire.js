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
 * Remove a validated step 4 level from the grading page (v2.11.2).
 *
 * @module     mod_gestionprojet/palier_retire
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Wire the remove buttons.
     *
     * @param {Object} cfg cmid, title
     */
    function init(cfg) {
        Array.prototype.slice.call(document.querySelectorAll('[data-gp-retire]')).forEach(function(button) {
            button.addEventListener('click', function() {
                Notification.saveCancelPromise(cfg.title, button.getAttribute('data-confirm'), cfg.title)
                    .then(function() {
                        button.disabled = true;
                        return Ajax.call([{
                            methodname: 'mod_gestionprojet_retire_palier',
                            args: {cmid: cfg.cmid, palierid: parseInt(button.getAttribute('data-gp-retire'), 10)},
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
                        // Cancelling the confirmation rejects without an error object: nothing to show.
                        if (error && error.message) {
                            Notification.exception(error);
                        }
                    });
            });
        });
    }

    return {init: init};
});

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
 * "Reseed the copies not started" button of the step 4 consigne page (preco 22, v2.11.3).
 *
 * @module     mod_gestionprojet/reseed_copies
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    'use strict';

    /**
     * Wire the button.
     *
     * @param {Object} cfg cmid, buttonId, title, confirm, done (with {$a} for the count)
     */
    function init(cfg) {
        var button = document.getElementById(cfg.buttonId);
        if (!button) {
            return;
        }
        button.addEventListener('click', function() {
            Notification.saveCancelPromise(cfg.title, cfg.confirm, cfg.title)
                .then(function() {
                    button.disabled = true;
                    return Ajax.call([{methodname: 'mod_gestionprojet_reseed_copies', args: {cmid: cfg.cmid}}])[0];
                })
                .then(function(result) {
                    button.disabled = false;
                    Notification.addNotification({message: cfg.done.replace('{$a}', String(result.count)), type: 'success'});
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
    }

    return {init: init};
});

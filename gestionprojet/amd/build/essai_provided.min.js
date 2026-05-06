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
 * Autosave + save button glue for the Essai consigne page (mode=provided).
 *
 * @module     mod_gestionprojet/essai_provided
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'mod_gestionprojet/autosave'], function($, Autosave) {
    return {
        /**
         * Initialise autosave on the consigne form and wire the manual save button.
         *
         * @param {Object} cfg
         * @param {number} cfg.cmid
         * @param {number} cfg.autosaveInterval Interval in milliseconds.
         */
        init: function(cfg) {
            Autosave.init({
                cmid: cfg.cmid,
                step: 5,
                groupid: 0,
                mode: 'provided',
                interval: cfg.autosaveInterval || 30000,
                formSelector: '#essaiProvidedForm'
            });

            var saveButton = document.getElementById('saveButton');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    Autosave.save();
                });
            }
        }
    };
});

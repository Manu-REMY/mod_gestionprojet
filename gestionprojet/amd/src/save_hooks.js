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
 * Registry of the immediate savers of a page (v2.11.4).
 *
 * Editors that do not use mod_gestionprojet/autosave (the FAST editor) register a function that saves now and
 * returns a promise (or nothing); flushAll() runs them all, so that an action can wait for the copy to be saved.
 *
 * @module     mod_gestionprojet/save_hooks
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var savers = [];

    return {
        /**
         * Register a saver.
         *
         * @param {Function} saver Saves now; returns a promise, a thenable or nothing.
         */
        register: function(saver) {
            savers.push(saver);
        },

        /**
         * Run every saver and wait for all of them (a failed save does not block).
         *
         * @returns {Promise}
         */
        flushAll: function() {
            return Promise.all(savers.map(function(saver) {
                return Promise.resolve(saver()).catch(function() { return null; });
            }));
        },
    };
});

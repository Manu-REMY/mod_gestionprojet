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
 * Teacher dates helper for correction model pages.
 *
 * Provides date-to-timestamp conversion utilities used by teacher model
 * autosave serialization.
 *
 * @module     mod_gestionprojet/teacher_dates
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Convert a date input string (YYYY-MM-DD) to a Unix timestamp.
     *
     * @param {String} dateStr The date string from an input[type=date].
     * @return {Number|null} Unix timestamp in seconds, or null if empty.
     */
    function dateToTimestamp(dateStr) {
        if (!dateStr) {
            return null;
        }
        var date = new Date(dateStr);
        return Math.floor(date.getTime() / 1000);
    }

    /**
     * Get submission and deadline date values from the form.
     *
     * @return {Object} Object with submission_date and deadline_date timestamps.
     */
    function getDateValues() {
        var submissionEl = document.getElementById('submission_date');
        var deadlineEl = document.getElementById('deadline_date');
        return {
            submission_date: submissionEl ? dateToTimestamp(submissionEl.value) : null,
            deadline_date: deadlineEl ? dateToTimestamp(deadlineEl.value) : null
        };
    }

    return {
        /**
         * Initialize and expose date helpers on window for inline serializers.
         */
        init: function() {
            // Expose as globals so existing teacher page serializers can access them.
            window.dateToTimestamp = dateToTimestamp;
            window.getDateValues = getDateValues;
        },

        dateToTimestamp: dateToTimestamp,
        getDateValues: getDateValues
    };
});

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
 * Gantt home dashboard — live step activation via AJAX.
 *
 * @module     mod_gestionprojet/gantt
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str', 'core/config'], function($, Notification, Str, Config) {

    var ENDPOINT = '/mod/gestionprojet/ajax/toggle_step.php';

    var Gantt = {

        init: function() {
            var $root = $('.gp-gantt');
            if (!$root.length) {
                return;
            }
            var cmid = $root.data('cmid');
            var sesskey = $root.data('sesskey');
            $root.on('change', '.gp-cell-cb', function() {
                Gantt.handleToggle($(this), cmid, sesskey);
            });
        },

        handleToggle: function($cb, cmid, sesskey) {
            var stepnum = $cb.data('stepnum');
            var row = $cb.data('row');
            var enabled = $cb.is(':checked') ? 1 : 0;
            var $cell = $cb.closest('.gp-cell');

            // Optimistic UI update.
            Gantt.applyVisualState($cell, $cb, row, enabled);

            $.ajax({
                url: Config.wwwroot + ENDPOINT,
                method: 'POST',
                dataType: 'json',
                data: {
                    cmid: cmid,
                    stepnum: stepnum,
                    enabled: enabled,
                    sesskey: sesskey
                }
            }).done(function(response) {
                if (!response.success) {
                    Gantt.revertVisualState($cell, $cb, row, !enabled);
                    Notification.alert('Erreur', response.message || 'Erreur lors de la mise à jour');
                }
            }).fail(function() {
                Gantt.revertVisualState($cell, $cb, row, !enabled);
                Str.get_string('gantt_toggle_error', 'mod_gestionprojet').done(function(s) {
                    Notification.alert('Erreur', s);
                });
            });
        },

        applyVisualState: function($cell, $cb, row, enabled) {
            if (enabled) {
                $cell.removeClass('gp-cell-disabled');
            } else {
                $cell.addClass('gp-cell-disabled');
            }
            // For shared (row=models) checkbox, mirror the visual change on row 3 (student).
            if (row === 'models') {
                var stepnum = $cb.data('stepnum');
                $('.gp-cell-student[data-stepnum="' + stepnum + '"]').toggleClass('gp-cell-disabled', !enabled);
            }
        },

        revertVisualState: function($cell, $cb, row, restoreEnabled) {
            $cb.prop('checked', restoreEnabled);
            this.applyVisualState($cell, $cb, row, restoreEnabled);
        }
    };

    return Gantt;
});

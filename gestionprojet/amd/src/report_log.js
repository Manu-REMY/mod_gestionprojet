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
 * AI log of the report page: show more / less of a long text, expand the prompts (moved out of the page, v2.13.7).
 *
 * @module     mod_gestionprojet/report_log
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    return {
        /**
         * Wire the buttons of the log.
         *
         * @param {Object} strings showMore, showLess, expandPrompts, collapsePrompts
         */
        init: function(strings) {
            $(document).on('click', '.show-more-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var fullText = String(btn.data('full-text'));
                var pre = btn.prev('pre');
                if (btn.hasClass('expanded')) {
                    pre.text(fullText.substring(0, 500) + '...');
                    btn.text(strings.showMore);
                    btn.removeClass('expanded');
                } else {
                    pre.text(fullText);
                    btn.text(strings.showLess);
                    btn.addClass('expanded');
                }
            });

            $(document).on('click', '.ai-log-expand', function() {
                var btn = $(this);
                var target = $(btn.attr('data-bs-target'));
                target.toggleClass('show');
                if (target.hasClass('show')) {
                    btn.text(strings.collapsePrompts);
                    btn.attr('aria-expanded', 'true');
                } else {
                    btn.text(strings.expandPrompts);
                    btn.attr('aria-expanded', 'false');
                }
            });
        }
    };
});

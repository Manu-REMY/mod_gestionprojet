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
 * Student-side polling for AI evaluation progress.
 *
 * @module     mod_gestionprojet/student_ai_progress
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var config = {};
    var pollTimer = null;
    var POLL_INTERVAL = 5000;

    /**
     * Initialize the progress poller.
     *
     * @param {Object} cfg {evaluationid, statusUrl, cmid, strings}
     */
    function init(cfg) {
        config = cfg || {};
        if (!config.evaluationid || !config.statusUrl) {
            return;
        }
        startPolling();
    }

    function startPolling() {
        // Immediate first check, then every POLL_INTERVAL.
        checkStatus();
        pollTimer = setInterval(checkStatus, POLL_INTERVAL);
    }

    function checkStatus() {
        var sesskeyEl = document.querySelector('input[name="sesskey"]');
        var sesskey = sesskeyEl ? sesskeyEl.value
            : (window.M && window.M.cfg && window.M.cfg.sesskey ? window.M.cfg.sesskey : '');

        var url = config.statusUrl
            + '?id=' + encodeURIComponent(config.cmid)
            + '&evaluationid=' + encodeURIComponent(config.evaluationid)
            + '&sesskey=' + encodeURIComponent(sesskey);

        fetch(url, {credentials: 'same-origin'})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.success) {
                    return;
                }
                updateBanner(data.status);
                if (data.status === 'completed' || data.status === 'applied') {
                    clearInterval(pollTimer);
                    window.location.reload();
                } else if (data.status === 'failed') {
                    clearInterval(pollTimer);
                    // Banner already updated to "failed_student" message; no reload needed.
                }
            })
            .catch(function() {
                // Silent: retry on next tick.
            });
    }

    function updateBanner(status) {
        var banner = document.getElementById('ai-progress-banner');
        if (!banner) {
            return;
        }
        banner.dataset.status = status;
        // Toggle CSS classes for status.
        banner.className = banner.className.replace(/\bstatus-\S+/g, '');
        banner.classList.add('ai-progress-banner', 'status-' + status);

        var label = banner.querySelector('.ai-progress-label');
        if (label) {
            // Map status to lang key (all use _student suffix).
            var key = status + '_student';
            if (config.strings && config.strings[key]) {
                label.textContent = config.strings[key];
            }
        }
    }

    return {
        init: init
    };
});

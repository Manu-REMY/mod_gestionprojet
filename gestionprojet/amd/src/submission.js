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
 * Submission handling for student step pages.
 *
 * @module     mod_gestionprojet/submission
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var config = {};

    /**
     * Initialize submission handling.
     *
     * @param {Object} cfg Configuration object from PHP.
     */
    function init(cfg) {
        config = cfg || {};

        var submitBtn = document.getElementById('submitStepBtn');
        if (!submitBtn) {
            return;
        }

        submitBtn.addEventListener('click', function() {
            submitStep(this);
        });
    }

    /**
     * Handle step submission.
     *
     * @param {HTMLElement} btn The submit button.
     */
    function submitStep(btn) {
        var confirmMsg = config.strings.confirm_submit || 'Are you sure?';
        if (!confirm(confirmMsg)) {
            return;
        }

        // Save original children for restoration.
        var originalNodes = [];
        var i;
        for (i = 0; i < btn.childNodes.length; i++) {
            originalNodes.push(btn.childNodes[i].cloneNode(true));
        }

        // Set loading state.
        btn.disabled = true;
        while (btn.firstChild) {
            btn.removeChild(btn.firstChild);
        }
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        btn.appendChild(spinner);
        btn.appendChild(document.createTextNode(' ' + (config.strings.submitting || 'Submitting...')));

        Ajax.call([{
            methodname: 'mod_gestionprojet_submit_step',
            args: {
                cmid: config.cmid,
                step: config.step,
                action: 'submit'
            }
        }])[0].done(function(data) {
            if (data.success) {
                window.location.reload();
            } else {
                restoreButton(btn, originalNodes);
                var errorMsg = config.strings.submission_error || 'Error';
                alert(data.message || errorMsg);
            }
        }).fail(function() {
            restoreButton(btn, originalNodes);
            alert(config.strings.submission_error || 'Error');
        });
    }

    /**
     * Restore a button from loading state.
     *
     * @param {HTMLElement} btn The button element.
     * @param {Array} originalNodes The saved child nodes.
     */
    function restoreButton(btn, originalNodes) {
        btn.disabled = false;
        while (btn.firstChild) {
            btn.removeChild(btn.firstChild);
        }
        var i;
        for (i = 0; i < originalNodes.length; i++) {
            btn.appendChild(originalNodes[i]);
        }
    }

    return {
        init: init
    };
});

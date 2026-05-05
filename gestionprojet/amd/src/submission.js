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
 * Submission handling for student step pages — modal confirmation + AJAX submit.
 *
 * @module     mod_gestionprojet/submission
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/modal_factory',
    'core/modal_events',
    'core/templates',
    'core/notification'
], function($, Ajax, ModalFactory, ModalEvents, Templates, Notification) {

    var config = {};

    /**
     * Initialize submission handling.
     *
     * @param {Object} cfg Configuration object from PHP.
     */
    function init(cfg) {
        config = cfg || {};
        var btn = document.getElementById('submitStepBtn');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', openModal);
    }

    /**
     * Open the confirmation modal.
     */
    function openModal() {
        Templates.render('mod_gestionprojet/submit_modal', {
            isgroup: !!config.isGroup,
            aienabled: !!config.aiEnabled
        }).then(function(html) {
            return ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: config.strings.modal_title,
                body: html,
                large: false
            });
        }).then(function(modal) {
            modal.setSaveButtonText(config.strings.confirm_submit_btn);

            // Disable save button until checkbox is checked.
            modal.getRoot().on('change', '#submit-confirm-checkbox', function() {
                modal.getRoot().find('[data-action="save"]').prop('disabled', !this.checked);
            });

            modal.getRoot().on(ModalEvents.shown, function() {
                modal.getRoot().find('[data-action="save"]').prop('disabled', true);
            });

            modal.getRoot().on(ModalEvents.save, function(e) {
                e.preventDefault();
                doSubmit(modal);
            });

            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Perform the AJAX submission.
     *
     * @param {Object} modal The modal instance.
     */
    function doSubmit(modal) {
        var saveBtn = modal.getRoot().find('[data-action="save"]');
        saveBtn.prop('disabled', true).text(config.strings.submitting);

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
                modal.hide();
                window.alert(data.message || config.strings.submission_error);
            }
        }).fail(function() {
            modal.hide();
            window.alert(config.strings.submission_error);
        });
    }

    return {
        init: init
    };
});

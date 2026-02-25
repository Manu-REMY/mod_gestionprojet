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
 * Grading page interactions for mod_gestionprojet.
 *
 * Handles toggle sections, AI trigger/apply/retry buttons,
 * unlock submission, bulk reevaluate, and auto-reload for pending evaluations.
 *
 * @module     mod_gestionprojet/grading
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var config = {};

    /**
     * Toggle a collapsible section.
     *
     * @param {HTMLElement} toggleElement The toggle header element.
     */
    function toggleSection(toggleElement) {
        toggleElement.classList.toggle('collapsed');
        var content = toggleElement.nextElementSibling;
        if (content) {
            content.classList.toggle('collapsed');
        }
    }

    /**
     * Set a button to loading state.
     *
     * @param {HTMLElement} btn The button element.
     * @param {String} loadingText Text to show during loading.
     * @return {Array} Original child nodes for restoration.
     */
    function setButtonLoading(btn, loadingText) {
        // Save original children by cloning.
        var originalNodes = [];
        var i;
        for (i = 0; i < btn.childNodes.length; i++) {
            originalNodes.push(btn.childNodes[i].cloneNode(true));
        }

        // Clear and set loading state.
        btn.disabled = true;
        while (btn.firstChild) {
            btn.removeChild(btn.firstChild);
        }

        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        btn.appendChild(spinner);
        btn.appendChild(document.createTextNode(' ' + (loadingText || '...')));

        return originalNodes;
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

    /**
     * Set up all collapsible section toggles via event delegation.
     */
    function initToggles() {
        document.addEventListener('click', function(e) {
            var toggle = e.target.closest('.ai-section-toggle');
            if (toggle) {
                e.preventDefault();
                toggleSection(toggle);
            }
        });
    }

    /**
     * Set up the AI trigger evaluation button.
     */
    function initTriggerButton() {
        var triggerBtn = document.getElementById('btn-trigger-ai-eval');
        if (!triggerBtn) {
            return;
        }

        triggerBtn.addEventListener('click', function() {
            var btn = this;
            if (typeof require !== 'undefined') {
                require(['mod_gestionprojet/ai_progress'], function(AIProgress) {
                    AIProgress.triggerEvaluation(
                        parseInt(btn.dataset.cmid),
                        parseInt(btn.dataset.step),
                        parseInt(btn.dataset.submissionid)
                    );
                });
            }
        });
    }

    /**
     * Set up the apply AI grade button.
     */
    function initApplyButton() {
        var applyBtn = document.getElementById('btn-apply-ai-grade');
        if (!applyBtn) {
            return;
        }

        applyBtn.addEventListener('click', function() {
            var btn = this;
            if (typeof require !== 'undefined') {
                require(['mod_gestionprojet/ai_progress'], function(AIProgress) {
                    AIProgress.applyGrade(
                        parseInt(btn.dataset.cmid),
                        parseInt(btn.dataset.evaluationid)
                    );
                });
            }
        });
    }

    /**
     * Set up the retry AI evaluation button.
     */
    function initRetryButton() {
        var retryBtn = document.getElementById('btn-retry-ai-eval');
        if (!retryBtn) {
            return;
        }

        retryBtn.addEventListener('click', function() {
            if (typeof require !== 'undefined') {
                require(['mod_gestionprojet/ai_progress'], function(AIProgress) {
                    AIProgress.retryEvaluation(
                        config.cmid,
                        config.step,
                        config.submissionid
                    );
                });
            }
        });
    }

    /**
     * Set up the "apply with modifications" button.
     * Fills the manual grade form with AI values.
     */
    function initModifyButton() {
        var modifyBtn = document.querySelector('.btn-ai-modify');
        if (!modifyBtn) {
            return;
        }

        modifyBtn.addEventListener('click', function() {
            var gradeInput = document.getElementById('grade');
            var feedbackInput = document.getElementById('feedback');

            if (gradeInput) {
                gradeInput.value = this.dataset.grade || '';
            }
            if (feedbackInput) {
                feedbackInput.value = this.dataset.feedback || '';
            }
            if (gradeInput) {
                gradeInput.focus();
            }
        });
    }

    /**
     * Set up the unlock submission button.
     */
    function initUnlockButton() {
        var unlockBtn = document.getElementById('btn-unlock-submission');
        if (!unlockBtn) {
            return;
        }

        unlockBtn.addEventListener('click', function() {
            var confirmMsg = config.strings.confirm_unlock || 'Are you sure?';
            if (!confirm(confirmMsg)) {
                return;
            }

            var btn = this;
            var saved = setButtonLoading(btn, '...');

            Ajax.call([{
                methodname: 'mod_gestionprojet_submit_step',
                args: {
                    cmid: parseInt(btn.dataset.cmid),
                    step: parseInt(btn.dataset.step),
                    action: 'unlock'
                }
            }])[0].done(function(data) {
                if (data.success) {
                    location.reload();
                } else {
                    var errorMsg = config.strings.error || 'Error';
                    alert(data.message || errorMsg);
                    restoreButton(btn, saved);
                }
            }).fail(function() {
                var networkMsg = config.strings.network_error || 'Network error';
                alert(networkMsg);
                restoreButton(btn, saved);
            });
        });
    }

    /**
     * Set up the bulk reevaluate button.
     */
    function initBulkReevaluateButton() {
        var bulkBtn = document.getElementById('btn-bulk-reevaluate');
        if (!bulkBtn) {
            return;
        }

        bulkBtn.addEventListener('click', function() {
            var confirmMsg = config.strings.confirm_bulk || 'Are you sure?';
            if (!confirm(confirmMsg)) {
                return;
            }

            var btn = this;
            var processingMsg = config.strings.bulk_processing || 'Processing...';
            var saved = setButtonLoading(btn, processingMsg);

            Ajax.call([{
                methodname: 'mod_gestionprojet_bulk_reevaluate',
                args: {
                    cmid: parseInt(btn.dataset.cmid),
                    step: parseInt(btn.dataset.step)
                }
            }])[0].done(function(data) {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    var errorMsg = config.strings.error || 'Error';
                    alert(data.message || errorMsg);
                    restoreButton(btn, saved);
                }
            }).fail(function() {
                var networkMsg = config.strings.network_error || 'Network error';
                alert(networkMsg);
                restoreButton(btn, saved);
            });
        });
    }

    /**
     * Set up the item selector dropdown for navigation.
     */
    function initItemSelector() {
        var selector = document.getElementById('grading-item-selector');
        if (!selector) {
            return;
        }

        selector.addEventListener('change', function() {
            if (this.value) {
                window.location.href = this.value;
            }
        });
    }

    /**
     * Set up auto-reload for pending AI evaluations.
     */
    function initAutoReload() {
        var pendingEl = document.querySelector('.ai-pending[data-auto-reload]');
        if (!pendingEl) {
            return;
        }

        var delay = parseInt(pendingEl.dataset.autoReload) || 10000;
        setTimeout(function() {
            location.reload();
        }, delay);
    }

    return {
        /**
         * Initialize grading page interactions.
         *
         * @param {Object} cfg Configuration object from PHP.
         */
        init: function(cfg) {
            config = cfg || {};
            config.strings = config.strings || {};

            initToggles();
            initTriggerButton();
            initApplyButton();
            initRetryButton();
            initModifyButton();
            initUnlockButton();
            initBulkReevaluateButton();
            initItemSelector();
            initAutoReload();
        }
    };
});

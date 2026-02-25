/**
 * Teacher Dashboard module for Gestion de Projet.
 *
 * @module     mod_gestionprojet/dashboard
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/templates'],
function($, Ajax, Notification, Str, Templates) {

    /**
     * Chart.js instance storage
     * @type {Object}
     */
    var charts = {};

    /**
     * Initialize grade distribution chart using Chart.js.
     *
     * @param {number} step - Step number
     * @param {Array} distribution - Array of 5 values for grade buckets
     */
    var initGradeChart = function(step, distribution) {
        var canvas = document.getElementById('grade-distribution-' + step);
        if (!canvas) {
            return;
        }

        // Check if Chart.js is available
        if (typeof window.Chart === 'undefined') {
            // Load Chart.js dynamically
            require(['core/chartjs'], function(Chart) {
                createChart(canvas, step, distribution, Chart);
            });
        } else {
            createChart(canvas, step, distribution, window.Chart);
        }
    };

    /**
     * Create the chart instance.
     *
     * @param {HTMLCanvasElement} canvas - Canvas element
     * @param {number} step - Step number
     * @param {Array} distribution - Grade distribution data
     * @param {Object} Chart - Chart.js constructor
     */
    var createChart = function(canvas, step, distribution, Chart) {
        // Destroy existing chart if any
        if (charts[step]) {
            charts[step].destroy();
        }

        var ctx = canvas.getContext('2d');
        charts[step] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['0-4', '5-8', '9-12', '13-16', '17-20'],
                datasets: [{
                    label: '',
                    data: distribution,
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.7)',   // Red - 0-4
                        'rgba(255, 193, 7, 0.7)',  // Yellow - 5-8
                        'rgba(23, 162, 184, 0.7)', // Cyan - 9-12
                        'rgba(40, 167, 69, 0.7)',  // Green - 13-16
                        'rgba(0, 123, 255, 0.7)'   // Blue - 17-20
                    ],
                    borderColor: [
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(0, 123, 255, 1)'
                    ],
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = context.raw;
                                var label = value === 1 ? ' soumission' : ' soumissions';
                                return value + label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    };

    /**
     * Refresh AI summary for a step.
     *
     * @param {number} cmid - Course module ID
     * @param {number} step - Step number
     * @param {jQuery} button - Button element
     */
    var refreshSummary = function(cmid, step, button) {
        // Disable button and show spinner
        button.prop('disabled', true);
        var icon = button.find('i');
        icon.removeClass('fa-sync-alt').addClass('fa-spinner fa-spin');

        Ajax.call([{
            methodname: 'mod_gestionprojet_generate_ai_summary',
            args: {
                cmid: cmid,
                step: step,
                force: true
            }
        }])[0].done(function(response) {
            if (response.success) {
                // Show success message
                Str.get_string('dashboard:summary_refreshed', 'gestionprojet').done(function(str) {
                    showToast(str, 'success');
                });
                // Reload the page to show updated summary
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                Notification.alert(
                    M.util.get_string('error', 'moodle') || 'Error',
                    response.message
                );
                // Re-enable button
                button.prop('disabled', false);
                icon.removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
            }
        }).fail(function(error) {
            Notification.exception(error);
            // Re-enable button
            button.prop('disabled', false);
            icon.removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
        });
    };

    /**
     * Show a toast notification.
     *
     * @param {string} message - Message to display
     * @param {string} type - Type (success, error, info)
     */
    var showToast = function(message, type) {
        var bgColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#17a2b8');

        var toast = $('<div class="gestionprojet-toast"></div>')
            .text(message)
            .css({
                'position': 'fixed',
                'bottom': '20px',
                'right': '20px',
                'background': bgColor,
                'color': 'white',
                'padding': '12px 24px',
                'border-radius': '8px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'z-index': '9999',
                'font-size': '14px',
                'opacity': '0',
                'transition': 'opacity 0.3s ease'
            });

        $('body').append(toast);

        // Fade in
        setTimeout(function() {
            toast.css('opacity', '1');
        }, 10);

        // Fade out and remove
        setTimeout(function() {
            toast.css('opacity', '0');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    };

    /**
     * Initialize dashboard module.
     *
     * @param {number} cmid - Course module ID
     * @param {number} step - Step number
     * @param {Array} gradeDistribution - Grade distribution array
     */
    var init = function(cmid, step, gradeDistribution) {
        // Initialize chart
        initGradeChart(step, gradeDistribution);

        // Bind refresh button click
        $(document).off('click.dashboard-refresh-' + step).on('click.dashboard-refresh-' + step, '.refresh-summary-btn[data-step="' + step + '"]', function(e) {
            e.preventDefault();
            var button = $(this);
            var buttonCmid = button.data('cmid') || cmid;
            var buttonStep = button.data('step');
            refreshSummary(buttonCmid, buttonStep, button);
        });
    };

    return {
        init: init,
        initGradeChart: initGradeChart,
        refreshSummary: refreshSummary
    };
});

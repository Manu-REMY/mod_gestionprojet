/**
 * Test API connection module for mod_gestionprojet.
 *
 * @module     mod_gestionprojet/test_api
 * @copyright  2026 Emmanuel REMY
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    /**
     * Initialize the test API button handler.
     *
     * @param {number} cmid Course module ID (0 for new activity)
     */
    var init = function(cmid) {
        var testBtn = $('#id_test_api_btn');

        if (!testBtn.length) {
            return;
        }

        testBtn.on('click', function(e) {
            e.preventDefault();

            var provider = $('#id_ai_provider').val();
            var apiKey = $('#id_ai_api_key').val();

            // Providers with built-in API keys
            var builtinKeyProviders = ['albert'];
            var hasBuiltinKey = builtinKeyProviders.indexOf(provider) !== -1;

            // Validate inputs
            if (!provider) {
                Str.get_string('ai_provider_required', 'mod_gestionprojet').then(function(str) {
                    Notification.alert('Error', str);
                }).catch(Notification.exception);
                return;
            }

            // Only require API key for providers without built-in keys
            if (!apiKey && !hasBuiltinKey) {
                Str.get_string('ai_api_key_required', 'mod_gestionprojet').then(function(str) {
                    Notification.alert('Error', str);
                }).catch(Notification.exception);
                return;
            }

            // Show loading state
            var originalText = testBtn.text();
            testBtn.prop('disabled', true).text('Testing...');

            // Call external service for API connection test.
            Ajax.call([{
                methodname: 'mod_gestionprojet_test_api_connection',
                args: {
                    cmid: cmid,
                    provider: provider,
                    apikey: hasBuiltinKey ? '' : apiKey
                }
            }])[0].done(function(response) {
                if (response.success) {
                    Notification.alert('Success', response.message, 'OK');
                } else {
                    Notification.alert('Error', response.message, 'OK');
                }
            }).fail(function(ex) {
                Notification.alert('Error', 'Connection failed: ' + (ex.message || ex.error), 'OK');
            }).always(function() {
                // Restore button state.
                testBtn.prop('disabled', false).text(originalText);
            });
        });
    };

    return {
        init: init
    };
});

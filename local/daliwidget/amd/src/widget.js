/**
 * Dali AI Widget AMD module for Moodle
 *
 * @module     local_daliwidget/widget
 * @copyright  2024 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        /**
         * Initialize the Dali AI Widget
         *
         * @param {Object} config Configuration object from Moodle
         * @param {string} config.apiKey The API key for authentication
         * @param {string} config.baseUrl The base URL of the Dali app
         * @param {Object} config.user User information
         * @param {Object|null} config.course Course information (if applicable)
         * @param {Object|null} config.activity Activity information (if applicable)
         * @param {Object} config.page Page information
         */
        init: function(config) {
            // Enable debug if set
            if (config && config.debug) {
                console.log('[DaliWidget Debug] AMD module init called');
            }
            
            // Prevent double initialization
            if (window.DaliSDK) {
                if (config && config.debug) {
                    console.log('[DaliWidget Debug] SDK already initialized, skipping');
                }
                return;
            }

            // Set up Dali settings with Moodle context
            window.daliSettings = {
                apiKey: config.apiKey,
                baseUrl: config.baseUrl,
                // User identity for chat widget (auto-fills identity form)
                user: {
                    name: config.user.fullname || config.user.username,
                    email: config.user.email || (config.user.username + '@moodle.local')
                },
                // Pass Moodle context as metadata
                metadata: {
                    platform: 'moodle',
                    moodle_user_id: config.user.id,
                    moodle_username: config.user.username,
                    moodle_fullname: config.user.fullname,
                    moodle_email: config.user.email,
                    moodle_roles: config.user.roles || [],
                    course_id: config.course ? config.course.id : null,
                    course_name: config.course ? config.course.fullname : null,
                    course_shortname: config.course ? config.course.shortname : null,
                    activity_type: config.activity ? config.activity.modname : null,
                    activity_id: config.activity ? config.activity.id : null,
                    activity_name: config.activity ? config.activity.name : null,
                    page_type: config.page.type,
                    page_url: config.page.url
                }
            };

            // Debug helper
            if (config.debug) {
                console.log('[DaliWidget Debug] Initializing with config:', config);
            }

            // Dynamically load the Dali SDK
            var script = document.createElement('script');
            script.src = config.baseUrl + '/widget-sdk.js';
            script.defer = true;
            script.async = true;
            script.onload = function() {
                if (window.DaliSDK) {
                    if (config.debug) {
                        console.log('[DaliWidget Debug] SDK loaded successfully, starting...');
                    }
                    window.DaliSDK.run(window.daliSettings);
                } else {
                    if (config.debug) {
                        console.error('[DaliWidget Debug] DaliSDK not found after script load');
                    }
                }
            };
            script.onerror = function() {
                console.error('[Dali AI Widget] Failed to load SDK from:', config.baseUrl);
                if (config.debug) {
                    console.error('[DaliWidget Debug] SDK load failed. Check URL:', script.src);
                }
            };

            document.head.appendChild(script);
        }
    };
});

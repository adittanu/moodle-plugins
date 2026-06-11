<?php
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
 * Hook callbacks for local_daliwidget
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget\hook\output;

use core\hook\output\before_footer_html_generation;

require_once(__DIR__ . '/../../../classes/fetch_auth_helper.php');

/**
 * Hook callbacks for injecting the Dali AI widget
 */
class before_footer_callback {
    
    /**
     * Inject the Dali AI widget into the page footer
     *
     * @param before_footer_html_generation $hook
     */
    public static function callback(before_footer_html_generation $hook): void {
        global $PAGE, $USER, $COURSE;

        // Check if plugin is enabled
        $enabled = get_config('local_daliwidget', 'enabled');
        if (!$enabled) {
            return;
        }

        // Check if user has capability to view widget
        $context = \context_system::instance();
        if (!has_capability('local/daliwidget:view', $context)) {
            return;
        }

        // Get settings
        $apikey = get_config('local_daliwidget', 'apikey');
        $baseurl = get_config('local_daliwidget', 'baseurl');
        $strictcoursemode = get_config('local_daliwidget', 'strict_course_mode');

        // Don't load widget if no API key configured
        if (empty($apikey) || empty($baseurl)) {
            return;
        }

        // Don't show widget on quiz pages (prevent cheating)
        // Check via course module
        if (isset($PAGE->cm) && $PAGE->cm && $PAGE->cm->modname === 'quiz') {
            return;
        }
        // Also check via page type for quiz attempt, review, summary pages
        if (strpos($PAGE->pagetype, 'mod-quiz') === 0) {
            return;
        }

        // Build context data
        $fetchauth = \local_daliwidget\fetch_auth_helper::generate_for_user((int)$USER->id) ?? [];
        $contextdata = [
            'apiKey' => $apikey,
            'baseUrl' => rtrim($baseurl, '/'),
            'endpoints' => [
                'myCourses' => (new \moodle_url('/local/daliwidget/user_courses.php'))->out(false),
            ],
            'sesskey' => sesskey(),
            'fetchAuth' => $fetchauth,
            'user' => [
                'id' => $USER->id,
                'username' => $USER->username ?? '',
                'fullname' => fullname($USER),
                'email' => $USER->email ?? '',
            ],
            'course' => null,
            'activity' => null,
            'page' => [
                'type' => $PAGE->pagetype,
                'url' => $PAGE->url->out(false),
            ],
            'strict_course_mode' => !empty($strictcoursemode),
        ];

        // Add course context if on a course page
        if ($COURSE && $COURSE->id > 1) {
            $contextdata['course'] = [
                'id' => $COURSE->id,
                'fullname' => $COURSE->fullname,
                'shortname' => $COURSE->shortname,
            ];
        }

        // Detect activity context
        if (isset($PAGE->cm) && $PAGE->cm) {
            $contextdata['activity'] = [
                'id' => $PAGE->cm->id,
                'name' => $PAGE->cm->name,
                'modname' => $PAGE->cm->modname,
            ];
        }

        // Detect user role
        $roles = [];
        if ($COURSE && $COURSE->id > 1) {
            $coursecontext = \context_course::instance($COURSE->id);
            $userroles = get_user_roles($coursecontext, $USER->id, true);
            foreach ($userroles as $role) {
                $roles[] = $role->shortname;
            }
        }
        $contextdata['user']['roles'] = $roles;

        // Encode context data as JSON
        $contextjson = json_encode($contextdata, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Generate the script HTML
        $html = <<<HTML
<script>
(function() {
    var daliConfig = {$contextjson};
    
    // Prevent double initialization
    if (window.DaliSDK) {
        return;
    }
    
    // Set up Dali settings with Moodle context
    window.daliSettings = {
        apiKey: daliConfig.apiKey,
        baseUrl: daliConfig.baseUrl,
        moodle: {
            sesskey: daliConfig.sesskey,
            endpoints: daliConfig.endpoints || {}
        },
        // User identity for chat widget (auto-fills identity form)
        user: {
            name: daliConfig.user.fullname || daliConfig.user.username,
            email: daliConfig.user.email || (daliConfig.user.username + '@moodle.local')
        },
        metadata: {
            platform: 'moodle',
            moodle_user_id: daliConfig.user.id,
            moodle_username: daliConfig.user.username,
            moodle_fullname: daliConfig.user.fullname,
            moodle_email: daliConfig.user.email,
            moodle_roles: daliConfig.user.roles || [],
            moodle_my_courses_endpoint: daliConfig.endpoints && daliConfig.endpoints.myCourses ? daliConfig.endpoints.myCourses : null,
            moodle_fetch_user_id: daliConfig.fetchAuth ? daliConfig.fetchAuth.signed_user_id : null,
            moodle_fetch_expires: daliConfig.fetchAuth ? daliConfig.fetchAuth.expires : null,
            moodle_fetch_sig: daliConfig.fetchAuth ? daliConfig.fetchAuth.sig : null,
            course_id: daliConfig.course ? daliConfig.course.id : null,
            course_name: daliConfig.course ? daliConfig.course.fullname : null,
            course_shortname: daliConfig.course ? daliConfig.course.shortname : null,
            activity_type: daliConfig.activity ? daliConfig.activity.modname : null,
            activity_id: daliConfig.activity ? daliConfig.activity.id : null,
            activity_name: daliConfig.activity ? daliConfig.activity.name : null,
            page_type: daliConfig.page.type,
            page_url: daliConfig.page.url,
            strict_course_mode: daliConfig.strict_course_mode
        }
    };

    if (daliConfig.endpoints && daliConfig.endpoints.myCourses && daliConfig.fetchAuth) {
        var debugMyCoursesUrl = new URL(daliConfig.endpoints.myCourses, window.location.origin);
        debugMyCoursesUrl.searchParams.set('signed_user_id', daliConfig.fetchAuth.signed_user_id || '');
        debugMyCoursesUrl.searchParams.set('expires', daliConfig.fetchAuth.expires || '');
        debugMyCoursesUrl.searchParams.set('sig', daliConfig.fetchAuth.sig || '');
        console.log('[Dali AI Widget] Moodle myCourses signed URL:', debugMyCoursesUrl.toString());
    }

    // Dynamically load the Dali SDK
    var script = document.createElement('script');
    script.src = daliConfig.baseUrl + '/widget-sdk.js';
    script.defer = true;
    script.async = true;
    script.onload = function() {
        if (window.DaliSDK) {
            window.DaliSDK.run(window.daliSettings);
        }
    };
    script.onerror = function() {
        console.error('[Dali AI Widget] Failed to load SDK from:', daliConfig.baseUrl);
    };

    document.head.appendChild(script);
})();
</script>
HTML;

        $hook->add_html($html);
    }
}

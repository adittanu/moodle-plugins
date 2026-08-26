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
 * Library functions for local_daliwidget
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/fetch_auth_helper.php');
require_once(__DIR__ . '/classes/appearance.php');

/**
 * Serve only the configured public assistant avatar.
 */
function local_daliwidget_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload,
        array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'avatar' || count($args) !== 2
            || (int) $args[0] !== 0) {
        return false;
    }
    $filename = clean_param($args[1], PARAM_FILE);
    $files = get_file_storage()->get_area_files(
        $context->id, 'local_daliwidget', 'avatar', 0, 'id DESC', false
    );
    $file = reset($files);
    if (!$file || $file->get_filename() !== $filename
            || !in_array($file->get_mimetype(), ['image/png', 'image/jpeg', 'image/webp'], true)
            || $file->get_filesize() > 2 * 1024 * 1024) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, false, $options);
}

/**
 * Extend course navigation to add Knowledge Base link
 *
 * @param navigation_node $navigation The navigation node
 * @param stdClass $course The course object
 * @param context $context The course context
 */
function local_daliwidget_extend_navigation_course($navigation, $course, $context) {
    // Check if user can edit course (teachers/admins)
    if (has_capability('moodle/course:update', $context)) {
        $url = new moodle_url('/local/daliwidget/knowledge.php', ['id' => $course->id]);
        $navigation->add(
            get_string('knowledge_base', 'local_daliwidget'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'dali_knowledge',
            new pix_icon('i/settings', '')
        );
    }
}


/**
 * Whether the widget may render for the current user under the access mode.
 *
 * @param string $knowledgeaccessmode
 * @return bool
 */
function local_daliwidget_can_render_for_user(string $knowledgeaccessmode): bool {
    return $knowledgeaccessmode !== 'site_wide' || !isguestuser();
}

/**
 * Legacy callback for before footer (Moodle 4.1 - 4.4).
 * Injects the Dali AI widget into page footer.
 *
 * @return string
 */
function local_daliwidget_before_footer() {
    global $PAGE, $USER, $COURSE;

    // Only use this callback if hook system is not available (Moodle 4.x).
    if (!class_exists('\core\hook\output\before_footer_html_generation')) {

        // Check if plugin is enabled
        $enabled = get_config('local_daliwidget', 'enabled');
        if (!$enabled) {
            return '';
        }

        // Check if user has capability to view widget
        $context = context_system::instance();
        if (!has_capability('local/daliwidget:view', $context)) {
            return '';
        }

        // Get settings
        $apikey = get_config('local_daliwidget', 'apikey');
        $baseurl = get_config('local_daliwidget', 'baseurl');
        $knowledgeaccessmode = get_config('local_daliwidget', 'knowledge_access_mode') ?: 'course_scoped';
        $answersourcepolicy = get_config('local_daliwidget', 'answer_source_policy') ?: 'knowledge_only';
        if (!local_daliwidget_can_render_for_user($knowledgeaccessmode)) {
            return '';
        }
        $debugmode = get_config('local_daliwidget', 'debug_mode');

        // Don't load widget if no API key configured
        if (empty($apikey) || empty($baseurl)) {
            return '';
        }

        // Don't show widget on quiz pages (prevent cheating)
        if (isset($PAGE->cm) && $PAGE->cm && $PAGE->cm->modname === 'quiz') {
            return '';
        }
        if (strpos($PAGE->pagetype, 'mod-quiz') === 0) {
            return '';
        }

        // Build context data
        $fetchauth = \local_daliwidget\fetch_auth_helper::generate_for_user((int)$USER->id) ?? [];
        $contextdata = [
            'apiKey' => $apikey,
            'baseUrl' => rtrim($baseurl, '/'),
            'endpoints' => [
                'myCourses' => (new moodle_url('/local/daliwidget/user_courses.php'))->out(false),
                'refreshAuth' => (new moodle_url('/local/daliwidget/refresh_auth.php'))->out(false),
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
            'knowledge_access_mode' => $knowledgeaccessmode,
            'answer_source_policy' => $answersourcepolicy,
            'appearance' => \local_daliwidget\appearance::overrides(),
            'debug_mode' => !empty($debugmode),
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
            $coursecontext = context_course::instance($COURSE->id);
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
    
    // Debug logging helper
    window.daliDebug = {
        enabled: daliConfig.debug_mode,
        log: function(type, data) {
            if (!this.enabled) return;
            var timestamp = new Date().toISOString();
            var prefix = '[DaliWidget Debug]';
            console.log(prefix + ' [' + timestamp + '] ' + type, data);
            
            // Update debug panel if exists
            var panel = document.getElementById('dali-debug-log');
            if (panel) {
                var entry = document.createElement('div');
                entry.className = 'dali-debug-entry';
                
                var color = '#0f0';
                if (type.includes('ERROR') || type.includes('FAIL')) color = '#f00';
                else if (type.includes('RESPONSE')) color = '#0ff';
                else if (type.includes('REQUEST')) color = '#ff0';
                else if (type.includes('LOADED')) color = '#0f0';
                
                var shortUrl = '';
                if (data && data.url) {
                    try {
                        shortUrl = new URL(data.url).pathname;
                    } catch(e) {
                        shortUrl = data.url.substring(0, 50);
                    }
                }
                
                var statusStr = '';
                if (data && data.status) {
                    statusStr = ' <span style="color:' + (data.status < 400 ? '#0f0' : '#f00') + '">[' + data.status + ']</span>';
                }
                
                entry.innerHTML = '<div style="border-bottom:1px solid #333;padding:4px 0;">' +
                    '<span style="color:#888">' + timestamp.substring(11, 19) + '</span> ' +
                    '<span style="color:' + color + ';font-weight:bold">' + type + '</span>' +
                    statusStr +
                    (shortUrl ? ' <span style="color:#aaa">' + shortUrl + '</span>' : '') +
                    (data && data.body ? '<pre style="margin:4px 0;padding:4px;background:#0a0a0a;max-height:100px;overflow:auto;font-size:10px;">' + 
                        data.body.replace(/</g, '&lt;').substring(0, 300) + '</pre>' : '') +
                    '</div>';
                    
                panel.insertBefore(entry, panel.firstChild);
                
                // Keep only last 30 entries
                while (panel.children.length > 30) {
                    panel.removeChild(panel.lastChild);
                }
            }
        },
        error: function(type, data) {
            if (!this.enabled) return;
            var timestamp = new Date().toISOString();
            console.error('[DaliWidget Debug] [' + timestamp + '] ' + type, data);
            this.log(type, data); // Also add to panel
        }
    };
    
    if (daliConfig.debug_mode) {
        window.daliDebug.log('INIT', {
            baseUrl: daliConfig.baseUrl,
            apiKeyPreview: daliConfig.apiKey ? daliConfig.apiKey.substring(0, 10) + '...' : 'NOT SET',
            user: daliConfig.user.fullname || daliConfig.user.username,
            course: daliConfig.course ? daliConfig.course.fullname : 'No course context',
            knowledge_access_mode: daliConfig.knowledge_access_mode
        });
    }
    
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
        appearance: daliConfig.appearance || {},
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
            assistant_display_name: daliConfig.appearance && daliConfig.appearance.botName ? daliConfig.appearance.botName : null,
            knowledge_access_mode: daliConfig.knowledge_access_mode,
            answer_source_policy: daliConfig.answer_source_policy
        },
        debug: daliConfig.debug_mode
    };

    if (daliConfig.endpoints && daliConfig.endpoints.myCourses && daliConfig.fetchAuth) {
        var debugMyCoursesUrl = new URL(daliConfig.endpoints.myCourses, window.location.origin);
        debugMyCoursesUrl.searchParams.set('signed_user_id', daliConfig.fetchAuth.signed_user_id || '');
        debugMyCoursesUrl.searchParams.set('expires', daliConfig.fetchAuth.expires || '');
        debugMyCoursesUrl.searchParams.set('sig', daliConfig.fetchAuth.sig || '');
        console.log('[Dali AI Widget] Moodle myCourses signed URL:', debugMyCoursesUrl.toString());
    }
    
    if (daliConfig.debug_mode) {
        window.daliDebug.log('SETTINGS', window.daliSettings);
    }

    // Dynamically load the Dali SDK
    var script = document.createElement('script');
    script.src = daliConfig.baseUrl + '/widget-sdk.js';
    script.defer = true;
    script.async = true;
    script.onload = function() {
        if (window.DaliSDK) {
            if (daliConfig.debug_mode) {
                window.daliDebug.log('SDK_LOADED', 'DaliSDK ready, starting widget...');
            }
            window.DaliSDK.run(window.daliSettings);
        } else {
            if (daliConfig.debug_mode) {
                window.daliDebug.error('SDK_ERROR', 'DaliSDK object not found after script load');
            }
        }
    };
    script.onerror = function() {
        console.error('[Dali AI Widget] Failed to load SDK from:', daliConfig.baseUrl);
        if (daliConfig.debug_mode) {
            window.daliDebug.error('SDK_LOAD_FAILED', {
                url: daliConfig.baseUrl + '/widget-sdk.js',
                message: 'Script load error - check if URL is correct and CORS is enabled'
            });
        }
    };

    document.head.appendChild(script);
    
    // Add debug panel if debug mode enabled
    if (daliConfig.debug_mode) {
        // Test backend status on load
        window.daliDebug.testBackend = function() {
            window.daliDebug.log('DEBUG_CHECK', 'Testing backend status...');
            
            var xhr = new XMLHttpRequest();
            xhr.open('GET', daliConfig.baseUrl + '/api/v1/debug/status');
            xhr.setRequestHeader('Authorization', 'Bearer ' + daliConfig.apiKey);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var data = JSON.parse(xhr.responseText);
                    window.daliDebug.log('DEBUG_STATUS', data);
                } else {
                    window.daliDebug.error('DEBUG_STATUS_ERROR', {
                        status: xhr.status,
                        body: xhr.responseText
                    });
                }
            };
            xhr.onerror = function() {
                window.daliDebug.error('DEBUG_NETWORK_ERROR', 'Cannot reach backend at ' + daliConfig.baseUrl);
            };
            xhr.send();
        };
        
        // Run debug check after 1 second
        setTimeout(window.daliDebug.testBackend, 1000);
        
        // Intercept all fetch/XHR to capture widget SDK API calls
        var originalFetch = window.fetch;
        window.fetch = function(url, options) {
            var method = options?.method || 'GET';
            var body = options?.body ? (typeof options.body === 'string' ? options.body : JSON.stringify(options.body)) : null;
            
            window.daliDebug.log('FETCH_REQUEST', {
                url: typeof url === 'string' ? url : url.url,
                method: method,
                body: body ? body.substring(0, 500) : null
            });
            
            return originalFetch.apply(this, arguments).then(function(response) {
                var cloned = response.clone();
                cloned.text().then(function(text) {
                    window.daliDebug.log('FETCH_RESPONSE', {
                        url: typeof url === 'string' ? url : url.url,
                        status: response.status,
                        statusText: response.statusText,
                        body: text.substring(0, 500)
                    });
                });
                return response;
            }).catch(function(error) {
                window.daliDebug.error('FETCH_ERROR', {
                    url: typeof url === 'string' ? url : url.url,
                    error: error.message
                });
                throw error;
            });
        };
        
        // Intercept XMLHttpRequest
        var origOpen = XMLHttpRequest.prototype.open;
        var origSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url) {
            this._daliDebugUrl = url;
            this._daliDebugMethod = method;
            return origOpen.apply(this, arguments);
        };
        
        XMLHttpRequest.prototype.send = function(body) {
            var self = this;
            window.daliDebug.log('XHR_REQUEST', {
                url: self._daliDebugUrl,
                method: self._daliDebugMethod,
                body: body ? (typeof body === 'string' ? body.substring(0, 500) : 'FormData/binary') : null
            });
            
            this.addEventListener('load', function() {
                window.daliDebug.log('XHR_RESPONSE', {
                    url: self._daliDebugUrl,
                    status: self.status,
                    body: self.responseText ? self.responseText.substring(0, 500) : null
                });
            });
            
            this.addEventListener('error', function() {
                window.daliDebug.error('XHR_ERROR', {
                    url: self._daliDebugUrl,
                    error: 'Network error'
                });
            });
            
            return origSend.apply(this, arguments);
        };
        
        // Add debug panel to page
        var panelHtml = '<div id="dali-debug-panel" style="position:fixed;bottom:10px;left:10px;width:450px;max-height:350px;background:#1a1a2e;color:#0f0;font-family:monospace;font-size:11px;z-index:99999;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.5);overflow:hidden;">' +
            '<div style="background:#16213e;padding:8px 12px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #0f0;">' +
            '<strong>AI Assistant Debug Panel</strong>' +
            '<button onclick="window.daliDebug.testBackend()" style="background:transparent;border:1px solid #0f0;color:#0f0;padding:2px 8px;cursor:pointer;border-radius:4px;margin:0 4px;font-size:10px;">Test</button>' +
            '<button onclick="document.getElementById(\'dali-debug-panel\').style.display=\'none\'" style="background:transparent;border:1px solid #0f0;color:#0f0;padding:2px 8px;cursor:pointer;border-radius:4px;">X</button>' +
            '</div>' +
            '<div style="padding:4px 8px;background:#0a0a1a;border-bottom:1px solid #333;font-size:10px;">' +
            'Backend: ' + daliConfig.baseUrl + '<br>' +
            'API Key: ' + (daliConfig.apiKey ? daliConfig.apiKey.substring(0, 10) + '...' : 'NOT SET') +
            '</div>' +
            '<div id="dali-debug-log" style="padding:8px;max-height:260px;overflow-y:auto;"></div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', panelHtml);
        
        console.log('[DaliWidget Debug] Network interceptor installed - all fetch/XHR calls will be logged');
    }
})();
</script>
HTML;

        echo $html;
    }

    return '';
}


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
 * Library functions for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve the files from the local_aiquizgen file areas.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_aiquizgen_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;

    // Check the contextlevel is as expected.
    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'pdffile') {
        return false;
    }

    // Make sure the user is logged in and has access to the course.
    require_login($course, true);

    $itemid = array_shift($args);
    $filename = array_pop($args);

    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_aiquizgen', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

function local_aiquizgen_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/aiquizgen:generate', $context)) {
        $url = new \moodle_url('/local/aiquizgen/generate.php', ['courseid' => $course->id]);
        $node = \navigation_node::create(
            get_string('pluginname', 'local_aiquizgen'),
            $url,
            \navigation_node::TYPE_CUSTOM,
            null,
            'aiquizgen',
            new \pix_icon('i/questions', '')
        );
        $navigation->add_node($node);

        // Moodle 4.1 compatibility: inject button from navigation callback too,
        // because footer hooks may not run consistently across versions/themes.
        local_aiquizgen_maybe_inject_questionbank_button();
    }
}

/**
 * Inject AI generation button on question bank page.
 *
 * Safe to call multiple times per request.
 *
 * @return void
 */
function local_aiquizgen_maybe_inject_questionbank_button(): void {
    global $PAGE;

    if (empty($PAGE) || empty($PAGE->url) || empty($PAGE->course) || empty($PAGE->course->id)) {
        return;
    }

    if ($PAGE->pagelayout !== 'incourse' || $PAGE->course->id <= 1) {
        return;
    }

    if (strpos($PAGE->url->get_path(), '/question/edit.php') === false) {
        return;
    }

    $context = \context_course::instance($PAGE->course->id, IGNORE_MISSING);
    if (!$context || !has_capability('local/aiquizgen:generate', $context)) {
        return;
    }

    $params = [
        'courseid' => $PAGE->course->id,
        'returnurl' => $PAGE->url->out_as_local_url(),
    ];

    $cat = optional_param('cat', '', PARAM_TEXT);
    if (!empty($cat)) {
        $params['cat'] = $cat;
    }

    $url = new \moodle_url('/local/aiquizgen/generate.php', $params);
    $cleanurl = $url->out(false);
    $buttontext = get_string('generatequestions', 'local_aiquizgen');

    $jsurl = json_encode($cleanurl);
    $jstext = json_encode($buttontext);

    $PAGE->requires->js_amd_inline("
        require(['jquery'], function($) {
            $(document).ready(function() {
                if ($('.local-aiquizgen-generate-btn').length) {
                    return;
                }

                var btn = $('<a>')
                    .attr('href', {$jsurl})
                    .addClass('btn btn-primary mb-3 local-aiquizgen-generate-btn')
                    .html('<i class=\"fa fa-magic\"></i> ' + {$jstext});

                var anchor = $('.questionbankwindow').first();
                if (!anchor.length) {
                    anchor = $('#region-main h1').first();
                }

                if (anchor.length) {
                    anchor.before(btn);
                } else {
                    $('#region-main').prepend(btn);
                }
            });
        });
    ");
}

/**
 * Legacy callback for before footer (Moodle 4.4 and earlier).
 * 
 * This is kept for backward compatibility with Moodle 4.4.
 * For Moodle 5.0+, use hook_callbacks::before_footer_html_generation() instead.
 * 
 * @deprecated since Moodle 5.0
 * @return string
 */
function local_aiquizgen_before_footer() {
    // Only use this callback if hook system is not available (Moodle 4.4).
    if (!class_exists('\core\hook\output\before_footer_html_generation')) {
        local_aiquizgen_maybe_inject_questionbank_button();
    }
    
    return '';
}

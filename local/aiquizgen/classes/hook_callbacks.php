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
 * Hook callbacks for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks class.
 */
class hook_callbacks {
    
    /**
     * Callback for before_footer_html_generation hook.
     *
     * Adds "Generate Questions with AI" button to question bank page.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        // Only add button on question bank page in course context.
        if ($PAGE->pagelayout !== 'incourse' || $PAGE->course->id <= 1) {
            return;
        }

        // Check if user has permission.
        $context = \context_course::instance($PAGE->course->id, IGNORE_MISSING);
        if (!$context || !has_capability('local/aiquizgen:generate', $context)) {
            return;
        }

        // Only show on question edit page.
        if (strpos($PAGE->url->get_path(), '/question/edit.php') === false) {
            return;
        }

        // Add button via JavaScript.
        $params = [
            'courseid' => $PAGE->course->id,
            'returnurl' => $PAGE->url->out_as_local_url()
        ];

        // Pass category from URL if available.
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
}

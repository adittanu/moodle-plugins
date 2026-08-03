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
 * SiteFrame block main class.
 *
 * @package     block_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_siteframe extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_siteframe');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Respect the global enable toggle from local_siteframe.
        if (!get_config('local_siteframe', 'enabled')) {
            $this->content->text = '';
            return $this->content;
        }

        $url = $this->config->url ?? '';

        if (empty($url)) {
            $this->content->text = get_string('no_items', 'local_siteframe');
            return $this->content;
        }

        // Validate URL.
        $sanitized = local_siteframe\domain_helper::sanitize_url($url);
        if ($sanitized === false || !local_siteframe\domain_helper::is_domain_allowed($sanitized)) {
            $this->content->text = get_string('domain_not_allowed', 'local_siteframe');
            return $this->content;
        }

        $height = isset($this->config->height) && $this->config->height > 0 ? (int) $this->config->height : 400;
        $width = \local_siteframe\domain_helper::sanitize_css_dimension($this->config->width ?? '', '100%');
        $scrolling = isset($this->config->scrolling) ? $this->config->scrolling : 'auto';
        $sandbox = local_siteframe\domain_helper::get_sandbox_attr();

        $this->content->text = html_writer::tag('iframe', '', [
            'src'       => $sanitized,
            'width'     => $width,
            'height'    => $height,
            'frameborder' => '0',
            'allowfullscreen' => 'true',
            'sandbox'   => $sandbox,
            'scrolling' => $scrolling,
            'class'     => 'siteframe-iframe siteframe-display-block',
            'style'     => 'width: ' . $width . '; height: ' . $height . 'px;',
        ]);

        return $this->content;
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return true;
    }

    public function applicable_formats() {
        return ['all' => true];
    }
}

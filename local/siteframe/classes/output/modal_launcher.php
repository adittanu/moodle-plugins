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
 * Modal launcher renderable for SiteFrame.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_siteframe\output;

defined('MOODLE_INTERNAL') || die();

class modal_launcher implements \renderable, \templatable {

    /** @var string The iframe URL */
    private $url;

    /** @var string The button label */
    private $label;

    /**
     * Constructor.
     *
     * @param string $url The iframe URL.
     * @param string $label The button label.
     */
    public function __construct(string $url, string $label = '') {
        $this->url = $url;
        $this->label = $label ?: get_string('widget_open', 'local_siteframe');
    }

    public function export_for_template(\renderer_base $output): array {
        return [
            'url'   => $this->url,
            'label' => $this->label,
            'sandbox' => \local_siteframe\domain_helper::get_sandbox_attr(),
        ];
    }
}

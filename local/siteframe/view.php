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
 * Full page iframe view for SiteFrame.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$item = $DB->get_record('local_siteframe_items', ['id' => $id], '*', MUST_EXIST);

require_login();

$context = context_system::instance();
require_capability('local/siteframe:view', $context);

// Hidden items only visible to managers (avoid info leak via direct ID).
if (empty($item->visible) && !has_capability('local/siteframe:manage', $context)) {
    throw new moodle_exception('item_hidden', 'local_siteframe');
}

// Validate domain.
use local_siteframe\domain_helper;
$url = domain_helper::sanitize_url($item->url);
if ($url === false) {
    throw new moodle_exception('url_invalid', 'local_siteframe');
}
if (!domain_helper::is_domain_allowed($url)) {
    throw new moodle_exception('domain_not_allowed', 'local_siteframe');
}

$PAGE->set_url('/local/siteframe/view.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($item->name));
$PAGE->set_heading(format_string($item->name));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_siteframe'), new moodle_url('/local/siteframe/'));
$PAGE->navbar->add(format_string($item->name));

$height = $item->height > 0 ? domain_helper::sanitize_css_dimension($item->height . 'px', '100%') : '100%';
$width = domain_helper::sanitize_css_dimension($item->width ?? '', '100%');
$sandbox = domain_helper::get_sandbox_attr();

echo $OUTPUT->header();

if ($item->displaymode === 'modal') {
    // Modal mode: render trigger button, AMD module opens the modal.
    $PAGE->requires->js_call_amd('local_siteframe/modal_launcher', 'init');
    echo html_writer::tag('button',
        get_string('widget_open', 'local_siteframe'),
        [
            'class'        => 'siteframe-modal-trigger btn btn-primary',
            'data-url'     => $url,
            'data-title'   => format_string($item->name),
            'data-sandbox' => $sandbox,
        ]
    );
} else {
    // Fullpage / inline mode: render iframe directly.
    // Ponytail: detect X-Frame-Options/CSP block via onload timeout — if iframe
    // doesn't fire load within 3s, show fallback message (server can't pre-check).
    echo html_writer::start_div('siteframe-container');
    echo html_writer::div(
        get_string('iframe_blocked', 'local_siteframe'),
        'siteframe-blocked-fallback',
        ['style' => 'display:none; padding:20px; background:#fff3cd; border:1px solid #ffeaa7; border-radius:8px; margin:10px 0;']
    );
    echo html_writer::tag('iframe', '', [
        'src'       => $url,
        'width'     => $width,
        'height'    => $height,
        'frameborder' => '0',
        'allowfullscreen' => 'true',
        'sandbox'   => $sandbox,
        'scrolling' => $item->scrolling,
        'class'     => 'siteframe-iframe siteframe-display-fullpage',
        'style'     => 'border: none; width: ' . $width . '; height: ' . $height . ';',
    ]);
    echo html_writer::end_div();
    echo '<script>
        (function() {
            var iframe = document.querySelector(".siteframe-display-fullpage");
            var fallback = document.querySelector(".siteframe-blocked-fallback");
            if (!iframe || !fallback) return;
            var loaded = false;
            iframe.addEventListener("load", function() { loaded = true; });
            setTimeout(function() {
                if (!loaded) {
                    try {
                        // If blocked by X-Frame-Options, accessing contentDocument throws.
                        var doc = iframe.contentDocument || iframe.contentWindow.document;
                    } catch (e) {
                        fallback.style.display = "block";
                        iframe.style.display = "none";
                    }
                }
            }, 3000);
        })();
    </script>';
}

echo $OUTPUT->footer();

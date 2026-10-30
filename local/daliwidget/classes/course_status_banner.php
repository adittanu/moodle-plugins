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
 * Course page AI knowledge status banner.
 *
 * @package     local_daliwidget
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders a compact status banner for course editors.
 *
 * The banner is rendered without calling the Dali API. The browser requests
 * the status asynchronously so course pages are never blocked by API latency.
 */
class course_status_banner {

    /**
     * Render the banner for the current course page.
     *
     * @return string Empty string when the banner does not apply.
     */
    public static function render(): string {
        global $COURSE, $PAGE;

        if (empty($COURSE) || $COURSE->id <= 1 || strpos($PAGE->pagetype, 'course-view') !== 0) {
            return '';
        }

        $context = \context_course::instance($COURSE->id);
        if (!has_capability('moodle/course:update', $context)) {
            return '';
        }

        $kburl = new \moodle_url('/local/daliwidget/knowledge.php', ['id' => $COURSE->id]);
        $syncurl = new \moodle_url('/local/daliwidget/knowledge.php', [
            'id' => $COURSE->id,
            'action' => 'syncmissing',
            'sesskey' => sesskey(),
        ]);
        $endpoint = new \moodle_url('/local/daliwidget/ajax.php');

        $strings = [
            'loading' => get_string('course_status_loading', 'local_daliwidget'),
            'unavailable' => get_string('course_status_unavailable', 'local_daliwidget'),
            'unavailabledetail' => get_string('course_status_unavailable_detail', 'local_daliwidget'),
        ];

        $html = \html_writer::start_div('dali-ai-status dali-ai-status--loading', [
            'id' => 'dali-ai-status',
            'role' => 'status',
            'aria-live' => 'polite',
            'data-courseid' => $COURSE->id,
            'data-sesskey' => sesskey(),
            'data-endpoint' => $endpoint->out(false),
            'data-strings' => json_encode($strings),
        ]);
        $html .= \html_writer::tag('span', '', [
            'class' => 'dali-ai-status__icon',
            'aria-hidden' => 'true',
        ]);
        $html .= \html_writer::start_div('dali-ai-status__text');
        $html .= \html_writer::tag('span', $strings['loading'], [
            'class' => 'dali-ai-status__label',
        ]);
        $html .= \html_writer::tag('span', '', ['class' => 'dali-ai-status__detail']);
        $html .= \html_writer::end_div();
        $html .= \html_writer::start_div('dali-ai-status__actions');
        $html .= \html_writer::link($syncurl, '', [
            'class' => 'btn btn-sm btn-outline-primary dali-ai-status__sync',
            'hidden' => 'hidden',
        ]);
        $html .= \html_writer::link($kburl, get_string('course_status_open_kb', 'local_daliwidget'), [
            'class' => 'btn btn-sm btn-primary dali-ai-status__link',
        ]);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        $html .= \html_writer::tag('style', self::styles());
        $html .= \html_writer::tag('script', self::script());

        return $html;
    }

    /**
     * Inline styles for the banner.
     *
     * @return string
     */
    private static function styles(): string {
        return <<<'CSS'
.dali-ai-status{display:flex;align-items:center;gap:.5rem .9rem;flex-wrap:wrap;margin:0 0 1rem;padding:.65rem .9rem;border:1px solid #d7dde3;border-radius:.65rem;background:#f8fafc;color:#1f2937;font-size:.9rem;line-height:1.35}
.dali-ai-status__icon{flex:0 0 auto;width:.6rem;height:.6rem;border-radius:50%;background:#94a3b8;box-shadow:0 0 0 3px rgba(148,163,184,.18)}
.dali-ai-status--loading .dali-ai-status__icon{animation:dali-ai-status-pulse 1.2s ease-in-out infinite}
.dali-ai-status__text{display:flex;flex-direction:column;gap:.1rem;min-width:0}
.dali-ai-status__label{font-weight:600}
.dali-ai-status__detail{color:#5b6773;font-size:.8rem}
.dali-ai-status__actions{margin-left:auto;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.dali-ai-status__sync[hidden]{display:none!important}
.dali-ai-status--ready{border-color:#b7e1cd;background:#f0fbf4}
.dali-ai-status--ready .dali-ai-status__icon{background:#1e9e5a;box-shadow:0 0 0 3px rgba(30,158,90,.18)}
.dali-ai-status--partial{border-color:#bfdbfe;background:#f3f8ff}
.dali-ai-status--partial .dali-ai-status__icon{background:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18)}
.dali-ai-status--processing{border-color:#fde3b3;background:#fffaf0}
.dali-ai-status--processing .dali-ai-status__icon{background:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,.18);animation:dali-ai-status-pulse 1.2s ease-in-out infinite}
.dali-ai-status--attention{border-color:#f5c2c2;background:#fef4f4}
.dali-ai-status--attention .dali-ai-status__icon{background:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.18)}
.dali-ai-status--empty{border-color:#d7dde3;background:#f8fafc}
.dali-ai-status--empty .dali-ai-status__icon{background:#6b7280;box-shadow:0 0 0 3px rgba(107,114,128,.18)}
.dali-ai-status--unavailable{border-color:#e2e8f0;background:#f8fafc}
.dali-ai-status--unavailable .dali-ai-status__icon{background:#9ca3af;box-shadow:0 0 0 3px rgba(156,163,175,.18)}
@keyframes dali-ai-status-pulse{0%,100%{opacity:1}50%{opacity:.35}}
@media(max-width:767.98px){.dali-ai-status__actions{margin-left:0;width:100%}.dali-ai-status__actions .btn{flex:1 1 auto}}
CSS;
    }

    /**
     * Inline script that loads the status and mounts the banner.
     *
     * @return string
     */
    private static function script(): string {
        return <<<'JS'
(function() {
    var root = document.getElementById('dali-ai-status');
    if (!root || root.getAttribute('data-booted') === '1') {
        return;
    }
    root.setAttribute('data-booted', '1');

    var strings = {};
    try {
        strings = JSON.parse(root.getAttribute('data-strings') || '{}');
    } catch (e) {
        strings = {};
    }

    var content = document.querySelector('.course-content');
    var main = document.querySelector('#region-main');
    if (content && content.parentNode) {
        content.parentNode.insertBefore(root, content);
    } else if (main) {
        main.insertBefore(root, main.firstChild);
    } else {
        root.style.display = 'none';
        return;
    }

    var label = root.querySelector('.dali-ai-status__label');
    var detail = root.querySelector('.dali-ai-status__detail');
    var syncLink = root.querySelector('.dali-ai-status__sync');

    var render = function(data) {
        root.className = 'dali-ai-status dali-ai-status--' + (data.state || 'unavailable');
        label.textContent = data.label || strings.unavailable || '';
        detail.textContent = data.detail || '';
        if (syncLink) {
            var hasMissing = typeof data.sync_label === 'string' && data.sync_label !== '';
            syncLink.hidden = !hasMissing;
            syncLink.textContent = hasMissing ? data.sync_label : '';
        }
    };

    if (!window.fetch) {
        render({state: 'unavailable', label: strings.unavailable, detail: strings.unavailabledetail});
        return;
    }

    var params = 'action=status&courseid=' + encodeURIComponent(root.getAttribute('data-courseid')) +
        '&sesskey=' + encodeURIComponent(root.getAttribute('data-sesskey'));

    window.fetch(root.getAttribute('data-endpoint') + '?' + params, {
        credentials: 'same-origin',
        headers: {'Accept': 'application/json'}
    }).then(function(response) {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    }).then(function(payload) {
        if (!payload || !payload.success || !payload.data) {
            throw new Error('Invalid status payload');
        }
        render(payload.data);
    }).catch(function() {
        render({state: 'unavailable', label: strings.unavailable, detail: strings.unavailabledetail});
    });
})();
JS;
    }
}

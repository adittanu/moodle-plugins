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
 * Admin management page for SiteFrame items.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();
require_capability('local/siteframe:manage', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url('/local/siteframe/manage.php', ['action' => $action, 'id' => $id]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage_siteframes', 'local_siteframe'));
$PAGE->set_heading(get_string('manage_siteframes', 'local_siteframe'));

admin_externalpage_setup('local_siteframe_manage');

use local_siteframe\domain_helper;

// Delete action.
if ($action === 'delete' && $id > 0) {
    $item = $DB->get_record('local_siteframe_items', ['id' => $id], '*', MUST_EXIST);

    if ($confirm && confirm_sesskey()) {
        $DB->delete_records('local_siteframe_items', ['id' => $id]);
        redirect($PAGE->url, get_string('item_deleted', 'local_siteframe'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('manage_heading', 'local_siteframe'));
    echo $OUTPUT->confirm(
        get_string('confirm_delete', 'local_siteframe') . ': ' . format_string($item->name),
        new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $PAGE->url
    );
    echo $OUTPUT->footer();
    exit;
}

// Add/Edit form processing.
if ($action === 'edit' || $action === 'add') {
    $item = new stdClass();
    if ($id > 0) {
        $item = $DB->get_record('local_siteframe_items', ['id' => $id]);
        if (!$item) {
            redirect($PAGE->url);
        }
    }

    $mform = new \local_siteframe\form\item_form($PAGE->url, ['item' => $item]);

    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($data = $mform->get_data()) {
        // Validate URL.
        $url = domain_helper::sanitize_url($data->url);
        if ($url === false) {
            throw new moodle_exception('url_invalid', 'local_siteframe');
        }
        if (!domain_helper::is_domain_allowed($url)) {
            throw new moodle_exception('domain_not_allowed', 'local_siteframe');
        }

        $data->url = $url;
        $data->timemodified = time();

        if (isset($data->id) && $data->id > 0) {
            $DB->update_record('local_siteframe_items', $data);
        } else {
            $data->timecreated = time();
            $DB->insert_record('local_siteframe_items', $data);
        }

        redirect($PAGE->url, get_string('item_saved', 'local_siteframe'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($action === 'add'
        ? get_string('add_siteframe', 'local_siteframe')
        : get_string('edit_siteframe', 'local_siteframe'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// List view.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_heading', 'local_siteframe'));

echo html_writer::div(
    html_writer::link(
        new moodle_url($PAGE->url, ['action' => 'add']),
        get_string('add_siteframe', 'local_siteframe'),
        ['class' => 'btn btn-primary mb-3']
    )
);

$items = $DB->get_records('local_siteframe_items', null, 'sortorder ASC');

if (empty($items)) {
    echo html_writer::div(get_string('no_items', 'local_siteframe'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('item_name', 'local_siteframe'),
        get_string('item_url', 'local_siteframe'),
        get_string('item_displaymode', 'local_siteframe'),
        get_string('item_courseid', 'local_siteframe'),
        get_string('item_visible', 'local_siteframe'),
        get_string('actions', 'local_siteframe'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($items as $item) {
        $editurl = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $item->id]);
        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $item->id]);

        $coursename = '-';
        if ($item->courseid > 0) {
            $course = $DB->get_record('course', ['id' => $item->courseid], 'fullname');
            $coursename = $course ? format_string($course->fullname) : $item->courseid;
        }

        $actions = html_writer::link($editurl, get_string('edit_siteframe', 'local_siteframe')) . ' | ' .
                   html_writer::link($deleteurl, get_string('delete', 'core'));

        $table->data[] = [
            format_string($item->name),
            html_writer::tag('code', s($item->url)),
            get_string('displaymode_' . $item->displaymode, 'local_siteframe'),
            $coursename,
            $item->visible ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();

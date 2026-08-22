<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/api_client.php');

use local_daliwidget\api_client;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$PAGE->set_url(new moodle_url('/local/daliwidget/wordpress_connections.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('wordpress_connections', 'local_daliwidget'));
$PAGE->set_heading(get_string('wordpress_connections', 'local_daliwidget'));

$editid = optional_param('edit', 0, PARAM_INT);
$client = new api_client();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    $id = optional_param('id', 0, PARAM_INT);
    if ($action === 'save') {
        $data = [
            'name' => required_param('name', PARAM_TEXT),
            'site_url' => required_param('site_url', PARAM_URL),
            'username' => optional_param('username', '', PARAM_TEXT),
            'application_password' => optional_param('application_password', '', PARAM_RAW_TRIMMED),
            'marker_taxonomy' => required_param('marker_taxonomy', PARAM_ALPHA),
            'marker_slug' => required_param('marker_slug', PARAM_ALPHANUMEXT),
            'enabled' => optional_param('enabled', 0, PARAM_BOOL),
        ];
        if ($id && $data['application_password'] === '') unset($data['application_password']);
        $result = $id ? $client->updateWordpressConnection($id, $data) : $client->createWordpressConnection($data);
    } else if ($action === 'validate') {
        $result = $client->validateWordpressConnection($id);
    } else if ($action === 'delete') {
        $result = $client->deleteWordpressConnection($id);
    } else if ($action === 'toggle') {
        $result = $client->updateWordpressConnection($id, ['enabled' => required_param('enabled', PARAM_BOOL)]);
    }
    redirect($PAGE->url, ($result['success'] ?? false) ? get_string('wordpress_action_success', 'local_daliwidget') : ($result['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')), null, ($result['success'] ?? false) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
}

$connections = $client->getWordpressConnections()['data'] ?? [];
echo $OUTPUT->header();
echo html_writer::tag('p', get_string('wordpress_connections_desc', 'local_daliwidget'));
echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::tag('tr', html_writer::tag('th', get_string('name')) . html_writer::tag('th', 'URL') . html_writer::tag('th', get_string('status')) . html_writer::tag('th', get_string('actions')));
foreach ($connections as $connection) {
    $actions = html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $connection['id']]);
    $actions .= html_writer::tag('button', get_string('wordpress_validate', 'local_daliwidget'), ['name' => 'action', 'value' => 'validate', 'class' => 'btn btn-sm btn-secondary']);
    $actions .= ' ' . html_writer::link(new moodle_url('/local/daliwidget/wordpress_posts.php', ['connection' => $connection['id']]), get_string('wordpress_posts', 'local_daliwidget'), ['class' => 'btn btn-sm btn-info']);
    $actions .= ' ' . html_writer::link(new moodle_url($PAGE->url, ['edit' => $connection['id']]), get_string('edit'), ['class' => 'btn btn-sm btn-primary']);
    $actions .= ' ' . html_writer::tag('button', $connection['enabled'] ? get_string('disable') : get_string('enable'), ['name' => 'action', 'value' => 'toggle', 'class' => 'btn btn-sm btn-warning']);
    $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'enabled', 'value' => $connection['enabled'] ? 0 : 1]);
    $actions .= ' ' . html_writer::tag('button', get_string('delete'), ['name' => 'action', 'value' => 'delete', 'class' => 'btn btn-sm btn-danger']);
    $actions .= html_writer::end_tag('form');
    echo html_writer::tag('tr', html_writer::tag('td', s($connection['name'])) . html_writer::tag('td', s($connection['site_url'])) . html_writer::tag('td', $connection['enabled'] ? get_string('enabled', 'local_daliwidget') : get_string('disabled', 'core')) . html_writer::tag('td', $actions));
}
echo html_writer::end_tag('table');

$editing = null;
foreach ($connections as $connection) {
    if ((int) $connection['id'] === $editid) $editing = $connection;
}
echo html_writer::tag('h3', get_string($editing ? 'wordpress_edit_connection' : 'wordpress_add_connection', 'local_daliwidget'));
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
if ($editing) echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $editing['id']]);
foreach ([['name', 'text', $editing['name'] ?? ''], ['site_url', 'url', $editing['site_url'] ?? 'https://'], ['username', 'text', $editing['username'] ?? ''], ['application_password', 'password', ''], ['marker_slug', 'text', $editing['marker_slug'] ?? 'knowledge-base']] as [$name, $type, $value]) {
    echo html_writer::start_div('form-group');
    echo html_writer::tag('label', get_string('wordpress_' . $name, 'local_daliwidget'), ['for' => $name]);
    echo html_writer::empty_tag('input', ['class' => 'form-control', 'id' => $name, 'name' => $name, 'type' => $type, 'value' => $value, 'required' => in_array($name, ['name', 'site_url', 'marker_slug'], true) ? 'required' : null]);
    echo html_writer::end_div();
}
echo html_writer::select(['tag' => 'Tag', 'category' => 'Category'], 'marker_taxonomy', $editing['marker_taxonomy'] ?? 'tag', false, ['class' => 'form-control mb-3']);
echo html_writer::checkbox('enabled', 1, $editing['enabled'] ?? true, get_string('enabled', 'local_daliwidget'));
echo html_writer::empty_tag('br');
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();

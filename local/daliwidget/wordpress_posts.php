<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/api_client.php');

use local_daliwidget\api_client;

require_login();
$context = context_system::instance();
require_capability('local/daliwidget:manageglobalwordpress', $context);
$connectionid = optional_param('connection', 0, PARAM_INT);
$PAGE->set_url(new moodle_url('/local/daliwidget/wordpress_posts.php', ['connection' => $connectionid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('wordpress_posts', 'local_daliwidget'));
$PAGE->set_heading(get_string('wordpress_posts', 'local_daliwidget'));
$client = new api_client();

if (!$connectionid) {
    echo $OUTPUT->header();
    foreach (($client->getWordpressConnections()['data'] ?? []) as $connection) {
        echo html_writer::div(html_writer::link(new moodle_url($PAGE->url, ['connection' => $connection['id']]), s($connection['name'])), 'mb-2');
    }
    echo $OUTPUT->footer();
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $selected = required_param('selected', PARAM_BOOL);
    $result = $client->selectWordpressPost($connectionid, required_param('post', PARAM_INT), $selected,
        required_param('locale', PARAM_ALPHANUMEXT), !$selected && required_param('confirmed', PARAM_BOOL));
    redirect($PAGE->url, ($result['success'] ?? false) ? get_string('wordpress_selection_saved', 'local_daliwidget') : ($result['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')));
}

$filters = [
    'page' => max(1, optional_param('page', 1, PARAM_INT)), 'per_page' => 20,
    'search' => optional_param('search', '', PARAM_TEXT), 'status' => optional_param('status', 'any', PARAM_ALPHA),
    'taxonomy' => optional_param('taxonomy', '', PARAM_INT) ?: null,
];
$result = $client->getWordpressPosts($connectionid, $filters);
$posts = $result['data'] ?? [];
$meta = $result['meta'] ?? ['page' => 1, 'pages' => 1];
$runs = $connectionid ? ($client->getWordpressRuns($connectionid)['data'] ?? []) : [];

echo $OUTPUT->header();
if ($runs) {
    echo html_writer::tag('h3', get_string('wordpress_recent_runs', 'local_daliwidget'));
    foreach (array_slice($runs, 0, 5) as $run) {
        $counts = $run['counts'] ?? [];
        echo html_writer::div(s(get_string('wordpress_run_summary', 'local_daliwidget', (object) [
            'status' => $run['status'], 'trigger' => $run['trigger'], 'added' => $counts['added'] ?? 0,
            'updated' => $counts['updated'] ?? 0, 'removed' => $counts['removed'] ?? 0, 'failed' => $counts['failed'] ?? 0,
        ])), in_array($run['status'], ['failed', 'partial'], true) ? 'alert alert-danger' : 'alert alert-light');
    }
}
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'connection', 'value' => $connectionid]);
echo html_writer::empty_tag('input', ['name' => 'search', 'value' => $filters['search'], 'placeholder' => get_string('search'), 'class' => 'form-control mr-2']);
echo html_writer::select(['any' => get_string('all'), 'publish' => 'Published', 'future' => 'Scheduled', 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private'], 'status', $filters['status'], false, ['class' => 'form-control mr-2']);
echo html_writer::empty_tag('input', ['name' => 'taxonomy', 'type' => 'number', 'min' => 1, 'value' => $filters['taxonomy'], 'placeholder' => get_string('wordpress_taxonomy_id', 'local_daliwidget'), 'class' => 'form-control mr-2']);
echo html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo html_writer::start_tag('table', ['class' => 'table table-striped']);
echo html_writer::tag('tr', html_writer::tag('th', get_string('name')) . html_writer::tag('th', get_string('status')) . html_writer::tag('th', get_string('wordpress_inclusion', 'local_daliwidget')) . html_writer::tag('th', get_string('actions')));
foreach ($posts as $post) {
    $reasons = array_filter([$post['automatic'] ? get_string('wordpress_automatic', 'local_daliwidget') : null, $post['manual'] ? get_string('wordpress_manual', 'local_daliwidget') : null]);
    if ($post['pending']) {
        $reasons[] = get_string('wordpress_pending', 'local_daliwidget');
    }
    $form = html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    foreach (['sesskey' => sesskey(), 'post' => $post['id'], 'locale' => $post['locale'], 'selected' => $post['manual'] ? 0 : 1, 'confirmed' => $post['manual'] ? 1 : 0] as $name => $value) {
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $form .= html_writer::tag('button', get_string($post['manual'] ? 'wordpress_cancel_selection' : 'wordpress_select', 'local_daliwidget'), ['class' => 'btn btn-sm btn-primary', 'onclick' => $post['manual'] ? "return confirm('" . get_string('wordpress_cancel_confirm', 'local_daliwidget') . "')" : null]);
    $form .= html_writer::end_tag('form');
    $title = $post['permalink'] ? html_writer::link($post['permalink'], s($post['title'])) : s($post['title']);
    echo html_writer::tag('tr', html_writer::tag('td', $title) . html_writer::tag('td', s($post['status'])) . html_writer::tag('td', s(implode(', ', $reasons))) . html_writer::tag('td', $form));
}
echo html_writer::end_tag('table');
$base = ['connection' => $connectionid, 'search' => $filters['search'], 'status' => $filters['status'], 'taxonomy' => $filters['taxonomy']];
if ($meta['page'] > 1) {
    echo html_writer::link(new moodle_url($PAGE->url, $base + ['page' => $meta['page'] - 1]), get_string('previous'), ['class' => 'btn btn-secondary mr-2']);
}
if ($meta['page'] < $meta['pages']) {
    echo html_writer::link(new moodle_url($PAGE->url, $base + ['page' => $meta['page'] + 1]), get_string('next'), ['class' => 'btn btn-secondary']);
}
echo $OUTPUT->footer();

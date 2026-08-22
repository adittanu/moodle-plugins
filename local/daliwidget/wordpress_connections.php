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
        $deleteChoice = required_param('delete_sources', PARAM_ALPHA);
        if (!in_array($deleteChoice, ['delete', 'retain'], true)) {
            redirect($PAGE->url, get_string('wordpress_delete_choice_required', 'local_daliwidget'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $result = $client->deleteWordpressConnection($id, ['delete_sources' => $deleteChoice]);
    } else if ($action === 'toggle') {
        $result = $client->updateWordpressConnection($id, ['enabled' => required_param('enabled', PARAM_BOOL)]);
    } else if ($action === 'reviewremovals') {
        $result = $client->reviewWordpressHeldRemovals($id, required_param('hold', PARAM_INT), required_param('decision', PARAM_ALPHA));
    } else {
        $result = ['success' => false, 'error' => get_string('wordpress_action_failed', 'local_daliwidget')];
    }
    redirect($PAGE->url, ($result['success'] ?? false) ? get_string('wordpress_action_success', 'local_daliwidget') : ($result['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')), null, ($result['success'] ?? false) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
}

$connections = $client->getWordpressConnections()['data'] ?? [];

// Surface failed/partial runs as admin notifications.
foreach ($connections as $conn) {
    $runs = $client->getWordpressRuns($conn['id'])['data'] ?? [];
    foreach (array_slice($runs, 0, 3) as $run) {
        if (in_array($run['status'] ?? '', ['failed', 'partial'], true)) {
            $counts = $run['counts'] ?? [];
            $msg = get_string('wordpress_run_notification', 'local_daliwidget', (object) [
                'name' => $conn['name'],
                'status' => $run['status'],
                'added' => $counts['added'] ?? 0,
                'updated' => $counts['updated'] ?? 0,
                'removed' => $counts['removed'] ?? 0,
                'failed' => $counts['failed'] ?? 0,
            ]);
            \core\notification::add($msg, \core\output\notification::NOTIFY_WARNING);
            break;
        }
    }
}

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
    $actions .= html_writer::end_tag('form');

    // Delete button opens a confirmation section (not inline form).
    $deleteBtn = html_writer::link(
        $PAGE->url . '#delete-' . $connection['id'],
        get_string('delete'),
        ['class' => 'btn btn-sm btn-danger', 'data-toggle' => 'collapse', 'data-target' => '#delete-confirm-' . $connection['id']]
    );

    $impact = '';
    if (!empty($connection['pending_removal_count'])) {
        $holds = $client->getWordpressHeldRemovals($connection['id'])['data'] ?? [];
        foreach ($holds as $hold) {
            $impact .= html_writer::start_div('alert alert-warning mt-2');
            $impact .= html_writer::tag('strong', get_string('wordpress_removals_held', 'local_daliwidget', $hold['count']));
            $impact .= html_writer::tag('ul', implode('', array_map(fn($removal) => html_writer::tag('li', s($removal['post_id'] . ' (' . $removal['locale'] . ')')), $hold['removals'])));
            foreach (['approve', 'reject'] as $decision) {
                $impact .= html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline mr-1']);
                foreach (['sesskey' => sesskey(), 'action' => 'reviewremovals', 'id' => $connection['id'], 'hold' => $hold['id'], 'decision' => $decision] as $name => $value) {
                    $impact .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                }
                $impact .= html_writer::tag('button', get_string('wordpress_' . $decision . '_removals', 'local_daliwidget'), [
                    'type' => 'submit', 'class' => 'btn btn-sm ' . ($decision === 'approve' ? 'btn-danger' : 'btn-secondary'),
                    'onclick' => "return confirm('" . get_string('wordpress_review_confirm', 'local_daliwidget') . "')",
                ]);
                $impact .= html_writer::end_tag('form');
            }
            $impact .= html_writer::end_div();
        }
    }
    $runs = $client->getWordpressRuns($connection['id'])['data'] ?? [];
    if ($runs) {
        $impact .= html_writer::tag('h6', get_string('wordpress_recent_runs', 'local_daliwidget'), ['class' => 'mt-2']);
        foreach (array_slice($runs, 0, 5) as $run) {
            $counts = $run['counts'] ?? [];
            $summary = get_string('wordpress_run_summary', 'local_daliwidget', (object) [
                'status' => $run['status'], 'trigger' => $run['trigger'],
                'added' => $counts['added'] ?? 0, 'updated' => $counts['updated'] ?? 0,
                'removed' => $counts['removed'] ?? 0, 'failed' => $counts['failed'] ?? 0,
            ]);
            $impact .= html_writer::start_div(in_array($run['status'], ['failed', 'partial'], true) ? 'alert alert-danger mt-1' : 'alert alert-light mt-1');
            $impact .= html_writer::tag('strong', s($summary));
            if (!empty($run['checkpoint_page'])) $impact .= html_writer::tag('div', get_string('wordpress_resume_page', 'local_daliwidget', $run['checkpoint_page']));
            if (!empty($run['error'])) $impact .= html_writer::tag('div', s($run['error']));
            $failures = array_filter($run['outcomes'] ?? [], fn($outcome) => ($outcome['status'] ?? '') === 'failed');
            if ($failures) $impact .= html_writer::tag('ul', implode('', array_map(fn($failure) => html_writer::tag('li', s(($failure['title'] ?? $failure['post_id']) . ': ' . ($failure['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')))), $failures)));
            $impact .= html_writer::end_div();
        }
    }

    // Delete confirmation with owned-source count and delete-vs-retain choice.
    $ownedCount = 0;
    $ownedResult = $client->getWordpressOwnedSourceCount($connection['id']);
    if (!empty($ownedResult['success'])) {
        $ownedCount = (int) ($ownedResult['data']['count'] ?? 0);
    }
    $deleteConfirm = html_writer::start_div('collapse mt-2', ['id' => 'delete-confirm-' . $connection['id']]);
    $deleteConfirm .= html_writer::start_div('alert alert-danger');
    $deleteConfirm .= html_writer::tag('p', get_string('wordpress_delete_confirm', 'local_daliwidget', $ownedCount));
    $deleteConfirm .= html_writer::start_tag('form', ['method' => 'post']);
    foreach (['sesskey' => sesskey(), 'action' => 'delete', 'id' => $connection['id']] as $name => $value) {
        $deleteConfirm .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $deleteConfirm .= html_writer::start_div('form-check');
    $deleteConfirm .= html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'delete_sources', 'value' => 'delete', 'id' => 'ds-del-' . $connection['id'], 'class' => 'form-check-input', 'required' => 'required']);
    $deleteConfirm .= html_writer::tag('label', get_string('wordpress_delete_sources', 'local_daliwidget'), ['for' => 'ds-del-' . $connection['id'], 'class' => 'form-check-label']);
    $deleteConfirm .= html_writer::end_div();
    $deleteConfirm .= html_writer::start_div('form-check');
    $deleteConfirm .= html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'delete_sources', 'value' => 'retain', 'id' => 'ds-ret-' . $connection['id'], 'class' => 'form-check-input']);
    $deleteConfirm .= html_writer::tag('label', get_string('wordpress_retain_sources', 'local_daliwidget'), ['for' => 'ds-ret-' . $connection['id'], 'class' => 'form-check-label']);
    $deleteConfirm .= html_writer::end_div();
    $deleteConfirm .= html_writer::tag('button', get_string('confirmdelete'), ['type' => 'submit', 'class' => 'btn btn-danger mt-2']);
    $deleteConfirm .= html_writer::end_tag('form');
    $deleteConfirm .= html_writer::end_div();
    $deleteConfirm .= html_writer::end_div();

    echo html_writer::tag('tr', html_writer::tag('td', s($connection['name'])) . html_writer::tag('td', s($connection['site_url'])) . html_writer::tag('td', $connection['enabled'] ? get_string('enabled', 'local_daliwidget') : get_string('disabled', 'core')) . html_writer::tag('td', $actions . ' ' . $deleteBtn . $deleteConfirm . $impact));
}
echo html_writer::end_tag('table');

// Preview section.
$previewid = optional_param('preview', 0, PARAM_INT);
if ($previewid) {
    $preview = $client->previewWordpressSync($previewid);
    if (!empty($preview['success'])) {
        $diff = $preview['data'] ?? [];
        echo html_writer::tag('h3', get_string('wordpress_preview_title', 'local_daliwidget'));
        echo html_writer::tag('p', get_string('wordpress_preview_desc', 'local_daliwidget'));
        $categories = ['add' => 'success', 'update' => 'info', 'remove' => 'danger', 'pending' => 'warning', 'unchanged' => 'secondary'];
        foreach ($categories as $cat => $style) {
            $items = $diff[$cat] ?? [];
            $label = get_string('wordpress_preview_' . $cat, 'local_daliwidget', count($items));
            echo html_writer::start_div('alert alert-' . $style . ' mb-1');
            echo html_writer::tag('strong', $label);
            if ($items) {
                echo html_writer::tag('ul', implode('', array_map(fn($item) => html_writer::tag('li', s($item['title'] ?? $item['post_id'] ?? '')), array_slice($items, 0, 20))));
                if (count($items) > 20) {
                    echo html_writer::tag('em', get_string('wordpress_preview_more', 'local_daliwidget', count($items) - 20));
                }
            }
            echo html_writer::end_div();
        }
    } else {
        echo html_writer::div(s($preview['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')), 'alert alert-danger');
    }
}

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

// Preview button for existing connections.
if ($editing) {
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mt-2']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'preview', 'value' => $editing['id']]);
    echo html_writer::tag('button', get_string('wordpress_preview_sync', 'local_daliwidget'), ['type' => 'submit', 'class' => 'btn btn-info']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();

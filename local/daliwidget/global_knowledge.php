<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Global Knowledge Base management page.
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/api_client.php');

use local_daliwidget\api_client;
use local_daliwidget\knowledge_lifecycle;

require_login();

$systemcontext = context_system::instance();
if (!knowledge_lifecycle::can_view_history()) {
    throw new required_capability_exception($systemcontext, 'moodle/site:config', 'nopermissions', '');
}
require_capability('moodle/site:config', $systemcontext);

$PAGE->set_url(new moodle_url('/local/daliwidget/global_knowledge.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('global_knowledge_page_title', 'local_daliwidget'));
$PAGE->set_heading(get_string('global_knowledge_page_title', 'local_daliwidget'));
$PAGE->navbar->add(get_string('pluginname', 'local_daliwidget'));
$PAGE->navbar->add(get_string('global_knowledge_base', 'local_daliwidget'));

$apiClient = new api_client();
$globalmetadata = [
    'scope' => 'global',
];

$format_source_error = static function(string $errorMessage): string {
    $normalized = core_text::strtolower(trim($errorMessage));

    if ($normalized !== '' && strpos($normalized, 'must be at least 100 characters') !== false) {
        return 'Mohon maaf, teks belum bisa diproses karena jumlah karakter masih kurang dari 100. Silakan tambahkan isi teks terlebih dahulu, lalu coba lagi.';
    }

    return $errorMessage;
};

$is_global_source = static function(array $source): bool {
    $courseid = (int) ($source['metadata']['course']['id'] ?? 0);
    return $courseid <= 0;
};

$wordpressconnections = array_values(array_filter(
    $apiClient->getWordpressConnections()['data'] ?? [],
    static fn(array $connection): bool => !empty($connection['enabled'])
));
$wordpressconnectionid = optional_param('wpconnection', 0, PARAM_INT);
$activewordpressconnection = null;
foreach ($wordpressconnections as $connection) {
    if ((int) $connection['id'] === $wordpressconnectionid) {
        $activewordpressconnection = $connection;
        break;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'add_text') {
        $title = required_param('text_title', PARAM_TEXT);
        $content = required_param('text_content', PARAM_RAW);
        $result = $apiClient->addTextSource($title, $content, $globalmetadata);
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($PAGE->url, $result['error'] ?? 'Failed to add text source', null, \core\output\notification::NOTIFY_ERROR);
    }


    if ($action === 'add_youtube') {
        $url = required_param('youtube_url', PARAM_URL);
        $result = $apiClient->addYoutubeSource($url, null, $globalmetadata, optional_param('additional_text', '', PARAM_RAW_TRIMMED));
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($PAGE->url, $result['error'] ?? 'Failed to add YouTube source', null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($action === 'add_wordpress_url') {
        $connectionid = required_param('wordpress_connection', PARAM_INT);
        $allowedids = array_map(static fn(array $connection): int => (int) $connection['id'], $wordpressconnections);
        if (!in_array($connectionid, $allowedids, true)) {
            throw new moodle_exception('invalidparameter');
        }
        $result = $apiClient->ingestWordpressUrl($connectionid, required_param('wordpress_url', PARAM_URL), $globalmetadata);
        redirect($PAGE->url, ($result['success'] ?? false) ? get_string('source_added', 'local_daliwidget') :
            ($result['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')), null,
            ($result['success'] ?? false) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
    }

    if ($action === 'upload') {
        $sourceType = optional_param('source_type', 'document', PARAM_ALPHA);
        if (!in_array($sourceType, ['document', 'video', 'audio', 'scorm'], true)) {
            $sourceType = 'document';
        }

        if (!empty($_FILES['source_file']['name'])) {
            $storedfile = get_file_storage()->create_file_from_pathname([
                'contextid' => $systemcontext->id,
                'component' => 'local_daliwidget',
                'filearea' => 'knowledge',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => clean_param($_FILES['source_file']['name'], PARAM_FILE),
            ], $_FILES['source_file']['tmp_name']);
            $metadata = array_merge($globalmetadata, ['moodle_file_id' => $storedfile->get_id()]);
            $result = $apiClient->uploadDocument($_FILES['source_file'], null, $metadata, $sourceType,
                $sourceType === 'video' ? optional_param('additional_text', '', PARAM_RAW_TRIMMED) : null);
            if ($result['success'] ?? false) {
                redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            $storedfile->delete();
            redirect($PAGE->url, $result['error'] ?? 'Failed to upload file', null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    if ($action === 'unsync') {
        $sourceids = optional_param_array('sourceids', [], PARAM_INT);
        $sourcesbyid = array_column($apiClient->getSources(null)['data'] ?? [], null, 'id');
        $selected = array_values(array_intersect_key($sourcesbyid, array_flip($sourceids)));
        $result = knowledge_lifecycle::unsync(
            $selected,
            null,
            static fn(int $sourceid): array => $apiClient->deleteSource($sourceid),
            sesskey()
        );
        redirect($PAGE->url, get_string('unsync_result', 'local_daliwidget', $result), null,
            $result['failed'] ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'selectwordpress') {
        $connectionid = required_param('wpconnection', PARAM_INT);
        $allowedconnectionids = array_map(static fn(array $connection): int => (int) $connection['id'], $wordpressconnections);
        if (!in_array($connectionid, $allowedconnectionids, true)) {
            throw new moodle_exception('invalidparameter');
        }
        $selected = required_param('selected', PARAM_BOOL);
        $result = $apiClient->selectWordpressPost(
            $connectionid,
            required_param('post', PARAM_INT),
            $selected,
            required_param('locale', PARAM_ALPHANUMEXT),
            !$selected && required_param('confirmed', PARAM_BOOL)
        );
        $returnurl = new moodle_url($PAGE->url, ['wpconnection' => $connectionid]);
        redirect($returnurl, ($result['success'] ?? false)
            ? get_string('wordpress_selection_saved', 'local_daliwidget')
            : ($result['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')));
    }

    if ($action === 'retry') {
        $sourceid = required_param('sourceid', PARAM_INT);
        $result = $apiClient->retrySource($sourceid);
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('global_retry_started', 'local_daliwidget'), null, \core\output\notification::NOTIFY_INFO);
        }
        redirect($PAGE->url, $result['error'] ?? 'Failed to retry source', null, \core\output\notification::NOTIFY_ERROR);
    }
}

$apiResponse = $apiClient->getSources(null);
$allSources = array_values(array_filter($apiResponse['data'] ?? [], $is_global_source));

$dedupedSources = [];
foreach ($allSources as $source) {
    $type = (string) ($source['type'] ?? '');
    $title = trim((string) ($source['title'] ?? ''));
    $key = $type . '|title:' . core_text::strtolower($title);

    $score = 0;
    $transport = trim((string) ($source['metadata']['transport'] ?? ''));
    if ($transport !== '') {
        $score += 20;
    }
    if (!empty($source['metadata']['fallback_reason'])) {
        $score += 10;
    }
    if (!empty($source['metadata']['debug_signed_url'])) {
        $score += 8;
    }
    if (!empty($source['content'])) {
        $score += 5;
    }
    if (($source['status'] ?? '') === 'ready') {
        $score += 3;
    } else if (in_array(($source['status'] ?? ''), ['queued', 'processing'], true)) {
        $score += 2;
    }

    if (!isset($dedupedSources[$key]) || $score > (int) ($dedupedSources[$key]['_render_score'] ?? 0)) {
        $source['_render_score'] = $score;
        $dedupedSources[$key] = $source;
    }
}
$allSources = array_values($dedupedSources);

$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 10;
$totalcount = count($allSources);
$pagedsources = array_slice($allSources, $page * $perpage, $perpage);

echo $OUTPUT->header();

echo html_writer::start_div('mb-4 d-flex justify-content-between align-items-center');
echo html_writer::start_div();
echo html_writer::tag('h2', get_string('global_knowledge_base', 'local_daliwidget'), ['class' => 'mb-2']);
echo html_writer::tag('p', get_string('global_knowledge_description', 'local_daliwidget'), ['class' => 'text-muted mb-0']);
echo html_writer::end_div();
echo html_writer::tag('div',
    html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add Source', [
        'type' => 'button',
        'class' => 'btn btn-primary mr-2',
        'data-toggle' => 'modal',
        'data-target' => '#global-add-source-modal',
        'data-bs-toggle' => 'modal',
        'data-bs-target' => '#global-add-source-modal',
    ]) .
    html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_daliwidget']),
        get_string('settings_heading', 'local_daliwidget'),
        ['class' => 'btn btn-outline-secondary']
    ),
    ['class' => 'd-flex align-items-center']
);
echo html_writer::end_div();

echo $OUTPUT->notification(
    get_string('global_knowledge_scope_info', 'local_daliwidget'),
    \core\output\notification::NOTIFY_INFO
);

if ($wordpressconnections) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header d-flex justify-content-between align-items-center flex-wrap', ['style' => 'gap:.75rem;']);
    echo html_writer::start_div();
    echo html_writer::tag('h5', get_string('wordpress_discovery_title', 'local_daliwidget'), ['class' => 'mb-1']);
    echo html_writer::tag('p', get_string('wordpress_discovery_desc', 'local_daliwidget'), ['class' => 'text-muted mb-0']);
    echo html_writer::end_div();
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline']);
    echo html_writer::select(array_column($wordpressconnections, 'name', 'id'), 'wpconnection',
        $wordpressconnectionid ?: '', ['' => get_string('wordpress_choose_connection', 'local_daliwidget')],
        ['class' => 'form-control mr-2', 'aria-label' => get_string('wordpress_choose_connection', 'local_daliwidget')]);
    echo html_writer::tag('button', get_string('wordpress_browse_posts', 'local_daliwidget'), [
        'type' => 'submit', 'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();

    if ($activewordpressconnection) {
        $wordpressfilters = [
            'page' => max(1, optional_param('wppage', 1, PARAM_INT)),
            'per_page' => 10,
            'search' => optional_param('wpsearch', '', PARAM_TEXT),
            'status' => optional_param('wpstatus', 'any', PARAM_ALPHA),
            'taxonomy' => null,
        ];
        $wordpressresult = $apiClient->getWordpressPosts($wordpressconnectionid, $wordpressfilters);
        $wordpressposts = $wordpressresult['data'] ?? [];
        $wordpressmeta = $wordpressresult['meta'] ?? ['page' => 1, 'pages' => 1];
        echo html_writer::start_div('card-body');
        echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline mb-3']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'wpconnection', 'value' => $wordpressconnectionid]);
        echo html_writer::empty_tag('input', ['name' => 'wpsearch', 'value' => $wordpressfilters['search'],
            'placeholder' => get_string('search'), 'class' => 'form-control mr-2']);
        echo html_writer::select(['any' => get_string('all'), 'publish' => 'Published', 'future' => 'Scheduled',
            'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private'], 'wpstatus',
            $wordpressfilters['status'], false, ['class' => 'form-control mr-2']);
        echo html_writer::tag('button', get_string('filter'), ['class' => 'btn btn-outline-secondary']);
        echo html_writer::end_tag('form');
        echo html_writer::start_div('table-responsive');
        echo html_writer::start_tag('table', ['class' => 'table table-striped mb-0']);
        echo html_writer::tag('tr', html_writer::tag('th', get_string('name'))
            . html_writer::tag('th', get_string('status'))
            . html_writer::tag('th', get_string('wordpress_inclusion', 'local_daliwidget'))
            . html_writer::tag('th', get_string('actions')));
        foreach ($wordpressposts as $post) {
            $reasons = array_filter([
                $post['automatic'] ? get_string('wordpress_automatic', 'local_daliwidget') : null,
                $post['manual'] ? get_string('wordpress_manual', 'local_daliwidget') : null,
                $post['pending'] ? get_string('wordpress_pending', 'local_daliwidget') : null,
            ]);
            $form = html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
            foreach (['sesskey' => sesskey(), 'action' => 'selectwordpress', 'wpconnection' => $wordpressconnectionid,
                'post' => $post['id'], 'locale' => $post['locale'], 'selected' => $post['manual'] ? 0 : 1,
                'confirmed' => $post['manual'] ? 1 : 0] as $name => $value) {
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
            }
            $form .= html_writer::tag('button', get_string($post['manual'] ? 'wordpress_cancel_selection' : 'wordpress_select',
                'local_daliwidget'), ['class' => 'btn btn-sm ' . ($post['manual'] ? 'btn-outline-danger' : 'btn-primary'),
                'onclick' => $post['manual'] ? "return confirm('" . get_string('wordpress_cancel_confirm', 'local_daliwidget') . "')" : null]);
            $form .= html_writer::end_tag('form');
            $title = $post['permalink'] ? html_writer::link($post['permalink'], s($post['title']), [
                'target' => '_blank', 'rel' => 'noopener noreferrer']) : s($post['title']);
            echo html_writer::tag('tr', html_writer::tag('td', $title) . html_writer::tag('td', s($post['status']))
                . html_writer::tag('td', s(implode(', ', $reasons))) . html_writer::tag('td', $form));
        }
        if (!$wordpressposts) {
            echo html_writer::tag('tr', html_writer::tag('td', get_string('wordpress_no_posts', 'local_daliwidget'), [
                'colspan' => 4, 'class' => 'text-center text-muted py-4']));
        }
        echo html_writer::end_tag('table');
        echo html_writer::end_div();
        $paginationbase = ['wpconnection' => $wordpressconnectionid, 'wpsearch' => $wordpressfilters['search'],
            'wpstatus' => $wordpressfilters['status']];
        echo html_writer::start_div('mt-3');
        if ($wordpressmeta['page'] > 1) {
            echo html_writer::link(new moodle_url($PAGE->url, $paginationbase + ['wppage' => $wordpressmeta['page'] - 1]),
                get_string('previous'), ['class' => 'btn btn-outline-secondary mr-2']);
        }
        if ($wordpressmeta['page'] < $wordpressmeta['pages']) {
            echo html_writer::link(new moodle_url($PAGE->url, $paginationbase + ['wppage' => $wordpressmeta['page'] + 1]),
                get_string('next'), ['class' => 'btn btn-outline-secondary']);
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'bulk-unsync-global',
    'onsubmit' => "return confirm('" . get_string('confirm_unsync', 'local_daliwidget') . "');"]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unsync']);
echo html_writer::end_tag('form');
echo html_writer::start_div('card');
echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
echo html_writer::tag('h5', 'Global Knowledge Sources', ['class' => 'mb-0']);
echo html_writer::tag('button', get_string('unsync_selected', 'local_daliwidget'), [
    'type' => 'submit', 'class' => 'btn btn-warning btn-sm', 'form' => 'bulk-unsync-global',
]);
echo html_writer::end_div();
echo html_writer::start_div('card-body p-0');
echo html_writer::start_tag('table', ['class' => 'table table-striped mb-0']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('select'));
echo html_writer::tag('th', get_string('name'));
echo html_writer::tag('th', 'Type');
echo html_writer::tag('th', get_string('status'));
echo html_writer::tag('th', 'Transport');
echo html_writer::tag('th', get_string('actions'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($pagedsources as $sourcearr) {
    $source = (object) $sourcearr;
    switch ($source->type) {
        case 'document':
            $typeConfig = ['label' => 'Document', 'icon' => 'file-alt', 'class' => 'text-primary'];
            break;
        case 'text':
            $typeConfig = ['label' => 'Custom Text', 'icon' => 'file-text', 'class' => 'text-info'];
            break;
        case 'url':
            $typeConfig = ['label' => 'Link', 'icon' => 'link', 'class' => 'text-info'];
            break;
        case 'video':
            $typeConfig = ['label' => 'Video', 'icon' => 'film', 'class' => 'text-primary'];
            break;
        case 'audio':
            $typeConfig = ['label' => 'Audio', 'icon' => 'music', 'class' => 'text-success'];
            break;
        case 'scorm':
            $typeConfig = ['label' => 'SCORM', 'icon' => 'archive', 'class' => 'text-warning'];
            break;
        default:
            $typeConfig = ['label' => 'YouTube', 'icon' => 'youtube', 'class' => 'text-danger'];
            break;
    }

    switch ($source->status) {
        case 'ready':
            $statusclass = 'badge-success';
            $statusicon = '<i class="fa fa-check mr-1"></i>';
            break;
        case 'failed':
            $statusclass = 'badge-danger';
            $statusicon = '<i class="fa fa-times mr-1"></i>';
            break;
        case 'queued':
            $statusclass = 'badge-secondary';
            $statusicon = '<i class="fa fa-clock mr-1"></i>';
            break;
        default:
            $statusclass = 'badge-warning';
            $statusicon = '<i class="fa fa-spinner fa-spin mr-1"></i>';
            break;
    }

    $transport = (string) ($source->metadata['transport'] ?? '');
    $transportLabel = '-';
    $transportClass = 'badge-light';
    if ($transport === 'signed_url') {
        $transportLabel = 'Signed URL';
        $transportClass = 'badge-info';
    } else if ($transport === 'binary_upload') {
        $transportLabel = 'File Upload';
        $transportClass = 'badge-secondary';
        if (!empty($source->metadata['fallback_reason'])) {
            $transportLabel = 'File Upload (Fallback)';
        }
    } else if ($transport === 'inline_text') {
        $transportLabel = 'Inline Text';
    } else if ($transport === 'url_fetch') {
        $transportLabel = 'URL Fetch';
    } else if ($transport === 'remote_url') {
        $transportLabel = 'Remote URL';
    }

    echo html_writer::start_tag('tr');
    $canunsync = !empty($source->metadata['moodle_file_id']);
    echo html_writer::tag('td', $canunsync ? html_writer::checkbox('sourceids[]', $source->id, false, '', [
        'value' => $source->id, 'form' => 'bulk-unsync-global',
    ]) : '');
    $isvideochild = ($source->metadata['relation'] ?? '') === 'video_additional_text';
    $titlehtml = '<i class="fa fa-' . $typeConfig['icon'] . ' ' . $typeConfig['class'] . ' mr-1"></i>' . s($source->title);
    echo html_writer::tag('td', $titlehtml, ['class' => $isvideochild ? 'pl-5' : '']);
    echo html_writer::tag('td', html_writer::tag('span', $typeConfig['label'], ['class' => 'badge badge-light']));

    $statushtml = html_writer::tag('span', $statusicon . ucfirst($source->status), ['class' => 'badge ' . $statusclass]);
    if ($source->status === 'failed' && !empty($source->error_message)) {
        $statushtml .= html_writer::div(s($format_source_error((string) $source->error_message)), 'small text-danger mt-1');
    }
    echo html_writer::tag('td', $statushtml);

    $transportHtml = html_writer::tag('span', $transportLabel, ['class' => 'badge ' . $transportClass]);
    if (!empty($source->metadata['fallback_reason'])) {
        $transportHtml .= html_writer::div(s((string) $source->metadata['fallback_reason']), 'small text-muted mt-1');
    }
    $debugSignedUrl = trim((string) ($source->metadata['debug_signed_url'] ?? ''));
    if ($debugSignedUrl !== '') {
        $transportHtml .= html_writer::div(
            html_writer::link($debugSignedUrl, 'Debug signed URL', [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'class' => 'small',
            ]),
            'mt-1'
        );
    }
    echo html_writer::tag('td', $transportHtml);

    echo html_writer::start_tag('td');
    echo html_writer::start_div('d-inline-flex align-items-center', ['style' => 'gap:8px; white-space:nowrap;']);
    if (($source->status ?? '') === 'failed') {
        echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'm-0']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'retry']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sourceid', 'value' => $source->id]);
        echo html_writer::tag('button', '<i class="fa fa-redo mr-1"></i>Retry', ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']);
        echo html_writer::end_tag('form');
    }
    if ($canunsync) {
        echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'm-0',
            'onsubmit' => "return confirm('" . get_string('confirm_unsync_single', 'local_daliwidget') . "');"]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unsync']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sourceids[]', 'value' => $source->id]);
        echo html_writer::tag('button', '<i class="fa fa-unlink"></i>', ['type' => 'submit',
            'class' => 'btn btn-sm btn-outline-danger', 'title' => get_string('unsync_source', 'local_daliwidget')]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
}

if (empty($pagedsources)) {
    echo html_writer::tag('tr', html_writer::tag('td', get_string('no_global_sources', 'local_daliwidget'), ['colspan' => 5, 'class' => 'text-center text-muted py-4']));
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();
if ($totalcount > $perpage) {
    $baseurl = new moodle_url('/local/daliwidget/global_knowledge.php');
    echo html_writer::start_div('card-footer');
    echo $OUTPUT->paging_bar(new paging_bar($totalcount, $page, $perpage, $baseurl));
    echo html_writer::end_div();
}
echo html_writer::end_div();
$history = knowledge_lifecycle::history();
echo html_writer::tag('h3', get_string('unsynced_history', 'local_daliwidget'), ['class' => 'mt-4']);
$table = new html_table();
    $table->head = [get_string('name'), get_string('source_type', 'local_daliwidget'), get_string('status'), get_string('user'), get_string('date')];
foreach ($history as $record) {
    $table->data[] = [s($record->title), s($record->sourcetype), s($record->lifecyclestatus),
        fullname($DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST)), userdate($record->timeunsynced)];
}
echo html_writer::table($table);

echo html_writer::start_div('modal fade', [
    'id' => 'global-add-source-modal',
    'tabindex' => '-1',
    'role' => 'dialog',
    'aria-labelledby' => 'global-add-source-modal-label',
    'aria-hidden' => 'true',
]);
echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
echo html_writer::start_div('modal-content');
echo html_writer::start_div('modal-header');
echo html_writer::tag('h5', 'Add Global Source', ['class' => 'modal-title', 'id' => 'global-add-source-modal-label']);
echo html_writer::tag('button', '&times;', [
    'type' => 'button',
    'class' => 'close',
    'data-dismiss' => 'modal',
    'data-bs-dismiss' => 'modal',
    'aria-label' => 'Close',
]);
echo html_writer::end_div();
echo html_writer::start_div('modal-body');

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Source Type', ['for' => 'global-add-source-type']);
echo html_writer::select([
    'document' => 'Document',
    'text' => 'Custom Text',
    'youtube' => 'YouTube URL',
    'wordpress' => get_string('wordpress_url_source', 'local_daliwidget'),
    'video' => 'Video File',
    'audio' => 'Audio File',
    'scorm' => 'SCORM Package',
], 'global_add_source_type_selector', 'document', false, [
    'id' => 'global-add-source-type',
    'class' => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div('global-add-source-section', ['data-source-section' => 'document']);
echo html_writer::start_tag('form', ['method' => 'post', 'enctype' => 'multipart/form-data']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source_type', 'value' => 'document']);
echo html_writer::tag('p', 'Upload PDF, DOCX, TXT, or PPTX files.', ['class' => 'text-muted']);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'source_file', 'class' => 'form-control mb-3', 'accept' => '.pdf,.doc,.docx,.txt,.ppt,.pptx', 'required' => true]);
echo html_writer::tag('button', '<i class="fa fa-upload mr-1"></i>Upload Document', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::start_div('global-add-source-section d-none', ['data-source-section' => 'text']);
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_text']);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Title', ['for' => 'global_text_title', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'text_title', 'id' => 'global_text_title', 'class' => 'form-control', 'required' => true]);
echo html_writer::end_div();
echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Content', ['for' => 'global_text_content', 'class' => 'form-label']);
echo html_writer::tag('textarea', '', ['name' => 'text_content', 'id' => 'global_text_content', 'class' => 'form-control', 'rows' => 8, 'required' => true]);
echo html_writer::end_div();
echo html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add Custom Text', ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
if ($wordpressconnections) {
    echo html_writer::start_div('global-add-source-section d-none', ['data-source-section' => 'wordpress']);
    echo html_writer::start_tag('form', ['method' => 'post']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_wordpress_url']);
    echo html_writer::select(array_column($wordpressconnections, 'name', 'id'), 'wordpress_connection', '',
        ['' => get_string('wordpress_choose_connection', 'local_daliwidget')], ['class' => 'form-control mb-3', 'required' => true]);
    echo html_writer::empty_tag('input', ['type' => 'url', 'name' => 'wordpress_url', 'class' => 'form-control',
        'placeholder' => 'https://example.com/article/', 'required' => true]);
    echo html_writer::tag('button', get_string('wordpress_ingest_url', 'local_daliwidget'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}


echo html_writer::start_div('global-add-source-section d-none', ['data-source-section' => 'youtube']);
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_youtube']);
echo html_writer::tag('label', 'YouTube URL', ['for' => 'global_youtube_url', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'url', 'name' => 'youtube_url', 'id' => 'global_youtube_url', 'class' => 'form-control', 'placeholder' => 'https://youtube.com/watch?v=...', 'required' => true]);
echo html_writer::tag('label', get_string('video_additional_text', 'local_daliwidget'), ['for' => 'global_youtube_additional_text', 'class' => 'form-label mt-3']);
echo html_writer::tag('textarea', '', ['name' => 'additional_text', 'id' => 'global_youtube_additional_text', 'class' => 'form-control', 'rows' => 5]);
echo html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add YouTube Video', ['type' => 'submit', 'class' => 'btn btn-danger mt-3']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

foreach ([
    'video' => ['label' => 'Video File', 'button' => 'Upload Video', 'accept' => '.mp4,.mov,.mkv,.webm', 'class' => 'btn-primary'],
    'audio' => ['label' => 'Audio File', 'button' => 'Upload Audio', 'accept' => '.mp3,.wav,.m4a,.aac,.flac,.ogg', 'class' => 'btn-success'],
    'scorm' => ['label' => 'SCORM Package', 'button' => 'Upload SCORM', 'accept' => '.zip,.scorm', 'class' => 'btn-warning'],
] as $modalType => $config) {
    echo html_writer::start_div('global-add-source-section d-none', ['data-source-section' => $modalType]);
    echo html_writer::start_tag('form', ['method' => 'post', 'enctype' => 'multipart/form-data']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source_type', 'value' => $modalType]);
    echo html_writer::tag('p', 'Upload a ' . $config['label'] . ' to the global knowledge base.', ['class' => 'text-muted']);
    echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'source_file', 'class' => 'form-control mb-3', 'accept' => $config['accept'], 'required' => true]);
    if ($modalType === 'video') {
        echo html_writer::tag('label', get_string('video_additional_text', 'local_daliwidget'), ['class' => 'form-label']);
        echo html_writer::tag('textarea', '', ['name' => 'additional_text', 'class' => 'form-control mb-3', 'rows' => 5]);
    }
    echo html_writer::tag('button', '<i class="fa fa-upload mr-1"></i>' . $config['button'], ['type' => 'submit', 'class' => 'btn ' . $config['class']]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
$wordpressconnectionjson = json_encode(array_map(static fn(array $connection): array => [
    'id' => (int) $connection['id'], 'site_url' => (string) $connection['site_url'],
], $wordpressconnections), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo html_writer::end_div();

echo <<<HTML
<script>
(function() {
    const wordpressConnections = {$wordpressconnectionjson};
    document.querySelectorAll('input[name="wordpress_url"]').forEach(input => input.addEventListener('change', function() {
        try {
            const origin = new URL(this.value).origin;
            const matches = wordpressConnections.filter(connection => new URL(connection.site_url).origin === origin);
            if (matches.length === 1) {
                this.form.querySelector('select[name="wordpress_connection"]').value = matches[0].id;
            }
        } catch (e) {
            // Native URL validation reports malformed input.
        }
    }));
    const selector = document.getElementById('global-add-source-type');
    const sections = Array.from(document.querySelectorAll('.global-add-source-section'));
    if (!selector || sections.length === 0) {
        return;
    }

    function updateSections() {
        const selected = selector.value;
        sections.forEach(section => {
            const active = section.getAttribute('data-source-section') === selected;
            section.classList.toggle('d-none', !active);
        });
    }

    selector.addEventListener('change', updateSections);
    updateSections();
})();
</script>
HTML;

echo $OUTPUT->footer();

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
 * Course Knowledge Base management page
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/sync_helper.php');

use local_daliwidget\api_client;
use local_daliwidget\sync_helper;
use local_daliwidget\knowledge_lifecycle;

// Get course ID parameter
$courseid = required_param('id', PARAM_INT);

// Get the course
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Require login and course access
require_login($course);

// Check capability
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);

// Page setup
$PAGE->set_url(new moodle_url('/local/daliwidget/knowledge.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('knowledge_page_title', 'local_daliwidget'));
$PAGE->set_heading($course->fullname);

// Add navigation
$PAGE->navbar->add(get_string('pluginname', 'local_daliwidget'));
$PAGE->navbar->add(get_string('knowledge_base', 'local_daliwidget'));

// Initialize API client
$apiClient = new api_client();
$courseMetadata = api_client::buildMoodleMetadata($course);
$wordpressconnections = array_values(array_filter(
    $apiClient->getWordpressConnections()['data'] ?? [],
    static fn(array $connection): bool => !empty($connection['enabled']) && !empty($connection['validated_at'])
));

// Handle sync_activity action (GET request from link)
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'sync_activity') {
    require_sesskey();
    $cmid = required_param('cmid', PARAM_INT);
    get_coursemodule_from_id(null, $cmid, $courseid, false, MUST_EXIST);

    $result = sync_helper::sync_or_queue($course, $cmid);
    if (!empty($result['queued'])) {
        redirect($PAGE->url, get_string('sync_queued_desc', 'local_daliwidget'), null, \core\output\notification::NOTIFY_INFO);
    }

    if (!empty($result['success'])) {
        redirect($PAGE->url, get_string('activity_synced', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect(
        $PAGE->url,
        'Sync failed: ' . ($result['error'] ?? 'Unknown error'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Handle form submissions (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $action = optional_param('action', '', PARAM_ALPHA);
    
    
    if ($action === 'add_youtube') {
        $url = required_param('youtube_url', PARAM_URL);
        $result = $apiClient->addYoutubeSource($url, null, $courseMetadata, optional_param('additional_text', '', PARAM_RAW_TRIMMED));
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $result['error'] ?? 'Failed to add YouTube video', null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    if ($action === 'add_wordpress_url') {
        $connectionid = required_param('wordpress_connection', PARAM_INT);
        $allowedids = array_map(static fn(array $connection): int => (int) $connection['id'], $wordpressconnections);
        if (!in_array($connectionid, $allowedids, true)) {
            throw new moodle_exception('invalidparameter');
        }
        $result = $apiClient->ingestWordpressUrl($connectionid, required_param('wordpress_url', PARAM_URL), $courseMetadata);
        redirect($PAGE->url, ($result['success'] ?? false) ? get_string('source_added', 'local_daliwidget') :
            ($result['error'] ?? get_string('wordpress_action_failed', 'local_daliwidget')), null,
            ($result['success'] ?? false) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
    }
    
    if ($action === 'upload') {
        $sourceType = optional_param('source_type', 'document', PARAM_ALPHA);
        if (!in_array($sourceType, ['document', 'video', 'audio', 'scorm'], true)) {
            $sourceType = 'document';
        }

        $fileField = 'source_file';
        if (empty($_FILES[$fileField]['name']) && !empty($_FILES['document']['name'])) {
            $fileField = 'document';
        }
        if (!empty($_FILES[$fileField]['name'])) {
            $storedfile = get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'local_daliwidget',
                'filearea' => 'knowledge',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => clean_param($_FILES[$fileField]['name'], PARAM_FILE),
            ], $_FILES[$fileField]['tmp_name']);
            $metadata = array_merge($courseMetadata, ['moodle_file_id' => $storedfile->get_id()]);
            $result = $apiClient->uploadDocument($_FILES[$fileField], null, $metadata, $sourceType,
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
        $sourcesbyid = array_column($apiClient->getSources($courseid)['data'] ?? [], null, 'id');
        $selected = array_values(array_intersect_key($sourcesbyid, array_flip($sourceids)));
        $result = knowledge_lifecycle::unsync(
            $selected,
            $courseid,
            $USER->id,
            static fn(int $sourceid): array => $apiClient->deleteSource($sourceid)
        );
        redirect($PAGE->url, get_string('unsync_result', 'local_daliwidget', $result), null,
            $result['failed'] ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
    }
    
}

// Fetch sources from API
$apiResponse = $apiClient->getSources($courseid);
$allSources = $apiResponse['data'] ?? [];

// De-duplicate sources that refer to the same activity/source so fallback
// attempts do not leave multiple visible rows for users.
$dedupedSources = [];
foreach ($allSources as $source) {
    $type = (string) ($source['type'] ?? '');
    $title = trim((string) ($source['title'] ?? ''));
    $activityid = (int) ($source['metadata']['activity']['id'] ?? 0);
    $key = $activityid > 0 ? 'activity:' . $activityid : $type . '|title:' . core_text::strtolower($title);

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

    if (!isset($dedupedSources[$key])) {
        $source['_render_score'] = $score;
        $dedupedSources[$key] = $source;
        continue;
    }

    $existing = $dedupedSources[$key];
    $existingscore = (int) ($existing['_render_score'] ?? 0);
    if ($score > $existingscore) {
        $source['_render_score'] = $score;
        $dedupedSources[$key] = $source;
    }
}
$allSources = array_values($dedupedSources);

$format_source_error = static function(string $errorMessage): string {
    $normalized = core_text::strtolower(trim($errorMessage));

    if ($normalized !== '' && strpos($normalized, 'must be at least 100 characters') !== false) {
        return 'Mohon maaf, teks belum bisa diproses karena jumlah karakter masih kurang dari 100. Silakan tambahkan isi teks terlebih dahulu, lalu coba lagi.';
    }

    return $errorMessage;
};

// Separate sources by type
$documents = [];
$links = [];
$youtubeVideos = [];
$videoFiles = [];
$audioSources = [];
$scormSources = [];
$textSources = [];

foreach ($allSources as $source) {
    switch ($source['type']) {
        case 'document':
            $documents[] = (object) $source;
            break;
        case 'url':
            $links[] = (object) $source;
            break;
        case 'youtube':
            $youtubeVideos[] = (object) $source;
            break;
        case 'video':
            $contentUrl = strtolower((string)($source['content'] ?? ''));
            if (strpos($contentUrl, 'youtube.com') !== false || strpos($contentUrl, 'youtu.be') !== false) {
                $youtubeVideos[] = (object) $source;
            } else {
                $videoFiles[] = (object) $source;
            }
            break;
        case 'audio':
            $audioSources[] = (object) $source;
            break;
        case 'scorm':
            $scormSources[] = (object) $source;
            break;
        case 'text':
            $textSources[] = (object) $source;
            break;
    }
}

// Output page
echo $OUTPUT->header();

// Page title and description
echo html_writer::start_div('mb-4 d-flex justify-content-between align-items-center');
echo html_writer::start_div();
echo html_writer::tag('h2', get_string('knowledge_base', 'local_daliwidget'), ['class' => 'mb-2']);
echo html_writer::tag('p', get_string('knowledge_description', 'local_daliwidget'), ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add Source', [
    'type' => 'button',
    'class' => 'btn btn-primary mr-2',
    'data-toggle' => 'modal',
    'data-target' => '#add-source-modal',
    'data-bs-toggle' => 'modal',
    'data-bs-target' => '#add-source-modal',
]);
if (has_capability('moodle/site:config', context_system::instance())) {
    echo html_writer::link(
        new moodle_url('/local/daliwidget/global_knowledge.php'),
        get_string('global_knowledge_base', 'local_daliwidget'),
        ['class' => 'btn btn-outline-secondary']
    );
}
echo html_writer::end_div();

// Info box about scope
echo $OUTPUT->notification(
    get_string('knowledge_scope_info', 'local_daliwidget', $course->fullname),
    \core\output\notification::NOTIFY_INFO
);

$knowledgeStyles = '
.dali-kb-summary{display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;padding:.85rem 1rem;margin-bottom:1.5rem;background:#f7f9fa;border:1px solid #dfe5e8;border-radius:.75rem}.dali-kb-summary strong{font-variant-numeric:tabular-nums;color:#111827}.dali-kb-card{border:1px solid #cbdedb!important;border-radius:.75rem!important;overflow:hidden}.dali-kb-card>.card-header{color:#0f4f4a;background:#f0fdfa!important;border-bottom-color:#cbdedb!important}.dali-kb-toolbar{padding:.75rem;background:#fff;border-bottom:1px solid #dfe5e8}.dali-kb-table{overflow-x:auto;scrollbar-color:#9ca3af transparent}.dali-kb-table table{min-width:760px}.dali-kb-table th{white-space:nowrap;font-size:.75rem;letter-spacing:.02em;color:#52606d;background:#f7f9fa}.dali-kb-table td{vertical-align:middle}.dali-kb-table tbody tr:hover{background:#f8fbfb}.dali-kb-table input[type="checkbox"]{width:1rem;height:1rem;accent-color:#0f766e}.dali-kb-table .btn:focus-visible,.dali-kb-table input:focus-visible{outline:3px solid rgba(15,118,110,.25);outline-offset:2px}@media(max-width:767.98px){.dali-kb-toolbar{align-items:stretch!important;flex-direction:column}.dali-kb-toolbar .btn{min-height:2.75rem}}
';
echo html_writer::tag('style', $knowledgeStyles);

// Compact operational summary.
$totalSources = count($allSources);
$readySources = count(array_filter($allSources, static fn(array $source): bool => ($source['status'] ?? '') === 'ready'));
$failedSources = count(array_filter($allSources, static fn(array $source): bool => ($source['status'] ?? '') === 'failed'));
echo html_writer::start_div('dali-kb-summary', ['aria-label' => 'Knowledge source summary']);
echo html_writer::tag('span', '<strong>' . $totalSources . '</strong> total sources');
echo html_writer::tag('span', '<strong>' . $readySources . '</strong> ready');
echo html_writer::tag('span', '<strong>' . $failedSources . '</strong> need attention');
echo html_writer::end_div();

// Activity content sync.
echo html_writer::start_div('card mb-4 dali-kb-card');
echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
echo html_writer::tag('h5', '<i class="fa fa-sync-alt mr-2"></i>' . get_string('activity_sync', 'local_daliwidget'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body p-0');
echo html_writer::div(get_string('activity_sync_desc', 'local_daliwidget'), 'text-muted p-3 pb-0');
echo html_writer::start_div('d-flex flex-wrap align-items-center gap-2 dali-kb-toolbar');
echo html_writer::tag('button', '<i class="fa fa-sync-alt mr-1"></i>Sync Selected', [
    'type' => 'button',
    'class' => 'btn btn-sm btn-primary',
    'id' => 'sync-selected-btn'
]);
echo html_writer::tag('button', '<i class="fa fa-layer-group mr-1"></i>Sync All', [
    'type' => 'button',
    'class' => 'btn btn-sm btn-outline-primary',
    'id' => 'sync-all-btn'
]);
echo html_writer::tag('small', 'Select rows for batch sync. Progress survives page refresh.', ['class' => 'text-muted', 'id' => 'bulk-sync-status']);
echo html_writer::end_div();

// Get course activities
$modinfo = get_fast_modinfo($course);
$activities = [];
$activitiesbyid = [];
$activitypreviews = [];
foreach ($modinfo->get_cms() as $cm) {
    // Only show supported activity types
    if ($cm->uservisible && in_array($cm->modname, ['page', 'book', 'assign', 'scorm', 'forum', 'lesson', 'folder', 'resource', 'label'])) {
        $activities[] = $cm;
        $activitiesbyid[$cm->id] = $cm;

        $preview = [
            'category' => 'text',
            'transport' => 'Text Extract',
        ];

        if (in_array($cm->modname, ['page', 'book', 'assign', 'forum', 'lesson', 'folder', 'label'], true)) {
            $preview = [
                'category' => 'text',
                'transport' => 'Text Extract',
            ];
        } else if ($cm->modname === 'scorm') {
            $preview = [
                'category' => 'scorm',
                'transport' => \local_daliwidget\file_url_helper::is_enabled() ? 'Signed URL first' : 'File Upload',
            ];
        } else if ($cm->modname === 'resource') {
            $preview['transport'] = \local_daliwidget\file_url_helper::is_enabled() ? 'Signed URL first' : 'File Upload';
            $files = get_file_storage()->get_area_files(
                $cm->context->id, 'mod_resource', 'content', 0,
                'sortorder DESC, id ASC', false
            );
            if ($files) {
                $file = reset($files);
                $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                if (in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx'], true)) {
                    $preview['category'] = 'documents';
                } else if (in_array($extension, ['mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg'], true)) {
                    $preview['category'] = 'audio';
                } else if (in_array($extension, ['mp4', 'mov', 'mkv', 'webm'], true)) {
                    $preview['category'] = 'video';
                } else if (in_array($extension, ['zip', 'scorm'], true)) {
                    $preview['category'] = 'scorm';
                }
            }
        } else if ($preview['category'] === 'text') {
            $preview['category'] = 'text';
        }

        $activitypreviews[$cm->id] = $preview;
    }
}

// Load latest queue status per cmid for this course.
$queuestatuses = [];
$queuerows = $DB->get_records_sql(
    "SELECT q.cmid, q.status, q.errormessage, q.timemodified
       FROM {local_daliwidget_sync_queue} q
       JOIN (
           SELECT cmid, MAX(id) as maxid
             FROM {local_daliwidget_sync_queue}
            WHERE courseid = :courseid
            GROUP BY cmid
       ) latest ON q.cmid = latest.cmid AND q.id = latest.maxid
      WHERE q.courseid = :courseid2",
    ['courseid' => $courseid, 'courseid2' => $courseid]
);
foreach ($queuerows as $row) {
    $queuestatuses[$row->cmid] = $row;
}

$sourceActivityIds = [];
foreach ($allSources as $source) {
    $activityid = (int) ($source['metadata']['activity']['id'] ?? 0);
    if ($activityid > 0) {
        $sourceActivityIds[$activityid] = true;
    }
}

$pendingKnowledgeRows = [];
foreach ($queuestatuses as $cmid => $row) {
    if (!in_array($row->status, ['queued', 'processing'], true)) {
        continue;
    }
    if (!isset($activitiesbyid[$cmid], $activitypreviews[$cmid])) {
        continue;
    }
    if (!empty($sourceActivityIds[$cmid])) {
        continue;
    }

    $cm = $activitiesbyid[$cmid];
    $preview = $activitypreviews[$cmid];
    $pendingtypes = [
        'documents' => 'document',
        'video' => 'video',
        'audio' => 'audio',
        'scorm' => 'scorm',
        'url' => 'url',
    ];
    $pendingType = $pendingtypes[$preview['category'] ?? 'text'] ?? 'text';

    $pendingKnowledgeRows[] = (object) [
        'id' => 0,
        'type' => $pendingType,
        'title' => $cm->name,
        'status' => $row->status,
        'error_message' => $row->errormessage,
        'metadata' => [
            'transport' => [
                'text' => 'inline_text',
                'url' => 'url_fetch',
            ][$pendingType] ?? (\local_daliwidget\file_url_helper::is_enabled() ? 'signed_url' : 'binary_upload'),
            'pending_placeholder' => true,
        ],
        'is_placeholder' => true,
    ];
}

// Activity table
echo html_writer::start_div('dali-kb-table');
echo html_writer::start_tag('table', ['class' => 'table table-sm table-hover mb-0']);
echo html_writer::start_tag('thead', ['class' => 'thead-light']);
echo html_writer::start_tag('tr');
echo html_writer::tag('th', html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'sync-select-all',
    'title' => 'Select all'
]));
echo html_writer::tag('th', get_string('activity'));
echo html_writer::tag('th', get_string('activity_type', 'local_daliwidget'));
echo html_writer::tag('th', 'Sync Path');
echo html_writer::tag('th', 'Queue Status');
echo html_writer::tag('th', get_string('actions'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($activities as $cm) {
    $icon = '';
    switch ($cm->modname) {
        case 'assign': $icon = '<i class="fa fa-clipboard text-info"></i>'; break;
        case 'scorm': $icon = '<i class="fa fa-archive text-warning"></i>'; break;
        case 'forum': $icon = '<i class="fa fa-comments text-primary"></i>'; break;
        case 'lesson': $icon = '<i class="fa fa-graduation-cap text-success"></i>'; break;
        case 'folder': $icon = '<i class="fa fa-folder-open text-warning"></i>'; break;
        case 'page': $icon = '<i class="fa fa-file-alt text-primary"></i>'; break;
        case 'book': $icon = '<i class="fa fa-book text-secondary"></i>'; break;
        case 'resource': $icon = '<i class="fa fa-file text-muted"></i>'; break;
        case 'url': $icon = '<i class="fa fa-link text-info"></i>'; break;
        case 'label': $icon = '<i class="fa fa-font text-success"></i>'; break;
        default: $icon = '<i class="fa fa-puzzle-piece"></i>';
    }
    
    $syncMode = $activitypreviews[$cm->id]['transport'] ?? 'Text Extract';

    echo html_writer::start_tag('tr', ['data-cmid' => $cm->id]);
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'class' => 'sync-select',
        'value' => $cm->id,
    ]));
    echo html_writer::tag('td', $icon . ' ' . $cm->name);
    echo html_writer::tag('td', html_writer::tag('span', ucfirst($cm->modname), ['class' => 'badge badge-secondary']));
    echo html_writer::tag('td', html_writer::tag('span', $syncMode, ['class' => 'badge badge-light']));

    $queueStatusHtml = html_writer::tag('span', '-', ['class' => 'text-muted']);
    if (isset($queuestatuses[$cm->id])) {
        $cmstatus = $queuestatuses[$cm->id];
        $statuslabel = get_string('sync_status_' . $cmstatus->status, 'local_daliwidget');
        $badgeclass = [
            'queued'     => 'badge-secondary',
            'processing' => 'badge-info',
            'done'       => 'badge-success',
            'failed'     => 'badge-danger',
        ][$cmstatus->status] ?? 'badge-secondary';
        $errortitle = '';
        if (!empty($cmstatus->errormessage)) {
            $errortitle = ' title="' . s($cmstatus->errormessage) . '"';
        }
        $queueStatusHtml = '<span class="badge ' . $badgeclass . '"' . $errortitle . '>' . $statuslabel . '</span>';
        if ($cmstatus->status === 'done' && !empty($cmstatus->errormessage)) {
            $queueStatusHtml .= ' ' . html_writer::tag('span', 'Fallback', [
                'class' => 'badge badge-warning',
                'title' => s($cmstatus->errormessage),
            ]);
        }
        if (!empty($cmstatus->timemodified)) {
            $queueStatusHtml .= html_writer::div(
                userdate($cmstatus->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
                'small text-muted mt-1'
            );
        }
    }
    echo html_writer::tag('td', $queueStatusHtml);

    echo html_writer::start_tag('td');
    $syncurl = new moodle_url($PAGE->url, ['action' => 'sync_activity', 'cmid' => $cm->id, 'sesskey' => sesskey()]);
    echo html_writer::link($syncurl, '<i class="fa fa-sync-alt mr-1"></i>Sync', ['class' => 'btn btn-sm btn-outline-primary', 'data-action' => 'sync', 'data-cmid' => $cm->id]);
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

if (empty($activities)) {
    echo html_writer::tag('tr', html_writer::tag('td', get_string('no_activities', 'local_daliwidget'), ['colspan' => 6, 'class' => 'text-center text-muted py-3']));
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

// Unified knowledge sources list.
$allKnowledgeSources = array_merge(
    $pendingKnowledgeRows,
    $documents,
    $textSources,
    $links,
    $youtubeVideos,
    $videoFiles,
    $audioSources,
    $scormSources
);

echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'bulk-unsync-course',
    'onsubmit' => "return confirm('" . get_string('confirm_unsync', 'local_daliwidget') . "');"]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unsync']);
echo html_writer::end_tag('form');
echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-header d-flex justify-content-between align-items-center flex-wrap', ['style' => 'gap:.75rem;']);
echo html_writer::tag('h5', 'Knowledge Sources', ['class' => 'mb-0']);
echo html_writer::tag('button', get_string('unsync_selected', 'local_daliwidget'), [
    'type' => 'submit', 'class' => 'btn btn-outline-danger btn-sm', 'form' => 'bulk-unsync-course',
    'id' => 'unsync-selected-btn', 'disabled' => true,
]);
echo html_writer::end_div();

echo html_writer::start_div('card-body p-0');
echo html_writer::start_div('dali-kb-table');
echo html_writer::start_tag('table', ['class' => 'table table-striped mb-0']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', html_writer::empty_tag('input', [
    'type' => 'checkbox', 'id' => 'source-select-all', 'title' => get_string('selectall'),
]));
echo html_writer::tag('th', get_string('name'));
echo html_writer::tag('th', 'Type');
echo html_writer::tag('th', get_string('status'));
echo html_writer::tag('th', 'Transport');
echo html_writer::tag('th', 'Last Synced');
echo html_writer::tag('th', get_string('actions'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($allKnowledgeSources as $source) {
    echo html_writer::start_tag('tr');

    $typeconfigs = [
        'document' => ['label' => 'Document', 'icon' => 'file-alt', 'class' => 'text-primary'],
        'text' => ['label' => 'Custom Text', 'icon' => 'file-text', 'class' => 'text-info'],
        'url' => ['label' => 'Link', 'icon' => 'link', 'class' => 'text-info'],
        'video' => ['label' => 'Video', 'icon' => 'film', 'class' => 'text-primary'],
        'audio' => ['label' => 'Audio', 'icon' => 'music', 'class' => 'text-success'],
        'scorm' => ['label' => 'SCORM', 'icon' => 'archive', 'class' => 'text-warning'],
    ];
    $typeConfig = $typeconfigs[$source->type] ?? ['label' => 'YouTube', 'icon' => 'youtube', 'class' => 'text-danger'];

    echo html_writer::tag('td', empty($source->is_placeholder)
        ? html_writer::checkbox('sourceids[]', $source->id, false, '', [
            'value' => $source->id, 'form' => 'bulk-unsync-course', 'class' => 'source-select',
            'aria-label' => get_string('select') . ': ' . s($source->title),
        ])
        : '');
    $isvideochild = ($source->metadata['relation'] ?? '') === 'video_additional_text';
    $titlehtml = '<i class="fa fa-' . $typeConfig['icon'] . ' ' . $typeConfig['class'] . ' mr-1"></i>' . s($source->title);
    echo html_writer::tag('td', $titlehtml, ['class' => $isvideochild ? 'pl-5' : '']);
    echo html_writer::tag('td', html_writer::tag('span', $typeConfig['label'], ['class' => 'badge badge-light']));

    $statusclasses = [
        'ready' => 'badge-success',
        'failed' => 'badge-danger',
        'queued' => 'badge-secondary',
    ];
    $statusclass = $statusclasses[$source->status] ?? 'badge-warning';
    $statusicons = [
        'ready' => '<i class="fa fa-check mr-1"></i>',
        'failed' => '<i class="fa fa-times mr-1"></i>',
        'queued' => '<i class="fa fa-clock mr-1"></i>',
    ];
    $statusicon = $statusicons[$source->status] ?? '<i class="fa fa-spinner fa-spin mr-1"></i>';
    $statushtml = html_writer::tag('span', $statusicon . ucfirst($source->status), ['class' => 'badge ' . $statusclass]);
    if ($source->status === 'failed' && !empty($source->error_message)) {
        $friendlyError = $format_source_error((string) $source->error_message);
        $statushtml .= html_writer::div(s($friendlyError), 'small text-danger mt-1');
    }
    echo html_writer::tag('td', $statushtml);

    $transport = (string) ($source->metadata['transport'] ?? '');
    $transportLabel = '-';
    $transportClass = 'badge-light';
    $transportNote = '';
    if (!empty($source->metadata['pending_placeholder'])) {
        $transportlabels = [
            'text' => 'Text Extract',
            'url' => 'URL Fetch',
        ];
        $transportLabel = $transportlabels[$source->type]
            ?? (\local_daliwidget\file_url_helper::is_enabled() ? 'Signed URL first' : 'File Upload');
    } else if ($transport === 'signed_url') {
        $transportLabel = 'Signed URL';
        $transportClass = 'badge-info';
    } else if ($transport === 'binary_upload') {
        $transportLabel = 'File Upload';
        $transportClass = 'badge-secondary';
        $fallbackReason = trim((string) ($source->metadata['fallback_reason'] ?? ''));
        if ($fallbackReason !== '') {
            $transportLabel = 'File Upload (Fallback)';
            $transportNote = $fallbackReason;
        }
    } else if ($source->type === 'text') {
        $transportLabel = 'Inline Text';
    } else if ($source->type === 'url') {
        $transportLabel = 'URL Fetch';
    } else if ($source->type === 'youtube') {
        $transportLabel = 'Remote URL';
    }
    $transportHtml = html_writer::tag('span', $transportLabel, ['class' => 'badge ' . $transportClass]);
    if ($transportNote !== '') {
        $transportHtml .= html_writer::div(s($transportNote), 'small text-muted mt-1');
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
    } else if (empty($source->metadata['pending_placeholder']) &&
            ($transport === 'signed_url' || !empty($source->metadata['fallback_reason']))) {
        $transportHtml .= html_writer::div(
            html_writer::tag('span', 'Signed URL debug unavailable', ['class' => 'small text-muted']),
            'mt-1'
        );
    }
    echo html_writer::tag('td', $transportHtml);

    // Last Synced column - show timemodified from queue for activity-synced sources.
    $lastSyncedHtml = '-';
    $sourceActivityId = (int) ($source->metadata['activity']['id'] ?? 0);
    if ($sourceActivityId > 0 && isset($queuestatuses[$sourceActivityId]) && $queuestatuses[$sourceActivityId]->status === 'done') {
        $lastSyncedHtml = userdate($queuestatuses[$sourceActivityId]->timemodified, get_string('strftimedatetimeshort', 'langconfig'));
    } else if (!empty($source->is_placeholder) && !empty($source->status) && in_array($source->status, ['queued', 'processing'], true)) {
        $lastSyncedHtml = '<i class="fa fa-spinner fa-spin text-muted"></i>';
    }
    echo html_writer::tag('td', $lastSyncedHtml, ['class' => 'small text-muted']);

    echo html_writer::start_tag('td');
    if (empty($source->is_placeholder)) {
        echo html_writer::start_div('d-inline-flex align-items-center', ['style' => 'gap:8px; white-space:nowrap;']);
        if (($source->status ?? '') === 'failed') {
            $retrycmid = (int) ($source->metadata['activity']['id'] ?? 0);
            echo html_writer::tag('button', '<i class="fa fa-redo mr-1"></i>Retry', [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-primary',
                'data-action' => 'retry-source',
                'data-sourceid' => $source->id,
                'data-cmid' => $retrycmid > 0 ? $retrycmid : '',
                'title' => $retrycmid > 0
                    ? 'Retry from Moodle activity (Signed URL first, then fallback if needed)'
                    : 'Retry this source from the beginning',
                'style' => 'display:inline-flex;align-items:center;white-space:nowrap;',
            ]);
        }
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'class' => 'm-0',
            'onsubmit' => "return confirm('" . get_string('confirm_unsync_single', 'local_daliwidget') . "');",
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unsync']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sourceids[]', 'value' => $source->id]);
        echo html_writer::tag('button', '<i class="fa fa-unlink mr-1"></i>' . get_string('unsync_source', 'local_daliwidget'), [
            'type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger',
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    } else {
        echo html_writer::tag('span', 'Waiting for sync...', ['class' => 'text-muted small']);
    }
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
}

if (empty($allKnowledgeSources)) {
    echo html_writer::tag('tr', html_writer::tag('td', 'No knowledge sources yet.', ['colspan' => 7, 'class' => 'text-center text-muted py-4']));
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
if (knowledge_lifecycle::can_view_history()) {
    $history = knowledge_lifecycle::history($courseid);
    echo html_writer::tag('h3', get_string('unsynced_history', 'local_daliwidget'), ['class' => 'mt-4']);
    $table = new html_table();
    $table->head = [get_string('name'), get_string('source_type', 'local_daliwidget'), get_string('status'), get_string('user'), get_string('date')];
    foreach ($history as $record) {
        $table->data[] = [s($record->title), s($record->sourcetype), s($record->lifecyclestatus),
            fullname($DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST)), userdate($record->timeunsynced)];
    }
    echo html_writer::table($table);
}

// Add Source modal.
echo html_writer::start_div('modal fade', [
    'id' => 'add-source-modal',
    'tabindex' => '-1',
    'role' => 'dialog',
    'aria-labelledby' => 'add-source-modal-label',
    'aria-hidden' => 'true',
]);
echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
echo html_writer::start_div('modal-content');
echo html_writer::start_div('modal-header');
echo html_writer::tag('h5', 'Add Source', ['class' => 'modal-title', 'id' => 'add-source-modal-label']);
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
echo html_writer::tag('label', 'Source Type', ['for' => 'add-source-type']);
echo html_writer::select([
    'document' => 'Document',
    'text' => 'Custom Text',
    'youtube' => 'YouTube URL',
    'wordpress' => get_string('wordpress_url_source', 'local_daliwidget'),
    'video' => 'Video File',
    'audio' => 'Audio File',
    'scorm' => 'SCORM Package',
], 'add_source_type_selector', 'document', false, [
    'id' => 'add-source-type',
    'class' => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div('add-source-section', ['data-source-section' => 'document']);
echo html_writer::start_tag('form', ['method' => 'post', 'enctype' => 'multipart/form-data']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source_type', 'value' => 'document']);
echo html_writer::tag('p', 'Upload PDF, DOCX, TXT, or PPTX files.', ['class' => 'text-muted']);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'source_file', 'class' => 'form-control mb-3', 'accept' => '.pdf,.doc,.docx,.txt,.ppt,.pptx', 'required' => true]);
echo html_writer::tag('button', '<i class="fa fa-upload mr-1"></i>Upload Document', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::start_div('add-source-section d-none', ['data-source-section' => 'text']);
echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'customtext-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_text']);
echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Title', ['for' => 'text_title', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'text_title', 'id' => 'text_title', 'class' => 'form-control', 'required' => true]);
echo html_writer::end_div();
echo html_writer::start_div('form-group');
echo html_writer::tag('label', 'Content', ['for' => 'text_content', 'class' => 'form-label']);
echo html_writer::tag('textarea', '', ['name' => 'text_content', 'id' => 'text_content', 'class' => 'form-control', 'rows' => 8, 'required' => true]);
echo html_writer::end_div();
echo html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add Custom Text', ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

if ($wordpressconnections) {
    echo html_writer::start_div('add-source-section d-none', ['data-source-section' => 'wordpress']);
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

echo html_writer::start_div('add-source-section d-none', ['data-source-section' => 'youtube']);
echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'youtube-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_youtube']);
echo html_writer::tag('label', 'YouTube URL', ['for' => 'youtube_url', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'url', 'name' => 'youtube_url', 'id' => 'youtube_url', 'class' => 'form-control', 'placeholder' => 'https://youtube.com/watch?v=...', 'required' => true]);
echo html_writer::tag('label', get_string('video_additional_text', 'local_daliwidget'), ['for' => 'youtube_additional_text', 'class' => 'form-label mt-3']);
echo html_writer::tag('textarea', '', ['name' => 'additional_text', 'id' => 'youtube_additional_text', 'class' => 'form-control', 'rows' => 5]);
echo html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add YouTube Video', ['type' => 'submit', 'class' => 'btn btn-danger mt-3']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

foreach ([
    'video' => ['label' => 'Video File', 'button' => 'Upload Video', 'accept' => '.mp4,.mov,.mkv,.webm', 'class' => 'btn-primary'],
    'audio' => ['label' => 'Audio File', 'button' => 'Upload Audio', 'accept' => '.mp3,.wav,.m4a,.aac,.flac,.ogg', 'class' => 'btn-success'],
    'scorm' => ['label' => 'SCORM Package', 'button' => 'Upload SCORM', 'accept' => '.zip,.scorm', 'class' => 'btn-warning'],
] as $modalType => $config) {
    echo html_writer::start_div('add-source-section d-none', ['data-source-section' => $modalType]);
    echo html_writer::start_tag('form', ['method' => 'post', 'enctype' => 'multipart/form-data']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$wordpressconnectionjson = json_encode(array_map(static fn(array $connection): array => [
    'id' => (int) $connection['id'], 'site_url' => (string) $connection['site_url'],
], $wordpressconnections), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source_type', 'value' => $modalType]);
    echo html_writer::tag('p', 'Upload a ' . $config['label'] . ' to the knowledge base.', ['class' => 'text-muted']);
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
echo html_writer::end_div();

// JavaScript for AJAX handling
$ajaxurl = new moodle_url('/local/daliwidget/ajax.php');
$sesskey = sesskey();
$str_sync_queued = get_string('sync_queued', 'local_daliwidget');
$str_activity_synced = get_string('activity_synced', 'local_daliwidget');

echo <<<JAVASCRIPT
<script>
(function() {
    const ajaxUrl = '{$ajaxurl->out(false)}';
    const courseId = {$courseid};
    const sesskey = '{$sesskey}';
    
    function showNotification(message, type) {
        // Remove existing notifications
        document.querySelectorAll('.dali-notification').forEach(el => el.remove());
        
        const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-danger' : 'alert-warning');
        const notification = document.createElement('div');
        notification.className = 'alert ' + alertClass + ' dali-notification';
        notification.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        notification.innerHTML = message + '<button type="button" class="close" onclick="this.parentElement.remove()">&times;</button>';
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 5000);
    }
    
    function setButtonLoading(btn, loading) {
        if (loading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
            btn.disabled = true;
        } else {
            btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
            btn.disabled = false;
        }
    }
    
    const bulkStatusEl = document.getElementById('bulk-sync-status');
    const bulkQueueKey = 'dali.bulkSync.' + courseId;

    function setBulkStatus(message) {
        if (bulkStatusEl) {
            bulkStatusEl.textContent = message;
        }
    }

    function requestSync(cmid) {
        return fetch(ajaxUrl + '?action=sync&courseid=' + courseId + '&cmid=' + cmid + '&sesskey=' + sesskey).then(r => r.json());
    }

    async function runBulkSync(cmids, options) {
        if (!Array.isArray(cmids) || cmids.length === 0) {
            showNotification('No activities selected for sync.', 'warning');
            return;
        }

        const queue = {
            pending: [...cmids],
            done: [],
            failed: [],
            startedAt: Date.now(),
        };
        localStorage.setItem(bulkQueueKey, JSON.stringify(queue));

        for (let i = 0; i < queue.pending.length; i++) {
            const cmid = queue.pending[i];
            const row = document.querySelector('tr[data-cmid="' + cmid + '"]');
            const rowBtn = row ? row.querySelector('[data-action="sync"]') : null;
            if (rowBtn) {
                setButtonLoading(rowBtn, true);
            }

            setBulkStatus('Syncing ' + (i + 1) + '/' + queue.pending.length + '...');
            try {
                const data = await requestSync(cmid);
                if (data.success) {
                    queue.done.push(cmid);
                } else {
                    queue.failed.push({ cmid: cmid, error: data.error || 'Unknown error' });
                }
            } catch (error) {
                queue.failed.push({ cmid: cmid, error: error.message || 'Network error' });
            }

            localStorage.setItem(bulkQueueKey, JSON.stringify(queue));
            if (rowBtn) {
                setButtonLoading(rowBtn, false);
            }
        }

        localStorage.removeItem(bulkQueueKey);
        setBulkStatus('Bulk sync finished. Success: ' + queue.done.length + ', Failed: ' + queue.failed.length);

        if (queue.failed.length > 0) {
            showNotification('Bulk sync finished with ' + queue.failed.length + ' failures.', 'warning');
        } else {
            showNotification('Bulk sync completed successfully!', 'success');
        }

        if (options && options.reloadOnDone) {
            setTimeout(() => location.reload(), 1200);
        }
    }

    function selectedCmids() {
        return Array.from(document.querySelectorAll('.sync-select:checked')).map(el => el.value).filter(Boolean);
    }

    document.querySelectorAll('[data-action="sync"]').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const cmid = this.dataset.cmid;
            if (!cmid) {
                showNotification('Missing activity ID.', 'error');
                return;
            }

            setButtonLoading(this, true);
            try {
                const data = await requestSync(cmid);
                if (data.queued) {
                    showNotification('{$str_sync_queued}', 'info');
                    setTimeout(() => location.reload(), 1500);
                } else if (data.success) {
                    showNotification('{$str_activity_synced}', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification('Sync failed: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (err) {
                showNotification('Error: ' + err.message, 'error');
            } finally {
                setButtonLoading(this, false);
            }
        });
    });

    const selectAll = document.getElementById('sync-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.sync-select').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }

    const sourceSelectAll = document.getElementById('source-select-all');
    const sourceCheckboxes = Array.from(document.querySelectorAll('.source-select'));
    const unsyncSelectedBtn = document.getElementById('unsync-selected-btn');
    function updateUnsyncSelection() {
        const selectedCount = sourceCheckboxes.filter(cb => cb.checked).length;
        if (unsyncSelectedBtn) {
            unsyncSelectedBtn.disabled = selectedCount === 0;
            unsyncSelectedBtn.textContent = selectedCount > 0 ? 'Unsync selected (' + selectedCount + ')' : 'Unsync selected';
        }
        if (sourceSelectAll) {
            sourceSelectAll.checked = sourceCheckboxes.length > 0 && selectedCount === sourceCheckboxes.length;
            sourceSelectAll.indeterminate = selectedCount > 0 && selectedCount < sourceCheckboxes.length;
        }
    }
    if (sourceSelectAll) {
        sourceSelectAll.addEventListener('change', function() {
            sourceCheckboxes.forEach(cb => { cb.checked = this.checked; });
            updateUnsyncSelection();
        });
    }
    sourceCheckboxes.forEach(cb => cb.addEventListener('change', updateUnsyncSelection));
    updateUnsyncSelection();

    const syncSelectedBtn = document.getElementById('sync-selected-btn');
    if (syncSelectedBtn) {
        syncSelectedBtn.addEventListener('click', function() {
            runBulkSync(selectedCmids(), { reloadOnDone: true });
        });
    }

    const syncAllBtn = document.getElementById('sync-all-btn');
    if (syncAllBtn) {
        syncAllBtn.addEventListener('click', function() {
            const cmids = Array.from(document.querySelectorAll('.sync-select')).map(el => el.value).filter(Boolean);
            runBulkSync(cmids, { reloadOnDone: true });
        });
    }

    const queued = localStorage.getItem(bulkQueueKey);
    if (queued) {
        try {
            const parsed = JSON.parse(queued);
            if (parsed && Array.isArray(parsed.pending) && parsed.pending.length > 0) {
                const done = Array.isArray(parsed.done) ? parsed.done : [];
                const failed = Array.isArray(parsed.failed) ? parsed.failed.map(f => f.cmid || f) : [];
                const remaining = parsed.pending.filter(id => !done.includes(id) && !failed.includes(id));
                if (remaining.length > 0) {
                    setBulkStatus('Resuming previous bulk sync (' + remaining.length + ' remaining)...');
                    runBulkSync(remaining, { reloadOnDone: true });
                } else {
                    localStorage.removeItem(bulkQueueKey);
                }
            }
        } catch (e) {
            localStorage.removeItem(bulkQueueKey);
        }
    }

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
    const sourceTypeSelect = document.getElementById('add-source-type');
    function updateAddSourceSections() {
        const selected = sourceTypeSelect ? sourceTypeSelect.value : 'document';
        document.querySelectorAll('[data-source-section]').forEach(section => {
            section.classList.toggle('d-none', section.dataset.sourceSection !== selected);
        });
    }
    if (sourceTypeSelect) {
        sourceTypeSelect.addEventListener('change', updateAddSourceSections);
        updateAddSourceSections();
    }
    
    // Handle Custom Text form
    const textForm = document.querySelector('#customtext-form');
    if (textForm) {
        textForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const title = formData.get('text_title');
            const content = (formData.get('text_content') || '').toString().trim();
            const submitBtn = this.querySelector('button[type="submit"]');

            if (content.length < 200) {
                showNotification('Custom text minimal 200 karakter agar bisa diproses knowledge engine.', 'warning');
                return;
            }
            
            setButtonLoading(submitBtn, true);
            
            // Use POST for long content
            fetch(ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=addtext&courseid=' + courseId + '&title=' + encodeURIComponent(title) + '&content=' + encodeURIComponent(content) + '&sesskey=' + sesskey
            })
                .then(r => r.json())
                .then(data => {
                    setButtonLoading(submitBtn, false);
                    if (data.success) {
                        showNotification('Custom text added successfully!', 'success');
                        this.reset();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    setButtonLoading(submitBtn, false);
                    showNotification('Error: ' + err.message, 'error');
                });
        });
    }
    
    // Handle Add YouTube form
    const youtubeForm = document.querySelector('#youtube-form');
    if (youtubeForm) {
        youtubeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const url = (formData.get('youtube_url') || '').toString().trim();
            const submitBtn = this.querySelector('button[type="submit"]');

            if (!/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i.test(url)) {
                showNotification('URL harus format YouTube yang valid (youtube.com atau youtu.be).', 'warning');
                return;
            }
            
            setButtonLoading(submitBtn, true);
            
            const additionalText = (formData.get('additional_text') || '').toString().trim();
            fetch(ajaxUrl + '?action=addyoutube&courseid=' + courseId + '&url=' + encodeURIComponent(url) + '&additional_text=' + encodeURIComponent(additionalText) + '&sesskey=' + sesskey)
                .then(r => r.json())
                .then(data => {
                    setButtonLoading(submitBtn, false);
                    if (data.success) {
                        showNotification('YouTube video added successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    setButtonLoading(submitBtn, false);
                    showNotification('Error: ' + err.message, 'error');
                });
        });
    }
    

    document.querySelectorAll('[data-action="retry-source"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sourceid = this.dataset.sourceid;
            const cmid = this.dataset.cmid || '';
            if (!sourceid) {
                showNotification('Missing source ID.', 'error');
                return;
            }

            setButtonLoading(this, true);

            let requestUrl = ajaxUrl + '?action=retrysource&courseid=' + courseId + '&sourceid=' + sourceid + '&sesskey=' + sesskey;
            if (cmid) {
                requestUrl += '&cmid=' + encodeURIComponent(cmid);
            }

            fetch(requestUrl)
                .then(r => r.json())
                .then(data => {
                    setButtonLoading(this, false);
                    if (data.success) {
                        if (data.retry_mode === 'moodle_activity') {
                            if (data.queued) {
                                showNotification('Retry started from Moodle activity. Signed URL will be tried first.', 'info');
                            } else {
                                showNotification('Retry finished from Moodle activity.', 'success');
                            }
                        } else {
                            showNotification('Retry started. Source is processing again.', 'info');
                        }
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showNotification('Retry failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    setButtonLoading(this, false);
                    showNotification('Error: ' + err.message, 'error');
                });
        });
    });
})();
</script>
JAVASCRIPT;

echo $OUTPUT->footer();


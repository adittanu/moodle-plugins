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
    
    if ($action === 'add_url') {
        $url = required_param('url', PARAM_URL);
        $name = required_param('name', PARAM_TEXT);
        $result = $apiClient->addUrlSource($url, $name, $courseMetadata);
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $result['error'] ?? 'Failed to add URL', null, \core\output\notification::NOTIFY_ERROR);
        }
    }
    
    if ($action === 'add_youtube') {
        $url = required_param('youtube_url', PARAM_URL);
        $result = $apiClient->addYoutubeSource($url, null, $courseMetadata);
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $result['error'] ?? 'Failed to add YouTube video', null, \core\output\notification::NOTIFY_ERROR);
        }
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
            $result = $apiClient->uploadDocument($_FILES[$fileField], null, $courseMetadata, $sourceType);
            if ($result['success'] ?? false) {
                redirect($PAGE->url, get_string('source_added', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
            } else {
                redirect($PAGE->url, $result['error'] ?? 'Failed to upload file', null, \core\output\notification::NOTIFY_ERROR);
            }
        }
    }
    
    if ($action === 'delete') {
        $sourceid = required_param('sourceid', PARAM_INT);
        $result = $apiClient->deleteSource($sourceid);
        if ($result['success'] ?? false) {
            redirect($PAGE->url, get_string('source_deleted', 'local_daliwidget'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($PAGE->url, $result['error'] ?? 'Failed to delete source', null, \core\output\notification::NOTIFY_ERROR);
        }
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

// Stats cards
$totalDocs = count($documents) + count($textSources);
echo html_writer::start_div('row mb-4');
echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', $totalDocs, ['class' => 'text-primary mb-0']);
echo html_writer::tag('small', get_string('documents', 'local_daliwidget'), ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body text-center');
echo html_writer::tag('h3', count($links), ['class' => 'text-success mb-0']);
echo html_writer::tag('small', get_string('web_links', 'local_daliwidget'), ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::start_div('card');
echo html_writer::start_div('card-body text-center');
$mediaCount = count($youtubeVideos) + count($videoFiles) + count($audioSources) + count($scormSources);
echo html_writer::tag('h3', $mediaCount, ['class' => 'text-danger mb-0']);
echo html_writer::tag('small', 'Media Sources', ['class' => 'text-muted']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Activity Auto-Sync Section with teal color
echo html_writer::start_div('card mb-4', ['style' => 'border-color: #14b8a6;']);
echo html_writer::start_div('card-header d-flex justify-content-between align-items-center', ['style' => 'background-color: #14b8a6; color: white;']);
echo html_writer::tag('h5', '<i class="fa fa-sync-alt mr-2"></i>' . get_string('activity_sync', 'local_daliwidget'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::tag('p', get_string('activity_sync_desc', 'local_daliwidget'), ['class' => 'text-muted mb-3']);
echo html_writer::start_div('d-flex flex-wrap align-items-center gap-2 mb-3');
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
    if ($cm->uservisible && in_array($cm->modname, ['page', 'book', 'assign', 'quiz', 'scorm', 'forum', 'lesson', 'folder', 'resource', 'url', 'label'])) {
        $activities[] = $cm;
        $activitiesbyid[$cm->id] = $cm;

        $preview = [
            'category' => 'text',
            'transport' => 'Text Extract',
        ];

        if ($cm->modname === 'url') {
            $preview = [
                'category' => 'url',
                'transport' => 'URL Fetch',
            ];
        } else if (in_array($cm->modname, ['page', 'book', 'assign', 'quiz', 'forum', 'lesson', 'folder', 'label'], true)) {
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
    $pendingType = match ($preview['category'] ?? 'text') {
        'documents' => 'document',
        'video' => 'video',
        'audio' => 'audio',
        'scorm' => 'scorm',
        'url' => 'url',
        default => 'text',
    };

    $pendingKnowledgeRows[] = (object) [
        'id' => 0,
        'type' => $pendingType,
        'title' => $cm->name,
        'status' => $row->status,
        'error_message' => $row->errormessage,
        'metadata' => [
            'transport' => match ($pendingType) {
                'text' => 'inline_text',
                'url' => 'url_fetch',
                default => \local_daliwidget\file_url_helper::is_enabled() ? 'signed_url' : 'binary_upload',
            },
            'pending_placeholder' => true,
        ],
        'is_placeholder' => true,
    ];
}

// Activity table
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
        case 'quiz': $icon = '<i class="fa fa-question-circle text-warning"></i>'; break;
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

echo html_writer::start_div('card mt-4');
echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
echo html_writer::tag('h5', 'Knowledge Sources', ['class' => 'mb-0']);
echo html_writer::tag('button', '<i class="fa fa-plus mr-1"></i>Add Source', [
    'type' => 'button',
    'class' => 'btn btn-primary btn-sm',
    'data-toggle' => 'modal',
    'data-bs-toggle' => 'modal',
    'data-target' => '#add-source-modal',
    'data-bs-target' => '#add-source-modal',
]);
echo html_writer::end_div();

echo html_writer::start_div('card-body p-0');
echo html_writer::start_tag('table', ['class' => 'table table-striped mb-0']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
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

    $typeConfig = match ($source->type) {
        'document' => ['label' => 'Document', 'icon' => 'file-alt', 'class' => 'text-primary'],
        'text' => ['label' => 'Custom Text', 'icon' => 'file-text', 'class' => 'text-info'],
        'url' => ['label' => 'Link', 'icon' => 'link', 'class' => 'text-info'],
        'video' => ['label' => 'Video', 'icon' => 'film', 'class' => 'text-primary'],
        'audio' => ['label' => 'Audio', 'icon' => 'music', 'class' => 'text-success'],
        'scorm' => ['label' => 'SCORM', 'icon' => 'archive', 'class' => 'text-warning'],
        default => ['label' => 'YouTube', 'icon' => 'youtube', 'class' => 'text-danger'],
    };

    echo html_writer::tag('td', '<i class="fa fa-' . $typeConfig['icon'] . ' ' . $typeConfig['class'] . ' mr-1"></i>' . s($source->title));
    echo html_writer::tag('td', html_writer::tag('span', $typeConfig['label'], ['class' => 'badge badge-light']));

    $statusclass = match ($source->status) {
        'ready' => 'badge-success',
        'failed' => 'badge-danger',
        'queued' => 'badge-secondary',
        default => 'badge-warning',
    };
    $statusicon = match ($source->status) {
        'ready' => '<i class="fa fa-check mr-1"></i>',
        'failed' => '<i class="fa fa-times mr-1"></i>',
        'queued' => '<i class="fa fa-clock mr-1"></i>',
        default => '<i class="fa fa-spinner fa-spin mr-1"></i>',
    };
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
        $transportLabel = match ($source->type) {
            'text' => 'Text Extract',
            'url' => 'URL Fetch',
            default => \local_daliwidget\file_url_helper::is_enabled() ? 'Signed URL first' : 'File Upload',
        };
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
        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'sourceid' => $source->id, 'sesskey' => sesskey()]);
        echo html_writer::link($deleteurl, '<i class="fa fa-trash"></i>', [
            'class' => 'btn btn-sm btn-outline-danger',
            'onclick' => "return confirm('" . get_string('confirm_delete', 'local_daliwidget') . "');"
        ]);
        echo html_writer::end_div();
    } else {
        echo html_writer::tag('span', 'Waiting for sync...', ['class' => 'text-muted small']);
    }
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
}

if (empty($allKnowledgeSources)) {
    echo html_writer::tag('tr', html_writer::tag('td', 'No knowledge sources yet.', ['colspan' => 6, 'class' => 'text-center text-muted py-4']));
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();
echo html_writer::end_div();

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

echo html_writer::start_div('add-source-section d-none', ['data-source-section' => 'youtube']);
echo html_writer::start_tag('form', ['method' => 'post', 'id' => 'youtube-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add_youtube']);
echo html_writer::tag('label', 'YouTube URL', ['for' => 'youtube_url', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'url', 'name' => 'youtube_url', 'id' => 'youtube_url', 'class' => 'form-control', 'placeholder' => 'https://youtube.com/watch?v=...', 'required' => true]);
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
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source_type', 'value' => $modalType]);
    echo html_writer::tag('p', 'Upload a ' . $config['label'] . ' to the knowledge base.', ['class' => 'text-muted']);
    echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'source_file', 'class' => 'form-control mb-3', 'accept' => $config['accept'], 'required' => true]);
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
            
            fetch(ajaxUrl + '?action=addyoutube&courseid=' + courseId + '&url=' + encodeURIComponent(url) + '&sesskey=' + sesskey)
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
    
    // Handle Delete buttons
    document.querySelectorAll('a[href*="action=delete"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this source?')) return;
            
            const url = new URL(this.href, location.origin);
            const sourceid = url.searchParams.get('sourceid');
            
            setButtonLoading(this, true);
            
            fetch(ajaxUrl + '?action=delete&courseid=' + courseId + '&sourceid=' + sourceid + '&sesskey=' + sesskey)
                .then(r => r.json())
                .then(data => {
                    setButtonLoading(this, false);
                    if (data.success) {
                        showNotification('Source deleted successfully!', 'success');
                        this.closest('tr')?.remove();
                    } else {
                        showNotification('Delete failed: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    setButtonLoading(this, false);
                    showNotification('Error: ' + err.message, 'error');
                });
        });
    });

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


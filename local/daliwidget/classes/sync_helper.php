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
 * Sync helper for extracting and uploading activity content to Dali Knowledge Base.
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/file_url_helper.php');

use local_daliwidget\task\sync_activity_task;

/**
 * Helper class for syncing activity content to Dali Knowledge Base.
 */
class sync_helper {

    /**
     * Resolve configured sync mode with a safe default.
     *
     * @return string
     */
    public static function get_sync_mode(): string {
        $syncmode = get_config('local_daliwidget', 'sync_mode') ?: 'async';
        if (!in_array($syncmode, ['sync', 'async'], true)) {
            return 'async';
        }

        return $syncmode;
    }

    /**
     * Execute sync immediately or queue it depending on plugin settings.
     *
     * @param \stdClass $course Course object
     * @param int $cmid Course module ID
     * @return array
     */
    public static function sync_or_queue(\stdClass $course, int $cmid): array {
        if (self::get_sync_mode() === 'async') {
            return self::queue_activity($course->id, $cmid);
        }

        return self::sync_activity($course, $cmid);
    }

    /**
     * Queue an activity sync, avoiding duplicate active jobs.
     *
     * @param int $courseid Course ID
     * @param int $cmid Course module ID
     * @return array
     */
    public static function queue_activity(int $courseid, int $cmid): array {
        global $DB;

        $existing = $DB->get_record_sql(
            "SELECT id, status
               FROM {local_daliwidget_sync_queue}
              WHERE courseid = :courseid
                AND cmid = :cmid
                AND status IN (:queued, :processing)
           ORDER BY id DESC",
            [
                'courseid' => $courseid,
                'cmid' => $cmid,
                'queued' => 'queued',
                'processing' => 'processing',
            ],
            IGNORE_MULTIPLE
        );

        if ($existing) {
            return [
                'success' => true,
                'queued' => true,
                'alreadyQueued' => true,
                'queueid' => (int) $existing->id,
                'status' => $existing->status,
            ];
        }

        $now = time();
        $queueid = $DB->insert_record('local_daliwidget_sync_queue', (object)[
            'courseid' => $courseid,
            'cmid' => $cmid,
            'status' => 'queued',
            'errormessage' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $task = new sync_activity_task();
        $task->set_custom_data([
            'courseid' => $courseid,
            'cmid' => $cmid,
            'queueid' => $queueid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);

        return [
            'success' => true,
            'queued' => true,
            'alreadyQueued' => false,
            'queueid' => (int) $queueid,
            'status' => 'queued',
        ];
    }

    /**
     * Sync a single course module to the Dali Knowledge Base.
     *
     * @param \stdClass $course Course object
     * @param int $cmid Course module ID
     * @return array Result array with 'success' key and optional 'error'
     */
    public static function sync_activity(\stdClass $course, int $cmid): array {
        global $DB;

        $apiClient = new api_client();
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cmid);

        $courseMetadata = api_client::buildMoodleMetadata($course, $cminfo);
        $content = '';
        $title = $cminfo->name;

        switch ($cminfo->modname) {
            case 'page':
                $page = $DB->get_record('page', ['id' => $cminfo->instance]);
                if ($page) {
                    $content = strip_tags($page->content);
                    $title = $page->name;
                }
                break;

            case 'book':
                $chapters = $DB->get_records('book_chapters', ['bookid' => $cminfo->instance], 'pagenum');
                foreach ($chapters as $chapter) {
                    $content .= "\n\n## " . $chapter->title . "\n" . strip_tags($chapter->content);
                }
                $book = $DB->get_record('book', ['id' => $cminfo->instance]);
                if ($book) {
                    $title = $book->name;
                }
                break;

            case 'assign':
                $assign = $DB->get_record('assign', ['id' => $cminfo->instance]);
                if ($assign) {
                    $content = strip_tags($assign->intro);
                    $title = $assign->name;
                }
                break;

            case 'quiz':
                $quiz = $DB->get_record('quiz', ['id' => $cminfo->instance]);
                if ($quiz) {
                    $content = strip_tags($quiz->intro ?? '');
                    $title = $quiz->name;
                }
                break;

            case 'scorm':
                $scorm = $DB->get_record('scorm', ['id' => $cminfo->instance]);
                if ($scorm) {
                    $title = $scorm->name;
                    $files = get_file_storage()->get_area_files(
                        $cminfo->context->id, 'mod_scorm', 'package', 0,
                        'sortorder DESC, id ASC', false
                    );
                    if ($files) {
                        $file = reset($files);
                        return self::sync_stored_file($apiClient, $file, $title, $courseMetadata, 'scorm');
                    }
                    $content = strip_tags($scorm->intro ?? '');
                }
                break;

            case 'forum':
                $forum = $DB->get_record('forum', ['id' => $cminfo->instance]);
                if ($forum) {
                    $content = strip_tags($forum->intro ?? '');
                    $title = $forum->name;
                }
                break;

            case 'lesson':
                $lesson = $DB->get_record('lesson', ['id' => $cminfo->instance]);
                if ($lesson) {
                    $content = strip_tags($lesson->intro ?? '');
                    $title = $lesson->name;
                }
                break;

            case 'folder':
                $folder = $DB->get_record('folder', ['id' => $cminfo->instance]);
                if ($folder) {
                    $content = strip_tags($folder->intro ?? '');
                    $title = $folder->name;
                }
                break;

            case 'label':
                $label = $DB->get_record('label', ['id' => $cminfo->instance]);
                if ($label) {
                    $content = strip_tags($label->intro);
                    $title = $label->name ?: 'Text and Media';
                }
                break;

            case 'resource':
                $fs = get_file_storage();
                $files = $fs->get_area_files(
                    $cminfo->context->id, 'mod_resource', 'content', 0,
                    'sortorder DESC, id ASC', false
                );
                if ($files) {
                    $file = reset($files);
                    $filename = $file->get_filename();
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    $docTypes = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx'];
                    $urlTypes = ['mp4', 'mov', 'mkv', 'webm', 'mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg', 'scorm', 'zip'];

                    if (in_array($extension, $docTypes)) {
                        return self::sync_stored_file($apiClient, $file, $cminfo->name, $courseMetadata, 'document');

                    } else if (in_array($extension, $urlTypes, true)) {
                        $sourceType = 'video';
                        if (in_array($extension, ['mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg'], true)) {
                            $sourceType = 'audio';
                        } else if (in_array($extension, ['scorm', 'zip'], true)) {
                            $sourceType = 'scorm';
                        }

                        return self::sync_stored_file($apiClient, $file, $cminfo->name, $courseMetadata, $sourceType);

                    } else {
                        $content = "File: " . $filename . "\nType: " . $file->get_mimetype();
                        return $apiClient->addTextSource($cminfo->name . ' (File Info)', $content, $courseMetadata);
                    }
                }
                break;

            case 'url':
                $urlmod = $DB->get_record('url', ['id' => $cminfo->instance]);
                if ($urlmod) {
                    return $apiClient->addUrlSource($urlmod->externalurl, $urlmod->name, $courseMetadata);
                }
                break;
        }

        if (!empty($content)) {
            return $apiClient->addTextSource($title, $content, $courseMetadata);
        }

        return ['success' => false, 'error' => 'No content to sync for this activity'];
    }

    /**
     * Sync a Moodle stored file using signed URLs with upload fallback.
     *
     * @param api_client $apiClient
     * @param \stored_file $file
     * @param string $title
     * @param array $metadata
     * @param string $sourceType
     * @return array
     */
    private static function sync_stored_file(
        api_client $apiClient,
        \stored_file $file,
        string $title,
        array $metadata,
        string $sourceType
    ): array {
        if (file_url_helper::is_enabled()) {
            try {
                $signedUrl = file_url_helper::generate_signed_file_url($file);
                $signedMetadata = array_merge($metadata, [
                    'transport' => 'signed_url',
                    'original_name' => $file->get_filename(),
                    'mime_type' => $file->get_mimetype(),
                    'size' => $file->get_filesize(),
                    'debug_signed_url' => $signedUrl,
                ]);

                $result = $apiClient->addRemoteFileSource($sourceType, $signedUrl, $title, $signedMetadata);
                $status = strtolower((string) ($result['data']['status'] ?? ''));
                if (!empty($result['success']) && $status !== 'failed') {
                    return $result;
                }

                $fallbackReason = self::describe_remote_failure($result);
                self::cleanup_failed_remote_source($apiClient, $result);
                return self::upload_stored_file(
                    $apiClient,
                    $file,
                    $title,
                    $metadata,
                    $sourceType,
                    $fallbackReason,
                    $signedUrl
                );
            } catch (\Throwable $e) {
                // Fall through to binary upload when signed URL generation fails.
                return self::upload_stored_file($apiClient, $file, $title, $metadata, $sourceType, $e->getMessage());
            }
        }

        return self::upload_stored_file($apiClient, $file, $title, $metadata, $sourceType);
    }

    /**
     * Upload a Moodle stored file to the API using the legacy binary path.
     *
     * @param api_client $apiClient
     * @param \stored_file $file
     * @param string $title
     * @param array $metadata
     * @param string $sourceType
     * @return array
     */
    private static function upload_stored_file(
        api_client $apiClient,
        \stored_file $file,
        string $title,
        array $metadata,
        string $sourceType,
        ?string $fallbackReason = null,
        ?string $debugSignedUrl = null
    ): array {
        $filename = $file->get_filename();
        $tempdir = make_temp_directory('daliwidget');
        $temppath = $tempdir . '/' . $filename;
        $file->copy_content_to($temppath);

        $fileData = [
            'tmp_name' => $temppath,
            'name' => $filename,
            'type' => $file->get_mimetype(),
        ];

        $legacyMetadata = array_merge($metadata, ['transport' => 'binary_upload']);
        if ($fallbackReason !== null && trim($fallbackReason) !== '') {
            $legacyMetadata['fallback_from'] = 'signed_url';
            $legacyMetadata['fallback_reason'] = trim($fallbackReason);
            if ($debugSignedUrl !== null && trim($debugSignedUrl) !== '') {
                $legacyMetadata['debug_signed_url'] = trim($debugSignedUrl);
            }
        }

        $result = $apiClient->uploadDocument($fileData, $title, $legacyMetadata, $sourceType);
        @unlink($temppath);

        if (!empty($result['success']) && !empty($legacyMetadata['fallback_reason'])) {
            $result['warning'] = 'Signed URL fallback: ' . $legacyMetadata['fallback_reason'];
        }

        return $result;
    }

    /**
     * Delete a failed remote source before falling back to the legacy upload path.
     *
     * @param api_client $apiClient
     * @param array $result
     * @return void
     */
    private static function cleanup_failed_remote_source(api_client $apiClient, array $result): void {
        $sourceid = (int) ($result['data']['id'] ?? 0);
        $status = strtolower((string) ($result['data']['status'] ?? ''));

        if ($sourceid <= 0 || $status !== 'failed') {
            return;
        }

        try {
            $apiClient->deleteSource($sourceid);
        } catch (\Throwable $e) {
            // Ignore cleanup failures so the legacy fallback can still proceed.
        }
    }

    /**
     * Extract a human-readable reason from a failed remote source attempt.
     *
     * @param array $result
     * @return string
     */
    private static function describe_remote_failure(array $result): string {
        $status = strtolower((string) ($result['data']['status'] ?? ''));
        $message = trim((string) (
            $result['data']['error_message']
            ?? $result['error']
            ?? $result['message']
            ?? ''
        ));

        if ($message !== '') {
            return $message;
        }

        if ($status === 'failed') {
            return 'Remote source was created but ingestion failed.';
        }

        return 'Remote source request failed before ingestion completed.';
    }
}

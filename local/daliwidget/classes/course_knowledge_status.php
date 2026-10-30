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
 * Course-level AI knowledge ingestion status.
 *
 * @package     local_daliwidget
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

/**
 * Computes whether a course is ingested into the Dali AI knowledge base.
 *
 * The status combines the knowledge sources stored by the Dali API for the
 * course with the Moodle activities that can be synced.
 */
class course_knowledge_status {

    /** @var string[] Course modules whose content can be ingested. */
    public const SYNCABLE_MODULES = [
        'page',
        'book',
        'assign',
        'scorm',
        'forum',
        'lesson',
        'folder',
        'resource',
        'label',
    ];

    /**
     * Return the ingestion status for a course.
     *
     * @param int $courseid Course ID.
     * @param api_client|null $client Optional API client (useful for tests).
     * @param bool $usecache Whether the cached status may be used.
     * @return array
     */
    public static function get(int $courseid, ?api_client $client = null, bool $usecache = true): array {
        $cache = \cache::make('local_daliwidget', 'course_status');
        $cachekey = self::cache_key($courseid);

        if ($usecache) {
            $cached = $cache->get($cachekey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $status = self::build($courseid, $client ?? new api_client());
        if ($usecache && $status['state'] !== 'unavailable') {
            $cache->set($cachekey, $status);
        }

        return $status;
    }

    /**
     * Remove the cached status for a course.
     *
     * @param int $courseid Course ID.
     * @return void
     */
    public static function invalidate(int $courseid): void {
        \cache::make('local_daliwidget', 'course_status')->delete(self::cache_key($courseid));
    }

    /**
     * Build the status from the API response and the course structure.
     *
     * @param int $courseid Course ID.
     * @param api_client $client API client.
     * @return array
     */
    private static function build(int $courseid, api_client $client): array {
        $status = [
            'courseid' => $courseid,
            'state' => 'unavailable',
            'ready' => 0,
            'processing' => 0,
            'failed' => 0,
            'chunks' => 0,
            'activities_total' => 0,
            'activities_synced' => 0,
            'unsynced_cmids' => [],
            'last_synced' => 0,
            'error' => null,
        ];

        $response = $client->getSources($courseid);
        if (!isset($response['data']) || !is_array($response['data'])) {
            $status['error'] = (string) ($response['error'] ?? 'No response from the Dali API');
            return $status;
        }

        $syncedactivityids = [];
        foreach ($response['data'] as $source) {
            $sourcecourseid = (int) ($source['metadata']['course']['id'] ?? 0);
            if ($sourcecourseid > 0 && $sourcecourseid !== $courseid) {
                continue;
            }

            $sourcestatus = strtolower((string) ($source['status'] ?? ''));
            if ($sourcestatus === 'ready' || $sourcestatus === 'done') {
                $status['ready']++;
                $status['chunks'] += (int) ($source['chunks_count'] ?? 0);
                $status['last_synced'] = max(
                    $status['last_synced'],
                    self::timestamp($source['updated_at'] ?? $source['created_at'] ?? null)
                );

                $activityid = (int) ($source['metadata']['activity']['id'] ?? 0);
                if ($activityid > 0) {
                    $syncedactivityids[$activityid] = true;
                }
            } else if ($sourcestatus === 'failed') {
                $status['failed']++;
            } else {
                $status['processing']++;
            }
        }

        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !empty($cm->deletioninprogress)) {
                continue;
            }
            if (!in_array($cm->modname, self::SYNCABLE_MODULES, true)) {
                continue;
            }

            $status['activities_total']++;
            if (isset($syncedactivityids[$cm->id])) {
                $status['activities_synced']++;
            } else {
                $status['unsynced_cmids'][] = (int) $cm->id;
            }
        }

        if ($status['failed'] > 0) {
            $status['state'] = 'attention';
        } else if ($status['processing'] > 0) {
            $status['state'] = 'processing';
        } else if ($status['ready'] > 0) {
            $incomplete = $status['activities_total'] > 0
                && $status['activities_synced'] < $status['activities_total'];
            $status['state'] = $incomplete ? 'partial' : 'ready';
        } else {
            $status['state'] = 'empty';
        }

        return $status;
    }

    /**
     * Build localized labels for the status.
     *
     * @param array $status Status returned by get().
     * @return array
     */
    public static function present(array $status): array {
        $a = (object) [
            'ready' => $status['ready'],
            'processing' => $status['processing'],
            'failed' => $status['failed'],
            'chunks' => $status['chunks'],
            'synced' => $status['activities_synced'],
            'total' => $status['activities_total'],
            'unsynced' => count($status['unsynced_cmids']),
        ];

        $state = $status['state'];
        $detail = get_string('course_status_' . $state . '_detail', 'local_daliwidget', $a);

        if ($status['last_synced'] > 0) {
            $detail .= ' ' . get_string(
                'course_status_last_sync',
                'local_daliwidget',
                userdate($status['last_synced'], get_string('strftimedatetimeshort', 'langconfig'))
            );
        }

        return [
            'state' => $state,
            'label' => get_string('course_status_' . $state, 'local_daliwidget', $a),
            'detail' => $detail,
            'sync_label' => $a->unsynced > 0
                ? get_string('course_status_sync_missing', 'local_daliwidget', $a)
                : '',
        ];
    }

    /**
     * Build the cache key for a course.
     *
     * @param int $courseid Course ID.
     * @return string
     */
    private static function cache_key(int $courseid): string {
        return 'course_' . $courseid;
    }

    /**
     * Convert an API timestamp to a Unix timestamp.
     *
     * @param mixed $value Timestamp value.
     * @return int
     */
    private static function timestamp($value): int {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = is_string($value) ? strtotime($value) : false;
        return $timestamp === false ? 0 : $timestamp;
    }
}

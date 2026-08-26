<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_daliwidget;

/**
 * Manages knowledge source removal and immutable Unsynced History.
 *
 * @package    local_daliwidget
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class knowledge_lifecycle {
    /**
     * Unsync independently selected sources.
     *
     * @param array $sources Selected API source records.
     * @param int|null $courseid Active course, or null for Global Knowledge.
     * @param int $userid User performing the action.
     * @param callable $remove Retrieval removal callback receiving the source ID.
     * @return array Aggregate and per-item outcomes.
     */
    public static function unsync(array $sources, ?int $courseid, int $userid, callable $remove): array {
        global $DB;
        $outcomes = [];
        foreach ($sources as $source) {
            $sourcecourseid = (int) ($source['metadata']['course']['id'] ?? 0);
            $fileid = (int) ($source['metadata']['moodle_file_id'] ?? 0);
            $validscope = $courseid === null ? $sourcecourseid === 0 : $sourcecourseid === $courseid;
            if (!$validscope || empty($source['id'])) {
                $outcomes[] = ['id' => (int) ($source['id'] ?? 0), 'success' => false, 'error' => 'Source is not in this scope'];
                continue;
            }

            try {

                $result = $remove((int) $source['id']);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }
            $success = !empty($result['success']);
            $status = strtolower((string) ($source['status'] ?? ''));
            $lifecycle = $success
                ? (in_array($status, ['ready', 'done'], true) ? 'removed' : 'cancelled_before_ready')
                : 'failed';
            $DB->insert_record('local_daliwidget_unsynced', (object) [
                'sourceulid' => (string) ($source['ulid'] ?? ''),
                'moodlefileid' => $fileid,
                'title' => (string) ($source['title'] ?? ''),
                'sourcetype' => (string) ($source['type'] ?? 'document'),
                'scope' => $sourcecourseid > 0 ? 'course' : 'global',
                'courseid' => $sourcecourseid,
                'lifecyclestatus' => $lifecycle,
                'timesynced' => self::timestamp($source['created_at'] ?? null),
                'timeunsynced' => time(),
                'userid' => $userid,
            ]);
            $outcomes[] = ['id' => (int) $source['id'], 'success' => $success, 'error' => $result['error'] ?? null];
        }

        return [
            'completed' => count(array_filter($outcomes, static fn(array $item): bool => $item['success'])),
            'failed' => count(array_filter($outcomes, static fn(array $item): bool => !$item['success'])),
            'outcomes' => $outcomes,
        ];
    }

    /** @return array Unsynced History, newest first. */
    public static function history(?int $courseid = null): array {
        global $DB;
        return array_values($DB->get_records(
            'local_daliwidget_unsynced',
            $courseid === null ? [] : ['courseid' => $courseid],
            'timeunsynced DESC, id DESC'
        ));
    }

    /** Site administrators are the sole Unsynced History readers. */
    public static function can_view_history(?int $userid = null): bool {
        return is_siteadmin($userid);
    }

    private static function timestamp($value): int {
        $timestamp = is_string($value) ? strtotime($value) : false;
        return $timestamp === false ? 0 : $timestamp;
    }
}

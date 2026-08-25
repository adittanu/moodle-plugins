<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_daliwidget;

/**
 * Synced Moodle File lifecycle tests.
 *
 * @package    local_daliwidget
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_daliwidget\knowledge_lifecycle
 * @group      local_daliwidget
 */
class knowledge_lifecycle_test extends \advanced_testcase {
    public function test_bulk_unsync_preserves_file_records_and_continues_after_failure(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_course::instance($course->id);
        $fs = get_file_storage();
        $first = $fs->create_file_from_string($this->file_record($context->id, 11, 'first.txt'), 'first');
        $second = $fs->create_file_from_string($this->file_record($context->id, 12, 'second.txt'), 'second');
        $sources = [
            $this->source(1, 'one', $course->id, $first->get_id(), 'ready'),
            $this->source(2, 'two', $course->id, $second->get_id(), 'processing'),
        ];

        $result = knowledge_lifecycle::unsync($sources, $course->id, $user->id, static fn(int $id): array =>
            $id === 1 ? ['success' => true] : ['success' => false, 'error' => 'remote failed']);

        $this->assertSame(1, $result['completed']);
        $this->assertSame(1, $result['failed']);
        $this->assertNotFalse($fs->get_file_by_id($first->get_id()));
        $this->assertNotFalse($fs->get_file_by_id($second->get_id()));
        $history = $DB->get_records('local_daliwidget_unsynced', [], 'id');
        $this->assertCount(2, $history);
        $this->assertSame('removed', reset($history)->lifecyclestatus);
        $this->assertSame('failed', end($history)->lifecyclestatus);
    }

    public function test_only_uploaded_moodle_files_in_active_course_are_accepted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $sources = [
            $this->source(1, 'valid', $course->id, 10, 'queued'),
            $this->source(2, 'other course', $course->id + 1, 11, 'ready'),
            ['id' => 3, 'ulid' => 'text', 'type' => 'text', 'title' => 'text', 'status' => 'ready', 'metadata' => ['course' => ['id' => $course->id]]],
        ];

        $result = knowledge_lifecycle::unsync($sources, $course->id, $user->id, static fn(): array => ['success' => true]);

        $this->assertSame(1, $result['completed']);
        $this->assertSame(2, $result['failed']);
    }

    public function test_history_filters_by_course(): void {
        global $DB;
        $this->resetAfterTest();
        foreach ([7, 8] as $courseid) {
            $DB->insert_record('local_daliwidget_unsynced', (object) [
                'sourceulid' => 'source-' . $courseid, 'moodlefileid' => $courseid,
                'title' => 'File', 'sourcetype' => 'document', 'scope' => 'course',
                'courseid' => $courseid, 'lifecyclestatus' => 'removed', 'timesynced' => 1,
                'timeunsynced' => 2, 'userid' => 2,
            ]);
        }
        $this->assertCount(1, knowledge_lifecycle::history(7));
        $this->assertCount(2, knowledge_lifecycle::history());
    }
    public function test_only_site_administrators_can_view_history(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(knowledge_lifecycle::can_view_history($user->id));
        $this->assertTrue(knowledge_lifecycle::can_view_history(get_admin()->id));
    }


    private function file_record(int $contextid, int $itemid, string $filename): array {
        return ['contextid' => $contextid, 'component' => 'local_daliwidget', 'filearea' => 'knowledge',
            'itemid' => $itemid, 'filepath' => '/', 'filename' => $filename];
    }

    private function source(int $id, string $title, int $courseid, int $fileid, string $status): array {
        return ['id' => $id, 'ulid' => 'source-' . $id, 'type' => 'document', 'title' => $title, 'status' => $status,
            'created_at' => '2026-08-24T00:00:00Z', 'metadata' => ['course' => ['id' => $courseid], 'moodle_file_id' => $fileid]];
    }
}

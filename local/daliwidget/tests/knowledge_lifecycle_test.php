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
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $context = \context_course::instance($course->id);
        $fs = get_file_storage();
        $first = $fs->create_file_from_string($this->file_record($context->id, 11, 'first.txt'), 'first');
        $second = $fs->create_file_from_string($this->file_record($context->id, 12, 'second.txt'), 'second');
        $sources = [
            $this->source(1, 'one', $course->id, $first->get_id(), 'ready'),
            $this->source(2, 'two', $course->id, $second->get_id(), 'processing'),
        ];

        $result = knowledge_lifecycle::unsync($sources, $course->id, static fn(int $id): array =>
            $id === 1 ? ['success' => true] : ['success' => false, 'error' => 'remote failed'], sesskey());

        $this->assertSame(1, $result['completed']);
        $this->assertSame(1, $result['failed']);
        $this->assertNotFalse($fs->get_file_by_id($first->get_id()));
        $this->assertNotFalse($fs->get_file_by_id($second->get_id()));
        $history = $DB->get_records('local_daliwidget_unsynced', [], 'id');
        $this->assertCount(2, $history);
        $this->assertSame('removed', reset($history)->lifecyclestatus);
        $this->assertSame('failed', end($history)->lifecyclestatus);
    }

    public function test_all_source_types_in_active_scope_are_recorded(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $sources = [
            $this->source(1, 'file', $course->id, 10, 'ready'),
            ['id' => 2, 'ulid' => 'text', 'type' => 'text', 'title' => 'text', 'status' => 'ready',
                'metadata' => ['course' => ['id' => $course->id]]],
            ['id' => 3, 'ulid' => 'youtube', 'type' => 'youtube', 'title' => 'video', 'status' => 'failed',
                'metadata' => ['course' => ['id' => $course->id]]],
            ['id' => 4, 'ulid' => 'other', 'type' => 'text', 'title' => 'other', 'status' => 'ready',
                'metadata' => ['course' => ['id' => $course->id + 1]]],
        ];

        $result = knowledge_lifecycle::unsync(
            $sources,
            $course->id,
            static fn(): array => ['success' => true],
            sesskey()
        );

        $this->assertSame(1, $result['completed']);
        $this->assertSame(3, $result['failed']);
        $this->assertCount(1, $DB->get_records('local_daliwidget_unsynced'));
    }

    public function test_processing_file_is_cancelled_before_ready(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $editor = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($editor->id, $course->id, 'editingteacher');
        $this->setUser($editor);

        $result = knowledge_lifecycle::unsync(
            [$this->source(1, 'queued', $course->id, 10, 'processing')],
            $course->id,
            static fn(): array => ['success' => true],
            sesskey()
        );

        $this->assertSame(1, $result['completed']);
        $this->assertSame('cancelled_before_ready', $DB->get_record('local_daliwidget_unsynced', [])->lifecyclestatus);
    }

    public function test_unsync_requires_valid_sesskey(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $editor = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($editor->id, $course->id, 'editingteacher');
        $this->setUser($editor);
        $this->expectException(\moodle_exception::class);

        knowledge_lifecycle::unsync([], $course->id, static fn(): array => ['success' => true], 'invalid');
    }

    public function test_unsync_requires_course_editor_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);

        knowledge_lifecycle::unsync([], $course->id, static fn(): array => ['success' => true], sesskey());
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

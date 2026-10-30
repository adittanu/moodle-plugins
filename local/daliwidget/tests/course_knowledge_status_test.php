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
 * Tests for course_knowledge_status.
 *
 * @package     local_daliwidget
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

/**
 * API client stub that returns a canned source list.
 */
class course_status_fake_api_client extends api_client {

    /** @var array */
    private $sources;

    /**
     * Constructor.
     *
     * @param array $sources Canned source list.
     */
    public function __construct(array $sources) {
        $this->sources = $sources;
    }

    /**
     * Return the canned sources.
     *
     * @param int|null $courseId Ignored.
     * @return array
     */
    public function getSources(?int $courseId = null): array {
        return ['success' => true, 'data' => $this->sources];
    }
}

/**
 * API client stub that simulates an unreachable Dali service.
 */
class course_status_failing_api_client extends api_client {

    /**
     * Return an error response.
     *
     * @param int|null $courseId Ignored.
     * @return array
     */
    public function getSources(?int $courseId = null): array {
        return ['success' => false, 'error' => 'Service unavailable'];
    }
}

/**
 * Tests for the course ingestion status calculation.
 *
 * @covers \local_daliwidget\course_knowledge_status
 */
class course_knowledge_status_test extends \advanced_testcase {

    /**
     * Create a course with the requested number of page activities.
     *
     * @param int $pages Number of page modules.
     * @return \stdClass
     */
    private function create_course_with_pages(int $pages = 1): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        for ($i = 1; $i <= $pages; $i++) {
            $generator->create_module('page', [
                'course' => $course->id,
                'name' => 'Page ' . $i,
                'content' => '<p>Content ' . $i . '</p>',
            ]);
        }
        rebuild_course_cache($course->id, true);

        return $course;
    }

    /**
     * Return the syncable course modules.
     *
     * @param \stdClass $course Course.
     * @return \cm_info[]
     */
    private function syncable_modules(\stdClass $course): array {
        $modules = [];
        foreach (get_fast_modinfo($course->id)->get_cms() as $cm) {
            if (in_array($cm->modname, course_knowledge_status::SYNCABLE_MODULES, true)) {
                $modules[] = $cm;
            }
        }

        return $modules;
    }

    /**
     * Build a knowledge source payload.
     *
     * @param int $courseid Course ID.
     * @param array $overrides Overrides.
     * @return array
     */
    private function source(int $courseid, array $overrides = []): array {
        return array_merge([
            'id' => 1,
            'type' => 'text',
            'title' => 'Source',
            'status' => 'ready',
            'chunks_count' => 5,
            'metadata' => ['course' => ['id' => $courseid]],
            'created_at' => '2026-10-01T10:00:00+00:00',
            'updated_at' => '2026-10-01T10:00:00+00:00',
        ], $overrides);
    }

    /**
     * A course without sources is reported as empty.
     */
    public function test_course_without_sources_is_empty(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(1);
        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client([]), false);

        $this->assertSame('empty', $status['state']);
        $this->assertSame(0, $status['ready']);
        $this->assertSame(count($this->syncable_modules($course)), $status['activities_total']);
        $this->assertCount($status['activities_total'], $status['unsynced_cmids']);
    }

    /**
     * A ready source linked to an activity marks the course as ready.
     */
    public function test_ready_source_marks_activity_as_ingested(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(1);
        $modules = $this->syncable_modules($course);
        $cmid = (int) reset($modules)->id;

        $source = $this->source($course->id, [
            'chunks_count' => 12,
            'metadata' => [
                'course' => ['id' => $course->id],
                'activity' => ['id' => $cmid],
            ],
        ]);

        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client([$source]), false);

        $this->assertSame('ready', $status['state']);
        $this->assertSame(1, $status['ready']);
        $this->assertSame(12, $status['chunks']);
        $this->assertSame(1, $status['activities_synced']);
        $this->assertSame([], $status['unsynced_cmids']);
    }

    /**
     * A course with some activities left is reported as partial.
     */
    public function test_partial_when_some_activities_are_missing(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(2);
        $modules = $this->syncable_modules($course);
        $cmid = (int) reset($modules)->id;

        $source = $this->source($course->id, [
            'metadata' => [
                'course' => ['id' => $course->id],
                'activity' => ['id' => $cmid],
            ],
        ]);

        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client([$source]), false);

        $this->assertSame('partial', $status['state']);
        $this->assertSame(1, $status['activities_synced']);
        $this->assertCount(1, $status['unsynced_cmids']);
    }

    /**
     * Sources still being processed keep the course in the processing state.
     */
    public function test_processing_source(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(1);
        $source = $this->source($course->id, ['status' => 'processing']);

        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client([$source]), false);

        $this->assertSame('processing', $status['state']);
        $this->assertSame(1, $status['processing']);
        $this->assertSame(0, $status['ready']);
    }

    /**
     * Failed sources require attention even when other sources are ready.
     */
    public function test_failed_source_needs_attention(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(1);
        $sources = [
            $this->source($course->id, ['status' => 'failed']),
            $this->source($course->id, ['id' => 2]),
        ];

        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client($sources), false);

        $this->assertSame('attention', $status['state']);
        $this->assertSame(1, $status['failed']);
        $this->assertSame(1, $status['ready']);
    }

    /**
     * Sources belonging to another course are ignored.
     */
    public function test_sources_from_other_courses_are_ignored(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(1);
        $source = $this->source($course->id, [
            'metadata' => ['course' => ['id' => $course->id + 1000]],
        ]);

        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client([$source]), false);

        $this->assertSame('empty', $status['state']);
        $this->assertSame(0, $status['ready']);
    }

    /**
     * An unreachable API is reported as unavailable.
     */
    public function test_unavailable_when_api_fails(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(1);
        $status = course_knowledge_status::get($course->id, new course_status_failing_api_client(), false);

        $this->assertSame('unavailable', $status['state']);
        $this->assertSame('Service unavailable', $status['error']);
    }

    /**
     * The presentation includes localized labels and the sync action count.
     */
    public function test_present_builds_labels(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_pages(2);
        $status = course_knowledge_status::get($course->id, new course_status_fake_api_client([]), false);
        $present = course_knowledge_status::present($status);

        $this->assertSame(
            get_string('course_status_empty', 'local_daliwidget'),
            $present['label']
        );
        $this->assertSame(
            get_string('course_status_sync_missing', 'local_daliwidget', (object) [
                'unsynced' => count($status['unsynced_cmids']),
            ]),
            $present['sync_label']
        );
    }
}

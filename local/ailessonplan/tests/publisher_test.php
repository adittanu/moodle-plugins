<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_ailessonplan;

/**
 * Publisher integration tests.
 *
 * @package local_ailessonplan
 * @covers \local_ailessonplan\publisher
 */
final class publisher_test extends \advanced_testcase {
    public function test_failed_activity_creation_rolls_back_before_fallback(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $urlmodule = $DB->get_record('modules', ['name' => 'url'], '*', MUST_EXIST);
        $DB->set_field('modules', 'name', 'url_disabled_test', ['id' => $urlmodule->id]);
        $plan = [
            'sections' => [[
                'week' => 1,
                'title' => 'Week 1',
                'activities' => [[
                    'mod' => 'url',
                    'title' => 'Broken URL',
                    'external_url' => 'not-a-url',
                ]],
            ]],
        ];

        $result = publisher::publish((object)[], $course, $plan);

        $this->assertSame(1, $result['activitiescreated']);
        $labelmoduleid = $DB->get_field('modules', 'id', ['name' => 'label'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('course_modules', [
            'course' => $course->id,
            'module' => $labelmoduleid,
            'id' => $result['cmid'],
        ]));
    }
}

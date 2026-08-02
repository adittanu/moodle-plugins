<?php
// This file is part of Moodle - http://moodle.org/.

namespace quiz_lightstats;

/** Calculator report contract tests. */
class calculator_test extends \advanced_testcase {
    public function test_empty_quiz_has_empty_statistics(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $result = (new calculator($DB))->calculate($quiz);
        $this->assertSame(0, $result['summary']['allcount']);
        $this->assertSame([], $result['questions']);
    }
}

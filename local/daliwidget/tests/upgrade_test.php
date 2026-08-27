<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_daliwidget;

/**
 * Upgrade tests.
 *
 * @package    local_daliwidget
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_daliwidget
 */
class upgrade_test extends \advanced_testcase {
    /**
     * @dataProvider knowledge_access_mode_migration_provider
     */
    public function test_legacy_knowledge_access_mode_is_migrated_without_overwriting_canonical_mode(
        $canonicalmode,
        $strictcoursemode,
        string $expected
    ): void {
        require_once(__DIR__ . '/../db/upgrade.php');

        $this->assertSame($expected, local_daliwidget_migrated_knowledge_access_mode(
            $canonicalmode,
            $strictcoursemode
        ));
    }

    public static function knowledge_access_mode_migration_provider(): array {
        return [
            'site-wide remains site-wide' => ['site_wide', '1', 'site_wide'],
            'course-scoped remains course-scoped' => ['course_scoped', '0', 'course_scoped'],
            'legacy strict enabled becomes course-scoped' => [false, '1', 'course_scoped'],
            'legacy strict disabled becomes site-wide' => [false, '0', 'site_wide'],
            'missing configuration defaults to course-scoped' => [false, false, 'course_scoped'],
        ];
    }
}

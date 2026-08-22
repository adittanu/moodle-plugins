<?php
// This file is part of Moodle - http://moodle.org/.

namespace report_dalireport;

/**
 * API client payload validation and CSV neutralization tests.
 *
 * @package    report_dalireport
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \report_dalireport\api_client
 * @group      report_dalireport
 */
class api_client_test extends \advanced_testcase {

    public function test_csv_formula_values_are_neutralized(): void {
        $this->assertSame("\t=SUM(A1)", api_client::neutralize_csv_value('=SUM(A1)'));
        $this->assertSame("\t+cmd", api_client::neutralize_csv_value('+cmd'));
        $this->assertSame("\t-1+1", api_client::neutralize_csv_value('-1+1'));
        $this->assertSame("\t@SUM", api_client::neutralize_csv_value('@SUM'));
        $this->assertSame('safe value', api_client::neutralize_csv_value('safe value'));
        $this->assertSame('', api_client::neutralize_csv_value(''));
        // Already-prefixed values get double-prefixed (idempotent-safe).
        $this->assertSame("\t\t=SUM(A1)", api_client::neutralize_csv_value("\t=SUM(A1)"));
    }

    public function test_validate_payload_rejects_missing_top_level_keys(): void {
        // Use reflection to call private static method.
        $method = new \ReflectionMethod(api_client::class, 'validate_payload');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Malformed report: missing summary');
        $method->invoke(null, ['sessions' => [], 'filterOptions' => [], 'responseQuality' => [], 'activity' => [], 'topTopics' => []]);
    }

    public function test_validate_payload_rejects_missing_sessions_pagination(): void {
        $method = new \ReflectionMethod(api_client::class, 'validate_payload');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Malformed report: missing sessions pagination');
        $method->invoke(null, [
            'summary' => ['tokenUsage' => []],
            'sessions' => [],
            'filterOptions' => [],
            'responseQuality' => [],
            'activity' => [],
            'topTopics' => [],
        ]);
    }

    public function test_validate_payload_rejects_missing_token_usage(): void {
        $method = new \ReflectionMethod(api_client::class, 'validate_payload');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Malformed report: missing token usage');
        $method->invoke(null, [
            'summary' => [],
            'sessions' => ['data' => [], 'total' => 0, 'per_page' => 20],
            'filterOptions' => [],
            'responseQuality' => [],
            'activity' => [],
            'topTopics' => [],
        ]);
    }

    public function test_validate_payload_accepts_complete_structure(): void {
        $method = new \ReflectionMethod(api_client::class, 'validate_payload');

        // Should not throw.
        $method->invoke(null, [
            'summary' => ['tokenUsage' => ['totalTokens' => 0]],
            'sessions' => ['data' => [], 'total' => 0, 'per_page' => 20],
            'filterOptions' => ['courses' => [], 'roles' => [], 'statuses' => []],
            'responseQuality' => [],
            'activity' => [],
            'topTopics' => [],
        ]);
        $this->assertTrue(true);
    }
}

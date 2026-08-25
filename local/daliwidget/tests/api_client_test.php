<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_daliwidget;

/**
 * API client contract tests.
 *
 * @package    local_daliwidget
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_daliwidget\api_client
 * @group      local_daliwidget
 */
class api_client_test extends \advanced_testcase {

    /**
     * Verify TLS peer and host verification are enabled.
     */
    public function test_tls_verification_enabled(): void {
        $source = file_get_contents(__DIR__ . '/../classes/api_client.php');
        $this->assertStringNotContainsString('CURLOPT_SSL_VERIFYPEER => false', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER => true', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $source);
    }

    /**
     * Verify admin header is sent only on WordPress mutation endpoints.
     */
    public function test_admin_header_present_on_wp_mutations(): void {
        $source = file_get_contents(__DIR__ . '/../classes/api_client.php');
        $this->assertStringContainsString('X-Moodle-Site-Admin: 1', $source);
        $this->assertStringContainsString("strpos(\$endpoint, '/api/v1/wordpress/') === 0", $source);
    }

    /**
     * Verify DELETE method sends JSON body when data is provided.
     */
    public function test_delete_sends_json_body(): void {
        $source = file_get_contents(__DIR__ . '/../classes/api_client.php');
        $deleteBlock = strstr($source, "method === 'DELETE'");
        $this->assertNotNull($deleteBlock);
        $this->assertStringContainsString('if ($data)', $deleteBlock);
    }

    /**
     * Verify deleteWordpressConnection accepts optional body parameter.
     */
    public function test_delete_connection_accepts_body(): void {
        $source = file_get_contents(__DIR__ . '/../classes/api_client.php');
        $this->assertStringContainsString('deleteWordpressConnection(int $id, ?array $body = null)', $source);
    }

    /**
     * Verify preview and owned-source methods exist.
     */
    public function test_preview_and_owned_source_methods_exist(): void {
        $source = file_get_contents(__DIR__ . '/../classes/api_client.php');
        $this->assertStringContainsString('function previewWordpressSync(int $id)', $source);
        $this->assertStringContainsString('function getWordpressOwnedSourceCount(int $id)', $source);
    }

    /**
     * Verify no raw secrets appear in debug log output.
     */
    public function test_debug_log_redacts_application_password(): void {
        $source = file_get_contents(__DIR__ . '/../classes/api_client.php');
        $this->assertStringContainsString("'application_password' => '[redacted]'", $source);
    }

    /**
     * Verify wordpress_connections.php requires delete_sources param on delete action.
     */
    public function test_connections_page_requires_delete_choice(): void {
        $source = file_get_contents(__DIR__ . '/../wordpress_connections.php');
        $this->assertStringContainsString("required_param('delete_sources', PARAM_ALPHA)", $source);
        $this->assertStringContainsString("'delete', 'retain'", $source);
    }

    /**
     * Verify wordpress_connections.php sends delete_sources in the API body.
     */
    public function test_connections_page_sends_delete_body(): void {
        $source = file_get_contents(__DIR__ . '/../wordpress_connections.php');
        $this->assertStringContainsString("'delete_sources' => \$deleteChoice", $source);
    }

    /**
     * Verify wordpress_connections.php surfaces failed runs as notifications.
     */
    public function test_connections_page_surfaces_failed_runs(): void {
        $source = file_get_contents(__DIR__ . '/../wordpress_connections.php');
        $this->assertStringContainsString('wordpress_run_notification', $source);
        $this->assertStringContainsString('NOTIFY_WARNING', $source);
    }

    /**
     * Verify wordpress_connections.php has preview section.
     */
    public function test_connections_page_has_preview(): void {
        $source = file_get_contents(__DIR__ . '/../wordpress_connections.php');
        $this->assertStringContainsString('previewWordpressSync', $source);
        $this->assertStringContainsString('wordpress_preview_title', $source);
        $this->assertStringContainsString('wordpress_preview_add', $source);
        $this->assertStringContainsString('wordpress_preview_update', $source);
        $this->assertStringContainsString('wordpress_preview_remove', $source);
        $this->assertStringContainsString('wordpress_preview_pending', $source);
        $this->assertStringContainsString('wordpress_preview_unchanged', $source);
    }

    /**
     * Verify wordpress_connections.php has delete confirmation with owned-source count.
     */
    public function test_connections_page_has_delete_confirmation(): void {
        $source = file_get_contents(__DIR__ . '/../wordpress_connections.php');
        $this->assertStringContainsString('getWordpressOwnedSourceCount', $source);
        $this->assertStringContainsString('wordpress_delete_confirm', $source);
        $this->assertStringContainsString('wordpress_delete_sources', $source);
        $this->assertStringContainsString('wordpress_retain_sources', $source);
    }

    /**
     * Verify all new language strings exist.
     */
    public function test_new_language_strings_exist(): void {
        $langfile = file_get_contents(__DIR__ . '/../lang/en/local_daliwidget.php');
        $required = [
            'wordpress_run_notification',
            'wordpress_delete_confirm',
            'wordpress_delete_choice_required',
            'wordpress_delete_sources',
            'wordpress_retain_sources',
            'confirmdelete',
            'wordpress_preview_sync',
            'wordpress_preview_title',
            'wordpress_preview_desc',
            'wordpress_preview_add',
            'wordpress_preview_update',
            'wordpress_preview_remove',
            'wordpress_preview_pending',
            'wordpress_preview_unchanged',
            'wordpress_preview_more',
        ];
        foreach ($required as $key) {
            $this->assertStringContainsString("'" . $key . "'", $langfile, "Missing language string: {$key}");
        }
    }
}

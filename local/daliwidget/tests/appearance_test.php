<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_daliwidget;

/**
 * Appearance override contract tests.
 *
 * @package    local_daliwidget
 * @copyright  2026 Dali AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_daliwidget\appearance
 * @group      local_daliwidget
 */
class appearance_test extends \advanced_testcase {
    public function test_supported_values_are_mapped_to_public_widget_settings(): void {
        $this->resetAfterTest();
        set_config('assistant_name', 'Campus Guide', 'local_daliwidget');
        set_config('welcome_message', 'Welcome to campus.', 'local_daliwidget');
        set_config('theme', 'dark', 'local_daliwidget');
        set_config('accent_color', '#12AbEF', 'local_daliwidget');
        set_config('border_radius', 'pill', 'local_daliwidget');

        $this->assertSame([
            'botName' => 'Campus Guide',
            'welcomeMessage' => 'Welcome to campus.',
            'accentColor' => '#12AbEF',
            'theme' => 'dark',
            'borderRadius' => 'pill',
        ], appearance::overrides());
    }

    public function test_empty_malformed_and_unsupported_values_are_omitted_independently(): void {
        $this->resetAfterTest();
        set_config('assistant_name', str_repeat('x', 61), 'local_daliwidget');
        set_config('welcome_message', '', 'local_daliwidget');
        set_config('theme', 'auto', 'local_daliwidget');
        set_config('accent_color', 'red', 'local_daliwidget');
        set_config('border_radius', '20px', 'local_daliwidget');

        $this->assertSame([], appearance::overrides());
    }

    public function test_only_supported_avatar_is_emitted(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_daliwidget',
            'filearea' => 'avatar',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'avatar.png',
            'mimetype' => 'image/png',
        ], 'png');

        $overrides = appearance::overrides();
        $this->assertStringContainsString('/local_daliwidget/avatar/0/avatar.png', $overrides['botAvatar']);

        $file->delete();
        $this->assertArrayNotHasKey('botAvatar', appearance::overrides());
    }

    public function test_invalid_avatar_type_and_size_are_omitted(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_daliwidget',
            'filearea' => 'avatar',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'avatar.svg',
            'mimetype' => 'image/svg+xml',
        ], '<svg/>');
        $this->assertArrayNotHasKey('botAvatar', appearance::overrides());

        get_file_storage()->delete_area_files($context->id, 'local_daliwidget', 'avatar');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_daliwidget',
            'filearea' => 'avatar',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'large.png',
            'mimetype' => 'image/png',
        ], str_repeat('x', 2 * 1024 * 1024 + 1));
        $this->assertArrayNotHasKey('botAvatar', appearance::overrides());
    }

    public function test_persona_configuration_accepts_supported_values(): void {
        $this->resetAfterTest();
        set_config('speaking_style', 'tutor', 'local_daliwidget');
        set_config('custom_instruction', 'Ask one guiding question at a time.', 'local_daliwidget');

        $this->assertSame('tutor', get_config('local_daliwidget', 'speaking_style'));
        $this->assertSame('Ask one guiding question at a time.', get_config('local_daliwidget', 'custom_instruction'));
    }

    public function test_fetch_signature_rejects_forged_and_expired_values(): void {
        $this->resetAfterTest();
        set_config('download_secret', 'test-secret', 'local_daliwidget');
        $signed = fetch_auth_helper::generate_for_user(7);

        $this->assertTrue(fetch_auth_helper::validate(7, $signed['expires'], $signed['sig']));
        $this->assertFalse(fetch_auth_helper::validate(7, $signed['expires'], str_repeat('a', 64)));
        $this->assertFalse(fetch_auth_helper::validate(7, time() - 1, $signed['sig']));
    }
}

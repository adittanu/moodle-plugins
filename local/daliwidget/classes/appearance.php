<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_daliwidget;

/** Validated public widget appearance overrides. */
class appearance {
    /** @return array<string, string> */
    public static function overrides(?\stdClass $user = null): array {
        $overrides = [];
        $values = [
            'botName' => self::text('assistant_name', 60),
            'welcomeMessage' => self::welcome_message($user),
            'accentColor' => self::color(),
            'theme' => self::choice('theme', ['light', 'dark']),
            'borderRadius' => self::choice('border_radius', ['sharp', 'rounded', 'pill']),
        ];

        foreach ($values as $key => $value) {
            if ($value !== null) {
                $overrides[$key] = $value;
            }
        }

        $avatar = self::avatar_url();
        if ($avatar !== null) {
            $overrides['botAvatar'] = $avatar;
        }

        return $overrides;
    }

    private static function text(string $name, int $maxlength): ?string {
        $value = trim((string) get_config('local_daliwidget', $name));
        return $value !== '' && \core_text::strlen($value) <= $maxlength ? $value : null;
    }
    private static function welcome_message(?\stdClass $user): ?string {
        $message = self::text('welcome_message', 500);
        if ($message === null || $user === null) {
            return $message;
        }

        return strtr($message, [
            '{fullname}' => fullname($user),
            '{firstname}' => $user->firstname ?? '',
        ]);
    }


    private static function color(): ?string {
        $value = trim((string) get_config('local_daliwidget', 'accent_color'));
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
    }

    /** @param string[] $allowed */
    private static function choice(string $name, array $allowed): ?string {
        $value = (string) get_config('local_daliwidget', $name);
        return in_array($value, $allowed, true) ? $value : null;
    }

    private static function avatar_url(): ?string {
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_daliwidget',
            'avatar',
            0,
            'id DESC',
            false
        );
        $file = reset($files);
        if (!$file || !in_array($file->get_mimetype(), ['image/png', 'image/jpeg', 'image/webp'], true)
                || $file->get_filesize() > 2 * 1024 * 1024) {
            return null;
        }

        // Validate actual image bytes to prevent MIME spoofing.
        $imageInfo = @getimagesizefromstring($file->get_content());
        if (!$imageInfo || !in_array($imageInfo['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_daliwidget',
            'avatar',
            0,
            '/',
            $file->get_filename(),
            false
        )->out(false);
    }
}

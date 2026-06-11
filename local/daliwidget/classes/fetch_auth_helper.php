<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper for short-lived signed auth tokens used by widget-side data fetches.
 */
class fetch_auth_helper {

    /**
     * Build a short-lived signed token for a Moodle user.
     *
     * @param int $userid
     * @param int $ttlseconds
     * @return array<string, int|string>|null
     */
    public static function generate_for_user(int $userid, int $ttlseconds = 600): ?array {
        $secret = self::get_secret();
        if ($secret === '') {
            return null;
        }

        $expires = time() + max(60, $ttlseconds);
        return [
            'signed_user_id' => $userid,
            'expires' => $expires,
            'sig' => self::build_signature($userid, $expires, $secret),
        ];
    }

    /**
     * Validate a signed token and return the target user id.
     *
     * @param int $userid
     * @param int $expires
     * @param string $signature
     * @return bool
     */
    public static function validate(int $userid, int $expires, string $signature): bool {
        $secret = self::get_secret();
        if ($secret === '' || $userid <= 0 || $expires < time()) {
            return false;
        }

        $expected = self::build_signature($userid, $expires, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Whether signed fetch auth is available.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return self::get_secret() !== '';
    }

    /**
     * Build HMAC signature.
     *
     * @param int $userid
     * @param int $expires
     * @param string $secret
     * @return string
     */
    private static function build_signature(int $userid, int $expires, string $secret): string {
        return hash_hmac('sha256', $userid . '|' . $expires, $secret);
    }

    /**
     * Resolve shared signing secret.
     *
     * @return string
     */
    private static function get_secret(): string {
        return trim((string) (get_config('local_daliwidget', 'download_secret') ?? ''));
    }
}

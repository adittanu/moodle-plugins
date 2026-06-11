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

namespace local_daliwidget;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper for generating signed URLs to Moodle stored files.
 */
class file_url_helper {

    /** @var int Default URL lifetime in seconds. */
    private const DEFAULT_TTL = 3600;

    /**
     * Whether signed URL delivery is configured and enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $enabled = (bool) get_config('local_daliwidget', 'signed_url_enabled');
        $secret = trim((string) (get_config('local_daliwidget', 'download_secret') ?? ''));

        return $enabled && $secret !== '';
    }

    /**
     * Generate a signed URL for a stored file.
     *
     * @param \stored_file $file
     * @param int|null $ttl
     * @return string
     */
    public static function generate_signed_file_url(\stored_file $file, ?int $ttl = null): string {
        $secret = self::get_secret();
        if ($secret === '') {
            throw new \moodle_exception('Signed URL secret is not configured for local_daliwidget.');
        }

        $expires = time() + max(60, (int) ($ttl ?? self::DEFAULT_TTL));
        $params = [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
            'expires' => $expires,
        ];

        $params['sig'] = self::sign_params($params, $secret);

        return (new \moodle_url(self::get_base_url() . '/local/daliwidget/file.php', $params))->out(false);
    }

    /**
     * Validate request parameters and return the matching stored file.
     *
     * @param array $params
     * @return \stored_file
     */
    public static function validate_request(array $params): \stored_file {
        $secret = self::get_secret();
        if ($secret === '') {
            throw new \moodle_exception('Signed URL secret is not configured for local_daliwidget.');
        }

        $expires = (int) ($params['expires'] ?? 0);
        if ($expires < time()) {
            throw new \moodle_exception('Signed file URL has expired.');
        }

        $provided = (string) ($params['sig'] ?? '');
        $expected = self::sign_params($params, $secret);
        if ($provided === '' || !hash_equals($expected, $provided)) {
            throw new \moodle_exception('Invalid file signature.');
        }

        $fs = get_file_storage();
        $file = $fs->get_file(
            (int) $params['contextid'],
            (string) $params['component'],
            (string) $params['filearea'],
            (int) $params['itemid'],
            (string) $params['filepath'],
            (string) $params['filename']
        );

        if (!$file || $file->is_directory()) {
            throw new \moodle_exception('Requested file was not found.');
        }

        return $file;
    }

    /**
     * Sign file URL parameters.
     *
     * @param array $params
     * @param string $secret
     * @return string
     */
    private static function sign_params(array $params, string $secret): string {
        $payload = implode('|', [
            (int) ($params['contextid'] ?? 0),
            (string) ($params['component'] ?? ''),
            (string) ($params['filearea'] ?? ''),
            (int) ($params['itemid'] ?? 0),
            (string) ($params['filepath'] ?? '/'),
            (string) ($params['filename'] ?? ''),
            (int) ($params['expires'] ?? 0),
        ]);

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Get signing secret.
     *
     * @return string
     */
    private static function get_secret(): string {
        return trim((string) (get_config('local_daliwidget', 'download_secret') ?? ''));
    }

    /**
     * Get the base URL used for signed links.
     *
     * @return string
     */
    private static function get_base_url(): string {
        global $CFG;

        $configured = trim((string) (get_config('local_daliwidget', 'signed_url_baseurl') ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) $CFG->wwwroot, '/');
    }
}

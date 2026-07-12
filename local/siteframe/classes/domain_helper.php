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
 * Domain validation helper for SiteFrame.
 *
 * @package     local_siteframe
 * @copyright   2026 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_siteframe;

defined('MOODLE_INTERNAL') || die();

class domain_helper {

    /**
     * Check if a URL's domain is in the allowed domains list.
     *
     * @param string $url The URL to check.
     * @return bool True if domain is allowed (or allowlist is empty).
     */
    public static function is_domain_allowed(string $url): bool {
        $allowed = get_config('local_siteframe', 'allowed_domains');
        if (empty($allowed)) {
            return true; // Empty allowlist = allow all.
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return false;
        }

        $hostname = strtolower($parsed['host']);
        $domains = array_filter(array_map('trim', explode("\n", $allowed)));

        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            if (empty($domain)) {
                continue;
            }
            // Exact match or subdomain match.
            if ($hostname === $domain || str_ends_with($hostname, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize and validate a URL.
     *
     * @param string $url The URL to sanitize.
     * @return string|false The sanitized URL, or false if invalid.
     */
    public static function sanitize_url(string $url) {
        $url = trim($url);
        if (empty($url)) {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        return $url;
    }

    /**
     * Get the configured sandbox attribute string.
     *
     * @return string The sandbox attribute value.
     */
    public static function get_sandbox_attr(): string {
        $flags = get_config('local_siteframe', 'sandbox_flags');
        if (empty($flags)) {
            return 'allow-scripts allow-same-origin allow-popups';
        }
        // Sanitize: only allow known sandbox tokens.
        $valid = [
            'allow-downloads', 'allow-forms', 'allow-modals', 'allow-orientation-lock',
            'allow-pointer-lock', 'allow-popups', 'allow-popups-to-escape-sandbox',
            'allow-presentation', 'allow-same-origin', 'allow-scripts',
            'allow-storage-access-by-user-activation', 'allow-top-navigation',
            'allow-top-navigation-by-user-activation',
        ];
        $tokens = array_filter(array_map('trim', explode(' ', $flags)), function($t) use ($valid) {
            return in_array($t, $valid);
        });
        return implode(' ', $tokens);
    }
}

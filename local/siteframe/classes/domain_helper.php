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
            $domain = self::normalize_domain($domain);
            if (empty($domain)) {
                continue;
            }
            // Exact match or subdomain match.
            if ($hostname === $domain || substr($hostname, -strlen('.' . $domain)) === '.' . $domain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a domain entry: strip scheme, path, port, whitespace.
     * Accepts "https://example.com:443/path" → "example.com".
     * ponytail: minimal normalize, no IDN/punycode conversion (add when intl ext required).
     *
     * @param string $domain Raw domain entry from config.
     * @return string Normalized hostname, or '' if invalid.
     */
    public static function normalize_domain(string $domain): string {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return '';
        }
        // If entry looks like a URL, parse it.
        if (strpos($domain, '://') !== false || strpos($domain, '/') !== false) {
            // parse_url needs scheme; prepend // for schemeless "host/path" entries.
            $candidate = strpos($domain, '://') === false ? '//' . $domain : $domain;
            $parsed = parse_url($candidate);
            $domain = $parsed['host'] ?? '';
        }
        return strtolower(trim($domain, " \t\n\r\0\x0B/"));
    }

    /**
     * Sanitize and validate a URL.
     *
     * @param string $url The URL to sanitize.
     * @return string|false The sanitized URL, or false if invalid.
     */
    public static function sanitize_url(string $url) {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        // Parse scheme + host manually — filter_var(FILTER_VALIDATE_URL) rejects
        // underscores in hostnames (common in Herd/Valet like dali_widget.test)
        // and some non-ASCII chars. We only need scheme + host to be present.
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
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

    /**
     * Sanitize a CSS dimension value for use in style attribute / width attribute.
     * Accepts: plain int (treated as px), "100%", "800px", "50vh".
     * Rejects: anything containing CSS-dangerous chars ( : ; ( ) { } / \ " ' ).
     * ponytail: whitelist regex over a stricter allow-list; no full CSS parser.
     *
     * @param string $value Raw dimension string.
     * @param string $default Fallback when invalid (default '100%').
     * @return string Sanitized dimension.
     */
    public static function sanitize_css_dimension(string $value, string $default = '100%'): string {
        $value = trim($value);
        if ($value === '') {
            return $default;
        }
        // Allow digits, optional unit suffix (% px vh vw em rem pt). No functions, no quotes.
        if (!preg_match('/^\d+(?:\.\d+)?(?:px|%|vh|vw|em|rem|pt)?$/', $value)) {
            return $default;
        }
        return $value;
    }
}

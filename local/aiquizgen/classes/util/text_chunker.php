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
 * Text chunker for AI Quiz Generator import mode.
 *
 * Splits a document into chunks at question boundaries so each AI request
 * receives complete questions together with their answer keys.
 *
 * @package    local_aiquizgen
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\util;

defined('MOODLE_INTERNAL') || die();

class text_chunker {
    /** Question boundary pattern used by the import documents. */
    private const QUESTION_PATTERN = '/(?im)^[ \t]*(?:soal|question|pertanyaan)\s+\d+\s*[.):\-]/u';

    /**
     * Split text into chunks that contain complete questions.
     *
     * @param string $text Full document text.
     * @param int $targetchars Approximate maximum chunk size in bytes.
     * @return array<int, string>
     */
    public static function chunk_questions(string $text, int $targetchars = 4000): array {
        $text = self::normalize($text);
        if ($text === '') {
            return [];
        }

        $targetchars = max(1000, $targetchars);
        $text = self::strip_preamble($text);

        preg_match_all(self::QUESTION_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);
        $starts = [];
        foreach (($matches[0] ?? []) as $match) {
            $starts[] = (int)$match[1];
        }

        // Not a question document (or only one question): fall back to size-based
        // chunking. The AI prompt is responsible for skipping partial fragments.
        if (count($starts) < 2) {
            return self::chunk_by_size($text, $targetchars);
        }

        $chunks = [];
        $chunkstart = 0;
        $questioncount = count($starts);

        for ($i = 1; $i < $questioncount; $i++) {
            $boundary = $starts[$i];
            if (($boundary - $chunkstart) < $targetchars) {
                continue;
            }

            $chunk = trim(substr($text, $chunkstart, $boundary - $chunkstart));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
            $chunkstart = $boundary;
        }

        $last = trim(substr($text, $chunkstart));
        if ($last !== '') {
            $chunks[] = $last;
        }

        return $chunks;
    }

    /**
     * Normalize whitespace while preserving line breaks.
     *
     * @param string $text
     * @return string
     */
    public static function normalize(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Drop the cover page and table of contents before the first question.
     *
     * @param string $text
     * @return string
     */
    private static function strip_preamble(string $text): string {
        if (preg_match(self::QUESTION_PATTERN, $text, $match, PREG_OFFSET_CAPTURE)) {
            $position = (int)$match[0][1];
            if ($position > 0) {
                return ltrim(substr($text, $position));
            }
        }
        return $text;
    }

    /**
     * Fallback chunking for documents without recognizable question boundaries.
     *
     * @param string $text
     * @param int $targetchars
     * @return array<int, string>
     */
    private static function chunk_by_size(string $text, int $targetchars): array {
        $chunks = [];
        $length = strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $size = min($targetchars, $length - $offset);

            // mb_strcut() keeps multi-byte characters intact when a chunk has to be
            // cut at an arbitrary position (for example, text without newlines).
            if (function_exists('mb_strcut')) {
                $piece = mb_strcut($text, $offset, $size, 'UTF-8');
            } else {
                $piece = substr($text, $offset, $size);
            }
            $size = strlen($piece);

            if (($offset + $size) < $length) {
                $break = strrpos($piece, "\n\n");
                if ($break === false || $break < (int)($targetchars * 0.5)) {
                    $paragraphbreak = $break;
                    $break = strrpos($piece, "\n");
                    if ($break === false || $break < (int)($targetchars * 0.5)) {
                        $break = $paragraphbreak;
                    }
                }
                if ($break !== false && $break > (int)($targetchars * 0.5)) {
                    $piece = substr($piece, 0, $break);
                    $size = strlen($piece);
                }
            }

            $piece = trim($piece);
            if ($piece !== '') {
                $chunks[] = $piece;
            }

            $offset += max(1, $size);
        }

        return $chunks;
    }
}

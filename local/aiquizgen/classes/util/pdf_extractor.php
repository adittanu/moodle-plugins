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
 * PDF text extractor utility for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizgen\util;

defined('MOODLE_INTERNAL') || die();

// Load composer autoload for PDF parser library
if (file_exists(__DIR__ . '/../../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
}

class pdf_extractor {
    
    /**
     * Extract text from PDF file.
     *
     * @param string $filepath Full path to PDF file
     * @param int $maxchars Maximum characters to extract (0 = no limit)
     * @return string Extracted text
     * @throws \Exception If extraction fails
     */
    public static function extract_text($filepath, $maxchars = 50000) {
        if (!file_exists($filepath)) {
            throw new \Exception('PDF file not found: ' . $filepath);
        }

        $text = false;

        // Try pdftotext command first (fastest and most reliable)
        $text = self::extract_with_pdftotext($filepath);
        
        if ($text === false) {
            // Try smalot/pdfparser library (handles compressed PDFs)
            $text = self::extract_with_pdfparser($filepath);
        }
        
        if ($text === false) {
            // Fallback to basic PHP extraction (limited capabilities)
            $text = self::extract_with_php($filepath);
        }

        if ($text === false || $text === null || trim((string)$text) === '') {
            throw new \Exception('Could not extract text from PDF. The file may be image-based (scanned) or use unsupported compression.');
        }

        // Clean and limit text
        $text = self::clean_text($text);
        
        if ($maxchars > 0 && strlen($text) > $maxchars) {
            $text = substr($text, 0, $maxchars) . '... [truncated]';
        }

        return $text;
    }

    /**
     * Extract text using pdftotext command-line tool.
     *
     * @param string $filepath Path to PDF file
     * @return string|false Extracted text or false on failure
     */
    private static function extract_with_pdftotext($filepath) {
        // Check if pdftotext is available
        $command = 'pdftotext';
        exec('which pdftotext 2>/dev/null', $output, $returncode);
        
        if ($returncode !== 0) {
            return false; // pdftotext not available
        }

        // Create temp file for output
        $tempfile = tempnam(sys_get_temp_dir(), 'pdf_') . '.txt';
        
        // Execute pdftotext
        $escapedpath = escapeshellarg($filepath);
        $escapedtemp = escapeshellarg($tempfile);
        exec("pdftotext -enc UTF-8 $escapedpath $escapedtemp 2>&1", $output, $returncode);
        
        if ($returncode === 0 && file_exists($tempfile)) {
            $text = file_get_contents($tempfile);
            unlink($tempfile);
            return $text;
        }
        
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        
        return false;
    }

    /**
     * Extract text using smalot/pdfparser library.
     * This handles compressed and complex PDFs.
     *
     * @param string $filepath Path to PDF file
     * @return string|false Extracted text or false on failure
     */
    private static function extract_with_pdfparser($filepath) {
        // Check if library is available
        if (!class_exists('\Smalot\PdfParser\Parser')) {
            return false;
        }
        
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filepath);
            $text = $pdf->getText();
            
            return !empty(trim($text)) ? $text : false;
        } catch (\Exception $e) {
            // Parser failed
            return false;
        }
    }

    /**
     * Extract text using basic PHP PDF parsing.
     * This is a simple fallback that works for basic PDFs.
     *
     * @param string $filepath Path to PDF file
     * @return string|false Extracted text or false on failure
     */
    private static function extract_with_php($filepath) {
        $content = file_get_contents($filepath);
        
        if ($content === false) {
            return false;
        }

        // Check if PDF is encrypted
        if (preg_match('/\/Encrypt\s+/', $content)) {
            return false; // Encrypted PDFs cannot be extracted
        }

        // Check for compression/encoding that we cannot handle
        if (preg_match('/\/Filter\s*\/(?:FlateDecode|ASCII85Decode|LZWDecode|JBIG2Decode|DCTDecode|CCITTFaxDecode)/', $content)) {
            // PDF uses compression we cannot decode with basic PHP
            // Try pdftotext or return false
            return false;
        }

        // Basic PDF text extraction using regex
        $text = '';
        
        // Extract text between BT and ET markers (text objects in PDF)
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Extract text from Tj and TJ operators
                if (preg_match_all('/\[(.*?)\]/s', $match, $textmatches)) {
                    foreach ($textmatches[1] as $textmatch) {
                        // Clean the text match - remove hex codes and escape sequences
                        $cleaned = self::clean_pdf_text($textmatch);
                        if (!empty($cleaned) && strlen($cleaned) >= 3) {
                            $text .= $cleaned . ' ';
                        }
                    }
                }
                if (preg_match_all('/\(([^)]*)\)/s', $match, $textmatches)) {
                    foreach ($textmatches[1] as $textmatch) {
                        $cleaned = self::clean_pdf_text($textmatch);
                        if (!empty($cleaned) && strlen($cleaned) >= 3) {
                            $text .= $cleaned . ' ';
                        }
                    }
                }
            }
        }

        $trimmedtext = trim($text ?? '');
        
        // Validate extracted text - check if it's mostly readable
        if (!empty($trimmedtext)) {
            $totalchars = strlen($trimmedtext);
            // Count readable characters (letters, numbers, common punctuation)
            $readablechars = 0;
            for ($i = 0; $i < $totalchars; $i++) {
                $char = $trimmedtext[$i];
                if (ord($char) >= 32 && ord($char) <= 126) {
                    $readablechars++;
                }
            }
            $readableratio = $readablechars / $totalchars;
            
            // If less than 60% readable, it's probably garbage
            if ($readableratio < 0.6) {
                return false;
            }
        }
        
        return !empty($trimmedtext) ? $text : false;
    }
    
    /**
     * Clean individual PDF text string.
     */
    private static function clean_pdf_text($text) {
        // Remove hex escape sequences like #xx
        $text = preg_replace('/#[0-9a-fA-F]{2}/', '', $text);
        
        // Remove PDF escape sequences
        $text = str_replace(['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'], [' ', ' ', ' ', '', '(', ')'], $text);
        
        // Remove non-printable characters except space
        $text = preg_replace('/[\x00-\x08\x0E-\x1F\x7F]/', '', $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove content that looks like PDF syntax
        $text = preg_replace('/\b\d+\s+\d+\s+obj\b/i', '', $text);
        $text = preg_replace('/\bend(obj|stream|obj)\b/i', '', $text);
        
        return trim($text);
    }

    /**
     * Clean extracted text.
     *
     * @param string|null $text Raw extracted text
     * @return string Cleaned text
     */
    private static function clean_text($text) {
        // Handle null or non-string input
        if ($text === null || $text === false) {
            return '';
        }
        
        // Ensure string type
        $text = (string)$text;
        
        // Remove PDF metadata and artifacts
        $artifacts = [
            'ReportLab PDF Library',
            'ReportLab',
            '/Producer',
            '/Title',
            '/Author', 
            '/Creator',
            '/CreationDate',
            'www.reportlab.com',
            'PDF-1.',
            'endstream',
            'endobj',
            'stream',
            '<<',
            '>>',
        ];
        
        foreach ($artifacts as $artifact) {
            $text = str_ireplace($artifact, '', $text);
        }
        
        // Remove hex strings (common in PDF)
        $text = preg_replace('/\b[0-9a-fA-F]{16,}\b/', '', $text);
        
        // Remove dates in PDF format
        $text = preg_replace('/D:\d{14}/', '', $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? $text;
        
        // Trim - ensure $text is not null
        $text = trim($text ?? '');
        
        return $text;
    }

    /**
     * Get PDF info (pages, size, etc).
     *
     * @param string $filepath Path to PDF file
     * @return array PDF information
     */
    public static function get_pdf_info($filepath) {
        $info = [
            'filesize' => filesize($filepath),
            'pages' => 0,
            'title' => '',
        ];

        $content = file_get_contents($filepath);
        
        // Try to get page count
        if (preg_match('/\/N\s+(\d+)/', $content, $matches)) {
            $info['pages'] = (int)$matches[1];
        } else if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
            $info['pages'] = (int)$matches[1];
        }

        // Try to get title
        if (preg_match('/\/Title\s*\((.*?)\)/', $content, $matches)) {
            $info['title'] = $matches[1];
        }

        return $info;
    }
}

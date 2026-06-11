<?php
/**
 * Test PDF extractor functionality.
 * 
 * Usage: Place a test.pdf in this directory and access this file via browser.
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/util/pdf_extractor.php');

require_login();
require_capability('local/aiquizgen:generate', context_system::instance());

$testpdf = __DIR__ . '/test.pdf';

echo "<!DOCTYPE html><html><head><title>PDF Extractor Test</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; background: #e8f5e9; padding: 10px; margin: 10px 0; }
.error { color: red; background: #ffebee; padding: 10px; margin: 10px 0; }
.info { color: blue; background: #e3f2fd; padding: 10px; margin: 10px 0; }
.extracted { background: #f5f5f5; padding: 15px; border: 1px solid #ddd; white-space: pre-wrap; }
</style>
</head><body>";

echo "<h1>PDF Text Extractor Test</h1>";

// Check if pdftotext is available
echo "<h2>1. Check pdftotext availability</h2>";
exec('which pdftotext 2>/dev/null', $output, $returncode);
if ($returncode === 0) {
    echo "<div class='success'>✓ pdftotext is available: " . htmlspecialchars($output[0]) . "</div>";
} else {
    echo "<div class='info'>ℹ pdftotext not found. Will use PHP fallback method.</div>";
}

// Check if test PDF exists
echo "<h2>2. Check test PDF</h2>";
if (file_exists($testpdf)) {
    $filesize = filesize($testpdf);
    echo "<div class='success'>✓ Test PDF found: " . htmlspecialchars($testpdf) . " (" . number_format($filesize) . " bytes)</div>";
    
    // Get PDF info
    try {
        $info = \local_aiquizgen\util\pdf_extractor::get_pdf_info($testpdf);
        echo "<div class='info'>";
        echo "<strong>PDF Info:</strong><br>";
        echo "- File size: " . number_format($info['filesize']) . " bytes<br>";
        echo "- Pages: " . $info['pages'] . "<br>";
        echo "- Title: " . htmlspecialchars($info['title']) . "<br>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>Error getting PDF info: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Extract text
    echo "<h2>3. Extract text from PDF</h2>";
    try {
        $text = \local_aiquizgen\util\pdf_extractor::extract_text($testpdf, 1000);
        $textlen = strlen($text);
        
        echo "<div class='success'>✓ Text extracted successfully!</div>";
        echo "<div class='info'><strong>Extracted text length:</strong> " . number_format($textlen) . " characters</div>";
        
        echo "<h3>Extracted text (first 1000 chars):</h3>";
        echo "<div class='extracted'>" . htmlspecialchars($text) . "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
} else {
    echo "<div class='error'>✗ Test PDF not found. Please place a test.pdf file in: " . htmlspecialchars(__DIR__) . "</div>";
    echo "<div class='info'>You can upload any PDF file and rename it to 'test.pdf' for testing.</div>";
}

// Upload form for testing
echo "<h2>4. Upload PDF for testing</h2>";
echo "<form method='POST' enctype='multipart/form-data'>";
echo "<input type='file' name='testpdf' accept='.pdf' required>";
echo "<button type='submit'>Test Extract</button>";
echo "</form>";

if (isset($_FILES['testpdf']) && $_FILES['testpdf']['error'] === UPLOAD_ERR_OK) {
    $uploadedfile = $_FILES['testpdf']['tmp_name'];
    echo "<h3>Testing uploaded PDF</h3>";
    
    try {
        $text = \local_aiquizgen\util\pdf_extractor::extract_text($uploadedfile, 1000);
        echo "<div class='success'>✓ Extracted " . number_format(strlen($text)) . " characters</div>";
        echo "<div class='extracted'>" . htmlspecialchars(substr($text, 0, 1000)) . "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "</body></html>";

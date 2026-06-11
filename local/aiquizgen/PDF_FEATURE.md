# PDF Upload Feature - AI Quiz Generator

## Overview
The AI Quiz Generator now supports generating questions from PDF documents. Users can upload a PDF file and the AI will extract the text content and generate relevant questions.

## How it Works

### 1. PDF Text Extraction
The plugin uses a hybrid approach for PDF text extraction:

**Primary Method:** `pdftotext` command-line tool (if available)
- Fast and reliable
- Handles complex PDFs well
- Requires `poppler-utils` package

**Fallback Method:** PHP-based parsing
- Pure PHP solution, no external dependencies
- Works for simple text-based PDFs
- May not work well with complex PDFs or image-based PDFs

### 2. Installation (Optional - for better PDF support)

To install `pdftotext` for better PDF extraction:

```bash
sudo apt-get update
sudo apt-get install -y poppler-utils
```

Test if installed:
```bash
pdftotext -v
```

### 3. Usage

1. Go to Question Bank in any course
2. Click "Generate Questions with AI" button
3. Either:
   - Enter a topic/subject (traditional way)
   - Upload a PDF file (new feature)
   - Or do both for more context
4. Fill in other options (question count, type, difficulty, language)
5. Click "Generate Questions"

### 4. Supported PDF Types

✅ **Works Well:**
- Text-based PDFs (most common)
- PDFs with selectable text
- Simple formatted documents
- Academic papers, textbooks (text-based)

❌ **May Not Work:**
- Scanned documents (image-based PDFs)
- Encrypted/password-protected PDFs
- PDFs with complex layouts
- PDFs with only images

### 5. Limitations

- Maximum file size: 10MB
- Only 1 PDF per generation
- Text is limited to 50,000 characters (to avoid token limits)
- Image-based PDFs require OCR (not currently supported)

### 6. Technical Details

**Files:**
- `/classes/util/pdf_extractor.php` - PDF text extraction utility
- `/classes/form/generate_form.php` - Updated form with file upload
- `/generate.php` - Updated to handle PDF processing

**PDF Processing Flow:**
1. User uploads PDF via filemanager
2. File is stored in draft area
3. On submit, file is copied to temp location
4. Text is extracted using pdf_extractor
5. Extracted text is combined with topic (if provided)
6. Combined text is sent to OpenAI API
7. Questions are generated based on the content

### 7. Error Handling

If PDF extraction fails:
- User will see error: "Error processing PDF: [reason]"
- Common reasons:
  - PDF is image-based (no selectable text)
  - PDF is encrypted
  - File is corrupted

### 8. Future Improvements

- OCR support for image-based PDFs
- Support for multiple PDFs
- Better handling of complex PDF layouts
- PDF preview before generation
- Show extracted text to user for verification

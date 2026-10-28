# PDF Feature - AI Quiz Generator

## Overview

The AI Quiz Generator can use PDF documents in two ways:

1. **Generate mode** — use a PDF as source material and let the AI create new questions.
2. **Import (extract) mode** — import existing questions and answer keys from a PDF without
   generating new content.

## How it Works

### 1. PDF text extraction

The plugin uses a hybrid approach:

**Primary method:** `pdftotext` command-line tool (if available)

- Fast and reliable
- Handles complex PDFs well
- Requires `poppler-utils`
- Detected with `where` on Windows and `command -v` on Unix

**Fallback method:** `smalot/pdfparser`

- Pure PHP, no external binary
- Handles compressed (FlateDecode) PDFs
- Install with `composer require smalot/pdfparser` in the Moodle dirroot, or bundle
  the package inside the plugin directory with a `vendor/autoload.php`
- The plugin loads every available autoloader, so a site-wide Composer vendor
  directory without the package does not hide a plugin-local copy

**Last resort:** basic PHP extraction for simple text-based PDFs.

### 2. Installation (optional, for better PDF support)

Install `pdftotext`:

```bash
sudo apt-get update
sudo apt-get install -y poppler-utils
```

Test it:

```bash
pdftotext -v
```

Moodle 5 does not ship `smalot/pdfparser` by default. The Moodle 5 deployment of
this plugin already includes the library under `local/aiquizgen/vendor/smalot/pdfparser`
with a minimal `vendor/autoload.php`, so no Composer changes are required. On other
sites, either bundle the package this way, install it with Composer, or make
`pdftotext` available.

### 3. Usage

#### Generate mode

1. Go to Question Bank in a course.
2. Click **Generate Questions with AI**.
3. Enter a topic and/or upload a PDF.
4. Choose count, type, difficulty, and language.
5. Click **Generate Questions**, review the drafts, then save.

#### Import mode

1. Go to Question Bank in a course.
2. Click **Generate Questions with AI**.
3. Select **Import questions from a PDF with answer keys**.
4. Upload the PDF. Question count, type, difficulty, and language are detected
   automatically and do not need to be filled in.
5. Choose the import format:
   - **As-is** — keep the original question types.
   - **Multiple choice** — convert every question to single-answer multiple choice with
     four AI-generated distractors.
6. Click **Generate Questions**. Each part of the PDF is processed with visible progress.
7. Review the imported questions:
   - Set the **Maximum mark** per question.
   - Edit the essay **Information for grader** (answer key + rubric).
   - Tick **Remove this question** to discard PDF artifacts or unwanted questions.
8. Save to the question bank.

### 4. Supported PDF types

Works well:

- Text-based PDFs with selectable text
- Documents with questions followed by answer keys (`ANSWER`, `KUNCI`, `JAWABAN`)
- Simple layouts and academic documents

May not work:

- Scanned/image-based PDFs
- Encrypted or password-protected PDFs
- PDFs with only images or complex multi-column layouts

### 5. Limitations

- Maximum file size: 10MB
- One PDF per operation
- Generate mode uses up to 8,000 characters of PDF text
- Import mode uses up to 50,000 characters of PDF text
- Image-based PDFs require OCR (not currently supported)

### 6. Technical details

**Files:**

- `classes/util/pdf_extractor.php` — PDF text extraction and normalization
- `classes/util/text_chunker.php` — splits long documents at question boundaries
- `classes/api/mastra_client.php` — calls the Dali generate/extract endpoints
- `classes/generator/question_generator.php` — saves questions, marks, and grader info
- `classes/form/generate_form.php` — mode selector and PDF upload
- `generate.php` — extraction flow, review form, and save handling

**Generate flow:**

1. User uploads a PDF.
2. File is copied to a temporary location.
3. Text is extracted (up to 8,000 characters) with line breaks preserved.
4. Text is combined with the topic.
5. Dali generates questions.
6. User reviews and saves them.

**Import flow:**

1. User uploads a PDF containing questions and answer keys.
2. Text is extracted (up to 50,000 characters, UTF-8 safe, cut at the last complete
   question when possible).
3. Cover pages and the table of contents before the first question are dropped.
4. Text is split at `Soal N.` / `Question N.` / `Pertanyaan N.` boundaries into parts of
   roughly 6,000 characters. Each part contains complete questions with their answers.
5. Each part is sent to `POST /api/moodle/quiz/extract`.
6. Extracted questions are merged and de-duplicated using the normalized question text
   and answer key.
7. The review form is displayed. It submits one compact JSON payload per question to stay
   below PHP's `max_input_vars` limit.
8. Questions are saved through the existing `question_generator`.

### 7. Import rules

- The AI extracts questions verbatim and preserves the original language.
- Numbered points inside an answer key are part of that answer, not new questions.
- Question numbering may restart in every section; de-duplication uses the full question
  text and answer, not the number.
- Only complete questions are imported. A fragment at the start or end of a part is
  skipped.
- Each question gets a detected type: multiple choice, true/false, short answer, or essay.
- Essay questions may have no answer options. Their answer key and a percentage-based
  rubric are stored in `qtype_essay_options.graderinfo`.
- `defaultmark` is saved per question. AI grading uses the actual quiz attempt maximum
  mark, so a 100-mark essay is graded on a 0–100 scale.

### 8. Error handling

If PDF extraction fails, the user sees a descriptive error. Common reasons:

- The PDF is image-based (no selectable text)
- The PDF is encrypted
- The file is corrupted
- Neither `pdftotext` nor `smalot/pdfparser` is available

If one part fails during import, the other parts are still shown and a warning lists the
failed part. If every part fails, the import stops with an error.

### 9. Future improvements

- OCR support for image-based PDFs
- Support for multiple PDFs
- Better handling of complex layouts
- PDF preview and extracted-text preview before import

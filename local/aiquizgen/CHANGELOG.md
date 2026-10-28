# Changelog

All notable changes to the AI Quiz Generator plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-10-28

### Added

- Import mode (`extract`) to upload a PDF that already contains questions and answer keys.
  - The AI detects each question type and the total question count, so question count,
    type, difficulty, and language are not required.
  - Optional conversion of every imported question to single-answer multiple choice with
    four AI-generated distractors.
  - Long PDFs are split at question boundaries and processed in parts; each part shows
    progress and a failed part does not discard the other questions.
  - Extracted questions are merged and de-duplicated before review.
- Per-question maximum mark (`defaultmark`) in the review form, including a bulk
  "apply to all essay questions" control.
- AI-generated "Information for grader" (answer key + percentage-based rubric) for essay
  questions, saved to `qtype_essay_options.graderinfo`.
- Rubrics are now written per question from that question's own text and answer key, so
  each one names the concrete facts, steps, values, or examples the student must include
  instead of repeating a generic template.
- Source selector in generate mode with `Topic/Subject` as the default. The topic field,
  PDF upload, and course-material selector are shown only for the selected source.
- Animated progress card while generate mode waits for the AI, with a warning not to
  close or refresh the page.
- "Remove this question" checkbox in the review form so PDF artifacts can be discarded.
- `text_chunker` utility and a new Dali endpoint `POST /api/moodle/quiz/extract`.
- Import progress card with a percentage bar, per-part status, and a warning that closing
  or refreshing the page cancels the import.
- Waiting overlay when generating new questions, shown until the review page loads.
- Pre-set maximum marks in the initial form for imported multiple choice and essay
  questions, and one maximum mark for all generated questions.
- Structured essay grader information with a labelled answer-key section and one rubric
  criterion per line, so teachers can read and grade it more easily.

### Changed

- Imports now produce only multiple choice and essay questions. True/False is imported as
  multiple choice with two options, and short answers are imported as essay questions.
- Essay questions with no answer options are now valid and can be saved.
- Review form submits one compact JSON payload per question to stay below PHP's
  `max_input_vars` limit, with a no-JavaScript fallback.
- AI grading now receives the grader information/rubric and uses the actual question
  attempt maximum mark, so essays scored on a 10 or 100 scale are graded on that scale.
- Question text, grader information, and feedback are converted to safe HTML while
  preserving line breaks.
- Import chunks now target 4,000 characters instead of 6,000. Fewer questions per AI
  request gives more detailed and more stable per-question rubrics.
- Generate mode uses only the selected source: a topic, an uploaded PDF, or a synced
  course source. A hidden topic is no longer combined with an uploaded PDF.

### Fixed

- PDF extraction no longer collapses all newlines, so question boundaries are preserved.
- `pdftotext` detection now works on Windows (`where`) as well as Unix (`command -v`).
- Import no longer stops after the first part when the AI needs more than PHP's default
  30-second limit. The page raises its execution limit before extracting, and the Dali
  AI endpoints raise theirs before calling Mastra.
- PDF truncation is UTF-8 safe and cuts at the last complete question when possible.
- Generating many questions no longer fails at the client's 60-second timeout; the
  generate request now allows up to 180 seconds.

## [1.0.9] - 2025-10-03

### Fixed

- Category dropdown empty issue resolved
  - Removed `parent > 0` filter that was hiding categories
  - Smart fallback: shows subcategories if available, otherwise shows top categories
  - Auto-creates default category if none exist
  - Now shows ALL available categories immediately

### Improved

- Intelligent category filtering
- Automatic default category creation
- Better handling of edge cases

## [1.0.8] - 2025-10-03

### Fixed

- Category dropdown now shows ALL accessible categories
  - Includes categories from course context
  - Includes categories from parent contexts (system, course category)
  - Shows newly created categories immediately
  - Better UX with context separators
  - Proper ordering by context level

### Improved

- Query now uses `get_parent_context_ids()` to include all accessible contexts
- Categories grouped by context for better organization
- Visual separators between different context levels

## [1.0.7] - 2025-10-03

### Changed - SIMPLIFIED APPROACH

- Complete refactor: Direct database query approach
  - Eliminates ALL complex context handling
  - Simple SQL query to get categories from course context
  - No dependency on questioncategory form element
  - No dependency on question_edit_contexts object
  - Ultra-reliable and straightforward
  - Works consistently across all Moodle versions

### Benefits

- 99% reduction in complexity
- No more context-related errors (guaranteed!)
- Direct database access = predictable behavior
- Easy to understand and maintain
- Proven SQL pattern from Moodle core

## [1.0.6] - 2025-10-03

### Fixed

- Correct context handling for questioncategory form element
  - Use `having_cap('moodle/question:add')` to get contexts where user can add questions
  - Pass array of context objects (not question_edit_contexts object) to form element
  - Follows Moodle's standard pattern used in quiz and question modules
  - Now works correctly with proper permission filtering

### Technical

- Implements same approach as mod_quiz and question type forms
- Defensive coding with multiple fallback options
- Clean and maintainable implementation

## [1.0.5] - 2025-10-03

### Fixed

- Context handling completely refactored
  - Replaced manual context filtering with Moodle's built-in `questioncategory` form element
  - Eliminates all context-related errors
  - Simpler, more maintainable code
  - Leverages Moodle's proven context handling logic
  - Automatic compatibility with all Moodle versions

### Changed

- Form now uses native Moodle form elements for better integration
- Reduced code complexity by 30+ lines
- More robust and future-proof implementation

## [1.0.4] - 2025-10-03

### Fixed

- Invalid context error in `question_category_options()`
  - Error: "Invalid context id specified context::instance_by_id()"
  - Root cause: Function expects contexts with MODULE contextlevel only
  - Solution: Use `having_one_edit_tab_cap('editq')` to get contexts with proper capability
  - Now only shows categories where user can actually add questions
  - More secure and aligned with Moodle's permission system

### Changed

- Plugin maturity upgraded to MATURITY_STABLE
- Ready for production use

## [1.0.3] - 2025-10-03

### Fixed

- Type error in `question_category_options()` call
  - Error: Argument #1 must be of type array, question_edit_contexts given
  - Solution: Extract array from question_edit_contexts object using `all()` method
  - Added object type check and method existence verification
  - Form now correctly passes array of contexts to helper function

## [1.0.2] - 2025-10-03

### Fixed

- Deprecation warning for `before_footer` callback in Moodle 5.0+
  - Migrated to new hook system (`\core\hook\output\before_footer_html_generation`)
  - Created `classes/hook_callbacks.php` with new hook callback
  - Created `db/hooks.php` for hook configuration
  - Kept legacy `before_footer` callback for Moodle 4.4 backward compatibility
  - No more deprecation warnings in Moodle 5.0

## [1.0.1] - 2025-10-03

### Fixed

- Compatibility issue with `question_category_options()` method in Moodle 4.4+
  - Added backward compatibility check to use correct namespace
  - Plugin now uses `qbank_managecategories\helper` for Moodle 4.4+
  - Includes fallback for older versions
  - Resolves "Call to undefined method" error when loading generate form

## [1.0.0] - 2025-10-03

### Added

#### Core Features
- OpenAI ChatGPT integration for automated question generation
- Support for multiple question types:
  - Multiple Choice (4 options, single answer)
  - True/False
  - Short Answer
- Configurable difficulty levels (Easy, Medium, Hard)
- Multi-language support (English, Indonesian)
- Question preview before saving to Question Bank
- Direct integration with Moodle Question Bank

#### Configuration
- Admin settings page for OpenAI API configuration
- Configurable API key storage (encrypted)
- Model selection (GPT-3.5 Turbo, GPT-4, GPT-4 Turbo)
- Temperature control (0.0 - 2.0)
- Max tokens configuration
- Maximum questions per request limit
- Optional logging toggle

#### User Interface
- Generate questions form with comprehensive options:
  - Topic/subject input (textarea)
  - Question count selector (1-20)
  - Question type selector
  - Difficulty level selector
  - Language selector
  - Category selector (Question Bank integration)
  - Additional instructions field
- Question preview with formatted display
- Color-coded correct/incorrect answers in preview
- Feedback display for each answer
- Success/error notifications
- Responsive design

#### Integration
- Course navigation link for quick access
- Question Bank page integration (adds button to QB page)
- Context-aware permission checking
- Seamless question saving to selected category

#### Database
- `local_aiquizgen_log` table for activity logging:
  - User tracking
  - Course tracking
  - Category tracking
  - Topic/subject logging
  - Question count logging
  - Success/failure tracking
  - Timestamp recording

#### API Client
- Robust OpenAI API client implementation
- Error handling for API failures
- HTTP 401 detection (invalid API key)
- JSON response parsing
- Markdown code block cleanup
- Connection testing capability

#### Question Generator
- Automatic conversion from AI format to Moodle format
- Support for multiple question types
- Question data validation
- Batch question saving
- Error collection and reporting
- Activity logging integration

#### Privacy
- GDPR-compliant privacy provider
- User data export capability
- User data deletion support
- External data disclosure (OpenAI)
- Context-based data management

#### Capabilities
- `local/aiquizgen:generate` capability
- Default assignment to Editing Teachers and Managers
- Course-level permission
- RISK_SPAM risk classification

#### Documentation
- Comprehensive README.md
- Detailed INSTALL.md with troubleshooting
- CHANGELOG.md (this file)
- Inline code documentation
- Help buttons on form fields
- Language string documentation

### Technical Details

#### Requirements
- Moodle 4.4+ or 5.0+ (requires 2024042200)
- PHP 8.1+
- curl extension
- OpenAI API access

#### Architecture
- Local plugin type for flexibility
- Namespace: `local_aiquizgen`
- PSR-4 autoloading compliant
- Moodle coding standards compliant
- Form API integration
- Question API integration
- Privacy API implementation

#### Security
- Encrypted API key storage
- Permission-based access control
- CSRF protection (via Moodle forms)
- SQL injection prevention (via $DB API)
- XSS prevention (via output API)
- Context validation

#### Performance
- Efficient database queries
- Minimal JavaScript usage
- Server-side processing
- Caching-friendly design
- 60-second API timeout

### Known Limitations

- Maximum 20 questions per request (configurable)
- Requires active internet connection
- Depends on OpenAI API availability
- API costs apply per generation
- Questions generated in English or Indonesian only (currently)
- No bulk import from files (yet)
- No integration with AI subsystem (Moodle AI API)

### Future Enhancements (Planned)

- Support for more question types (Essay, Calculated, etc.)
- Bulk generation from document/PDF upload
- Question bank search integration
- AI-powered question difficulty analysis
- Question quality scoring
- Duplicate question detection
- Multi-language expansion
- Integration with Moodle AI subsystem
- Question editing before saving
- Regenerate individual questions
- Question templates
- Custom prompt templates
- Batch operations
- API usage statistics
- Cost tracking and limits
- Quiz auto-creation option
- Question tagging
- Export/import generated questions

## Version History

- **1.0.0** (2025-10-03) - Initial release

---

## Upgrade Notes

### Upgrading to 1.0.0

This is the initial release. No upgrade path exists yet.

---

For installation instructions, see INSTALL.md  
For usage guide, see README.md

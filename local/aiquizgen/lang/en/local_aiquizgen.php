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
 * Language strings for AI Quiz Generator plugin.
 *
 * @package    local_aiquizgen
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'AI Quiz Generator';
$string['aiquizgen:generate'] = 'Generate quiz questions with AI';

// Settings page.
$string['openaisettings'] = 'AI Service Settings';
$string['openaisettings_desc'] = 'Configure AI service settings for question generation.';
$string['apibaseurl'] = 'Dali API Base URL';
$string['apibaseurl_desc'] = 'The base URL for the Dali AI service (e.g., http://localhost:8000).';
$string['apikey'] = 'Dali API Key';
$string['apikey_desc'] = 'Enter your Dali API key.';
$string['maxquestions'] = 'Maximum Questions per Request';
$string['maxquestions_desc'] = 'Maximum number of questions that can be generated in a single request.';
$string['enablelog'] = 'Enable Logging';
$string['enablelog_desc'] = 'Log all question generation activities for auditing purposes.';

// Generate form.
$string['generatequestions'] = 'Generate Quiz Questions with AI';
$string['mode'] = 'Mode';
$string['mode_help'] = 'Generate new questions from a topic or source, or import existing questions and answer keys from a PDF.';
$string['mode_generate'] = 'Create new questions from material/topic';
$string['mode_extract'] = 'Import questions from a PDF with answer keys';
$string['extractformat'] = 'Import Format';
$string['extractformat_help'] = 'Keep the original question types, or convert every imported question into a single-answer multiple choice question with four distractors.';
$string['extractformat_asis'] = 'As-is (AI detects essay/multiple choice)';
$string['extractformat_multichoice'] = 'Convert everything to multiple choice (AI creates distractors)';
$string['extractpdfhint'] = 'Upload a PDF that already contains the questions and their answer keys. The AI detects the question types and total count automatically.';
$string['extractpdf_required'] = 'Please upload a PDF containing the questions and answer keys.';
$string['extractformatinvalid'] = 'Invalid import format.';
$string['mcqmaxmark'] = 'Maximum mark for multiple choice questions';
$string['mcqmaxmark_help'] = 'Optional. Applied to every imported multiple choice question before the review page. Leave empty to keep the mark detected from the document.';
$string['essaymaxmark'] = 'Maximum mark for essay questions';
$string['essaymaxmark_help'] = 'Optional. Applied to every imported essay question before the review page. Leave empty to keep the mark detected from the document.';
$string['extractmarkhint'] = 'Set a mark once here to avoid editing every question on the review page.';
$string['maxmark'] = 'Maximum mark per question';
$string['maxmark_help'] = 'Mark given to each generated question. You can still adjust individual questions on the review page.';
$string['maxmark_invalid'] = 'Maximum mark must be greater than 0.';
$string['topic'] = 'Topic/Subject';
$string['topic_help'] = 'Enter the topic or subject for which you want to generate questions. Required when Source is Topic/Subject.';
$string['topicexample'] = 'Example: "Photosynthesis in plants" or "Python programming loops"';
$string['pdffile'] = 'Upload PDF Document';
$string['pdffile_help'] = 'Upload a PDF document to generate questions from its content. The AI will read and understand the document to create relevant questions.';
$string['pdfornote'] = 'Choose where the questions should come from: a topic, an uploaded PDF, or a synced course source.';
$string['topicorpdf_required'] = 'Please enter a topic or upload/select a source (or both).';
$string['topicrequired'] = 'Please enter a topic or subject.';
$string['pdfrequired'] = 'Please upload a PDF document.';
$string['sourcerequired'] = 'Please select a course source.';

// Source selection
$string['pdfsource'] = 'Source';
$string['pdfsource_help'] = 'Choose Topic/Subject to write questions from a subject, Upload new PDF file to read a PDF, or Select from course materials to use a synced course source.';
$string['pdfsource_topic'] = 'Topic/Subject';
$string['pdfsource_upload'] = 'Upload new PDF file';
$string['pdfsource_course'] = 'Select from course materials';
$string['coursetpdf'] = 'Select Source';
$string['coursetpdf_help'] = 'Choose a synced Dali knowledge source from this course. Questions will be generated from retrieved source content.';
$string['nocoursepdf'] = 'No synced knowledge sources found for this course. Sync course materials to the Dali knowledge base first, or upload a PDF.';
$string['select_pdf_from_course'] = '-- Select a source --';
$string['sources_hint'] = 'Uses Dali knowledge-base retrieval from the selected ready source.';
$string['questioncount'] = 'Number of Questions';
$string['questioncount_help'] = 'How many questions do you want to generate?';
$string['questiontype'] = 'Question Type';
$string['questiontype_help'] = 'Select the type of questions to generate.';
$string['answeroptioncount'] = 'Answer Option Count';
$string['answeroptioncount_help'] = 'Choose how many answer options each Multiple Choice question should have.';
$string['answeroptioncountinvalid'] = 'Answer Option Count must be between 3 and 10.';
$string['difficulty'] = 'Difficulty Level';
$string['difficulty_help'] = 'Select the difficulty level for the questions.';
$string['language'] = 'Language';
$string['language_help'] = 'Select the language for questions and answers.';
$string['category'] = 'Question Category';
$string['category_help'] = 'Select the category where generated questions will be saved.';
$string['nocategories'] = 'No question categories available. Please create a category first.';
$string['additionalinstructions'] = 'Additional Instructions';
$string['additionalinstructions_help'] = 'Optional: Provide any additional instructions or context for the AI.';

// Question types.
$string['multichoice'] = 'Multiple Choice';
$string['truefalse'] = 'True/False';
$string['shortanswer'] = 'Short Answer';
$string['essay'] = 'Essay';

// Difficulty levels.
$string['easy'] = 'Easy';
$string['medium'] = 'Medium';
$string['hard'] = 'Hard';

// Languages.
$string['english'] = 'English';
$string['indonesian'] = 'Indonesian (Bahasa Indonesia)';
$string['thai'] = 'Thai (ภาษาไทย)';
$string['vietnamese'] = 'Vietnamese (Tiếng Việt)';
$string['malay'] = 'Malay (Bahasa Melayu)';
$string['filipino'] = 'Filipino (Tagalog)';
$string['burmese'] = 'Burmese (မြန်မာစာ)';
$string['khmer'] = 'Khmer (ភាសាខ្មែរ)';
$string['lao'] = 'Lao (ພາສາລາວ)';
$string['tetum'] = 'Tetum (Tetun)';

// Buttons.
$string['generate'] = 'Generate Questions';
$string['preview'] = 'Preview Questions';
$string['savetobank'] = 'Save to Question Bank';
$string['regenerate'] = 'Regenerate';
$string['cancel'] = 'Cancel';
$string['gotoqbank'] = 'Go to Question Bank';

// Messages.
$string['generating'] = 'Generating questions... This may take a few moments.';
$string['generatesuccess'] = '{$a} questions generated successfully!';
$string['generateerror'] = 'Error generating questions: {$a}';
$string['savesuccess'] = '{$a} questions saved to question bank successfully!';
$string['saveerror'] = 'Error saving questions: {$a}';
$string['noapikey'] = 'API key is not configured. Please contact your site administrator.';
$string['invalidapikey'] = 'Invalid API key. Please check your configuration.';
$string['apierror'] = 'AI API error: {$a}';
$string['nocategory'] = 'Please select a question category.';
$string['invalidjson'] = 'Invalid response from AI. Please try again.';
$string['extractingpdf'] = 'Extracting text from PDF document...';
$string['pdfextracted'] = 'PDF text extracted successfully ({$a} characters).';
$string['pdferror'] = 'Error processing PDF: {$a}';
$string['retrievingsource'] = 'Retrieving content from selected knowledge source...';
$string['source_retrieved'] = 'Source content retrieved successfully ({$a} characters).';
$string['pdftruncated'] = 'Note: the PDF content was truncated to {$a} characters. Questions after the cut-off were not imported.';

// Import progress.
$string['importprogress_title'] = 'Importing questions from the PDF';
$string['importprogress_warning'] = 'Do not close, refresh, or navigate away from this page. The import will be cancelled and no questions will be saved.';
$string['importprogress_starting'] = 'Preparing the PDF...';
$string['importprogress_pdf'] = 'PDF text extracted ({$a} characters). Splitting into parts...';
$string['importprogress_part'] = 'Analyzing part {$a->part} of {$a->total} with AI. This can take up to a minute per part...';
$string['importprogress_partdone'] = 'Part {$a->part} of {$a->total} done ({$a->count} questions found).';
$string['importprogress_partfailed'] = 'Part {$a->part} of {$a->total} failed and was skipped.';
$string['importprogress_done'] = 'Import finished: {$a} questions ready to review.';
$string['importprogress_failed'] = 'Import failed. See the error below.';
$string['generatewait_title'] = 'Preparing your questions';
$string['generatewait_message'] = 'The AI is generating questions. This can take up to a minute or two. Please do not close or refresh this page.';
$string['generateprogress_title'] = 'Generating questions';
$string['generateprogress_starting'] = 'Preparing your source and sending the request to the AI...';
$string['generateprogress_warning'] = 'Do not close, refresh, or navigate away from this page. The request will be cancelled and no questions will be generated.';
$string['generateprogress_done'] = 'Questions ready to review: {$a}.';
$string['generateprogress_failed'] = 'Question generation failed. See the error below.';

// Import/extract mode.
$string['extractingpart'] = 'Extracting questions from part {$a->part} of {$a->total}...';
$string['extractpartsuccess'] = 'Part {$a} processed.';
$string['extractchunkfailed'] = 'Part {$a} could not be processed and was skipped.';
$string['extractpartfailedlist'] = 'Some parts could not be processed: {$a}';
$string['extractsuccess'] = '{$a} questions extracted from the PDF. Review them before saving.';
$string['extractduplicates'] = '{$a} duplicate question(s) were removed.';
$string['noquestionsfound'] = 'No questions were found in the PDF. Make sure the document contains question text with answer keys.';
$string['reviewgeneratednotice'] = 'Questions generated. Review and edit them first, then save to question bank.';

// Preview.
$string['previewtitle'] = 'Preview Generated Questions';
$string['previewextractedtitle'] = 'Review Imported Questions';
$string['question'] = 'Question';
$string['answers'] = 'Answers';
$string['correctanswer'] = 'Correct Answer';
$string['feedback'] = 'Feedback';
$string['maximummark'] = 'Maximum mark';
$string['maximummark_help'] = 'The maximum mark for this question. Essay grading uses this scale (for example 100).';
$string['graderinfo'] = 'Information for grader (model answer & rubric)';
$string['graderinfo_help'] = 'Used by AI grading. A percentage-based rubric (total 100%) is recommended so the mark can be changed without rewriting the rubric.';
$string['acceptedanswers'] = 'Accepted answers (all are treated as correct)';
$string['shortanswernote'] = 'All non-empty answers below are accepted as correct.';
$string['skipquestion'] = 'Remove this question';
$string['applytoessay'] = 'Apply maximum mark to all essay questions';
$string['apply'] = 'Apply';
$string['reviewimportednotice'] = 'Questions imported. Review, edit, and remove any PDF artifacts before saving to the question bank.';

// Capabilities.
$string['aiquizgen:generate'] = 'Generate quiz questions using AI';

// Privacy.
$string['privacy:metadata:local_aiquizgen_log'] = 'Log of AI question generation activities';
$string['privacy:metadata:local_aiquizgen_log:userid'] = 'The ID of the user who generated questions';
$string['privacy:metadata:local_aiquizgen_log:topic'] = 'The topic used for generation';
$string['privacy:metadata:local_aiquizgen_log:questioncount'] = 'Number of questions generated';
$string['privacy:metadata:local_aiquizgen_log:timecreated'] = 'When the questions were generated';
$string['privacy:metadata:openai'] = 'AI Quiz Generator sends data to Dali AI service for question generation';
$string['privacy:metadata:openai:topic'] = 'The topic/subject provided by the user';
$string['privacy:metadata:openai:instructions'] = 'Additional instructions provided by the user';
$string['invalidcategoryid'] = 'Invalid category ID provided: {$a}';

// Test connection.
$string['testconnection'] = 'Test Connection';
$string['testconnection_help'] = 'Click to test if the plugin can connect to the Dali API.';
$string['testconnection_noapikey'] = 'API key is not configured. Please enter an API key first.';
$string['testconnection_curlerror'] = 'Connection failed: {$a}';
$string['testconnection_success'] = 'Connection successful! The plugin can communicate with the Dali API.';
$string['testconnection_unauthorized'] = 'Connection failed: Invalid API key. Please check your API key configuration.';
$string['testconnection_serviceerror'] = 'Connection failed: Dali service error - {$a}';
$string['testconnection_httperror'] = 'Connection failed: HTTP error {$a}';

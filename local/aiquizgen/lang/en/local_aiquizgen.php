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
$string['topic'] = 'Topic/Subject';
$string['topic_help'] = 'Enter the topic or subject for which you want to generate questions. Be as specific as possible. Leave empty if you are uploading a PDF file or selecting a synced source.';
$string['topicexample'] = 'Example: "Photosynthesis in plants" or "Python programming loops"';
$string['pdffile'] = 'Upload PDF Document';
$string['pdffile_help'] = 'Upload a PDF document to generate questions from its content. The AI will read and understand the document to create relevant questions.';
$string['pdfornote'] = 'You can either enter a topic above OR upload a PDF/select a synced source (or combine them for more context).';
$string['topicorpdf_required'] = 'Please enter a topic or upload/select a source (or both).';

// Source selection
$string['pdfsource'] = 'Source';
$string['pdfsource_help'] = 'Choose whether to upload a PDF or retrieve content from a synced course knowledge source.';
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

// Preview.
$string['previewtitle'] = 'Preview Generated Questions';
$string['question'] = 'Question';
$string['answers'] = 'Answers';
$string['correctanswer'] = 'Correct Answer';
$string['feedback'] = 'Feedback';

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

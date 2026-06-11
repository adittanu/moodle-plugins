<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Language strings for AI Lesson Plan plugin.
 *
 * @package    local_ailessonplan
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Lesson Plan';
$string['ailessonplan:generate'] = 'Generate AI lesson plans';

$string['apisettings'] = 'Dali API settings';
$string['apisettings_desc'] = 'Configure the Dali/Laravel backend used by AI Lesson Plan.';
$string['apibaseurl'] = 'Dali API Base URL';
$string['apibaseurl_desc'] = 'Base URL for the Dali app, for example http://localhost:8000.';
$string['apikey'] = 'Dali API Key';
$string['apikey_desc'] = 'API key from Dali My Agents.';
$string['enablelog'] = 'Enable saved draft history';
$string['enablelog_desc'] = 'Store generated lesson plans in Moodle so trainers can open them later.';

$string['generateplan'] = 'Generate Lesson Plan';
$string['savedplans'] = 'Saved lesson plans';
$string['noplan'] = 'No lesson plans have been saved yet.';
$string['viewplan'] = 'View lesson plan';
$string['backtocourse'] = 'Back to course';
$string['backtolist'] = 'Back to saved plans';
$string['saveplan'] = 'Save draft';
$string['savedraftsuccess'] = 'Lesson plan draft saved.';
$string['downloadjson'] = 'Download JSON';
$string['publishtomoodle'] = 'Publish to Moodle';
$string['publishtomoodle_desc'] = 'Preview and publish this plan as a Moodle course skeleton: sections, AI-managed summaries, and activity placeholders selected by AI.';
$string['publishplan'] = 'Publish plan';
$string['republishplan'] = 'Republish / update Moodle page';
$string['publishsuccess'] = 'Course skeleton published to Moodle.';
$string['openpublishedpage'] = 'Open published page';
$string['sectionsupdated'] = '{$a} weekly course sections updated.';
$string['activitiespublished'] = '{$a->created} activities created, {$a->updated} activities updated.';
$string['publishedpagetitleprefix'] = 'RPS / Syllabus';
$string['publishedintro'] = 'Generated from AI Lesson Plan.';
$string['publishedstatus'] = 'Published to Moodle. Last publish: {$a}';
$string['previewpublishbutton'] = 'Preview publish';
$string['publishpreview'] = 'Publish preview';
$string['publishpreview_desc'] = 'Review the exact targets before anything is written.';
$string['reviewcourseskeleton'] = 'Review course skeleton';
$string['editablepreview_desc'] = 'Edit section titles, summaries, objectives, activity types, titles, and instructions before publishing. Uncheck anything you do not want to create.';
$string['pagepreview_desc'] = 'Confirm publish creates or updates course sections and the selected activity skeletons.';
$string['publish_target_page'] = 'Page activity to create/update: {$a}';
$string['confirmpublish'] = 'Confirm publish';
$string['nosectionchanges'] = 'No weekly section changes to preview.';
$string['currentsection'] = 'Current section';
$string['publishplacement'] = 'Publish placement';
$string['placement_append'] = 'Append as new sections after existing course sections';
$string['placement_update'] = 'Update existing sections starting from section 1';
$string['placement_custom'] = 'Start from a specific section number';
$string['startsection'] = 'Start section number';
$string['startsection_help'] = 'Only used when "Start from a specific section number" is selected. Append always creates new sections after the current last section.';
$string['publishaction'] = 'Action';
$string['previewchange'] = 'Preview';
$string['sectionpreviewtarget'] = 'Current section: {$a}';
$string['sectiontitle'] = 'Section title';
$string['sectionsummary'] = 'Section summary';
$string['sectionwillbecreated'] = 'Section will be created if missing.';
$string['showproposedsummary'] = 'Show proposed AI block';
$string['showactivitypreview'] = 'Show activity preview';
$string['sectionblocktitle'] = 'AI Lesson Plan — Week {$a->week}: {$a->topic}';
$string['sectionaction_create'] = 'Create section and add AI block';
$string['sectionaction_update_ai_block'] = 'Update existing AI block only';
$string['sectionaction_append_to_empty'] = 'Add AI block to empty summary';
$string['sectionaction_append_to_existing'] = 'Append AI block, keep existing summary';

$string['topic'] = 'Topic / subject focus';
$string['topic_help'] = 'Main topic to plan. Leave broad if you want the plan to follow the course context.';
$string['topicexample'] = 'Example: Dasar Pemrograman Python, Project-based learning for web development, or LMS onboarding for trainers.';
$string['level'] = 'Learner level';
$string['level_beginner_mixed'] = 'Beginner / mixed ability';
$string['level_beginner'] = 'Beginner';
$string['level_intermediate'] = 'Intermediate';
$string['level_advanced'] = 'Advanced';
$string['level_foundation'] = 'Foundation / basic education';
$string['level_secondary'] = 'Secondary / pre-university';
$string['level_vocational'] = 'Vocational / skills training';
$string['level_higher_education'] = 'Higher education / university';
$string['level_professional'] = 'Professional / corporate training';
$string['duration'] = 'Duration per meeting';
$string['meetings'] = 'Number of meetings';
$string['language'] = 'Language';
$string['outputtype'] = 'Output type';
$string['activitydensity'] = 'Activity density';
$string['density_light'] = 'Light (1-2 activities per section)';
$string['density_balanced'] = 'Balanced (2-3 activities per section)';
$string['density_rich'] = 'Rich (3-5 activities per section)';
$string['curriculumreference'] = 'Curriculum reference / standard';
$string['additionalinstructions'] = 'Additional instructions';
$string['includecontext'] = 'Course context to include';
$string['includecoursemetadata'] = 'Course metadata and summary';
$string['includesections'] = 'Course sections/topics';
$string['includeactivities'] = 'Activity list and short descriptions';
$string['includesource'] = 'Use synced Dali knowledge source';
$string['knowledgesource'] = 'Knowledge source';
$string['selectsource'] = 'Do not use a knowledge source';
$string['nosources'] = 'No ready Dali knowledge sources found for this course.';
$string['sources_hint'] = 'Sources come from Dali Widget Knowledge Base sync.';

$string['output_rps'] = 'RPS / semester lesson plan';
$string['output_syllabus'] = 'Syllabus';
$string['output_weekly'] = 'Weekly lesson plan';
$string['english'] = 'English';
$string['indonesian'] = 'Indonesian';
$string['generating'] = 'Generating lesson plan...';
$string['retrievingsource'] = 'Retrieving relevant course source context...';
$string['source_retrieved'] = 'Retrieved {$a} characters of relevant context.';
$string['previewtitle'] = 'Generated lesson plan preview';
$string['apierror'] = 'AI service error: {$a}';
$string['noapikey'] = 'Dali API key is not configured. Please configure it in Site administration → Plugins → Local plugins → AI Lesson Plan.';
$string['invalidjson'] = 'Invalid JSON response from AI service.';
$string['plantitle'] = 'Plan title';
$string['description'] = 'Description';
$string['coursesummary'] = 'Course summary';
$string['courseskeleton'] = 'Course skeleton';
$string['learningoutcomes'] = 'Learning outcomes';
$string['meetingssection'] = 'Meeting plan';
$string['assessmentplan'] = 'Assessment plan';
$string['references'] = 'References';
$string['week'] = 'Week';
$string['objectives'] = 'Objectives';
$string['materials'] = 'Materials';
$string['activities'] = 'Learning activities';
$string['activity'] = 'Activity';
$string['activitytype'] = 'Activity type';
$string['activitytitle'] = 'Activity title';
$string['activitybody'] = 'Activity instruction / content';
$string['publishactivity'] = 'Publish';
$string['publishedas'] = 'Published as {$a}';
$string['placeholderactivity'] = 'Placeholder';
$string['purpose'] = 'Purpose';
$string['activitystatus_create'] = 'Create';
$string['activitystatus_update'] = 'Update';
$string['assessment'] = 'Assessment';
$string['copyjson'] = 'Structured JSON';
$string['quizplaceholder'] = 'Quiz container only. Add questions later using AI Quiz Generator.';
$string['scormplaceholder'] = 'SCORM package placeholder. Attach a valid SCORM .zip package later before using it as a real SCORM activity.';
$string['unsupportedplaceholder'] = '{$a} is published as a safe placeholder in this version.';
$string['gradinghint'] = 'Grading hint';
$string['activityfallback'] = 'The requested activity could not be created directly, so AI Lesson Plan created this safe placeholder instead. Technical detail: {$a}';
$string['materialactivityprefix'] = 'Material';
$string['materialactivityinstruction'] = 'Review this material and prepare for the weekly activities.';
$string['discussionactivityprefix'] = 'Discussion';
$string['assignmentactivityprefix'] = 'Assignment';
$string['mod_label'] = 'Label';
$string['mod_page'] = 'Page';
$string['mod_forum'] = 'Forum';
$string['mod_assign'] = 'Assignment';
$string['mod_quiz'] = 'Quiz';
$string['mod_url'] = 'URL';
$string['mod_scorm'] = 'SCORM placeholder';
$string['mod_book'] = 'Book placeholder';
$string['mod_choice'] = 'Choice placeholder';
$string['mod_feedback'] = 'Feedback placeholder';
$string['mod_glossary'] = 'Glossary placeholder';
$string['mod_wiki'] = 'Wiki placeholder';

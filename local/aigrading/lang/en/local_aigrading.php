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
 * Language strings for AI Grading plugin.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'AI Grading';

// Settings.
$string['apisettings'] = 'Dali';
$string['apisettings_desc'] = 'Configure Dali API connection settings.';
$string['apikey'] = 'Dali API Key';
$string['apikey_desc'] = 'Enter your Dali API key.';
$string['apibaseurl'] = 'Dali API Base URL';
$string['apibaseurl_desc'] = 'Base URL for Dali API (e.g., https://dali-app.test).';
$string['model'] = 'Model';
$string['model_desc'] = 'OpenAI model to use for grading. Examples: gpt-4o-mini, gpt-4o, gpt-3.5-turbo';

$string['gradingsettings'] = 'Grading Settings';
$string['gradingsettings_desc'] = 'Configure how AI grades essay answers.';
$string['defaultrubric'] = 'Default Rubric';
$string['defaultrubric_desc'] = 'Default grading rubric/criteria. This will be used if no specific rubric is provided in the question.';
$string['systemprompt'] = 'System Prompt';
$string['systemprompt_desc'] = 'System prompt template for AI grading. Use this to customize how AI evaluates and responds.';

// Usage Guide.
$string['usageguide'] = '📖 Usage Guide - How to Get Best Results';
$string['usageguide_desc'] = '<div class="alert alert-info">
<h5><i class="fa fa-lightbulb-o"></i> Tips for Better AI Grading</h5>
<p>For best results, fill in the <strong>"Information for graders"</strong> field when creating essay questions. This helps AI understand what a good answer should contain.</p>

<h6>Where to find it:</h6>
<p>Question Bank → Edit Essay Question → Scroll to <strong>"Grader information"</strong> section → Fill in <strong>"Information for graders"</strong></p>

<h6>Example 1 - Factual Question:</h6>
<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">
Correct Answer:
OpenAI is an AI research company founded in 2015 by Sam Altman, 
Elon Musk, and others. OpenAI created ChatGPT, GPT-4, and DALL-E.

Grading Points:
- Mentions OpenAI is an AI company (2 points)
- Mentions founding year 2015 or founders (1 point)
- Mentions products like ChatGPT/GPT (2 points)
</pre>

<h6>Example 2 - Argumentative Essay:</h6>
<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">
Grading Criteria:
1. Clear introduction with thesis statement (2 points)
2. At least 3 supporting arguments with examples (3 points)
3. Use of relevant references/sources (2 points)
4. Conclusion that summarizes arguments (2 points)
5. Good grammar and paragraph structure (1 point)

The answer should discuss the impact of technology on education 
from accessibility, efficiency, and implementation challenges.
</pre>

<h6>Example 3 - Definition Question:</h6>
<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">
Correct Answer:
Photosynthesis is the process by which plants convert sunlight, 
water, and CO2 into glucose and oxygen.

Must mention: sunlight, water, CO2, glucose, oxygen
Equation: 6CO2 + 6H2O + light → C6H12O6 + 6O2
</pre>

<p><strong>Note:</strong> If "Information for graders" is empty, AI will grade based on general criteria like structure, coherence, and language use. The confidence level will be lower.</p>
</div>';

// Capabilities.
$string['aigrading:useaigrading'] = 'Use AI grading suggestions';

// UI strings.
$string['aisuggestgrade'] = 'AI Suggest Grade';
$string['bulkaigrade'] = 'Bulk AI Grade All';
$string['autograde'] = 'Review with AI';
$string['autogradeall'] = 'Auto Grade ALL Questions';
$string['processing'] = 'Processing...';
$string['processingprogress'] = 'Processing {$a->current} of {$a->total}...';
$string['suggestedgrade'] = 'Suggested Grade';
$string['feedback'] = 'Feedback for Student';
$string['explanation'] = 'Explanation for Teacher';
$string['applygrade'] = 'Apply Grade';
$string['applyall'] = 'Apply All Grades';
$string['cancel'] = 'Cancel';
$string['error'] = 'Error';
$string['success'] = 'Success';
$string['gradeapplied'] = 'Grade has been applied successfully.';
$string['allgradesapplied'] = 'All grades have been applied successfully.';
$string['autogradecomplete'] = 'Auto-grading complete: {$a->graded} graded, {$a->failed} failed.';
$string['autogradeconfirm'] = 'Are you sure you want to auto-grade all ungraded essays for this question? This will apply grades automatically without review.';

// Error messages.
$string['error:noapikey'] = 'Dali API key is not configured. Please configure it in plugin settings.';
$string['error:apierror'] = 'Dali API error: {$a}';
$string['error:invalidresponse'] = 'Invalid response from Dali. Please try again.';
$string['error:nopermission'] = 'You do not have permission to use AI grading.';

// Test connection.
$string['testconnection'] = 'Test Connection';
$string['testconnection_help'] = 'Click to test if the plugin can connect to the Dali API.';
$string['testconnection_noapikey'] = 'API key is not configured. Please enter an API key first.';
$string['testconnection_curlerror'] = 'Connection failed: {$a}';
$string['testconnection_success'] = 'Connection successful! The plugin can communicate with the Dali API.';
$string['testconnection_unauthorized'] = 'Connection failed: Invalid API key. Please check your API key configuration.';
$string['testconnection_serviceerror'] = 'Connection failed: Dali service error - {$a}';
$string['testconnection_httperror'] = 'Connection failed: HTTP error {$a}';

// Batch Processing Settings.
$string['batchsettings'] = 'Batch Processing Settings';
$string['batchsettings_desc'] = 'Configure how bulk grading is processed.';
$string['enablebackgroundtask'] = 'Enable Background Task';
$string['enablebackgroundtask_desc'] = 'When enabled, bulk grading will run as a background task (cron). When disabled, grading runs synchronously with progress updates.';
$string['batchsize'] = 'Batch Size';
$string['batchsize_desc'] = 'Number of answers to process in each batch (recommended: 10-20).';
$string['maxretries'] = 'Max Retries per Batch';
$string['maxretries_desc'] = 'Maximum number of retries if a batch fails.';

// Progress strings.
$string['progress:starting'] = 'Starting grading...';
$string['progress:processing'] = 'Processing answer {$a->current} of {$a->total}...';
$string['progress:completed'] = 'Grading completed: {$a->graded} graded, {$a->failed} failed.';
$string['progress:failed'] = 'Grading failed: {$a}';
$string['progress:skipped'] = 'Skipped {$a} already graded answers.';
$string['progress:batchcomplete'] = 'Batch {$a->batch} complete. {$a->graded} graded so far.';

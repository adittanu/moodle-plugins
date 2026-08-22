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
 * Language strings for local_daliwidget
 *
 * @package     local_daliwidget
 * @copyright   2024 Dali AI
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Dali AI Widget';
$string['daliwidget:view'] = 'View Dali AI Widget';

// Settings
$string['settings_heading'] = 'Dali AI Widget Settings';
$string['settings_heading_desc'] = 'Configure your connection to the Dali AI platform.';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Enter the API key from your Dali AI dashboard (My Agents > Manage > API Key).';
$string['baseurl'] = 'Base URL';
$string['baseurl_desc'] = 'The URL of your Dali AI application (e.g., https://dali-app.test).';
$string['maxuploadmb'] = 'Max upload size (MB)';
$string['maxuploadmb_desc'] = 'Maximum file size sent to Dali Knowledge Sync, in MB. Keep this equal to or below your API server upload limit.';
$string['signed_url_enabled'] = 'Enable signed URL file sync';
$string['signed_url_enabled_desc'] = 'When enabled, Moodle sends secure temporary file URLs for resource/scorm/media sync first. If the remote API rejects the URL workflow, the plugin automatically falls back to binary upload.';
$string['download_secret'] = 'Signed download secret';
$string['download_secret_desc'] = 'Secret key used to sign temporary Moodle file URLs for remote ingestion. Keep this long and private.';
$string['signed_url_baseurl'] = 'Signed URL base URL';
$string['signed_url_baseurl_desc'] = 'Optional public base URL used when generating signed file links. Use this for temporary tunnels such as Cloudflare Tunnel, for example https://your-tunnel.trycloudflare.com. Leave empty to use Moodle wwwroot.';
$string['enabled'] = 'Enable Widget';
$string['enabled_desc'] = 'When enabled, the AI chat widget will appear on all pages.';

// Widget
$string['widget_title'] = 'AI Assistant';
$string['widget_placeholder'] = 'Type a message...';
$string['widget_error'] = 'Sorry, there was an error connecting to the AI assistant.';

// Knowledge Base
$string['knowledge_base'] = 'Knowledge Base';
$string['global_knowledge_base'] = 'Global Knowledge Base';
$string['knowledge_page_title'] = 'Dali AI Knowledge Base';
$string['global_knowledge_page_title'] = 'Dali AI Global Knowledge Base';
$string['knowledge_description'] = 'Add documents, links, and videos to provide context for the AI assistant in this course.';
$string['global_knowledge_description'] = 'Manage global knowledge sources that are not attached to any Moodle course.';
$string['knowledge_scope_info'] = 'Sources added here will only be available when using AI in the "{$a}" course.';
$string['global_knowledge_scope_info'] = 'Sources on this page are global sources without a Moodle course ID. They can be used outside specific course scope.';
$string['documents'] = 'Documents';
$string['web_links'] = 'Web Links';
$string['youtube_videos'] = 'YouTube Videos';
$string['upload'] = 'Upload';
$string['add_url'] = 'Add URL';
$string['add_video'] = 'Add Video';
$string['link_name'] = 'Link name';
$string['duration'] = 'Duration';
$string['source_added'] = 'Source added successfully. It will be processed shortly.';
$string['source_deleted'] = 'Source deleted successfully.';
$string['confirm_delete'] = 'Are you sure you want to delete this source?';
$string['no_documents'] = 'No documents uploaded yet. Upload PDF, DOCX, or TXT files.';
$string['no_links'] = 'No web links added yet. Add URLs to scrape for knowledge.';
$string['no_videos'] = 'No YouTube videos added yet. Add video URLs for transcript extraction.';
$string['no_global_sources'] = 'No global knowledge sources yet.';
$string['global_retry_started'] = 'Retry started. The global source is processing again.';
$string['wordpress_connections'] = 'WordPress Connections';
$string['wordpress_connections_desc'] = 'Connect public or Application Password-protected WordPress sites. Credentials are stored only by Dali.';
$string['wordpress_add_connection'] = 'Add WordPress Connection';
$string['wordpress_edit_connection'] = 'Edit WordPress Connection';
$string['wordpress_name'] = 'Connection name';
$string['wordpress_site_url'] = 'WordPress site URL';
$string['wordpress_username'] = 'WordPress username';
$string['wordpress_application_password'] = 'Application Password';
$string['wordpress_marker_slug'] = 'Automatic marker slug';
$string['wordpress_validate'] = 'Validate';
$string['wordpress_action_success'] = 'WordPress connection updated.';
$string['wordpress_action_failed'] = 'WordPress connection request failed.';
$string['wordpress_posts'] = 'Discover posts';
$string['wordpress_taxonomy_id'] = 'Taxonomy ID';
$string['wordpress_inclusion'] = 'Inclusion';
$string['wordpress_automatic'] = 'Automatic';
$string['wordpress_manual'] = 'Manual';
$string['wordpress_pending'] = 'Pending publication';
$string['wordpress_select'] = 'Select';
$string['wordpress_cancel_selection'] = 'Cancel selection';
$string['wordpress_cancel_confirm'] = 'Cancel this manual selection? Knowledge may be removed when no automatic marker remains.';
$string['wordpress_selection_saved'] = 'WordPress post selection updated.';
$string['wordpress_removals_held'] = '{$a} proposed removals are held for site administrator review.';
$string['wordpress_approve_removals'] = 'Approve exact removals';
$string['wordpress_reject_removals'] = 'Reject removals';
$string['wordpress_review_confirm'] = 'Confirm this decision for the exact listed WordPress sources?';
$string['wordpress_recent_runs'] = 'Recent synchronization runs';
$string['wordpress_run_summary'] = '{$a->status} ({$a->trigger}): {$a->added} added, {$a->updated} updated, {$a->removed} removed, {$a->failed} failed';
$string['wordpress_resume_page'] = 'Synchronization will resume at page {$a}.';


// Activity Sync
$string['activity_sync'] = 'Activity Content Sync';
$string['activity_sync_desc'] = 'Automatically extract content from course activities (quizzes, assignments, pages, etc.) to use as AI knowledge.';
$string['sync_all_activities'] = 'Sync All Activities';
$string['sync_activity'] = 'Sync Activity';
$string['activity_synced'] = 'Activity content synced successfully.';
$string['no_activities'] = 'No activities found in this course.';
$string['showing_activities'] = 'Showing {$a->shown} of {$a->total} activities.';
$string['activity_type'] = 'Type';

// Knowledge Access Mode.
$string['knowledge_access_heading'] = 'Knowledge Access';
$string['knowledge_access_heading_desc'] = 'Control which Course Knowledge and Global Knowledge the assistant may query.';
$string['knowledge_access_mode'] = 'Knowledge Access Mode';
$string['knowledge_access_mode_desc'] = 'Course-scoped uses the Active Course plus Global Knowledge, or Global Knowledge alone outside a course. Site-wide allows all eligible visible-course knowledge.';
$string['knowledge_access_mode_course_scoped'] = 'Course-scoped';
$string['knowledge_access_mode_site_wide'] = 'Site-wide';

// Sync Mode
$string['sync_mode'] = 'Sync Mode';
$string['sync_mode_desc'] = 'Asynchronous is recommended and now the default: sync requests are queued to Moodle background tasks so large files do not block the web request. Use synchronous mode only when you need an immediate result and the payload is small.';
$string['sync_mode_sync'] = 'Synchronous (langsung)';
$string['sync_mode_async'] = 'Asynchronous (background task)';
$string['sync_queued'] = 'Sync dijadwalkan';
$string['sync_queued_desc'] = 'Activity akan disinkronkan di background. Refresh halaman untuk melihat status terbaru.';
$string['sync_status_queued'] = 'Queued';
$string['sync_status_processing'] = 'Processing';
$string['sync_status_done'] = 'Done';
$string['sync_status_failed'] = 'Failed';
$string['task_sync_activity'] = 'Dali Widget: Sync Activity to Knowledge Base';

// Debug Mode
$string['debug_mode'] = 'Debug Mode';
$string['debug_mode_desc'] = 'Enable debug logging to troubleshoot API connections and widget issues. Debug info will appear in browser console (F12) and in a debug panel on the page.';

// Debug Panel
$string['debug_panel_title'] = 'Dali AI Debug Panel';
$string['debug_api_url'] = 'API URL';
$string['debug_api_key'] = 'API Key (first 10 chars)';
$string['debug_response'] = 'API Response';
$string['debug_request'] = 'API Request';
$string['debug_status'] = 'Status';
$string['debug_timestamp'] = 'Timestamp';

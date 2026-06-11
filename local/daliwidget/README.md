# Dali AI Widget for Moodle

A Moodle local plugin that integrates the Dali AI chat assistant into your Moodle LMS.

## Requirements

- Moodle 4.4 or higher
- A Dali AI account with an API key

## Installation

### Method 1: Upload via Moodle

1. Download the plugin ZIP file
2. Go to **Site Administration → Plugins → Install plugins**
3. Upload the ZIP file and click **Install plugin from the ZIP file**
4. Follow the on-screen instructions to complete the installation

### Method 2: Manual Installation

1. Extract the ZIP file contents
2. Copy the `local_daliwidget` folder to your Moodle's `/local/` directory
3. Go to **Site Administration → Notifications**
4. Moodle will detect the new plugin and prompt you to complete the installation

## Configuration

After installation:

1. Go to **Site Administration → Plugins → Local plugins → Dali AI Widget**
2. Enter your **API Key** (found in your Dali dashboard under My Agents → Manage)
3. Set the **Base URL** to your Dali app (e.g., `https://dali-app.test`)
4. Enable or disable the widget as needed
5. Save changes

## Features

- **Automatic Injection**: The chat widget appears on all Moodle pages automatically
- **Context Awareness**: Passes user, course, and activity information to the AI
- **Role-Based Access**: Only authorized users can see the widget (configurable via capabilities)
- **Easy Configuration**: Simple settings page with API key and base URL

## Moodle Context Passed to AI

The plugin automatically provides the following context to the AI assistant:

- **User**: ID, username, full name, role(s)
- **Course**: Course ID, full name, short name (when on a course page)
- **Activity**: Activity type, ID, name (when on an activity page)
- **Page**: Page type and URL

## Uninstallation

1. Go to **Site Administration → Plugins → Plugins overview**
2. Find "Dali AI Widget" and click **Uninstall**
3. Confirm the uninstallation

## Support

For support and documentation, visit your Dali AI dashboard or contact support.

## License

This plugin is licensed under the GNU GPL v3 or later.

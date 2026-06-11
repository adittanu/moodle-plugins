# Compatibility Information

## Moodle Versions

This plugin is compatible with:
- **Moodle 4.4+** (version 2024042200 or higher)
- **Moodle 5.0+** (version 2025040800 or higher)

### Tested On:
- ✅ Moodle 4.4
- ✅ Moodle 5.0

### Supported Branches:
- Moodle 4.4 (branch 404)
- Moodle 5.0 (branch 500)

## PHP Requirements

- **PHP 8.1 or higher** is required
- Required extensions:
  - curl (for OpenAI API calls)
  - json (for data processing)
  - mbstring (for text handling)

## Browser Compatibility

The plugin interface is compatible with modern browsers:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Database Compatibility

Compatible with all databases supported by Moodle:
- MySQL 5.7+
- MariaDB 10.4+
- PostgreSQL 12+
- Microsoft SQL Server 2017+

## Third-Party Dependencies

### Required External Services:
- **OpenAI API** - For question generation
  - API endpoint: https://api.openai.com/v1/chat/completions
  - Requires active API key
  - Internet connection required

### Supported AI Models:
- GPT-3.5 Turbo (recommended)
- GPT-4
- GPT-4 Turbo

## Known Issues

### Moodle 4.4 Specific:
- None reported

### Moodle 5.0 Specific:
- None reported

## Upgrade Path

### From Future Versions:
When upgrading the plugin, simply replace files and run:
1. Site administration → Notifications
2. Follow upgrade wizard

## Version History

- **v1.0.0** (2025-10-03)
  - Initial release
  - Support for Moodle 4.4 and 5.0

## Testing

The plugin has been tested with:
- Multiple course contexts
- Different user roles (editingteacher, manager)
- Various question types
- Both English and Indonesian languages
- Different difficulty levels

## Support

For compatibility issues:
1. Check Moodle version: Site administration → Notifications
2. Check PHP version: `php -v`
3. Check curl extension: `php -m | grep curl`
4. Check plugin version: Site administration → Plugins → Local plugins
5. Contact system administrator

## Future Compatibility

We aim to maintain compatibility with:
- Current stable Moodle releases
- Next major Moodle release (when available)

Updates will be provided to ensure continued compatibility.

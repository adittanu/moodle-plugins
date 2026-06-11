# Installation Guide - AI Quiz Generator

## Prerequisites

- Moodle 4.4 or higher (tested on 4.4 and 5.0)
- PHP 8.1 or higher
- PHP curl extension enabled
- OpenAI API account

## Installation Steps

### 1. Get OpenAI API Key

Before installing the plugin, you need an OpenAI API key:

1. Visit https://platform.openai.com/
2. Sign up or login to your account
3. Navigate to **API keys** section
4. Click **Create new secret key**
5. Copy the API key (you won't be able to see it again)
6. Ensure your account has credits/billing enabled

### 2. Install Plugin

#### Option A: Manual Installation

1. Download or clone this plugin
2. Extract files to `/path/to/moodle/local/aiquizgen/`
3. Ensure directory structure is correct:
   ```
   /local/aiquizgen/
   ├── version.php
   ├── settings.php
   ├── lib.php
   ├── generate.php
   ├── index.php
   ├── README.md
   ├── classes/
   ├── db/
   └── lang/
   ```

4. Set correct permissions:
   ```bash
   cd /path/to/moodle/local/aiquizgen
   chown -R www-data:www-data .
   chmod -R 755 .
   ```

5. Login to Moodle as admin
6. Navigate to **Site administration → Notifications**
7. Follow the installation wizard
8. Click **Upgrade Moodle database now**
9. Click **Continue**

#### Option B: Git Installation

```bash
cd /path/to/moodle/local/
git clone [repository-url] aiquizgen
cd aiquizgen
chown -R www-data:www-data .
chmod -R 755 .
```

Then follow steps 5-9 from Option A.

### 3. Configure Plugin

After installation:

1. Navigate to **Site administration → Plugins → Local plugins → AI Quiz Generator**
2. Enter your OpenAI API Key in the **API Key** field
3. Select AI Model:
   - **GPT-3.5 Turbo**: Fast and cost-effective (recommended for most use cases)
   - **GPT-4**: Most accurate but slower and more expensive
   - **GPT-4 Turbo**: Balanced option
4. Set **Temperature** (default: 0.7):
   - Lower values (0.0-0.5): More focused and deterministic
   - Higher values (0.8-2.0): More creative and varied
5. Set **Max Tokens** (default: 2000):
   - Recommended: 2000-3000 for generating multiple questions
6. Set **Maximum Questions per Request** (default: 20)
7. Enable/disable **Logging** (recommended: enabled for audit)
8. Click **Save changes**

### 4. Test Connection

To verify the plugin is working:

1. Navigate to any course
2. Look for **AI Quiz Generator** link in course navigation
3. Click the link
4. Try generating 1-2 test questions
5. If successful, you'll see a preview and questions will be saved to Question Bank

## Post-Installation

### Assign Permissions

By default, the following roles can generate questions:
- Editing Teacher
- Manager

To modify permissions:

1. **Site administration → Users → Permissions → Define roles**
2. Select a role
3. Search for `local/aiquizgen:generate`
4. Modify as needed

### Verify Installation

Check that these components are working:

- [ ] Plugin appears in Site administration → Plugins → Local plugins
- [ ] Settings page is accessible
- [ ] Link appears in course navigation (for teachers)
- [ ] Generate page loads without errors
- [ ] Questions can be generated and saved
- [ ] Questions appear in Question Bank
- [ ] Logging is working (if enabled)

## Troubleshooting

### Plugin doesn't appear after installation

1. Clear all caches: **Site administration → Development → Purge all caches**
2. Check file permissions
3. Verify directory structure is correct
4. Check Moodle logs for errors

### "API key not configured" error

1. Verify API key is entered in settings
2. Check for spaces or special characters
3. Re-enter the API key
4. Save changes again

### Database installation fails

1. Check database user has CREATE TABLE permissions
2. Verify Moodle version compatibility
3. Check PHP error logs
4. Try manual database installation:
   ```sql
   -- Run the SQL from db/install.xml manually
   ```

### Permission denied errors

Fix file permissions:
```bash
cd /path/to/moodle/local/aiquizgen
chown -R www-data:www-data .
chmod -R 755 .
```

### cURL errors

Verify curl is installed and enabled:
```bash
php -m | grep curl
```

If not installed:
```bash
# Ubuntu/Debian
apt-get install php-curl

# CentOS/RHEL
yum install php-curl

# Restart web server
systemctl restart apache2  # or nginx/php-fpm
```

## Upgrading

### From Previous Version

1. Backup your database
2. Backup the plugin directory
3. Replace old plugin files with new version
4. Login as admin
5. Navigate to **Site administration → Notifications**
6. Follow upgrade process

## Uninstallation

To remove the plugin:

1. **Site administration → Plugins → Local plugins → AI Quiz Generator**
2. Click **Uninstall**
3. Confirm uninstallation
4. Delete plugin directory:
   ```bash
   rm -rf /path/to/moodle/local/aiquizgen
   ```

**Note**: Generated questions will remain in Question Bank after uninstallation.

## Security Considerations

- API keys are stored encrypted in the database
- Only users with `local/aiquizgen:generate` capability can use the plugin
- All generation activities are logged (if enabled)
- Rate limiting should be configured at firewall/proxy level
- Monitor OpenAI API usage to prevent excessive costs

## Support

For issues or questions:
- Check Moodle logs: **Site administration → Reports → Logs**
- Check PHP error logs
- Check OpenAI API status: https://status.openai.com/
- Contact system administrator

## License

GNU GPL v3 or later

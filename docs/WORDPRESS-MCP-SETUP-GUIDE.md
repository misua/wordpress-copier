# WordPress MCP Setup Guide

**Complete end-to-end guide for connecting any WordPress site to AI IDEs (OpenCode, Windsurf, Cursor, Claude Desktop)**

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Part 1: WordPress Server Setup](#part-1-wordpress-server-setup)
4. [Part 2: IDE Configuration](#part-2-ide-configuration)
5. [Part 3: Testing the Connection](#part-3-testing-the-connection)
6. [Part 4: Installing Custom Abilities](#part-4-installing-custom-abilities-optional)
7. [Troubleshooting](#troubleshooting)

---

## Overview

The WordPress MCP (Model Context Protocol) adapter allows AI assistants to interact with your WordPress site directly through APIs. This enables:

- Reading and updating page/post content
- Managing CSS and theme settings
- Creating new pages and posts
- Managing site configuration
- Custom WordPress functionality through abilities

**Architecture:**
```
AI IDE (OpenCode/Windsurf/Cursor)
    ↓
MCP Client (NPM package: @automattic/mcp-wordpress-remote)
    ↓
WordPress MCP Endpoint (https://yoursite.com/wp-json/mcp/...)
    ↓
WordPress Plugins (mcp-adapter + abilities-api)
    ↓
WordPress Database
```

---

## Prerequisites

### What You Need:

1. **WordPress Site Requirements:**
   - WordPress 6.9 or higher
   - PHP 8.0 or higher
   - Admin access to WordPress dashboard
   - HTTPS enabled (required for secure API access)

2. **Your Computer:**
   - One of these AI IDEs installed:
     - OpenCode
     - Windsurf
     - Cursor
     - Claude Desktop
   - Node.js/NPM (usually included with IDEs)
   - Text editor access to config files

3. **Time Required:** 15-30 minutes

---

## Part 1: WordPress Server Setup

### Step 1: Download Required Plugins

You need two core plugins:

1. **WordPress Abilities API** - Provides the ability framework
2. **WordPress MCP Adapter** - Exposes abilities through MCP protocol

**Download Links:**
- Abilities API: https://github.com/WordPress/abilities-api/releases
- MCP Adapter: https://github.com/WordPress/mcp-adapter/releases

**Alternative:** If you have existing plugin files (like from SharedFare setup):
- `wordpress-abilities-api.zip`
- `wordpress-mcp-adapter.zip` or `mcp-adapter-0.4.1-patched.zip` (patched version fixes hook timing issues)

---

### Step 2: Install WordPress Plugins

**Install Abilities API:**

1. Log into your WordPress admin panel: `https://yoursite.com/wp-admin`
2. Go to **Plugins → Add New Plugin**
3. Click **Upload Plugin** button at the top
4. Click **Choose File** and select `wordpress-abilities-api.zip`
5. Click **Install Now**
6. Click **Activate Plugin**

**Install MCP Adapter:**

1. Go to **Plugins → Add New Plugin**
2. Click **Upload Plugin**
3. Select `mcp-adapter-0.4.1-patched.zip` (or latest version)
4. Click **Install Now**
5. Click **Activate Plugin**

**Verify Installation:**

After both plugins are activated, you should see them in **Plugins → Installed Plugins**:
- ✅ WordPress Abilities API (Active)
- ✅ MCP Adapter (Active)

---

### Step 3: Create Application Password

WordPress uses application passwords for API authentication (more secure than using your main password).

**Create Application Password:**

1. In WordPress admin, go to **Users → Profile** (or **Users → All Users → Edit your user**)
2. Scroll down to the **Application Passwords** section
3. Under "New Application Password Name", enter: `MCP Access`
4. Click **Add New Application Password**
5. **IMPORTANT:** Copy the generated password immediately (looks like: `AbCd 1234 EfGh 5678 IjKl 9012`)
6. Save it somewhere safe - you won't be able to see it again!

**Format the Password:**
- The password is shown with spaces: `AbCd 1234 EfGh 5678 IjKl 9012`
- When using it in config, **remove the spaces**: `AbCd1234EfGh5678IjKl9012`

---

### Step 4: Verify MCP Endpoint

Test that the MCP adapter is accessible:

**Browser Test:**

Visit this URL in your browser (replace `yoursite.com`):
```
https://yoursite.com/wp-json/mcp/mcp-adapter-default-server
```

**Expected Response:**

You should see either:
- A JSON response (good!)
- A 401 error saying "Authentication required" (also good - means it's working but needs credentials)

**If you see 404 Not Found:**
- Check that both plugins are activated
- Try visiting `https://yoursite.com/wp-json/` first to verify REST API is working
- Check WordPress permalink settings (Settings → Permalinks, click "Save Changes")

---

## Part 2: IDE Configuration

Choose your IDE and follow the corresponding section:

### Option A: OpenCode

**Config File Location:**
```
~/.config/opencode/opencode.json
```
(On Windows: `%APPDATA%\opencode\opencode.json`)

**Add this configuration:**

```json
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "wordpress-yoursite": {
      "type": "local",
      "command": ["npx", "-y", "@automattic/mcp-wordpress-remote@latest"],
      "enabled": true,
      "environment": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "AbCd1234EfGh5678IjKl9012",
        "LOG_FILE": "/tmp/wordpress-mcp.log"
      }
    }
  }
}
```

**Replace these values:**
- `wordpress-yoursite` - Any name you want (e.g., `wordpress-myblog`)
- `https://yoursite.com` - Your actual WordPress site URL
- `your-wordpress-username` - Your WordPress admin username
- `AbCd1234EfGh5678IjKl9012` - Your application password (no spaces)
- `/tmp/wordpress-mcp.log` - Log file path (on Windows use `C:\\Temp\\wordpress-mcp.log`)

**Save the file and restart OpenCode.**

---

### Option B: Windsurf

**Config File Location:**
```
~/.config/Windsurf/mcp.json
```
(On Windows: `%APPDATA%\Windsurf\mcp.json`)

**If the file doesn't exist, create it with this content:**

```json
{
  "mcpServers": {
    "wordpress-yoursite": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "AbCd1234EfGh5678IjKl9012",
        "LOG_FILE": "/tmp/wordpress-mcp.log"
      }
    }
  }
}
```

**If the file already exists**, add the `wordpress-yoursite` section inside the existing `mcpServers` object.

**Replace the same values as OpenCode above.**

**Save the file and restart Windsurf.**

---

### Option C: Cursor

**Config File Location:**
```
~/.config/cursor/mcp.json
```
(On Windows: `%APPDATA%\cursor\mcp.json`)

**Use the same configuration as Windsurf** (they use the same format):

```json
{
  "mcpServers": {
    "wordpress-yoursite": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "AbCd1234EfGh5678IjKl9012",
        "LOG_FILE": "/tmp/wordpress-mcp.log"
      }
    }
  }
}
```

**Save and restart Cursor.**

---

### Option D: Claude Desktop

**Config File Location:**
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`
- **Linux:** `~/.config/claude/claude_desktop_config.json`

**Configuration:**

```json
{
  "mcpServers": {
    "wordpress-yoursite": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "AbCd1234EfGh5678IjKl9012"
      }
    }
  }
}
```

**Note:** Claude Desktop may not support LOG_FILE, so it's omitted above.

**Save and restart Claude Desktop.**

---

## Part 3: Testing the Connection

### Test 1: Discover Available Abilities

Open your IDE and ask the AI assistant:

```
Can you list all available WordPress abilities?
```

Or use the tool directly:
```
Use the wordpress-yoursite_mcp-adapter-discover-abilities tool
```

**Expected Result:**

You should see a list of abilities including at least these 3 core abilities:
- `mcp-adapter/discover-abilities`
- `mcp-adapter/get-ability-info`
- `mcp-adapter/execute-ability`

**Example output:**
```json
{
  "abilities": [
    "mcp-adapter/discover-abilities",
    "mcp-adapter/get-ability-info",
    "mcp-adapter/execute-ability"
  ]
}
```

---

### Test 2: Get Site Information

Ask the AI:
```
Get my WordPress site information
```

Or execute directly:
```javascript
wordpress-yoursite_mcp-adapter-execute-ability({
  ability_name: "core/get-site-info",
  parameters: {}
})
```

**Expected Result:**

```json
{
  "success": true,
  "data": {
    "name": "Your Site Name",
    "url": "https://yoursite.com",
    "version": "6.9.1",
    "admin_email": "admin@yoursite.com"
  }
}
```

---

### Test 3: List Pages

Ask the AI:
```
List all WordPress pages on my site
```

**Expected Result:**

Should return a list of your WordPress pages with titles, slugs, and URLs.

---

### ✅ If All Tests Pass

**Congratulations!** Your WordPress MCP connection is working correctly. You can now:
- Ask the AI to read/update content
- Manage pages and posts
- Update site settings
- Use any available WordPress abilities

---

## Part 4: Installing Custom Abilities (Optional)

Custom abilities extend what the AI can do with your WordPress site. The SharedFare example includes abilities for CSS management, page/post management, and theme customization.

### What Are Custom Abilities?

Custom abilities are WordPress plugins that register new functions the AI can call. Examples:
- `sharedfare/update-custom-css` - Update site CSS directly
- `sharedfare/create-page` - Create new WordPress pages
- `sharedfare/get-theme-mods` - Get theme settings

---

### Creating Your Own Custom Abilities Plugin

**Example: Simple "Get Post Count" Ability**

Create a new WordPress plugin file: `my-custom-abilities.php`

```php
<?php
/**
 * Plugin Name: My Custom WordPress Abilities
 * Description: Custom abilities for MCP access
 * Version: 1.0.0
 * Requires Plugins: abilities-api, mcp-adapter
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom ability category
 */
add_action('wp_abilities_api_categories_init', 'my_register_ability_categories');
function my_register_ability_categories() {
    wp_register_ability_category('my-custom', [
        'label' => __('My Custom Abilities'),
        'description' => __('Custom abilities for my WordPress site')
    ]);
}

/**
 * Register custom abilities
 */
add_action('wp_abilities_api_init', 'my_register_custom_abilities');
function my_register_custom_abilities() {
    
    // Example: Get post count
    wp_register_ability('my-custom/get-post-count', [
        'label' => __('Get Post Count'),
        'description' => __('Returns the total number of published posts'),
        'category' => 'my-custom',
        'input_schema' => [
            'type' => 'object',
            'properties' => (object)[],
            'additionalProperties' => false,
            'default' => []
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'post_count' => ['type' => 'integer'],
                'page_count' => ['type' => 'integer']
            ]
        ],
        'execute_callback' => function($input = []) {
            $post_count = wp_count_posts('post');
            $page_count = wp_count_posts('page');
            
            return [
                'success' => true,
                'data' => [
                    'post_count' => (int) $post_count->publish,
                    'page_count' => (int) $page_count->publish
                ]
            ];
        }
    ]);
    
    // Make ability available via MCP
    add_filter('wp_register_ability_args', 'my_add_mcp_metadata', 10, 2);
}

/**
 * Add MCP metadata to make abilities discoverable
 */
function my_add_mcp_metadata($args, $name) {
    // Add MCP metadata to all our custom abilities
    if (strpos($name, 'my-custom/') === 0) {
        $args['meta']['mcp']['public'] = true;
        $args['meta']['mcp']['type'] = 'tool';
    }
    return $args;
}
```

**Install Your Custom Plugin:**

1. Save the code above as `my-custom-abilities.php`
2. Zip the file: `zip my-custom-abilities.zip my-custom-abilities.php`
3. Upload to WordPress: Plugins → Add New → Upload Plugin
4. Activate the plugin
5. Test by asking AI: "Get my post count using the custom ability"

---

### Installing SharedFare Example Abilities

If you have the SharedFare abilities plugin (includes 14 content and design management abilities):

1. Upload `sharedfare-abilities-v2.zip` to WordPress
2. Activate the plugin
3. You'll gain these abilities:
   - Content: list/get/create/update pages and posts
   - Design: get/update CSS, theme mods, bulk updates
   - Debug: comprehensive theme info

**Test:**
```
Update my site's custom CSS to make the header background blue
```

---

## Troubleshooting

### Issue: "MCP server not found" or tools not available

**Solutions:**
1. Verify config file syntax is valid JSON (use https://jsonlint.com)
2. Check that file path is correct for your OS
3. Restart your IDE completely
4. Check IDE logs for MCP connection errors

---

### Issue: "Authentication failed" or 401 errors

**Solutions:**
1. Verify application password was copied correctly (no spaces)
2. Check username is correct (case-sensitive)
3. Try regenerating the application password
4. Verify REST API is enabled in WordPress (Settings → Permalinks → Save)

**Test authentication manually with curl:**
```bash
curl -u "username:AbCd1234EfGh5678IjKl9012" \
  "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server"
```

Should return JSON, not an error.

---

### Issue: "discover-abilities returns empty array"

**Possible Causes:**

1. **MCP metadata missing:** Abilities need `mcp.public = true` metadata to be discoverable

**Solution:** Install the "Abilities Enabler" plugin:

```php
<?php
/**
 * Plugin Name: MCP Abilities Enabler
 * Description: Makes core WordPress abilities visible via MCP
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_filter('wp_register_ability_args', 'enable_core_abilities_mcp', 10, 2);

function enable_core_abilities_mcp($args, $name) {
    // Enable core WordPress abilities
    $core_abilities = [
        'core/get-site-info',
        'core/get-user-info',
        'core/get-environment-info'
    ];
    
    if (in_array($name, $core_abilities)) {
        $args['meta']['mcp']['public'] = true;
        $args['meta']['mcp']['type'] = 'tool';
    }
    
    return $args;
}
```

Save as `mcp-abilities-enabler.php`, zip, and install.

2. **Hook timing issue:** The MCP adapter may have a bug where abilities aren't registered in time

**Solution:** Use the patched version `mcp-adapter-0.4.1-patched.zip` (fixes GitHub issue #117)

---

### Issue: "Changes not visible on site"

**Cause:** Server-side caching (common with hosts like SiteGround, WP Engine, Cloudflare)

**Solutions:**

1. **Check if logged in:** Log out and view site in incognito/private window
2. **Flush WordPress cache:** Install WP Super Cache or W3 Total Cache and flush
3. **Flush hosting cache:**
   - **SiteGround:** Site Tools → Speed → Caching → Flush Cache
   - **WP Engine:** WP Engine dashboard → Purge all caches
   - **Cloudflare:** Dashboard → Caching → Purge Everything
4. **Disable caching temporarily** while testing MCP changes

---

### Issue: "Ability execution fails with validation error"

**Example Error:**
```
Ability "my-ability" has invalid input. Reason: input is not of type object.
```

**Solution:** Check your input_schema:

**Incorrect (causes error):**
```php
'input_schema' => [
    'type' => 'object',
    'properties' => [],  // This is an array, not object!
]
```

**Correct:**
```php
'input_schema' => [
    'type' => 'object',
    'properties' => (object)[],  // Cast to object
    'additionalProperties' => false,
    'default' => []  // Required for abilities with no parameters
]
```

---

### Issue: "Connection timeout" or "No response"

**Solutions:**

1. Check WordPress site is accessible: visit `https://yoursite.com`
2. Check REST API works: visit `https://yoursite.com/wp-json/`
3. Verify firewall isn't blocking API requests
4. Check WordPress debug logs for PHP errors
5. Increase timeout in MCP config (if supported by your IDE)

---

### Issue: "npx command not found"

**Cause:** Node.js/NPM not installed or not in PATH

**Solutions:**

1. Install Node.js from https://nodejs.org/
2. Restart your terminal/IDE after installation
3. Test: run `npx --version` in terminal
4. If using nvm, make sure it's activated: `nvm use node`

---

## Check Logs for Debugging

**View MCP logs:**

**Linux/macOS:**
```bash
tail -f /tmp/wordpress-mcp.log
```

**Windows:**
```cmd
type C:\Temp\wordpress-mcp.log
```

**What to look for:**
- Connection attempts
- Authentication failures
- API endpoint responses
- Error messages from WordPress

---

## Security Best Practices

1. **Use Application Passwords:** Never use your main WordPress password in config files
2. **Limit User Permissions:** Create a dedicated user with only necessary capabilities
3. **Use HTTPS:** Always use `https://` URLs for API endpoints
4. **Rotate Passwords:** Regenerate application passwords periodically
5. **Restrict Access:** Consider IP whitelisting for API access (via firewall/security plugin)
6. **File Permissions:** Keep config files readable only by your user:
   ```bash
   chmod 600 ~/.config/opencode/opencode.json
   ```

---

## Next Steps

### Learn More About Abilities

**WordPress Abilities API Documentation:**
- GitHub: https://github.com/WordPress/abilities-api

**MCP Adapter Documentation:**
- GitHub: https://github.com/WordPress/mcp-adapter

**Model Context Protocol:**
- Specification: https://modelcontextprotocol.io/

---

### Common Use Cases

**Content Management:**
```
"Create a new page called 'About Us' with a hero section"
"Update the homepage content with new copy"
"List all draft posts"
```

**Design Changes:**
```
"Change the site's primary color to #3EAFFA"
"Update the custom CSS to make headers bold"
"Get the current theme settings"
```

**Site Maintenance:**
```
"Get site information and WordPress version"
"List all users with admin access"
"Check environment configuration"
```

---

## Support and Resources

### Get Help

1. **WordPress Support Forums:** https://wordpress.org/support/
2. **MCP GitHub Issues:** https://github.com/WordPress/mcp-adapter/issues
3. **Your IDE's Support:** Check IDE-specific documentation

### Example Files

This guide references example files from the SharedFare implementation:
- `sharedfare-abilities-v2.zip` - 14 custom abilities for content and design
- `mcp-adapter-0.4.1-patched.zip` - Patched MCP adapter (fixes hook timing)
- `operational_memory.md` - Comprehensive workflow documentation

---

## Summary Checklist

- [ ] WordPress 6.9+ with HTTPS enabled
- [ ] Installed WordPress Abilities API plugin
- [ ] Installed WordPress MCP Adapter plugin (patched version recommended)
- [ ] Created application password for WordPress user
- [ ] Added MCP configuration to IDE config file
- [ ] Restarted IDE
- [ ] Tested: discover-abilities returns results
- [ ] Tested: can get site info
- [ ] Tested: can list pages
- [ ] (Optional) Installed custom abilities plugin
- [ ] (Optional) Configured cache flushing workflow

---

**Congratulations!** You now have a fully functional WordPress MCP setup. Your AI assistant can interact with your WordPress site directly through natural language commands.

**Version:** 1.0  
**Last Updated:** March 6, 2026  
**Author:** SharedFare Team / Cascade AI Assistant

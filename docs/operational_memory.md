# SharedFare WordPress - Operational Memory

**Date Created:** March 5, 2026  
**Last Updated:** March 5, 2026 (Session 4)

---

## Critical Issues & Resolutions

### Issue #1: WordPress Critical Error (HTTP 500)
**Date:** March 5, 2026  
**Status:** ✅ RESOLVED

#### Problem
The WordPress site at https://sharedfare.com.au was displaying "There has been a critical error on this website" with HTTP 500 error on the frontend.

#### Root Cause
The **Fodge child theme** was causing a fatal PHP error that prevented the site from loading.

#### Resolution
- Switched the active theme from **Fodge** to **MaxxBizz** theme
- Site is now functioning correctly
- WordPress admin panel (`/wp-admin`) remained accessible throughout the issue

#### Key Learning
When WordPress shows a critical error but admin panel is accessible, the issue is typically with the active theme or a frontend-only plugin.

---

### Issue #2: MCP Adapter Showing No WordPress Abilities
**Date:** March 5, 2026  
**Status:** ✅ RESOLVED

#### Problem
The WordPress MCP adapter was installed and functioning, but `discover-abilities` was returning an empty array `{"abilities":[]}` even though WordPress has built-in core abilities.

#### Root Cause
The MCP adapter filters abilities based on the `mcp.public` metadata flag. Core WordPress abilities (`core/get-site-info`, `core/get-user-info`, `core/get-environment-info`) are registered WITHOUT this metadata flag, causing them to be filtered out by the MCP adapter's discovery mechanism.

**Technical Details:**
- MCP adapter code location: `includes/Abilities/DiscoverAbilitiesAbility.php`
- Filter check: `if ( ! self::is_ability_mcp_public( $ability ) ) { continue; }`
- Required metadata: `$meta['mcp']['public'] = true`

#### Resolution
Created a custom WordPress plugin: **SharedFare MCP Abilities Enabler** (v1.0.1)

**Plugin Location:** `/home/hn/Desktop/sharedfare/sharedfare-mcp-enabler.zip`

**What it does:**
- Hooks into the `wp_register_ability_args` filter
- Adds `mcp.public = true` and `mcp.type = 'tool'` metadata to core WordPress abilities
- Enables the following abilities to be exposed via MCP:
  - `core/get-site-info`
  - `core/get-user-info`
  - `core/get-environment-info`

**Installation Steps:**
1. Go to WordPress admin → Plugins → Add New Plugin → Upload Plugin
2. Upload `sharedfare-mcp-enabler.zip`
3. Activate the plugin
4. Core abilities will now be visible through MCP adapter

---

### Issue #4: SiteGround Cache Causing Content Discrepancy Between Browsers
**Date:** March 5, 2026 (Session 2 - first noted; Session 4 - confirmed root cause)
**Status:** ⚠️ PENDING - Requires manual cache flush

#### Problem
Updated site content (home page HTML, CSS changes) shows correctly when logged into WordPress admin, but other browsers (e.g. Brave, incognito) show the old version of the site.

#### Root Cause
SiteGround runs a server-level Nginx proxy cache called **Dynamic Cache**. This is independent of WordPress - the `sg-cachepress` plugin being inactive does NOT disable it. The cache is confirmed active via response header:
```
x-proxy-cache-info: DT:1
```
Logged-in WordPress users automatically bypass the cache (SiteGround detects the WordPress login cookie). Anonymous/logged-out visitors are served the cached stale version.

This has nothing to do with Elementor. The page content in the WordPress database is correct and up to date. The only problem is the cache serving old content to anonymous visitors.

#### Resolution
Flush the SiteGround Dynamic Cache manually:
1. Log into **SiteGround Site Tools**
2. Go to **Speed → Caching**
3. Under **Dynamic Cache**, click **Flush Cache**
4. Verify the site shows updated content in an incognito/private window

#### Key Learning
Always flush the SiteGround cache after making content or CSS changes. The WordPress admin view bypasses cache and will always show fresh content — this does NOT mean the changes are live for public visitors.

---

### Issue #5: WordPress Abilities API Rejects Empty Parameter Objects
**Date:** March 5, 2026 (Session 4)
**Status:** ✅ RESOLVED in v2.1.3

#### Problem
Abilities with no required input parameters (e.g. `get-custom-css`, `get-theme-mods`, `debug-theme-info`) failed with:
```
Ability "sharedfare/get-custom-css" has invalid input. Reason: input is not of type object.
```
This happened even when calling with an empty object `{}`.

#### Root Cause
Two bugs combined:

1. **PHP array vs JSON object**: In PHP, `'properties' => []` serializes to JSON as `[]` (an array), not `{}` (an object). The Abilities API's JSON Schema validator expects `properties` to be an object. Fix: use `'properties' => (object)[]`.

2. **Missing `default` key**: The WordPress Abilities API requires `'default' => []` in the `input_schema` for abilities that accept empty input. This was discovered by comparing our schemas against working core WordPress abilities (e.g. `core/get-site-info`), which all include this key.

#### Resolution
Added both fixes to all affected abilities in `sharedfare-abilities-v2.php`:
```php
'input_schema' => [
    'type' => 'object',
    'properties' => (object)[],      // (object)[] not [] - must be JSON object
    'additionalProperties' => false,
    'default' => []                  // Required by Abilities API for empty-param abilities
],
```
Also updated `execute_callback` signatures to accept `$input = []` even when unused, matching the core abilities pattern.

#### Plugin Versions Involved
- v2.1.0 - Had bug: `'properties' => []` (array, not object)
- v2.1.1 - Fixed to `'properties' => (object)[]` but still missing `'default' => []`
- v2.1.2 - Removed `additionalProperties` (wrong approach, reverted)
- v2.1.3 - **FINAL FIX**: Added `'default' => []` + correct object cast + restored `additionalProperties`

---

### Issue #3: MCP Adapter Default Abilities Not Registering (GitHub Issue #117)
**Date:** March 5, 2026  
**Status:** ✅ RESOLVED

#### Problem
The MCP adapter's three default abilities (`mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`) were not being registered properly, even though they should work by default.

#### Root Cause
**Hook Timing Mismatch:**

The MCP adapter has a critical timing bug in how it registers abilities:

1. `McpAdapter::instance()` schedules initialization on the `rest_api_init` hook (line 64)
2. During initialization, `maybe_create_default_server()` tries to add actions to `wp_abilities_api_categories_init` and `wp_abilities_api_init` hooks (lines 261-262)
3. **BUT:** The `wp_abilities_api_init` hook fires during WordPress's `init` action
4. **PROBLEM:** The `rest_api_init` hook fires AFTER the `init` action
5. **RESULT:** By the time the MCP adapter tries to register its abilities, the ability registration hooks have already fired and finished

**In other words:** The MCP adapter was arriving to the party after everyone had already gone home.

#### Solution
Applied the fix from GitHub issue #117: https://github.com/WordPress/mcp-adapter/issues/117

**The Fix:**
1. Created new method `register_ability_hooks()` that registers ability hooks immediately
2. Modified `instance()` method to call `register_ability_hooks()` BEFORE scheduling deferred initialization
3. Modified `maybe_create_default_server()` to only handle server creation (removed ability registration)

**Technical Implementation:**

**Location:** `/tmp/mcp-adapter/includes/Core/McpAdapter.php`

**Changes Made:**

1. In `instance()` method (line 62):
```php
// Register ability hooks IMMEDIATELY so they fire before wp_abilities_api_init
self::$instance->register_ability_hooks();
```

2. Added new method `register_ability_hooks()` (lines 76-95):
```php
/**
 * Register ability hooks immediately to ensure they fire before wp_abilities_api_init
 *
 * This method must be called during instance() creation to work around the hook timing
 * issue where wp_abilities_api_init fires during the init action, but the MCP adapter
 * was trying to register abilities during rest_api_init (which happens AFTER init).
 *
 * @internal This method must be called during instance() creation
 * @see https://github.com/WordPress/mcp-adapter/issues/117
 */
private function register_ability_hooks(): void {
    // Allow disabling default server creation
    if ( ! apply_filters( 'mcp_adapter_create_default_server', true ) ) {
        return;
    }
    
    // Register category before abilities
    add_action( 'wp_abilities_api_categories_init', array( $this, 'register_default_category' ) );
    add_action( 'wp_abilities_api_init', array( $this, 'register_default_abilities' ) );
}
```

3. Modified `maybe_create_default_server()` (lines 280-289):
```php
private function maybe_create_default_server(): void {
    // Allow disabling default server creation
    if ( ! apply_filters( 'mcp_adapter_create_default_server', true ) ) {
        return;
    }
    
    // Ability hooks are now registered in register_ability_hooks() to fix timing issue
    // See: https://github.com/WordPress/mcp-adapter/issues/117
    add_action( 'mcp_adapter_init', array( DefaultServerFactory::class, 'create' ) );
}
```

#### Patched Plugin
**File:** `/home/hn/Desktop/sharedfare/mcp-adapter-0.4.1-patched.zip`  
**Version:** 0.4.1-patched  
**Size:** 124KB

**Installation Steps:**
1. Go to WordPress admin → Plugins
2. Deactivate and delete the existing MCP Adapter plugin (v0.4.1)
3. Go to Plugins → Add New Plugin → Upload Plugin
4. Upload `mcp-adapter-0.4.1-patched.zip`
5. Activate the plugin
6. Test by calling `discover-abilities` - should now show the 3 default abilities

**Expected Result After Fix:**
The `discover-abilities` endpoint should now return:
- `mcp-adapter/discover-abilities`
- `mcp-adapter/get-ability-info`
- `mcp-adapter/execute-ability`

---

## WordPress MCP Setup Configuration

### MCP Server Configuration
**Config File:** `~/.config/opencode/opencode.json`

```json
{
  "wordpress-sharedfare": {
    "type": "local",
    "command": ["npx", "-y", "@automattic/mcp-wordpress-remote@latest"],
    "enabled": true,
    "environment": {
      "WP_API_URL": "https://sharedfare.com.au/wp-json/mcp/mcp-adapter-default-server",
      "WP_API_USERNAME": "seriesdev",
      "WP_API_PASSWORD": "Sb1IT8lwSXzZIGnfyJDNW2Jv",
      "LOG_FILE": "/tmp/wordpress-mcp.log"
    }
  }
}
```

### Installed WordPress Plugins
1. **MCP Adapter** (v0.4.1-patched-v2) - Exposes WordPress abilities through Model Context Protocol
   - Endpoint: `https://sharedfare.com.au/wp-json/mcp/mcp-adapter-default-server`
   - Patched to fix hook timing bug (GitHub Issue #117)

2. **Abilities API** - Provides WordPress abilities infrastructure
   - REST API: `https://sharedfare.com.au/wp-json/wp-abilities/v1/`

3. **SharedFare MCP Abilities Enabler** (v1.0.1) - Adds `mcp.public=true` to core WordPress abilities
   - Source: `/home/hn/Desktop/sharedfare/sharedfare-mcp-enabler.zip`

4. **SharedFare Abilities v2** (v2.1.3) - 14 custom MCP abilities for content + design management
   - Source: `/home/hn/Desktop/sharedfare/sharedfare-abilities-v2.zip`
   - Plugin dir: `/home/hn/Desktop/sharedfare/sharedfare-abilities-v2/`

5. **SharedFare CSS Injector v2** (active) - Injects brand CSS into the site frontend
   - Handles brand color (#3EAFFA), Segoe UI font, custom layout styles

---

## Available WordPress Abilities via MCP

### Quick Start Guide

**How to Use MCP Abilities (OpenCode, Windsurf, Cursor):**

All abilities are accessed through the same pattern:
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "ability/name-here",
  parameters: { /* your parameters */ }
})
```

**Common Tasks:**

1. **Update CSS:** `sharedfare/update-custom-css` with `{ css: "..." }` parameter
2. **Get page content:** `sharedfare/get-page` with `{ page_id: 2 }` or `{ slug: "home" }`
3. **Update page:** `sharedfare/update-page` with `{ page_id: 2, content: "...", title: "..." }`
4. **Get theme colors:** `sharedfare/get-theme-mods` with `{ mod_names: ["main_color"] }`
5. **Update brand color:** `sharedfare/update-theme-mod` with `{ mod_name: "main_color", value: "#3EAFFA" }`

**Important:** After CSS or content changes, flush SiteGround cache (Site Tools > Speed > Caching > Flush Cache) for public visitors to see updates.

---

### Custom Abilities (sharedfare-abilities-v2 v2.1.3)

All 14 abilities tested and confirmed working as of March 5, 2026 Session 4.

#### Phase 1: Content Management (8 abilities)
| Ability | Description | Parameters |
|---|---|---|
| `sharedfare/list-pages` | List pages with filters | number, offset, status, search |
| `sharedfare/get-page` | Get page by ID or slug | page_id OR slug |
| `sharedfare/update-page` | Update page properties | page_id, title, content, status, etc. |
| `sharedfare/create-page` | Create new page | title, content, status, parent_id, etc. |
| `sharedfare/list-posts` | List posts with filters | number, offset, status, category, search |
| `sharedfare/get-post` | Get post by ID or slug | post_id OR slug |
| `sharedfare/update-post` | Update post + categories/tags | post_id, title, content, categories, tags |
| `sharedfare/create-post` | Create new post | title, content, categories, tags, etc. |

#### Phase 2: Design Management (6 abilities)
| Ability | Description | Parameters |
|---|---|---|
| `sharedfare/get-custom-css` | Get current custom CSS | _(none)_ |
| `sharedfare/update-custom-css` | Replace site custom CSS | css (string, max 65535 chars) |
| `sharedfare/get-theme-mods` | Get theme customizations | mod_names (optional array) |
| `sharedfare/update-theme-mod` | Update single theme setting | mod_name, value |
| `sharedfare/bulk-update-theme-mods` | Update multiple settings | mods (object of key-value pairs) |
| `sharedfare/debug-theme-info` | Diagnostic: theme name, mods, CSS, menus | _(none)_ |

**Known theme mods (Maxbizz v1.2.3.15):**
- `main_color` - Primary brand color (currently `#fe5630`)
- `body_typo` - Body font settings object
- `heading1_typo` through `heading6_typo` - Heading font settings (currently Montserrat 500)
- `header_layout`, `footer_layout`, `sidepanel_layout` - Layout template IDs
- `blog_layout`, `blog_style` - Blog display settings
- `pheader_img` - Page header background image URL
- `is_sidepanel`, `panel_left` - Sidepanel visibility/position

---

### Core Abilities

### 1. core/get-site-info
**Description:** Returns site information configured in WordPress

**Usage:**
```javascript
{
  "ability_name": "core/get-site-info",
  "parameters": {
    "fields": ["name", "url", "version"] // Optional: limit to specific fields
  }
}
```

**Returns:**
- `name` - Site title
- `description` - Site tagline
- `url` - Site home URL
- `wpurl` - WordPress installation URL
- `admin_email` - Administrator email
- `charset` - Character encoding (UTF-8)
- `language` - Locale code (en-US)
- `version` - WordPress version (6.9.1)

**Permissions:** Requires `manage_options` capability

---

### 2. core/get-user-info
**Description:** Returns basic profile details for the current authenticated user

**Usage:**
```javascript
{
  "ability_name": "core/get-user-info",
  "parameters": {}
}
```

**Returns:**
- `id` - User ID
- `display_name` - Display name
- `user_nicename` - URL-friendly name
- `user_login` - Login username
- `roles` - Array of user roles
- `locale` - User locale string

**Permissions:** Requires authenticated user

---

### 3. core/get-environment-info
**Description:** Returns core details about the site's runtime context

**Usage:**
```javascript
{
  "ability_name": "core/get-environment-info",
  "parameters": {}
}
```

**Returns:**
- `environment` - Runtime environment (production/staging/development/local)
- `php_version` - PHP version (8.2.30)
- `db_server_info` - Database server version (8.4.5-5)
- `wp_version` - WordPress version (6.9.1)

**Permissions:** Requires `manage_options` capability

---

## Using MCP Adapter Tools

### Discovery
```javascript
// List all available abilities
wordpress-sharedfare_mcp-adapter-discover-abilities()
// Returns: {"abilities": [...]}
```

### Get Ability Details
```javascript
// Get detailed info about a specific ability
wordpress-sharedfare_mcp-adapter-get-ability-info({
  "ability_name": "core/get-site-info"
})
```

### Execute Ability
```javascript
// Execute any WordPress ability
wordpress-sharedfare_mcp-adapter-execute-ability({
  "ability_name": "core/get-site-info",
  "parameters": {}
})
// Returns: {"success": true, "data": {...}}
```

---

## Site Information

**WordPress Site:** https://sharedfare.com.au  
**WordPress Admin:** https://sharedfare.com.au/wp-admin  
**Current Theme:** MaxxBizz  
**WordPress Version:** 6.9.1  
**PHP Version:** 8.2.30  
**Database:** MySQL 8.4.5-5  
**Environment:** Production  

**Admin User:** seriesdev  
**Admin Email:** xobicoronel@gmail.com  

---

## Troubleshooting

### MCP Adapter Not Showing Abilities
**Symptoms:** `discover-abilities` returns empty array

**Checks:**
1. Verify Abilities API plugin is active
2. Verify MCP Adapter plugin is active
3. Verify SharedFare MCP Abilities Enabler plugin is active
4. Check abilities directly via REST API:
   ```bash
   curl -u "seriesdev:PASSWORD" "https://sharedfare.com.au/wp-json/wp-abilities/v1/abilities"
   ```
5. Check MCP logs: `tail -f /tmp/wordpress-mcp.log`

### WordPress Critical Error
**Symptoms:** "There has been a critical error on this website"

**Checks:**
1. Can you access `/wp-admin`? If yes, likely a theme issue
2. Try switching to a default WordPress theme
3. Try deactivating plugins one by one
4. Check PHP error logs (requires server access)

### MCP Connection Issues
**Symptoms:** MCP tools not responding or timing out

**Checks:**
1. Verify MCP server is running: check OpenCode MCP status
2. Check credentials in `~/.config/opencode/opencode.json`
3. Verify site is accessible: `curl https://sharedfare.com.au/wp-json/`
4. Check application password is valid (regenerate if needed)
5. Review logs: `tail -f /tmp/wordpress-mcp.log`

---

## Future Enhancements

### Potential Additional Abilities to Create
- Post management (create, read, update, delete posts)
- Media library management (upload, list, delete media)
- Plugin management (list, activate, deactivate)
- User management (create, list, update users)
- Menu management
- Widget management
- Site options management

### How to Create Custom Abilities
To expose more WordPress functionality via MCP, you can either:

1. **Create new WordPress abilities** using `wp_register_ability()` with `mcp.public=true` metadata
2. **Extend the MCP Abilities Enabler plugin** to include additional core or custom abilities

**Example:**
```php
add_filter( 'wp_register_ability_args', 'add_mcp_metadata', 10, 2 );
function add_mcp_metadata( $args, $name ) {
    if ( $name === 'your-ability-name' ) {
        $args['meta']['mcp']['public'] = true;
        $args['meta']['mcp']['type'] = 'tool';
    }
    return $args;
}
```

---

## Important Files & Locations

**Local Machine:**
- MCP Config: `~/.config/opencode/opencode.json`
- MCP Logs: `/tmp/wordpress-mcp.log`
- Plugin Files: `/home/hn/Desktop/sharedfare/`
- This Document: `/home/hn/Desktop/sharedfare/operational_memory.md`

**WordPress Server:**
- Plugin Directory: `/wp-content/plugins/`
- Theme Directory: `/wp-content/themes/`
- MCP Adapter: `/wp-content/plugins/mcp-adapter/`
- Abilities API: `/wp-content/plugins/abilities-api/`

---

## Contact & Support

**Site Owner:** SharedFare  
**Developer:** seriesdev  
**Email:** xobicoronel@gmail.com  
**Address:** Level 3, 320 Pitt Street, Sydney, NSW 2000  
**Phone:** 02 7228 8177  
**ABN:** 69202510648  

---

## Change Log

### March 5, 2026 - Session 1 (Morning)
- ✅ Resolved WordPress critical error by switching from Fodge to MaxxBizz theme
- ✅ Created SharedFare MCP Abilities Enabler plugin (v1.0.1)
- ✅ Successfully exposed core WordPress abilities via MCP adapter
- ✅ Verified all three core abilities are functional
- 📝 Created operational memory document

### March 5, 2026 - Session 2 (Afternoon)
- ✅ Updated brand colors to #3EAFFA (RGB 62, 175, 250) - correct SharedFare blue
- ✅ Updated primary font to Segoe UI across the site
- ✅ Created SharedFare CSS Injector v2 plugin (v2.1.0) with all custom styles
- ✅ Created new WordPress pages: Plans, Coverage, Claims
- ✅ Updated Home page content with:
  - Hero section with gradient background
  - Coverage Plans section (3 plan cards)
  - What Our Coverage Includes section (3 feature cards)
  - Separate FAQ sections for Single Car Cover and Fleet Cover
- ✅ Updated Fleet Coverage page with Key Benefits, What's Included, Pricing Tiers
- ✅ Updated navigation menu structure (Home, Plans, Coverage, Claims)
- ⚠️ **PENDING:** SiteGround Dynamic Cache needs to be flushed to display updated content
- 🔧 Removed old Elementor content from Home page to prevent conflicts

### March 6, 2026 - Session 3 (Morning)
- ✅ Configured WordPress MCP server in Windsurf (wordpress-sharedfare)
- ✅ Updated CSS to match reference design:
  - Single black underline below section headings (not blue lines on sides)
  - Header with darker blue background (#2B8FD9) and white "SharedFare" text
  - Removed white separator line between header and hero section
  - Hidden footer section with address and NIBA logo
  - All hero heading text in white
- ✅ Updated Coverage page with FAQ sections matching reference design
- ✅ Used WordPress REST API to update page content directly
- 🔧 **Current workflow:** CSS changes require updating and re-uploading plugin file

### March 5, 2026 - Session 3 (Late Afternoon)
- ✅ Identified and documented MCP Adapter bug (GitHub Issue #117)
- ✅ Applied fix for hook timing issue in McpAdapter.php
- ✅ Created patched MCP Adapter plugin (v0.4.1-patched)
- ✅ Updated operational memory with bug details and fix implementation
- 📝 Documented Fodge theme dependencies and compatibility issues
- 📝 Created fixed Fodge functions.php with graceful plugin handling

### March 5, 2026 - Session 4 (MCP Phase 1 & 2 Testing + Bugfixes)
- ✅ Confirmed all 14 custom abilities registered and discoverable via MCP
- ✅ Tested all Phase 1 content abilities (list/get/create/update pages and posts)
- ✅ Found and fixed input schema bug in Phase 2 abilities (Issues #4 and #5 above)
- ✅ Released plugin v2.1.3 with all fixes applied
- ✅ Tested all Phase 2 design abilities: get/update CSS, get/update theme mods, bulk update, debug
- ✅ Confirmed all changes persist correctly in WordPress database
- ✅ Clarified browser content discrepancy: root cause is SiteGround cache (not Elementor)
  - Logged-in users bypass cache → see live content
  - Anonymous users get cached stale version → need cache flush
- ⚠️ **STILL PENDING:** Flush SiteGround Dynamic Cache to make changes live for all visitors

### March 6, 2026 - Session 5 (MCP Abilities for CSS/Design)
- ✅ Successfully used `sharedfare/update-custom-css` ability to update CSS directly via MCP
- ✅ Fixed header "SharedFare" logo visibility (white text on darker blue background #2B8FD9)
- ✅ Updated all site CSS (5,610 characters) directly to WordPress database without plugin upload
- ✅ Verified CSS persists correctly in WordPress Customizer's "Additional CSS" field
- ✅ Updated operational_memory.md with comprehensive MCP abilities workflow documentation
- 📝 **NEW WORKFLOW:** CSS changes now via MCP abilities instead of manual plugin re-upload
- ⚠️ **PENDING:** Flush SiteGround Dynamic Cache to make CSS changes visible to public visitors

### March 6, 2026 - Session 6 (White Line Issue - UNRESOLVED)
- ⚠️ **PERSISTENT ISSUE:** White line between header and hero section
- **Root Cause:** `header-transparent` class on header adds 80px padding-top to #content.site-content
- **Attempted Solutions (all failed):**
  - CSS with !important declarations (theme CSS loads after and overrides)
  - JavaScript via page content (displayed as visible text)
  - JavaScript via CSS file (WordPress strips it)
  - JavaScript plugin via wp_footer hook (didn't execute)
  - Theme mod `header_transparent` toggle (doesn't affect hardcoded class)
  - Maximum specificity CSS selectors (still overridden)
- **Why it persists:** Elementor header template (ID 267) has `header-transparent` class hardcoded in section settings
- **SOLUTION NEEDED:** Manually edit Elementor template 267 to remove `header-transparent` class from CSS Classes field in Advanced tab
- **DEFERRED:** Will address this issue in a future session when user has time

### Current Status
**Active Theme:** Maxbizz v1.2.3.15
**CSS Management:** WordPress Customizer Additional CSS (updated via MCP abilities)
**Custom Abilities Plugin:** SharedFare Abilities v2 (v2.1.3) - all 14 abilities working
**Brand Color:** #3EAFFA (light cyan blue, stored in WordPress database)
**Primary Font:** Segoe UI (stored in WordPress database)

**Development Method:** All CSS and content changes now use MCP abilities (no more manual plugin uploads)

**Known Pending Issue:** SiteGround Dynamic Cache (`x-proxy-cache-info: DT:1`) must be flushed via SiteGround Site Tools → Speed → Caching → Dynamic Cache → Flush Cache. Until this is done, anonymous visitors see stale cached content while logged-in users see current content.

**Key Files (Local):**
- `/home/hn/Desktop/sharedfare/sharedfare-abilities-v2/` - Plugin source (v2.1.3)
- `/home/hn/Desktop/sharedfare/sharedfare-css-fixed/` - LEGACY CSS plugin (use MCP abilities instead)
- `/home/hn/Desktop/sharedfare/home-page-updated.html` - Home page HTML template (reference)
- `/home/hn/Desktop/sharedfare/fleet-coverage-updated.html` - Fleet Coverage page template (reference)
- `/home/hn/Desktop/sharedfare/custom-sharedfare-styles.css` - Complete CSS reference file
- `/home/hn/Desktop/sharedfare/apppass` - WordPress application password for seriesdev
- `/home/hn/Desktop/sharedfare/operational_memory.md` - This document

## Current Development Workflow

### Making CSS Changes
**✅ RECOMMENDED: Use MCP Abilities (Direct API Access)**

Use the WordPress MCP abilities to update CSS directly without plugin uploads:

**Step 1: Get Current CSS (Optional)**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/get-custom-css",
  parameters: {}
})
```

**Step 2: Update CSS**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/update-custom-css",
  parameters: {
    css: "/* Your CSS here - up to 65,535 characters */"
  }
})
```

**Step 3: Flush SiteGround Cache**
- Log into SiteGround Site Tools
- Go to Speed → Caching → Dynamic Cache
- Click "Flush Cache"

**Example CSS Update:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/update-custom-css",
  parameters: {
    css: `:root {
  --primary-blue: #3EAFFA;
  --white: #FFFFFF;
}

.site-header {
  background-color: #2B8FD9 !important;
}

.site-title:before {
  content: "SharedFare";
  color: var(--white) !important;
  font-size: 1.5rem;
  font-weight: 700;
}`
  }
})
```

**Benefits:**
- ✅ No plugin editing or re-uploading needed
- ✅ Changes persist in WordPress database
- ✅ Works from any IDE with MCP configured (OpenCode, Windsurf, Cursor)
- ✅ Instant updates (after cache flush)

---

**⚠️ LEGACY: Manual Plugin Upload (NOT RECOMMENDED)**

Only use this if MCP abilities are unavailable:

1. Edit `/home/hn/Desktop/sharedfare/sharedfare-css-fixed/sharedfare-css-injector.php` locally
2. Update CSS within the embedded `<style>` tag in the plugin file
3. Zip the plugin: `cd /home/hn/Desktop/sharedfare && zip -r sharedfare-css-fixed.zip sharedfare-css-fixed/`
4. In WordPress admin:
   - Deactivate current "SharedFare CSS Injector v2" plugin
   - Delete it
   - Upload new plugin zip file
   - Activate it
5. Clear browser cache to see changes

---

### Making Content Changes
**Use MCP Abilities (Direct API Access):**

**List Pages:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/list-pages",
  parameters: { number: 20, status: "publish" }
})
```

**Get Specific Page:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/get-page",
  parameters: { page_id: 2 }
})
// OR by slug:
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/get-page",
  parameters: { slug: "home" }
})
```

**Update Page:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/update-page",
  parameters: {
    page_id: 2,
    title: "New Page Title",
    content: "<div class='hero-section'><h1>Updated Content</h1></div>"
  }
})
```

**Create New Page:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/create-page",
  parameters: {
    title: "About Us",
    content: "<p>Page content here</p>",
    status: "publish"
  }
})
```

### Making Theme/Design Changes
**Use MCP Design Abilities:**

**Get Theme Settings:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/get-theme-mods",
  parameters: {}
})
```

**Update Single Theme Setting:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/update-theme-mod",
  parameters: {
    mod_name: "main_color",
    value: "#3EAFFA"
  }
})
```

**Update Multiple Theme Settings:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/bulk-update-theme-mods",
  parameters: {
    mods: {
      "main_color": "#3EAFFA",
      "header_layout": "header-style-1"
    }
  }
})
```

**Debug Theme Info:**
```javascript
wordpress-sharedfare_mcp-adapter-execute-ability({
  ability_name: "sharedfare/debug-theme-info",
  parameters: {}
})
```

### Cache Management
- **Logged-in users:** See changes immediately
- **Public visitors:** May see cached version until SiteGround Dynamic Cache is flushed
- **Flush location:** SiteGround Site Tools > Speed > Caching > Flush Cache

**IMPORTANT:** Always flush the SiteGround cache after making CSS or content changes to make them visible to public visitors.

---

### Current CSS in WordPress Database

The CSS is stored in WordPress Customizer's "Additional CSS" field and includes:

- CSS variables for brand colors (#3EAFFA, #2B8FD9)
- Header styling (darker blue background, white "SharedFare" text logo)
- Hero section gradient background
- Section headings with black underlines
- Card grid layouts for plans and coverage includes
- FAQ accordion styling
- Footer hiding rules
- Responsive breakpoints

**To view current CSS:** Use `sharedfare/get-custom-css` ability
**To update CSS:** Use `sharedfare/update-custom-css` ability (max 65,535 characters)

---

*Document maintained by: Cascade AI Assistant*  
*Last Updated: March 6, 2026 (Session 5) - Added MCP abilities workflow documentation*

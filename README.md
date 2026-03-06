# WordPress MCP Integration for SharedFare

Complete WordPress MCP (Model Context Protocol) setup with custom abilities for AI-powered content and design management.

## 🎯 Overview

This repository contains everything needed to connect AI assistants (OpenCode, Windsurf, Cursor, Claude Desktop) to WordPress sites through the Model Context Protocol, enabling direct content and design management via natural language.

## 📁 Repository Structure

```
.
├── plugins/                          # WordPress plugins
│   ├── sharedfare-abilities-v2/      # Custom abilities plugin (source)
│   ├── sharedfare-mcp-abilities-enabler.php  # Core abilities enabler
│   ├── wordpress-abilities-api.zip   # WordPress Abilities API
│   ├── wordpress-mcp-adapter.zip     # Standard MCP adapter
│   └── mcp-adapter-0.4.1-patched-v2.zip  # Patched MCP adapter (recommended)
│
├── docs/                             # Documentation
│   ├── WORDPRESS-MCP-SETUP-GUIDE.md  # Complete setup guide
│   ├── operational_memory.md         # Development history & workflow
│   └── IMPLEMENTATION_PLAN.md        # Implementation notes
│
├── templates/                        # HTML/CSS templates
│   ├── home-page-updated.html        # Homepage template
│   ├── fleet-coverage-updated.html   # Fleet coverage page
│   ├── homepage-template.html        # Original homepage
│   ├── fleet-coverage-template.html  # Original fleet coverage
│   ├── custom-sharedfare-styles.css  # Brand CSS styles
│   └── sharedfare-custom.css         # Additional custom CSS
│
├── assets/                           # Images and assets
│   └── logo.png                      # SharedFare logo
│
└── apppass                           # WordPress application password (gitignored)
```

## 🚀 Quick Start

### 1. Install WordPress Plugins

Upload and activate these plugins on your WordPress site:

1. **WordPress Abilities API** (`plugins/wordpress-abilities-api.zip`)
2. **MCP Adapter** (`plugins/mcp-adapter-0.4.1-patched-v2.zip`) - Patched version recommended
3. **SharedFare MCP Abilities Enabler** (`plugins/sharedfare-mcp-abilities-enabler.php`)
4. **SharedFare Abilities v2** (`plugins/sharedfare-abilities-v2/`)

### 2. Configure Your IDE

Add this to your IDE's MCP config file:

**OpenCode** (`~/.config/opencode/opencode.json`):
```json
{
  "mcp": {
    "wordpress-yoursite": {
      "type": "local",
      "command": ["npx", "-y", "@automattic/mcp-wordpress-remote@latest"],
      "enabled": true,
      "environment": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-app-password"
      }
    }
  }
}
```

**Windsurf/Cursor** (`~/.config/Windsurf/mcp.json` or `~/.config/cursor/mcp.json`):
```json
{
  "mcpServers": {
    "wordpress-yoursite": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-app-password"
      }
    }
  }
}
```

### 3. Test the Connection

Ask your AI assistant:
```
List all WordPress abilities available on my site
```

## 📚 Available Abilities

### Content Management (8 abilities)
- `sharedfare/list-pages` - List all pages with filters
- `sharedfare/get-page` - Get page by ID or slug
- `sharedfare/create-page` - Create new pages
- `sharedfare/update-page` - Update page content
- `sharedfare/list-posts` - List all posts with filters
- `sharedfare/get-post` - Get post by ID or slug
- `sharedfare/create-post` - Create new posts
- `sharedfare/update-post` - Update post content

### Design Management (6 abilities)
- `sharedfare/get-custom-css` - Get current custom CSS
- `sharedfare/update-custom-css` - Update site CSS (up to 65KB)
- `sharedfare/get-theme-mods` - Get theme settings
- `sharedfare/update-theme-mod` - Update single theme setting
- `sharedfare/bulk-update-theme-mods` - Update multiple settings
- `sharedfare/debug-theme-info` - Get theme diagnostic info

### Core Abilities (3 abilities)
- `core/get-site-info` - Get WordPress site information
- `core/get-user-info` - Get current user info
- `core/get-environment-info` - Get environment details

## 💡 Usage Examples

### Update CSS
```
Update the site's custom CSS to make all headings use the color #3EAFFA
```

### Create a Page
```
Create a new page called "About Us" with a hero section and company description
```

### Get Site Info
```
What is my WordPress version and site URL?
```

### Update Theme Color
```
Change the primary theme color to #3EAFFA
```

## 📖 Documentation

- **[Complete Setup Guide](docs/WORDPRESS-MCP-SETUP-GUIDE.md)** - End-to-end installation instructions
- **[Operational Memory](docs/operational_memory.md)** - Development workflow and history
- **[Implementation Plan](docs/IMPLEMENTATION_PLAN.md)** - Technical implementation notes

## 🔧 Development Workflow

### Making CSS Changes (NEW METHOD)

**✅ Recommended:** Use MCP abilities directly

```javascript
wordpress-yoursite_mcp-adapter-execute-ability({
  ability_name: "sharedfare/update-custom-css",
  parameters: {
    css: "body { font-family: 'Segoe UI', sans-serif; }"
  }
})
```

**Benefits:**
- No plugin upload required
- Changes persist in WordPress database
- Works from any IDE with MCP configured
- Instant updates (after cache flush)

### Making Content Changes

```javascript
// Update a page
wordpress-yoursite_mcp-adapter-execute-ability({
  ability_name: "sharedfare/update-page",
  parameters: {
    page_id: 2,
    title: "New Title",
    content: "<div class='hero'>Updated content</div>"
  }
})
```

### Cache Management

**Important:** After making CSS or content changes, flush the SiteGround cache:
1. Log into SiteGround Site Tools
2. Go to Speed → Caching → Dynamic Cache
3. Click "Flush Cache"

## 🛠️ Technical Details

### WordPress Requirements
- WordPress 6.9+
- PHP 8.0+
- HTTPS enabled
- Permalinks enabled

### Plugin Dependencies
- **abilities-api** - Provides ability framework
- **mcp-adapter** - Exposes abilities via MCP protocol

### Security
- Uses WordPress Application Passwords (more secure than main password)
- Respects WordPress user capabilities and permissions
- HTTPS required for all API communications

## 🐛 Troubleshooting

### No abilities showing
- Verify all plugins are activated
- Check that MCP Abilities Enabler is installed
- Use patched MCP adapter (`mcp-adapter-0.4.1-patched-v2.zip`)

### Authentication errors
- Verify application password is correct (no spaces)
- Check username matches WordPress admin username
- Regenerate application password if needed

### Changes not visible
- Flush WordPress cache (if using cache plugin)
- Flush hosting cache (SiteGround, WP Engine, etc.)
- View site in incognito mode to bypass browser cache

See [Complete Setup Guide](docs/WORDPRESS-MCP-SETUP-GUIDE.md) for detailed troubleshooting.

## 📝 Version History

- **v2.1.3** (2026-03-05) - Phase 2: Design management abilities
- **v2.0.0** (2026-03-05) - Phase 1: Content management abilities
- **v1.0.1** (2026-03-05) - Initial MCP abilities enabler

---

**Last Updated:** March 6, 2026  
**Repository:** https://github.com/misua/wordpress-copier

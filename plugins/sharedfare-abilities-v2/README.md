# SharedFare Abilities v2 - Phases 1 & 2

AI-powered content and design management for SharedFare WordPress site via MCP.

## What This Plugin Does

Exposes 14 WordPress abilities through the MCP (Model Context Protocol) adapter, allowing AI assistants to:

**Content Management:**
- ✅ List pages and posts
- ✅ Get detailed page/post information
- ✅ Create new pages and posts
- ✅ Update existing pages and posts

**Design Management:**
- ✅ Get and update custom CSS
- ✅ Get and update theme modifications
- ✅ Bulk update theme settings
- ✅ Debug theme configuration

## Phases Included

## Phases Included

### Phase 1: Content Management (8 abilities)

**Pages:**
1. **sharedfare/list-pages** - List all WordPress pages with filtering
2. **sharedfare/get-page** - Get detailed information about a specific page
3. **sharedfare/update-page** - Update page content, title, status, etc.
4. **sharedfare/create-page** - Create new WordPress pages

**Posts:**
5. **sharedfare/list-posts** - List all blog posts with filtering
6. **sharedfare/get-post** - Get detailed information about a specific post
7. **sharedfare/update-post** - Update post content, title, categories, tags, etc.
8. **sharedfare/create-post** - Create new blog posts

### Phase 2: Design Management (6 abilities)

**CSS Management:**
9. **sharedfare/get-custom-css** - Retrieve current custom CSS
10. **sharedfare/update-custom-css** - Update site custom CSS

**Theme Customization:**
11. **sharedfare/get-theme-mods** - Get theme modifications (colors, fonts, etc.)
12. **sharedfare/update-theme-mod** - Update a single theme setting
13. **sharedfare/bulk-update-theme-mods** - Update multiple theme settings at once

**Diagnostics:**
14. **sharedfare/debug-theme-info** - Get complete theme configuration for debugging

## Requirements

- WordPress 6.9+
- **wordpress-abilities-api** plugin (active)
- **wordpress-mcp-adapter** plugin (active)

## Installation

1. Upload `sharedfare-abilities-v2.zip` to WordPress
2. Go to **Plugins** → **Add New** → **Upload Plugin**
3. Activate the plugin
4. Verify abilities are registered: Use MCP `discover-abilities` command

## Testing

After activation, test via MCP:

### Content Management Examples

```javascript
// List all pages
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/list-pages",
  parameters: { number: 10 }
}

// Get a specific page
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/get-page",
  parameters: { page_id: 2 }
}

// Update a page
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/update-page",
  parameters: { 
    page_id: 2,
    title: "New Page Title",
    content: "<p>Updated content</p>"
  }
}
```

### Design Management Examples

```javascript
// Get current custom CSS
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/get-custom-css",
  parameters: {}
}

// Update custom CSS
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/update-custom-css",
  parameters: { 
    css: "body { background-color: #f5f5f5; }"
  }
}

// Get all theme settings
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/get-theme-mods",
  parameters: {}
}

// Update a theme setting
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/update-theme-mod",
  parameters: { 
    mod_name: "header_textcolor",
    value: "#333333"
  }
}

// Debug theme info
wordpress-sharedfare_mcp-adapter-execute-ability {
  ability_name: "sharedfare/debug-theme-info",
  parameters: {}
}
```

## Version History

- **2.1.0** (2026-03-05) - Phase 2: Added design management abilities (6 abilities) - CSS and theme customization
- **2.0.2** (2026-03-05) - Fixed hook timing for ability registration
- **2.0.1** (2026-03-05) - Fixed plugin dependencies
- **2.0.0** (2026-03-05) - Phase 1: Content management abilities (8 abilities)

## Support

For issues or questions, contact SharedFare support.

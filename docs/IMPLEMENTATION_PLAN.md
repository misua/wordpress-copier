# SharedFare Abilities - Incremental Implementation Plan

## Overview

**Approach**: Build → Test → Feedback → Iterate  
**Strategy**: Start with Phase 1 (content), validate it works, then move to Phase 2 (design)  
**Total estimated time**: 4-5 hours across both phases

---

## Phase 1: Core Content Management (PRIORITY 1)

### Objective
Enable AI to read, create, and edit pages and posts on SharedFare WordPress site.

### Abilities to Implement (8 total)

#### 1. `sharedfare/list-pages`
**Status**: Refine existing implementation  
**Changes needed**:
- Add `category` field: `'sharedfare-content'`
- Add `status` filter parameter
- Add `offset` for pagination
- Add `search` parameter
- Include `modified_date` in output
- Add proper annotations and MCP metadata

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "status": {
      "type": "string",
      "enum": ["publish", "draft", "pending", "private", "any"],
      "description": "Filter by post status"
    },
    "number": {
      "type": "integer",
      "default": 20,
      "description": "Number of pages to retrieve"
    },
    "offset": {
      "type": "integer",
      "default": 0,
      "description": "Pagination offset"
    },
    "search": {
      "type": "string",
      "description": "Search term for page titles"
    }
  },
  "additionalProperties": false
}
```

**Output Schema**:
```json
{
  "type": "array",
  "items": {
    "type": "object",
    "properties": {
      "id": {"type": "integer"},
      "title": {"type": "string"},
      "slug": {"type": "string"},
      "status": {"type": "string"},
      "url": {"type": "string"},
      "modified_date": {"type": "string"}
    }
  }
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    $args = [
        'post_type' => 'page',
        'post_status' => $input['status'] ?? 'any',
        'numberposts' => $input['number'] ?? 20,
        'offset' => $input['offset'] ?? 0,
        'orderby' => 'modified',
        'order' => 'DESC'
    ];
    
    if (!empty($input['search'])) {
        $args['s'] = sanitize_text_field($input['search']);
    }
    
    $pages = get_posts($args);
    
    return array_map(function($page) {
        return [
            'id' => $page->ID,
            'title' => $page->post_title,
            'slug' => $page->post_name,
            'status' => $page->post_status,
            'url' => get_permalink($page->ID),
            'modified_date' => $page->post_modified
        ];
    }, $pages);
},
'permission_callback' => function() {
    return current_user_can('read');
},
'meta' => [
    'annotations' => [
        'readonly' => true,
        'destructive' => false,
        'idempotent' => true
    ],
    'mcp' => [
        'public' => true,
        'type' => 'tool'
    ]
]
```

---

#### 2. `sharedfare/get-page`
**Status**: Refine existing implementation  
**Changes needed**:
- Add `category` field: `'sharedfare-content'`
- Add more output fields: `excerpt`, `author`, `modified_date`, `template`, `parent_id`, `menu_order`, `featured_image_url`
- Improve error handling
- Add proper annotations

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "page_id": {
      "type": "integer",
      "description": "The page ID"
    },
    "slug": {
      "type": "string",
      "description": "The page slug"
    }
  },
  "additionalProperties": false
}
```

**Output Schema**:
```json
{
  "type": "object",
  "properties": {
    "id": {"type": "integer"},
    "title": {"type": "string"},
    "content": {"type": "string"},
    "excerpt": {"type": "string"},
    "slug": {"type": "string"},
    "status": {"type": "string"},
    "url": {"type": "string"},
    "author": {"type": "string"},
    "modified_date": {"type": "string"},
    "template": {"type": "string"},
    "parent_id": {"type": "integer"},
    "menu_order": {"type": "integer"},
    "featured_image_url": {"type": "string"}
  }
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    // Validate input
    if (empty($input['page_id']) && empty($input['slug'])) {
        return [
            'success' => false,
            'error_code' => 'missing_required_field',
            'message' => 'Either page_id or slug is required'
        ];
    }
    
    // Get page
    if (!empty($input['page_id'])) {
        $page = get_post(absint($input['page_id']));
    } else {
        $page = get_page_by_path(sanitize_text_field($input['slug']), OBJECT, 'page');
    }
    
    if (!$page || $page->post_type !== 'page') {
        return [
            'success' => false,
            'error_code' => 'page_not_found',
            'message' => 'Page not found'
        ];
    }
    
    // Get author name
    $author = get_userdata($page->post_author);
    
    // Get featured image
    $featured_image_url = '';
    if (has_post_thumbnail($page->ID)) {
        $featured_image_url = get_the_post_thumbnail_url($page->ID, 'full');
    }
    
    return [
        'id' => $page->ID,
        'title' => $page->post_title,
        'content' => $page->post_content,
        'excerpt' => $page->post_excerpt,
        'slug' => $page->post_name,
        'status' => $page->post_status,
        'url' => get_permalink($page->ID),
        'author' => $author ? $author->display_name : '',
        'modified_date' => $page->post_modified,
        'template' => get_page_template_slug($page->ID),
        'parent_id' => $page->post_parent,
        'menu_order' => $page->menu_order,
        'featured_image_url' => $featured_image_url
    ];
}
```

---

#### 3. `sharedfare/update-page`
**Status**: Refine existing implementation  
**Changes needed**:
- Add `category` field: `'sharedfare-content'`
- Add more parameters: `excerpt`, `slug`, `status`, `parent_id`, `menu_order`
- Improve sanitization
- Add better error responses
- Return more details in output

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "page_id": {
      "type": "integer",
      "description": "The page ID to update"
    },
    "title": {
      "type": "string",
      "description": "New page title"
    },
    "content": {
      "type": "string",
      "description": "New page content (HTML allowed)"
    },
    "excerpt": {
      "type": "string",
      "description": "New page excerpt"
    },
    "status": {
      "type": "string",
      "enum": ["publish", "draft", "pending", "private"],
      "description": "Post status"
    },
    "slug": {
      "type": "string",
      "description": "Page slug"
    },
    "parent_id": {
      "type": "integer",
      "description": "Parent page ID"
    },
    "menu_order": {
      "type": "integer",
      "description": "Menu order position"
    }
  },
  "required": ["page_id"],
  "additionalProperties": false
}
```

**Output Schema**:
```json
{
  "type": "object",
  "properties": {
    "success": {"type": "boolean"},
    "page_id": {"type": "integer"},
    "message": {"type": "string"},
    "url": {"type": "string"}
  }
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    // Validate page exists
    $page = get_post(absint($input['page_id']));
    if (!$page || $page->post_type !== 'page') {
        return [
            'success' => false,
            'error_code' => 'page_not_found',
            'message' => 'Page not found'
        ];
    }
    
    // Build update array
    $page_data = ['ID' => absint($input['page_id'])];
    
    if (isset($input['title'])) {
        $page_data['post_title'] = sanitize_text_field($input['title']);
    }
    if (isset($input['content'])) {
        $page_data['post_content'] = wp_kses_post($input['content']);
    }
    if (isset($input['excerpt'])) {
        $page_data['post_excerpt'] = sanitize_text_field($input['excerpt']);
    }
    if (isset($input['status'])) {
        $page_data['post_status'] = sanitize_text_field($input['status']);
    }
    if (isset($input['slug'])) {
        $page_data['post_name'] = sanitize_title($input['slug']);
    }
    if (isset($input['parent_id'])) {
        $page_data['post_parent'] = absint($input['parent_id']);
    }
    if (isset($input['menu_order'])) {
        $page_data['menu_order'] = absint($input['menu_order']);
    }
    
    // Update page
    $result = wp_update_post($page_data, true);
    
    if (is_wp_error($result)) {
        return [
            'success' => false,
            'error_code' => $result->get_error_code(),
            'message' => $result->get_error_message()
        ];
    }
    
    return [
        'success' => true,
        'page_id' => $result,
        'message' => 'Page updated successfully',
        'url' => get_permalink($result)
    ];
},
'permission_callback' => function() {
    return current_user_can('edit_pages');
},
'meta' => [
    'annotations' => [
        'readonly' => false,
        'destructive' => false,
        'idempotent' => true
    ],
    'mcp' => [
        'public' => true,
        'type' => 'tool'
    ]
]
```

---

#### 4. `sharedfare/create-page` (NEW)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "title": {
      "type": "string",
      "description": "Page title"
    },
    "content": {
      "type": "string",
      "description": "Page content (HTML allowed)"
    },
    "status": {
      "type": "string",
      "enum": ["publish", "draft", "pending", "private"],
      "default": "draft",
      "description": "Post status"
    },
    "slug": {
      "type": "string",
      "description": "Page slug"
    },
    "parent_id": {
      "type": "integer",
      "description": "Parent page ID"
    },
    "template": {
      "type": "string",
      "description": "Page template filename"
    }
  },
  "required": ["title"],
  "additionalProperties": false
}
```

**Output Schema**:
```json
{
  "type": "object",
  "properties": {
    "success": {"type": "boolean"},
    "page_id": {"type": "integer"},
    "message": {"type": "string"},
    "url": {"type": "string"},
    "edit_url": {"type": "string"}
  }
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    $page_data = [
        'post_type' => 'page',
        'post_title' => sanitize_text_field($input['title']),
        'post_content' => isset($input['content']) ? wp_kses_post($input['content']) : '',
        'post_status' => $input['status'] ?? 'draft'
    ];
    
    if (isset($input['slug'])) {
        $page_data['post_name'] = sanitize_title($input['slug']);
    }
    if (isset($input['parent_id'])) {
        $page_data['post_parent'] = absint($input['parent_id']);
    }
    
    $page_id = wp_insert_post($page_data, true);
    
    if (is_wp_error($page_id)) {
        return [
            'success' => false,
            'error_code' => $page_id->get_error_code(),
            'message' => $page_id->get_error_message()
        ];
    }
    
    // Set template if provided
    if (isset($input['template'])) {
        update_post_meta($page_id, '_wp_page_template', sanitize_text_field($input['template']));
    }
    
    return [
        'success' => true,
        'page_id' => $page_id,
        'message' => 'Page created successfully',
        'url' => get_permalink($page_id),
        'edit_url' => get_edit_post_link($page_id, 'raw')
    ];
},
'permission_callback' => function() {
    return current_user_can('edit_pages') && current_user_can('publish_pages');
}
```

---

#### 5. `sharedfare/list-posts` (NEW)

Similar to `list-pages` but for blog posts. Add `categories` and `tags` to output.

**Output includes**: `id`, `title`, `slug`, `status`, `url`, `modified_date`, `categories`, `tags`

---

#### 6. `sharedfare/get-post` (NEW)

Similar to `get-page` but for blog posts. Add `categories` and `tags` to output.

---

#### 7. `sharedfare/update-post` (NEW)

Similar to `update-page` but for posts. Add `categories` (array of IDs) and `tags` (array of names) to input.

**Additional implementation for categories/tags**:
```php
if (isset($input['categories'])) {
    wp_set_post_categories($post_id, array_map('absint', $input['categories']));
}
if (isset($input['tags'])) {
    wp_set_post_tags($post_id, array_map('sanitize_text_field', $input['tags']));
}
```

---

#### 8. `sharedfare/create-post` (NEW)

Similar to `create-page` but for posts. Add `categories` and `tags` to input.

---

### Phase 1 Testing Checklist

After implementing Phase 1, test each ability via MCP:

- [ ] `discover-abilities` - Verify 8 new abilities appear
- [ ] `sharedfare/list-pages` - List all pages, test filters
- [ ] `sharedfare/get-page` - Get a specific page by ID and by slug
- [ ] `sharedfare/create-page` - Create a draft page
- [ ] `sharedfare/update-page` - Update the draft page title and content
- [ ] `sharedfare/update-page` - Publish the page
- [ ] `sharedfare/list-posts` - List all posts
- [ ] `sharedfare/get-post` - Get a specific post
- [ ] `sharedfare/create-post` - Create a draft post
- [ ] `sharedfare/update-post` - Update post with categories/tags

**Success criteria**: All operations work via MCP, changes visible on WordPress site.

---

## Phase 2: Core Design Management (PRIORITY 2)

### Objective
Enable AI to modify CSS, colors, and theme settings on SharedFare WordPress site.

### Pre-Implementation: Theme Discovery

Before implementing Phase 2, need to discover MaxxBizz theme structure:

**Create diagnostic ability** `sharedfare/debug-theme-info`:
```php
'execute_callback' => function() {
    $theme = wp_get_theme();
    $all_mods = get_theme_mods();
    
    return [
        'theme_name' => $theme->get('Name'),
        'theme_version' => $theme->get('Version'),
        'theme_mods' => $all_mods,
        'custom_css' => wp_get_custom_css(),
        'custom_css_post_id' => wp_get_custom_css_post() ? wp_get_custom_css_post()->ID : null,
        'nav_menus' => get_registered_nav_menus(),
        'sidebars' => $GLOBALS['wp_registered_sidebars'] ?? []
    ];
}
```

Run this ability to understand theme structure, then implement abilities below.

---

### Abilities to Implement (4-6 total)

#### 9. `sharedfare/get-custom-css` (NEW)

**Input Schema**: None

**Output Schema**:
```json
{
  "type": "object",
  "properties": {
    "css": {"type": "string"},
    "length": {"type": "integer"}
  }
}
```

**Implementation**:
```php
'execute_callback' => function() {
    $css = wp_get_custom_css();
    return [
        'css' => $css,
        'length' => strlen($css)
    ];
},
'permission_callback' => function() {
    return current_user_can('edit_theme_options');
},
'meta' => [
    'annotations' => [
        'readonly' => true,
        'destructive' => false,
        'idempotent' => true
    ],
    'mcp' => ['public' => true, 'type' => 'tool']
]
```

---

#### 10. `sharedfare/update-custom-css` (REFINE EXISTING)

**Changes needed**:
- Rename from `add-custom-css`
- Remove `append` mode (always replace for simplicity)
- Add validation for CSS length
- Add category field

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "css": {
      "type": "string",
      "description": "The complete CSS code to set"
    }
  },
  "required": ["css"],
  "additionalProperties": false
}
```

**Implementation** (improved):
```php
'execute_callback' => function($input) {
    $css = $input['css'];
    
    // Validate CSS length (WordPress has limits)
    if (strlen($css) > 65535) {
        return [
            'success' => false,
            'error_code' => 'css_too_long',
            'message' => 'CSS exceeds maximum length of 65535 characters'
        ];
    }
    
    // Update custom CSS
    $result = wp_update_custom_css_post($css);
    
    if (is_wp_error($result)) {
        return [
            'success' => false,
            'error_code' => $result->get_error_code(),
            'message' => $result->get_error_message()
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Custom CSS updated successfully',
        'css_length' => strlen($css)
    ];
},
'meta' => [
    'annotations' => [
        'readonly' => false,
        'destructive' => false,
        'idempotent' => true
    ],
    'mcp' => ['public' => true, 'type' => 'tool']
]
```

---

#### 11. `sharedfare/get-theme-mods` (NEW)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "mod_names": {
      "type": "array",
      "items": {"type": "string"},
      "description": "Specific theme mods to retrieve. If omitted, returns all."
    }
  },
  "additionalProperties": false
}
```

**Output Schema**:
```json
{
  "type": "object",
  "additionalProperties": true
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    if (!empty($input['mod_names'])) {
        $result = [];
        foreach ($input['mod_names'] as $mod_name) {
            $result[$mod_name] = get_theme_mod($mod_name, null);
        }
        return $result;
    }
    
    // Return all theme mods
    return get_theme_mods() ?: [];
}
```

---

#### 12. `sharedfare/update-theme-mod` (NEW)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "mod_name": {
      "type": "string",
      "description": "The theme mod name"
    },
    "value": {
      "description": "The new value (can be string, number, array, or object)"
    }
  },
  "required": ["mod_name", "value"],
  "additionalProperties": false
}
```

**Output Schema**:
```json
{
  "type": "object",
  "properties": {
    "success": {"type": "boolean"},
    "mod_name": {"type": "string"},
    "message": {"type": "string"}
  }
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    $mod_name = sanitize_text_field($input['mod_name']);
    $value = $input['value']; // Can be any type
    
    set_theme_mod($mod_name, $value);
    
    return [
        'success' => true,
        'mod_name' => $mod_name,
        'message' => "Theme mod '{$mod_name}' updated successfully"
    ];
},
'meta' => [
    'annotations' => [
        'readonly' => false,
        'destructive' => false,
        'idempotent' => true
    ],
    'mcp' => ['public' => true, 'type' => 'tool']
]
```

---

#### 13. `sharedfare/bulk-update-theme-mods` (NEW - OPTIONAL)

**Input Schema**:
```json
{
  "type": "object",
  "properties": {
    "mods": {
      "type": "object",
      "additionalProperties": true,
      "description": "Key-value pairs of theme mod names and values"
    }
  },
  "required": ["mods"],
  "additionalProperties": false
}
```

**Implementation**:
```php
'execute_callback' => function($input) {
    $updated = 0;
    foreach ($input['mods'] as $mod_name => $value) {
        set_theme_mod(sanitize_text_field($mod_name), $value);
        $updated++;
    }
    
    return [
        'success' => true,
        'updated_count' => $updated,
        'message' => "Updated {$updated} theme modifications"
    ];
}
```

---

### Phase 2 Testing Checklist

After implementing Phase 2:

- [ ] Run `sharedfare/debug-theme-info` - Document MaxxBizz theme_mods structure
- [ ] `sharedfare/get-custom-css` - Retrieve current CSS
- [ ] `sharedfare/update-custom-css` - Add simple CSS rule (e.g., body background color)
- [ ] Verify CSS appears on site frontend
- [ ] `sharedfare/get-theme-mods` - Get all theme mods
- [ ] Identify color/font theme_mod keys from output
- [ ] `sharedfare/update-theme-mod` - Update a color theme_mod
- [ ] Verify color change appears on site
- [ ] `sharedfare/bulk-update-theme-mods` - Update multiple mods at once

**Success criteria**: Can change site appearance via MCP, changes persist and are visible.

---

## File Structure for Minimal Implementation

```
sharedfare-abilities-v2/
├── sharedfare-abilities-v2.php          # Main plugin file
└── README.md                             # Quick documentation
```

**Single file approach** for speed - all abilities in one file (~500-600 lines total).

---

## Implementation Sequence

### Step 1: Setup (15 min)
1. Create plugin directory structure
2. Write plugin header and registration logic
3. Add category registration for `sharedfare-content` and `sharedfare-design`

### Step 2: Phase 1 Content Abilities (2 hours)
1. Refine `list-pages`, `get-page`, `update-page`
2. Implement `create-page`
3. Implement `list-posts`, `get-post`, `update-post`, `create-post`
4. Package as .zip

### Step 3: Phase 1 Testing (30 min)
1. Upload plugin to WordPress
2. Test via `discover-abilities`
3. Test each ability via `execute-ability`
4. Verify changes on WordPress site
5. Document any issues

### Step 4: User Feedback (PAUSE HERE)
- Get user confirmation Phase 1 works as expected
- Discuss any needed adjustments
- Decide whether to proceed to Phase 2

### Step 5: Phase 2 Design Abilities (1.5 hours)
1. Implement `get-custom-css`, `update-custom-css`
2. Implement `get-theme-mods`, `update-theme-mod`, `bulk-update-theme-mods`
3. Run `debug-theme-info` to understand MaxxBizz structure
4. Package updated plugin

### Step 6: Phase 2 Testing (30 min)
1. Upload updated plugin
2. Test CSS abilities
3. Test theme mod abilities
4. Verify design changes on site

### Step 7: User Feedback (FINAL)
- Demonstrate AI-powered content + design editing
- Discuss whether additional abilities needed
- Document final plugin usage

---

## Success Metrics

### Phase 1 Success
- ✅ Can list all pages and posts via AI
- ✅ Can read specific page/post content via AI
- ✅ Can create new pages/posts via AI
- ✅ Can edit existing page/post content via AI
- ✅ Changes appear immediately on WordPress site
- ✅ All operations work through MCP proxy

### Phase 2 Success
- ✅ Can read current CSS via AI
- ✅ Can add/modify CSS via AI and see changes on site
- ✅ Can read theme customization settings via AI
- ✅ Can modify theme colors/fonts via AI
- ✅ Design changes persist across page reloads

### Overall Success
- ✅ User can ask AI: "Change the homepage heading to X" → works
- ✅ User can ask AI: "Make all buttons blue" → works via CSS
- ✅ User can ask AI: "What pages exist on my site?" → gets accurate list
- ✅ Time from request to live change: < 10 seconds

---

## Rollback Plan

If anything breaks:

1. **Deactivate plugin** via wp-admin → Plugins
2. **Previous plugins still active**: MCP Adapter, Abilities API, Enabler
3. **No data loss**: Plugin only reads/writes WordPress data, doesn't modify database structure
4. **CSS rollback**: Use `sharedfare/update-custom-css` with previous CSS, or clear via Customizer
5. **Theme mod rollback**: Use `sharedfare/update-theme-mod` to restore previous values

---

## Next Action

Ready to start implementation of Phase 1!

**Awaiting confirmation to proceed with:**
1. Creating plugin file structure
2. Implementing 8 Phase 1 abilities (content management)
3. Packaging and uploading to WordPress
4. Testing via MCP

Respond "proceed" or "go" to start, or ask any questions first.

<?php
/**
 * Plugin Name: SharedFare Abilities v2
 * Description: AI-powered content and design management for SharedFare via MCP - Phases 1 & 2: Content + Design Management
 * Version: 2.1.3
 * Author: SharedFare
 * Requires Plugins: abilities-api, mcp-adapter
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom ability categories
 */
function sharedfare_v2_register_categories() {
    wp_register_ability_category(
        'sharedfare-content',
        [
            'label' => __('SharedFare Content'),
            'description' => __('Abilities for managing pages, posts, and content on SharedFare.')
        ]
    );
    
    wp_register_ability_category(
        'sharedfare-design',
        [
            'label' => __('SharedFare Design'),
            'description' => __('Abilities for managing site appearance, CSS, colors, and theme settings.')
        ]
    );
    
    error_log('SharedFare Abilities v2: Registered 2 categories');
}

/**
 * Register all Phase 1 abilities
 */
function sharedfare_v2_register_abilities() {
    
    // ============================================================
    // PHASE 1: CONTENT MANAGEMENT ABILITIES
    // ============================================================
    
    // ------------------------------------------------------------
    // Ability 1: List Pages
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/list-pages', [
        'label' => __('List Pages'),
        'description' => __('Retrieve a list of WordPress pages with filtering options.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private', 'any'],
                    'description' => 'Filter by post status'
                ],
                'number' => [
                    'type' => 'integer',
                    'default' => 20,
                    'description' => 'Number of pages to retrieve'
                ],
                'offset' => [
                    'type' => 'integer',
                    'default' => 0,
                    'description' => 'Pagination offset'
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term for page titles'
                ]
            ],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'modified_date' => ['type' => 'string']
                ]
            ]
        ],
        'execute_callback' => function($input) {
            $args = [
                'post_type' => 'page',
                'post_status' => isset($input['status']) ? sanitize_text_field($input['status']) : 'any',
                'numberposts' => isset($input['number']) ? absint($input['number']) : 20,
                'offset' => isset($input['offset']) ? absint($input['offset']) : 0,
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 2: Get Page
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/get-page', [
        'label' => __('Get Page Details'),
        'description' => __('Retrieve detailed information about a specific WordPress page.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'page_id' => [
                    'type' => 'integer',
                    'description' => 'The page ID'
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'The page slug'
                ]
            ],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'author' => ['type' => 'string'],
                'modified_date' => ['type' => 'string'],
                'template' => ['type' => 'string'],
                'parent_id' => ['type' => 'integer'],
                'menu_order' => ['type' => 'integer'],
                'featured_image_url' => ['type' => 'string']
            ]
        ],
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 3: Update Page
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/update-page', [
        'label' => __('Update Page'),
        'description' => __('Update content, title, or other properties of a WordPress page.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'page_id' => [
                    'type' => 'integer',
                    'description' => 'The page ID to update'
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'New page title'
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'New page content (HTML allowed)'
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'New page excerpt'
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private'],
                    'description' => 'Post status'
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Page slug'
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Parent page ID'
                ],
                'menu_order' => [
                    'type' => 'integer',
                    'description' => 'Menu order position'
                ]
            ],
            'required' => ['page_id'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'page_id' => ['type' => 'integer'],
                'message' => ['type' => 'string'],
                'url' => ['type' => 'string']
            ]
        ],
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 4: Create Page
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/create-page', [
        'label' => __('Create Page'),
        'description' => __('Create a new WordPress page.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Page title'
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Page content (HTML allowed)'
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private'],
                    'default' => 'draft',
                    'description' => 'Post status'
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Page slug'
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Parent page ID'
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'Page template filename'
                ]
            ],
            'required' => ['title'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'page_id' => ['type' => 'integer'],
                'message' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'edit_url' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $page_data = [
                'post_type' => 'page',
                'post_title' => sanitize_text_field($input['title']),
                'post_content' => isset($input['content']) ? wp_kses_post($input['content']) : '',
                'post_status' => isset($input['status']) ? sanitize_text_field($input['status']) : 'draft'
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
        },
        'meta' => [
            'annotations' => [
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ],
            'mcp' => [
                'public' => true,
                'type' => 'tool'
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 5: List Posts
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/list-posts', [
        'label' => __('List Posts'),
        'description' => __('Retrieve a list of blog posts with filtering options.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private', 'any'],
                    'description' => 'Filter by post status'
                ],
                'number' => [
                    'type' => 'integer',
                    'default' => 20,
                    'description' => 'Number of posts to retrieve'
                ],
                'offset' => [
                    'type' => 'integer',
                    'default' => 0,
                    'description' => 'Pagination offset'
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search term for post titles'
                ]
            ],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'url' => ['type' => 'string'],
                    'modified_date' => ['type' => 'string'],
                    'categories' => ['type' => 'array'],
                    'tags' => ['type' => 'array']
                ]
            ]
        ],
        'execute_callback' => function($input) {
            $args = [
                'post_type' => 'post',
                'post_status' => isset($input['status']) ? sanitize_text_field($input['status']) : 'any',
                'numberposts' => isset($input['number']) ? absint($input['number']) : 20,
                'offset' => isset($input['offset']) ? absint($input['offset']) : 0,
                'orderby' => 'modified',
                'order' => 'DESC'
            ];
            
            if (!empty($input['search'])) {
                $args['s'] = sanitize_text_field($input['search']);
            }
            
            $posts = get_posts($args);
            
            return array_map(function($post) {
                $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
                $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
                
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'status' => $post->post_status,
                    'url' => get_permalink($post->ID),
                    'modified_date' => $post->post_modified,
                    'categories' => $categories,
                    'tags' => $tags
                ];
            }, $posts);
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 6: Get Post
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/get-post', [
        'label' => __('Get Post Details'),
        'description' => __('Retrieve detailed information about a specific blog post.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'description' => 'The post ID'
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'The post slug'
                ]
            ],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'excerpt' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'author' => ['type' => 'string'],
                'modified_date' => ['type' => 'string'],
                'categories' => ['type' => 'array'],
                'tags' => ['type' => 'array'],
                'featured_image_url' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            // Validate input
            if (empty($input['post_id']) && empty($input['slug'])) {
                return [
                    'success' => false,
                    'error_code' => 'missing_required_field',
                    'message' => 'Either post_id or slug is required'
                ];
            }
            
            // Get post
            if (!empty($input['post_id'])) {
                $post = get_post(absint($input['post_id']));
            } else {
                $post = get_page_by_path(sanitize_text_field($input['slug']), OBJECT, 'post');
            }
            
            if (!$post || $post->post_type !== 'post') {
                return [
                    'success' => false,
                    'error_code' => 'post_not_found',
                    'message' => 'Post not found'
                ];
            }
            
            // Get author name
            $author = get_userdata($post->post_author);
            
            // Get categories and tags
            $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
            $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
            
            // Get featured image
            $featured_image_url = '';
            if (has_post_thumbnail($post->ID)) {
                $featured_image_url = get_the_post_thumbnail_url($post->ID, 'full');
            }
            
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'slug' => $post->post_name,
                'status' => $post->post_status,
                'url' => get_permalink($post->ID),
                'author' => $author ? $author->display_name : '',
                'modified_date' => $post->post_modified,
                'categories' => $categories,
                'tags' => $tags,
                'featured_image_url' => $featured_image_url
            ];
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 7: Update Post
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/update-post', [
        'label' => __('Update Post'),
        'description' => __('Update content, title, or other properties of a blog post.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'description' => 'The post ID to update'
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'New post title'
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'New post content (HTML allowed)'
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'New post excerpt'
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private'],
                    'description' => 'Post status'
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Post slug'
                ],
                'categories' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array of category IDs'
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array of tag names'
                ]
            ],
            'required' => ['post_id'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'post_id' => ['type' => 'integer'],
                'message' => ['type' => 'string'],
                'url' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            // Validate post exists
            $post = get_post(absint($input['post_id']));
            if (!$post || $post->post_type !== 'post') {
                return [
                    'success' => false,
                    'error_code' => 'post_not_found',
                    'message' => 'Post not found'
                ];
            }
            
            // Build update array
            $post_data = ['ID' => absint($input['post_id'])];
            
            if (isset($input['title'])) {
                $post_data['post_title'] = sanitize_text_field($input['title']);
            }
            if (isset($input['content'])) {
                $post_data['post_content'] = wp_kses_post($input['content']);
            }
            if (isset($input['excerpt'])) {
                $post_data['post_excerpt'] = sanitize_text_field($input['excerpt']);
            }
            if (isset($input['status'])) {
                $post_data['post_status'] = sanitize_text_field($input['status']);
            }
            if (isset($input['slug'])) {
                $post_data['post_name'] = sanitize_title($input['slug']);
            }
            
            // Update post
            $result = wp_update_post($post_data, true);
            
            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'error_code' => $result->get_error_code(),
                    'message' => $result->get_error_message()
                ];
            }
            
            // Update categories and tags
            if (isset($input['categories'])) {
                wp_set_post_categories($result, array_map('absint', $input['categories']));
            }
            if (isset($input['tags'])) {
                wp_set_post_tags($result, array_map('sanitize_text_field', $input['tags']));
            }
            
            return [
                'success' => true,
                'post_id' => $result,
                'message' => 'Post updated successfully',
                'url' => get_permalink($result)
            ];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts');
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 8: Create Post
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/create-post', [
        'label' => __('Create Post'),
        'description' => __('Create a new blog post.'),
        'category' => 'sharedfare-content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Post title'
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Post content (HTML allowed)'
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private'],
                    'default' => 'draft',
                    'description' => 'Post status'
                ],
                'slug' => [
                    'type' => 'string',
                    'description' => 'Post slug'
                ],
                'categories' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array of category IDs'
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array of tag names'
                ]
            ],
            'required' => ['title'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'post_id' => ['type' => 'integer'],
                'message' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'edit_url' => ['type' => 'string']
            ]
        ],
        'execute_callback' => function($input) {
            $post_data = [
                'post_type' => 'post',
                'post_title' => sanitize_text_field($input['title']),
                'post_content' => isset($input['content']) ? wp_kses_post($input['content']) : '',
                'post_status' => isset($input['status']) ? sanitize_text_field($input['status']) : 'draft'
            ];
            
            if (isset($input['slug'])) {
                $post_data['post_name'] = sanitize_title($input['slug']);
            }
            
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                return [
                    'success' => false,
                    'error_code' => $post_id->get_error_code(),
                    'message' => $post_id->get_error_message()
                ];
            }
            
            // Set categories and tags
            if (isset($input['categories'])) {
                wp_set_post_categories($post_id, array_map('absint', $input['categories']));
            }
            if (isset($input['tags'])) {
                wp_set_post_tags($post_id, array_map('sanitize_text_field', $input['tags']));
            }
            
            return [
                'success' => true,
                'post_id' => $post_id,
                'message' => 'Post created successfully',
                'url' => get_permalink($post_id),
                'edit_url' => get_edit_post_link($post_id, 'raw')
            ];
        },
        'permission_callback' => function() {
            return current_user_can('edit_posts') && current_user_can('publish_posts');
        },
        'meta' => [
            'annotations' => [
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ],
            'mcp' => [
                'public' => true,
                'type' => 'tool'
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ============================================================
    // PHASE 2: DESIGN & APPEARANCE MANAGEMENT ABILITIES
    // ============================================================
    
    // ------------------------------------------------------------
    // Ability 9: Get Custom CSS
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/get-custom-css', [
        'label' => __('Get Custom CSS'),
        'description' => __('Retrieve the current custom CSS for the site.'),
        'category' => 'sharedfare-design',
        'input_schema' => [
            'type' => 'object',
            'properties' => (object)[],
            'additionalProperties' => false,
            'default' => []
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'css' => ['type' => 'string'],
                'length' => ['type' => 'integer']
            ]
        ],
        'execute_callback' => function($input = []) {
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
            'mcp' => [
                'public' => true,
                'type' => 'tool'
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 10: Update Custom CSS
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/update-custom-css', [
        'label' => __('Update Custom CSS'),
        'description' => __('Update the custom CSS for the site (replaces existing CSS).'),
        'category' => 'sharedfare-design',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'css' => [
                    'type' => 'string',
                    'description' => 'The complete CSS code to set'
                ]
            ],
            'required' => ['css'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
                'css_length' => ['type' => 'integer']
            ]
        ],
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
        'permission_callback' => function() {
            return current_user_can('edit_theme_options');
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 11: Get Theme Mods
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/get-theme-mods', [
        'label' => __('Get Theme Modifications'),
        'description' => __('Retrieve current theme customizations (colors, fonts, logos, etc.).'),
        'category' => 'sharedfare-design',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'mod_names' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Specific theme mods to retrieve. If omitted, returns all.'
                ]
            ],
            'additionalProperties' => false,
            'default' => []
        ],
        'output_schema' => [
            'type' => 'object',
            'additionalProperties' => true
        ],
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
            'mcp' => [
                'public' => true,
                'type' => 'tool'
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 12: Update Theme Mod
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/update-theme-mod', [
        'label' => __('Update Theme Modification'),
        'description' => __('Update a single theme customization setting.'),
        'category' => 'sharedfare-design',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'mod_name' => [
                    'type' => 'string',
                    'description' => 'The theme mod name'
                ],
                'value' => [
                    'description' => 'The new value (can be string, number, array, or object)'
                ]
            ],
            'required' => ['mod_name', 'value'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'mod_name' => ['type' => 'string'],
                'message' => ['type' => 'string']
            ]
        ],
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
        'permission_callback' => function() {
            return current_user_can('edit_theme_options');
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 13: Bulk Update Theme Mods
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/bulk-update-theme-mods', [
        'label' => __('Bulk Update Theme Modifications'),
        'description' => __('Update multiple theme customization settings at once.'),
        'category' => 'sharedfare-design',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'mods' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                    'description' => 'Key-value pairs of theme mod names and values'
                ]
            ],
            'required' => ['mods'],
            'additionalProperties' => false
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'updated_count' => ['type' => 'integer'],
                'message' => ['type' => 'string']
            ]
        ],
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
        },
        'permission_callback' => function() {
            return current_user_can('edit_theme_options');
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
            ],
            'show_in_rest' => true
        ]
    ]);
    
    // ------------------------------------------------------------
    // Ability 14: Debug Theme Info (Diagnostic)
    // ------------------------------------------------------------
    wp_register_ability('sharedfare/debug-theme-info', [
        'label' => __('Debug Theme Info'),
        'description' => __('Get detailed theme information for debugging (theme name, mods, CSS, menus, sidebars).'),
        'category' => 'sharedfare-design',
        'input_schema' => [
            'type' => 'object',
            'properties' => (object)[],
            'additionalProperties' => false,
            'default' => []
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'theme_name' => ['type' => 'string'],
                'theme_version' => ['type' => 'string'],
                'theme_mods' => ['type' => 'object'],
                'custom_css' => ['type' => 'string'],
                'custom_css_length' => ['type' => 'integer'],
                'nav_menus' => ['type' => 'object'],
                'sidebars' => ['type' => 'array']
            ]
        ],
        'execute_callback' => function($input = []) {
            global $wp_registered_sidebars;
            
            $theme = wp_get_theme();
            $all_mods = get_theme_mods();
            $custom_css = wp_get_custom_css();
            $custom_css_post = wp_get_custom_css_post();
            
            return [
                'theme_name' => $theme->get('Name'),
                'theme_version' => $theme->get('Version'),
                'theme_mods' => $all_mods ?: [],
                'custom_css' => $custom_css,
                'custom_css_length' => strlen($custom_css),
                'custom_css_post_id' => $custom_css_post ? $custom_css_post->ID : null,
                'nav_menus' => get_registered_nav_menus(),
                'sidebars' => array_keys($wp_registered_sidebars ?: [])
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
            'mcp' => [
                'public' => true,
                'type' => 'tool'
            ],
            'show_in_rest' => true
        ]
    ]);
    
    error_log('SharedFare Abilities v2: Registered 14 abilities total (8 content + 6 design)');
}

/**
 * Initialize plugin - register hooks immediately
 */
function sharedfare_v2_init() {
    // Register abilities hooks immediately (before plugins_loaded)
    add_action('wp_abilities_api_categories_init', 'sharedfare_v2_register_categories', 10);
    add_action('wp_abilities_api_init', 'sharedfare_v2_register_abilities', 10);
    
    // Also register immediately if hooks already fired
    if (did_action('wp_abilities_api_categories_init')) {
        sharedfare_v2_register_categories();
    }
    if (did_action('wp_abilities_api_init')) {
        sharedfare_v2_register_abilities();
    }
}

// Call init immediately when plugin loads
sharedfare_v2_init();

<?php
/**
 * Plugin Name: SharedFare MCP Abilities Enabler
 * Description: Enables core WordPress abilities to be exposed through the MCP adapter by adding mcp.public=true metadata
 * Version: 1.0.1
 * Author: SharedFare
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add MCP metadata to core WordPress abilities using the registration filter
 */
add_filter( 'wp_register_ability_args', 'sharedfare_add_mcp_metadata_to_abilities', 10, 2 );

function sharedfare_add_mcp_metadata_to_abilities( $args, $name ) {
    // List of abilities to enable for MCP (core + custom)
    $mcp_abilities = array(
        'core/get-site-info',
        'core/get-user-info',
        'core/get-environment-info',
        'sharedfare/update-page',
        'sharedfare/add-custom-css',
        'sharedfare/get-page',
        'sharedfare/list-pages',
    );

    // Check if this ability should be exposed via MCP
    if ( in_array( $name, $mcp_abilities, true ) ) {
        // Ensure meta array exists
        if ( ! isset( $args['meta'] ) ) {
            $args['meta'] = array();
        }
        
        // Add MCP public flag
        if ( ! isset( $args['meta']['mcp'] ) ) {
            $args['meta']['mcp'] = array();
        }
        
        $args['meta']['mcp']['public'] = true;
        $args['meta']['mcp']['type'] = 'tool';
    }

    return $args;
}

<?php
/**
 * Uninstall script for WordPress Custom Functions
 *
 * @package WPCustomFunctions
 */

// Si no se llama desde WordPress, salir
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Eliminar opciones
$options = [
    'wp_custom_functions_version',
    'wp_custom_geo_cache_expiry',
    'wp_custom_hubspot_portal_id',
    'wp_custom_hubspot_region',
    'wp_custom_functions_config',
];

foreach ( $options as $option ) {
    delete_option( $option );
    delete_site_option( $option ); // Multisite
}

// Eliminar transients
global $wpdb;
$wpdb->query( 
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_wp_custom_%' 
     OR option_name LIKE '_transient_timeout_wp_custom_%'"
);

// Eliminar tabla de cache de geolocalización
$table_name = $wpdb->prefix . 'wp_custom_geo_cache';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Flush rewrite rules
flush_rewrite_rules();
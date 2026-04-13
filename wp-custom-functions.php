<?php
/**
 * Plugin Name: WordPress Custom Functions
 * Plugin URI: https://example.com/
 * Description: Advanced geolocation, HubSpot, WhatsApp integrations, optimizations and accessibility for WordPress
 * Version: 1.0.0
  * Author: Lucas Bettera
 * Author URI: https://github.com/lucasbettera
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-custom-functions
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * 
 * @package WPCustomFunctions
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definir constantes del plugin
define( 'WP_CUSTOM_FUNCTIONS_VERSION', '1.0.1' );
define( 'WP_CUSTOM_FUNCTIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_CUSTOM_FUNCTIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_CUSTOM_FUNCTIONS_BASENAME', plugin_basename( __FILE__ ) );

// Cargar archivos de traducción
add_action( 'plugins_loaded', 'wp_custom_functions_load_textdomain' );
function wp_custom_functions_load_textdomain() {
    load_plugin_textdomain( 
        'wp-custom-functions', 
        false, 
        dirname( plugin_basename( __FILE__ ) ) . '/languages' 
    );
}

// Clase principal del plugin
class WP_Custom_Functions {
    
    private static $instance = null;
    private $modules = [];
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->define_hooks();
    }
    
    private function define_hooks() {
        // Registrar activación/desactivación
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        // Inicializar después de que todos los plugins estén cargados
        add_action( 'plugins_loaded', [ $this, 'init_plugin' ], 5 );
        
        // Cargar módulos después del tema
        add_action( 'after_setup_theme', [ $this, 'load_modules' ], 20 );
        
        // Cargar assets del admin
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        }
    }
    
    public function activate() {
                // Crear tabla de cache de geolocalización si no existe
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_custom_geo_cache';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            country_code varchar(2) NOT NULL,
            data text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address),
            KEY country_code (country_code),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
                // Añadir opciones por defecto
        add_option( 'wp_custom_functions_version', WP_CUSTOM_FUNCTIONS_VERSION );
        add_option( 'wp_custom_geo_cache_expiry', 86400 ); // 24 horas en segundos
        add_option( 'wp_custom_hubspot_portal_id', '22204650' );
        add_option( 'wp_custom_hubspot_region', 'na1' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Limpiar cache de transients
        $this->clean_geo_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
        private function clean_geo_cache() {
        // Eliminar transients antiguos
        global $wpdb;
        $wpdb->query( 
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_custom_geo_%' 
             OR option_name LIKE '_transient_timeout_wp_custom_geo_%'"
        );
    }
    
        public function init_plugin() {
        // Cargar archivos helpers primero
        $this->load_helper_files();
        
        // Cargar configuración
        $this->load_config();
        
        // Inicializar componentes
        $this->init_components();
        
                // Hacer que el plugin sea extensible
        do_action( 'wp_custom_functions_loaded' );
    }
    
        private function load_helper_files() {
        $helper_files = [
            'includes/helpers.php',
        ];
        
                foreach ( $helper_files as $file ) {
            $file_path = WP_CUSTOM_FUNCTIONS_PATH . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
    
        private function load_config() {
        $config_file = WP_CUSTOM_FUNCTIONS_PATH . 'modules/config.php';
        if ( file_exists( $config_file ) ) {
            require_once $config_file;
        }
    }
    
    private function init_components() {
        // Aquí se inicializan componentes que deben estar disponibles temprano
        // Por ahora vacío, se puede extender
    }
    
            public function load_modules() {
                // Cargar configuración si no está cargada
        if ( ! function_exists( 'wp_custom_config' ) ) {
            $this->load_config();
        }
        
                // Definir módulos disponibles
        $this->modules = [
            'geolocation'        => [
                'file'    => 'modules/geolocation.php',
                'enabled' => wp_custom_config( 'modules.geolocation.enabled', true ),
                'priority' => 10,
            ],
            'hubspot-integration' => [
                'file'    => 'modules/hubspot-integration.php',
                'enabled' => wp_custom_config( 'modules.hubspot-integration.enabled', true ),
                'priority' => 20,
            ],
            'whatsapp-integration' => [
                'file'    => 'modules/whatsapp-integration.php',
                'enabled' => wp_custom_config( 'modules.whatsapp-integration.enabled', true ),
                'priority' => 30,
            ],
            'optimizations'      => [
                'file'    => 'modules/optimizations.php',
                'enabled' => wp_custom_config( 'modules.optimizations.enabled', true ),
                'priority' => 40,
            ],
            'accessibility'      => [
                'file'    => 'modules/accessibility.php',
                'enabled' => wp_custom_config( 'modules.accessibility.enabled', true ),
                'priority' => 50,
            ],
            'security'           => [
                'file'    => 'modules/security.php',
                'enabled' => wp_custom_config( 'modules.security.enabled', false ), // Desactivado por defecto
                'priority' => 60,
            ],
                        'shortcodes'         => [
                'file'    => 'modules/shortcodes.php',
                'enabled' => wp_custom_config( 'modules.shortcodes.enabled', true ),
                'priority' => 70,
            ],
            'wpml-redirector'    => [
                'file'    => 'modules/wpml-redirector.php',
                'enabled' => wp_custom_config( 'modules.wpml-redirector.enabled', true ),
                'priority' => 80,
            ],
            'utm-manager'        => [
                'file'    => 'modules/utm-manager.php',
                'enabled' => wp_custom_config( 'modules.utm-manager.enabled', true ),
                'priority' => 90,
            ],
            'hreflang-manager'   => [
                'file'    => 'modules/hreflang-manager.php',
                'enabled' => wp_custom_config( 'modules.hreflang-manager.enabled', true ),
                'priority' => 95,
            ],
        ];
        
                // Filtrar módulos (permitir que otros plugins/modifiquen)
        $this->modules = apply_filters( 'wp_custom_functions_modules', $this->modules );
        
        // Ordenar módulos por prioridad
        uasort( $this->modules, function( $a, $b ) {
            return ( $a['priority'] ?? 100 ) - ( $b['priority'] ?? 100 );
        });
        
        // Cargar módulos habilitados
        foreach ( $this->modules as $module ) {
            if ( $module['enabled'] ) {
                $file_path = WP_CUSTOM_FUNCTIONS_PATH . $module['file'];
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                    
                                        // Debug: registrar módulo cargado
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'WP Custom Functions: Módulo cargado - ' . basename( $module['file'] ) );
                    }
                }
            }
        }
    }
    
    public function enqueue_admin_assets( $hook ) {
                // Solo cargar en nuestra página de settings
        if ( 'settings_page_wp-custom-functions-settings' === $hook ) {
            wp_enqueue_style( 
                'wp-custom-functions-admin', 
                WP_CUSTOM_FUNCTIONS_URL . 'assets/css/admin.css', 
                [], 
                WP_CUSTOM_FUNCTIONS_VERSION 
            );
            
            wp_enqueue_script( 
                'wp-custom-functions-admin', 
                WP_CUSTOM_FUNCTIONS_URL . 'assets/js/admin.js', 
                [ 'jquery' ], 
                WP_CUSTOM_FUNCTIONS_VERSION, 
                true 
            );
        }
    }
    
    public function get_module( $module_name ) {
        return isset( $this->modules[ $module_name ] ) ? $this->modules[ $module_name ] : null;
    }
    
    public function get_modules() {
        return $this->modules;
    }
}

// Inicializar el plugin
function wp_custom_functions() {
    return WP_Custom_Functions::get_instance();
}

// Iniciar
add_action( 'plugins_loaded', 'wp_custom_functions', 1 );

// Hook para compatibilidad con versiones anteriores
function wp_custom_functions_init() {
    do_action( 'wp_custom_functions_init' );
}
add_action( 'init', 'wp_custom_functions_init' );

// Añadir enlace de settings en plugins page
add_filter( 'plugin_action_links_' . WP_CUSTOM_FUNCTIONS_BASENAME, 'wp_custom_functions_action_links' );
function wp_custom_functions_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=wp-custom-functions-settings' ) . '">' . __( 'Settings', 'wp-custom-functions' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
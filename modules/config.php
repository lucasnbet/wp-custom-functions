<?php
/**
 * Configuration Module
 * 
 * @package WPCustomFunctions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Custom_Config {
    
    private static $instance = null;
    private $config = [];
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_default_config();
        $this->load_user_config();
    }
    
    private function load_default_config() {
        $this->config = [
            'modules' => [
                'geolocation' => [
                    'enabled' => true,
                    'settings' => [
                        'cache_expiry' => 86400,
                        'enable_ip_lookup' => true,
                        'enable_cdn_headers' => true,
                        'enable_url_detection' => true,
                        'enable_locale_detection' => true,
                    ]
                ],
                'hubspot-integration' => [
                    'enabled' => true,
                    'settings' => [
                        'portal_id' => '22204650',
                        'region' => 'na1',
                        'lazy_load' => true,
                    ]
                ],
                'whatsapp-integration' => [
                    'enabled' => true,
                    'settings' => [
                        'phone_number' => '',
                        'default_message' => '',
                        'business_hours' => true,
                    ]
                ],
                'optimizations' => [
                    'enabled' => true,
                    'settings' => [
                        'disable_heartbeat_frontend' => false,
                        'optimize_images' => true,
                        'remove_jquery_migrate' => true,
                        'cleanup_emojis' => true,
                        'preload_lcp_image' => true,
                        'force_sitemap_update' => false,
                    ]
                ],
                'accessibility' => [
                    'enabled' => true,
                    'settings' => [
                        'enable_skip_links' => true,
                        'enable_aria_labels' => true,
                        'enable_contrast_toggle' => true,
                        'enable_font_size_toggle' => true,
                    ]
                ],
                'security' => [
                    'enabled' => false,
                    'settings' => [
                        'enable_security_headers' => false,
                        'enable_csp' => false,
                        'enable_xml_error_prevention' => false,
                        'enable_version_removal' => false,
                    ]
                ],
                'shortcodes' => [
                    'enabled' => true,
                    'settings' => []
                ],
                'wpml-redirector' => [
                    'enabled' => true,
                    'settings' => [
                        'enable_redirects' => true,
                        'show_selector_on_fail' => true,
                        'log_detections' => true,
                        'log_redirects' => true,
                    ]
                ],
                'utm-manager' => [
                    'enabled' => true,
                    'settings' => [
                        'enable_utm_persistence' => true,
                        'storage_method' => 'local_storage', // Cambiado a local_storage para compatibilidad con GTM
                        'storage_key' => 'wp_custom_utm_params', // Usar misma key que tu script GTM
                        'utm_parameters' => [
                            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                            'gclid', 'fbclid', 'msclkid', // Parámetros adicionales
                        ],
                        'session_duration' => 864000, // 10 días en segundos (como tu script)
                        'propagate_to_links' => true,
                        'propagate_to_forms' => true,
                        'update_on_change' => true,
                        'clear_on_direct_access' => false, // No limpiar en acceso directo
                        'preserve_on_empty_params' => true, // Preservar cuando no hay parámetros nuevos
                        'debug_mode' => true, // Activar debug para troubleshooting
                        'custom_domains' => [ // Dominios personalizados
                            'example.com',
                            'www.example.com'
                        ],
                        'expiry_days' => 10, // 10 días como tu script
                        'filter_params_after_day' => true, // Filtrar parámetros después de 1 día
                        'filtered_params_after_day' => ['gclid', 'fbclid', 'msclkid', 'utm_term', 'utm_content'],
                    ]
                ],
                'hreflang-manager' => [
                    'enabled' => true,
                    'settings' => [
                        'enable_hreflang' => true,
                        'languages' => [
                            'es' => [
                                'code' => 'es',
                                'name' => 'Español',
                                'hreflang' => 'es',
                                'locale' => 'es_ES',
                                'countries' => ['ES', 'AR', 'CL', 'CO', 'EC', 'MX', 'PE', 'VE', 'UY', 'PY', 'BO', 'CR', 'CU', 'DO', 'GT', 'HN', 'NI', 'PA', 'PR', 'SV'],
                                'default_country' => 'ES',
                                'url_pattern' => '/es/',
                                'is_default' => true,
                            ],
                            'pt-br' => [
                                'code' => 'pt-br',
                                'name' => 'Português Brasileiro',
                                'hreflang' => 'pt-br',
                                'locale' => 'pt_BR',
                                'countries' => ['BR'],
                                'default_country' => 'BR',
                                'url_pattern' => '/pt-br/',
                                'is_default' => false,
                            ],
                            'en' => [
                                'code' => 'en',
                                'name' => 'English',
                                'hreflang' => 'en',
                                'locale' => 'en_US',
                                'countries' => ['US', 'GB', 'CA', 'AU', 'NZ', 'IE', 'SG', 'IN', 'ZA', 'AE', 'PT', 'DE', 'FR', 'IT', 'NL', 'BE', 'CH', 'SE', 'NO', 'DK', 'FI', 'PL', 'RU', 'CN', 'JP', 'KR', 'JM', 'BB', 'BS', 'GD', 'LC', 'VC', 'TT'],
                                'default_country' => 'US',
                                'url_pattern' => '/en/',
                                'is_default' => false,
                            ],
                        ],
                        'x_default' => 'es', // Idioma por defecto
                        'auto_detect' => true,
                        'add_to_head' => true,
                        'debug_mode' => true,
                        'sitemap_integration' => true,
                    ]
                ],
            ],
            'debug' => [
                'enabled' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'log_level' => 'error',
            ]
        ];
    }
    
    private function load_user_config() {
        $user_config = get_option( 'wp_custom_functions_config', [] );
        
        if ( ! empty( $user_config ) && is_array( $user_config ) ) {
            $this->config = array_replace_recursive( $this->config, $user_config );
        }
    }
    
    public function get( $key, $default = null ) {
        $keys = explode( '.', $key );
        $value = $this->config;
        
        foreach ( $keys as $k ) {
            if ( isset( $value[ $k ] ) ) {
                $value = $value[ $k ];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    public function set( $key, $value ) {
        $keys = explode( '.', $key );
        $config = &$this->config;
        
        foreach ( $keys as $k ) {
            if ( ! isset( $config[ $k ] ) ) {
                $config[ $k ] = [];
            }
            $config = &$config[ $k ];
        }
        
        $config = $value;
        return $this->save();
    }
    
    public function is_module_enabled( $module_name ) {
        return $this->get( "modules.{$module_name}.enabled", false );
    }
    
    public function get_module_settings( $module_name ) {
        return $this->get( "modules.{$module_name}.settings", [] );
    }
    
    private function save() {
        return update_option( 'wp_custom_functions_config', $this->config );
    }
    
    public function get_all_config() {
        return $this->config;
    }
    
    public function reset_to_defaults() {
        $this->load_default_config();
        return $this->save();
    }
}

// Funcion helper para acceder a la configuracion
if ( ! function_exists( 'wp_custom_config' ) ) {
    function wp_custom_config( $key = null, $default = null ) {
        $config = WP_Custom_Config::get_instance();
        
        if ( $key === null ) {
            return $config;
        }
        
        return $config->get( $key, $default );
    }
}

// Inicializar configuracion
add_action( 'plugins_loaded', function() {
    WP_Custom_Config::get_instance();
}, 1 );

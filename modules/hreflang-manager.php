<?php
/**
 * Hreflang Manager Module
 * 
 * Maneja correctamente los atributos hreflang para SEO multilingüe
 * Compatible con WPML y arquitectura CDN → WAF → Balancer → Server
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Hreflang_Manager {
    
    private static $instance = null;
    private $settings = [];
    private $languages = [];
    private $current_language = '';
    private $default_language = 'es';
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = cirion_config( 'modules.hreflang-manager.settings', [] );
        
        if ( $this->is_enabled() ) {
            $this->init_languages();
            $this->init_current_language();
            $this->init_hooks();
        }
    }
    
    private function is_enabled() {
        return cirion_config( 'modules.hreflang-manager.enabled', false ) && 
               $this->settings['enable_hreflang'] ?? false;
    }
    
    private function init_languages() {
        $this->languages = $this->settings['languages'] ?? [];
        $this->default_language = $this->settings['x_default'] ?? 'es';
    }
    
    private function init_current_language() {
        // Detectar idioma actual
        $this->current_language = $this->detect_current_language();
    }
    
    private function detect_current_language() {
        // 1. Verificar si WPML está activo
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $wpml_lang = apply_filters( 'wpml_current_language', null );
            if ( $wpml_lang ) {
                return $wpml_lang;
            }
        }
        
        // 2. Verificar en la URL
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        foreach ( $this->languages as $lang_code => $lang_data ) {
            $pattern = $lang_data['url_pattern'] ?? '';
            if ( $pattern && strpos( $uri, $pattern ) === 0 ) {
                return $lang_code;
            }
        }
        
        // 3. Verificar cookie de idioma
        if ( isset( $_COOKIE['_icl_current_language'] ) ) {
            $cookie_lang = $_COOKIE['_icl_current_language'];
            if ( isset( $this->languages[ $cookie_lang ] ) ) {
                return $cookie_lang;
            }
        }
        
        // 4. Detectar por geolocalización si está habilitado
        if ( $this->settings['auto_detect'] ?? true ) {
            $geo_lang = $this->detect_language_by_geolocation();
            if ( $geo_lang ) {
                return $geo_lang;
            }
        }
        
        // 5. Idioma por defecto
        return $this->default_language;
    }
    
    private function detect_language_by_geolocation() {
        // Usar el módulo de geolocalización si está disponible
        if ( class_exists( 'Cirion_Geolocation' ) && method_exists( 'Cirion_Geolocation', 'get_instance' ) ) {
            try {
                $geo = Cirion_Geolocation::get_instance();
                if ( method_exists( $geo, 'get_country_code' ) ) {
                    $country = $geo->get_country_code();
                    return $this->country_to_language( $country );
                }
            } catch ( Exception $e ) {
                // Fallback
            }
        }
        
        // Fallback: usar headers HTTP
        if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            $country = strtoupper( $_SERVER['HTTP_CF_IPCOUNTRY'] );
            return $this->country_to_language( $country );
        }
        
        // Fallback: Accept-Language header
        if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            $accept_lang = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
            if ( $accept_lang === 'pt' ) return 'pt-br';
            if ( $accept_lang === 'en' ) return 'en';
            if ( $accept_lang === 'es' ) return 'es';
        }
        
        return null;
    }
    
    private function country_to_language( $country_code ) {
        foreach ( $this->languages as $lang_code => $lang_data ) {
            $countries = $lang_data['countries'] ?? [];
            if ( in_array( $country_code, $countries ) ) {
                return $lang_code;
            }
        }
        
        return $this->default_language;
    }
    
    private function init_hooks() {
        // Añadir hreflang al head
        if ( $this->settings['add_to_head'] ?? true ) {
            add_action( 'wp_head', [ $this, 'output_hreflang_tags' ], 5 );
        }
        
        // Integración con sitemap
        if ( $this->settings['sitemap_integration'] ?? true ) {
            add_filter( 'wpseo_sitemap_url', [ $this, 'add_hreflang_to_sitemap' ], 10, 2 );
            add_filter( 'rank_math/sitemap/url', [ $this, 'add_hreflang_to_sitemap_rankmath' ], 10, 2 );
        }
        
        // Shortcode para testing
        add_shortcode( 'cirion_hreflang_info', [ $this, 'shortcode_hreflang_info' ] );
        
        // Debug
        if ( $this->settings['debug_mode'] ?? false ) {
            add_action( 'wp_footer', [ $this, 'output_debug_info' ] );
            add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_debug' ], 100 );
        }
    }
    
    /**
     * Obtiene la URL canónica para un idioma específico
     */
    public function get_canonical_url( $lang_code = null ) {
        if ( $lang_code === null ) {
            $lang_code = $this->current_language;
        }
        
        if ( ! isset( $this->languages[ $lang_code ] ) ) {
            return home_url();
        }
        
        global $wp;
        $current_url = home_url( add_query_arg( [], $wp->request ) );
        
        // Si es la página principal
        if ( is_front_page() ) {
            $pattern = $this->languages[ $lang_code ]['url_pattern'] ?? '';
            return home_url( $pattern );
        }
        
        // Para otras páginas, necesitamos la URL traducida
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            // Usar WPML para obtener URL traducida
            $post_id = get_the_ID();
            if ( $post_id ) {
                $translated_id = apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), false, $lang_code );
                if ( $translated_id ) {
                    return get_permalink( $translated_id );
                }
            }
        }
        
        // Fallback: añadir prefijo de idioma a la URL actual
        $pattern = $this->languages[ $lang_code ]['url_pattern'] ?? '';
        $base_pattern = $this->languages[ $this->default_language ]['url_pattern'] ?? '';
        
        if ( $lang_code === $this->default_language ) {
            // Remover prefijo de idioma si existe
            foreach ( $this->languages as $code => $data ) {
                $lang_pattern = $data['url_pattern'] ?? '';
                if ( $code !== $this->default_language && strpos( $current_url, $lang_pattern ) !== false ) {
                    return str_replace( $lang_pattern, '/', $current_url );
                }
            }
            return $current_url;
        } else {
            // Añadir prefijo de idioma
            if ( strpos( $current_url, $base_pattern ) === false ) {
                return str_replace( home_url(), home_url( $pattern ), $current_url );
            }
            return str_replace( $base_pattern, $pattern, $current_url );
        }
    }
    
    /**
     * Genera y output los tags hreflang
     */
    public function output_hreflang_tags() {
        // No output en admin
        if ( is_admin() ) {
            return;
        }
        
        // No output en páginas de archivo, búsqueda, 404, etc.
        if ( is_archive() || is_search() || is_404() ) {
            return;
        }
        
        $hreflang_tags = [];
        
        // Añadir x-default
        if ( isset( $this->languages[ $this->default_language ] ) ) {
            $xdefault_url = $this->get_canonical_url( $this->default_language );
            $hreflang_tags[] = sprintf(
                '<link rel="alternate" hreflang="x-default" href="%s" />',
                esc_url( $xdefault_url )
            );
        }
        
        // Añadir todos los idiomas
        foreach ( $this->languages as $lang_code => $lang_data ) {
            $hreflang_code = $lang_data['hreflang'] ?? $lang_code;
            $lang_url = $this->get_canonical_url( $lang_code );
            
            $hreflang_tags[] = sprintf(
                '<link rel="alternate" hreflang="%s" href="%s" />',
                esc_attr( $hreflang_code ),
                esc_url( $lang_url )
            );
        }
        
        // Output los tags
        echo "\n<!-- Cirion Hreflang Tags -->\n";
        echo implode( "\n", $hreflang_tags );
        echo "\n<!-- End Cirion Hreflang Tags -->\n";
        
        // Debug info
        if ( $this->settings['debug_mode'] ?? false ) {
            $this->log_hreflang_output( $hreflang_tags );
        }
    }
    
    /**
     * Añade hreflang al sitemap de Yoast SEO
     */
    public function add_hreflang_to_sitemap( $output, $url ) {
        if ( ! isset( $url['loc'] ) ) {
            return $output;
        }
        
        $hreflang_output = '';
        
        // Añadir x-default
        $xdefault_url = $this->get_canonical_url( $this->default_language );
        $hreflang_output .= "\t\t<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . esc_url( $xdefault_url ) . "\"/>\n";
        
        // Añadir todos los idiomas
        foreach ( $this->languages as $lang_code => $lang_data ) {
            $hreflang_code = $lang_data['hreflang'] ?? $lang_code;
            $lang_url = $this->get_canonical_url( $lang_code );
            
            $hreflang_output .= "\t\t<xhtml:link rel=\"alternate\" hreflang=\"" . esc_attr( $hreflang_code ) . "\" href=\"" . esc_url( $lang_url ) . "\"/>\n";
        }
        
        // Insertar después de <loc>
        $output = str_replace( "</loc>", "</loc>\n" . $hreflang_output, $output );
        
        return $output;
    }
    
    /**
     * Añade hreflang al sitemap de Rank Math
     */
    public function add_hreflang_to_sitemap_rankmath( $output, $url ) {
        return $this->add_hreflang_to_sitemap( $output, $url );
    }
    
    /**
     * Log de output hreflang para debugging
     */
    private function log_hreflang_output( $tags ) {
        $log_file = WP_CONTENT_DIR . '/logs/cirion-hreflang.log';
        
        $message = sprintf(
            "[%s] URL: %s, Current Lang: %s, Tags: %s\n",
            date( 'Y-m-d H:i:s' ),
            home_url( add_query_arg( [], $GLOBALS['wp']->request ) ),
            $this->current_language,
            json_encode( array_map( function( $tag ) {
                return strip_tags( $tag );
            }, $tags ) )
        );
        
        $this->write_log( $log_file, $message );
    }
    
    /**
     * Escribe en archivo de log
     */
    private function write_log( $file, $message ) {
        // Crear directorio si no existe
        $log_dir = dirname( $file );
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }
        
        // Escribir log
        @file_put_contents( $file, $message, FILE_APPEND | LOCK_EX );
    }
    
    /**
     * Output debug information
     */
    public function output_debug_info() {
        if ( ! $this->settings['debug_mode'] ?? false ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        echo '<div style="position: fixed; bottom: 10px; left: 10px; background: #f0f0f0; padding: 10px; border: 1px solid #ccc; z-index: 9999; font-size: 12px; max-width: 400px;">';
        echo '<strong>Hreflang Debug Info</strong><br>';
        echo 'Current Language: ' . esc_html( $this->current_language ) . '<br>';
        echo 'Default Language: ' . esc_html( $this->default_language ) . '<br>';
        echo 'Languages Configured: ' . implode( ', ', array_keys( $this->languages ) ) . '<br>';
        echo 'Current URL: ' . esc_url( home_url( add_query_arg( [], $GLOBALS['wp']->request ) ) ) . '<br>';
        
        echo '<hr><strong>Canonical URLs:</strong><br>';
        foreach ( $this->languages as $lang_code => $lang_data ) {
            echo esc_html( $lang_code ) . ': ' . esc_url( $this->get_canonical_url( $lang_code ) ) . '<br>';
        }
        
        echo '</div>';
    }
    
    /**
     * Debug en admin bar
     */
    public function add_admin_bar_debug( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $wp_admin_bar->add_node( [
            'id'    => 'cirion-hreflang-debug',
            'title' => '🌐 ' . strtoupper( $this->current_language ) . ' (hreflang)',
            'href'  => '#',
            'meta'  => [
                'title' => 'Current Language: ' . $this->current_language,
                'class' => 'cirion-hreflang-debug-node',
            ],
        ] );
        
        // Submenu con info detallada
        $wp_admin_bar->add_node( [
            'id'     => 'cirion-hreflang-details',
            'parent' => 'cirion-hreflang-debug',
            'title'  => 'Default: ' . $this->default_language,
        ] );
        
        foreach ( $this->languages as $lang_code => $lang_data ) {
            $wp_admin_bar->add_node( [
                'id'     => 'cirion-hreflang-' . $lang_code,
                'parent' => 'cirion-hreflang-debug',
                'title'  => strtoupper( $lang_code ) . ': ' . esc_url( $this->get_canonical_url( $lang_code ) ),
                'href'   => $this->get_canonical_url( $lang_code ),
                'meta'   => [
                    'target' => '_blank',
                ],
            ] );
        }
    }
    
    /**
     * Shortcode para mostrar info de hreflang
     */
    public function shortcode_hreflang_info( $atts ) {
        $atts = shortcode_atts( [
            'show' => 'all', // all, current, languages, urls
        ], $atts );
        
        ob_start();
        ?>
        <div class="cirion-hreflang-info" style="margin:20px 0; padding:15px; background:#f5f5f5; border-left:4px solid #0073aa;">
            <h4 style="margin-top:0;">🌐 Hreflang Information</h4>
            
            <?php if ( in_array( $atts['show'], ['all', 'current'] ) ) : ?>
            <p><strong>Current Language:</strong> <?php echo esc_html( $this->current_language ); ?></p>
            <p><strong>Default Language:</strong> <?php echo esc_html( $this->default_language ); ?></p>
            <?php endif; ?>
            
            <?php if ( in_array( $atts['show'], ['all', 'languages'] ) ) : ?>
            <p><strong>Configured Languages:</strong></p>
            <ul style="margin-left:20px;">
                <?php foreach ( $this->languages as $lang_code => $lang_data ) : ?>
                <li>
                    <strong><?php echo esc_html( $lang_code ); ?>:</strong> 
                                        <?php echo esc_html( $lang_data['name'] ?? $lang_code ); ?>
                    (hreflang: <?php echo esc_html( $lang_data['hreflang'] ?? $lang_code ); ?>)
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <?php if ( in_array( $atts['show'], ['all', 'urls'] ) ) : ?>
            <p><strong>Canonical URLs:</strong></p>
            <ul style="margin-left:20px;">
                <?php foreach ( $this->languages as $lang_code => $lang_data ) : ?>
                <li>
                    <strong><?php echo esc_html( $lang_code ); ?>:</strong> 
                    <a href="<?php echo esc_url( $this->get_canonical_url( $lang_code ) ); ?>" target="_blank">
                        <?php echo esc_url( $this->get_canonical_url( $lang_code ) ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Inicializar el módulo
add_action( 'plugins_loaded', function() {
    if ( cirion_config( 'modules.hreflang-manager.enabled', false ) ) {
        Cirion_Hreflang_Manager::get_instance();
    }
}, 20 );

// Funciones helper para templates
if ( ! function_exists( 'cirion_get_hreflang_info' ) ) {
    function cirion_get_hreflang_info( $show = 'all' ) {
        $hreflang_manager = Cirion_Hreflang_Manager::get_instance();
        return $hreflang_manager->shortcode_hreflang_info( [ 'show' => $show ] );
    }
}

if ( ! function_exists( 'cirion_get_current_language' ) ) {
    function cirion_get_current_language() {
        $hreflang_manager = Cirion_Hreflang_Manager::get_instance();
        return $hreflang_manager->current_language;
    }
}

if ( ! function_exists( 'cirion_get_canonical_url' ) ) {
    function cirion_get_canonical_url( $lang_code = null ) {
        $hreflang_manager = Cirion_Hreflang_Manager::get_instance();
        return $hreflang_manager->get_canonical_url( $lang_code );
    }
}
                   
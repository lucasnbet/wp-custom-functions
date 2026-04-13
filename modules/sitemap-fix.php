"<?php
/**
 * Sitemap Fix Module
 * 
 * Soluciona conflictos entre diferentes plugins de sitemap
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Sitemap_Fix {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Desactivar sitemaps de otros plugins
        add_action( 'plugins_loaded', [ $this, 'disable_other_sitemaps' ], 20 );
        
        // Forzar uso de Google XML Sitemaps
        add_action( 'init', [ $this, 'force_google_xml_sitemaps' ], 5 );
        
        // Limpiar cache de sitemaps
        add_action( 'save_post', [ $this, 'clear_sitemap_cache' ], 99, 1 );
    }
    
    /**
     * Desactiva los sitemaps de Yoast SEO y Rank Math
     */
    public function disable_other_sitemaps() {
        // Desactivar sitemaps de Yoast SEO
        if ( class_exists( 'WPSEO_Options' ) ) {
            add_filter( 'wpseo_enable_xml_sitemap', '__return_false' );
            add_filter( 'option_wpseo', function( $options ) {
                if ( is_array( $options ) ) {
                    $options['enable_xml_sitemap'] = false;
                }
                return $options;
            } );
        }
        
        // Desactivar sitemaps de Rank Math
        if ( class_exists( 'RankMath' ) ) {
            add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );
            add_filter( 'rank_math/sitemap/enabled', '__return_false' );
        }
    }
    
    /**
     * Fuerza el uso de Google XML Sitemaps
     */
    public function force_google_xml_sitemaps() {
        // Verificar que Google XML Sitemaps esté activo
        if ( ! function_exists( 'sm_get_sitemap_manager' ) ) {
            return;
        }
        
        // Asegurar que el sitemap principal sea el de Google XML Sitemaps
        add_filter( 'robots_txt', [ $this, 'fix_robots_txt_sitemaps' ], 20, 2 );
        
        // Redirigir sitemap_index.xml a sitemap.xml si es necesario
        add_action( 'template_redirect', [ $this, 'redirect_sitemap_urls' ] );
    }
    
    /**
     * Corrige las URLs de sitemap en robots.txt
     */
    public function fix_robots_txt_sitemaps( $output, $public ) {
        if ( '0' === $public ) {
            return $output;
        }
        
        // Remover todas las líneas de sitemap existentes
        $lines = explode( "\n", $output );
        $new_lines = [];
        
        foreach ( $lines as $line ) {
            if ( strpos( $line, 'Sitemap:' ) === false ) {
                $new_lines[] = $line;
            }
        }
        
        // Añadir solo el sitemap de Google XML Sitemaps
        $new_lines[] = "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
        
        return implode( "\n", $new_lines );
    }
    
    /**
     * Redirige URLs de sitemap conflictivas
     */
    public function redirect_sitemap_urls() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Redirigir sitemap_index.xml a sitemap.xml
        if ( $uri === '/sitemap_index.xml' || $uri === '/sitemap_index.xml/' ) {
            wp_redirect( home_url( '/sitemap.xml' ), 301 );
            exit;
        }
        
        // Redirigir otros sitemaps conflictivos
        $conflictive_sitemaps = [
            '/page-sitemap.xml',
            '/post-sitemap.xml',
            '/category-sitemap.xml',
            '/post_tag-sitemap.xml',
            '/author-sitemap.xml',
        ];
        
        foreach ( $conflictive_sitemaps as $sitemap ) {
            if ( $uri === $sitemap || $uri === $sitemap . '/' ) {
                wp_redirect( home_url( '/sitemap.xml' ), 301 );
                exit;
            }
        }
    }
    
    /**
     * Limpia el cache de sitemaps
     */
    public function clear_sitemap_cache( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Limpiar cache de Google XML Sitemaps
        if ( function_exists( 'sm_get_sitemap_manager' ) ) {
            $sitemap_manager = sm_get_sitemap_manager();
            if ( method_exists( $sitemap_manager, 'invalidate_sitemap_cache' ) ) {
                $sitemap_manager->invalidate_sitemap_cache();
            }
        }
        
        // Limpiar cache de transients relacionados con sitemaps
        global $wpdb;
        $wpdb->query( 
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_gsg_%' 
             OR option_name LIKE '_transient_timeout_gsg_%'"
        );
    }
    
    /**
     * Verifica el estado de los sitemaps
     */
    public function check_sitemap_status() {
        $status = [
            'google_xml_sitemaps' => function_exists( 'sm_get_sitemap_manager' ),
            'yoast_seo' => class_exists( 'WPSEO_Options' ),
            'rank_math' => class_exists( 'RankMath' ),
            'active_sitemap' => '',
        ];
        
        if ( $status['google_xml_sitemaps'] ) {
            $status['active_sitemap'] = 'Google XML Sitemaps';
        } elseif ( $status['yoast_seo'] ) {
            $status['active_sitemap'] = 'Yoast SEO';
        } elseif ( $status['rank_math'] ) {
            $status['active_sitemap'] = 'Rank Math';
        }
        
        return $status;
    }
}

// Inicializar el módulo
add_action( 'plugins_loaded', function() {
    // Solo activar si hay conflictos de sitemap
    if ( defined( 'CIRION_FIX_SITEMAP_CONFLICTS' ) && CIRION_FIX_SITEMAP_CONFLICTS ) {
        Cirion_Sitemap_Fix::get_instance();
    }
}, 30 );

// Función helper para verificar estado
if ( ! function_exists( 'cirion_check_sitemap_status' ) ) {
    function cirion_check_sitemap_status() {
        $fix = Cirion_Sitemap_Fix::get_instance();
        return $fix->check_sitemap_status();
    }
}
?>
"
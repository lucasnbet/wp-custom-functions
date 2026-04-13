<?php
/**
 * Security Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Security {
    
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
        // Headers de seguridad
        add_action( 'send_headers', [ $this, 'add_security_headers' ], 999 );
        
        // Prevención de errores XML - DESACTIVADO TEMPORALMENTE
        // add_action( 'init', [ $this, 'prevent_xml_errors' ], 1 );
        
        // robots.txt consistente
        add_filter( 'robots_txt', [ $this, 'custom_robots_txt' ], 10, 2 );
        
        // Remover version numbers
        add_filter( 'style_loader_src', [ $this, 'remove_version_numbers' ], 9999 );
        add_filter( 'script_loader_src', [ $this, 'remove_version_numbers' ], 9999 );
        
        // Ajustes WPML admin
        add_filter( 'wpml_admin_language_filter_default_language', [ $this, 'wpml_admin_language_filter' ] );
    }
    
    /**
     * Añade headers de seguridad
     */
    public function add_security_headers() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        
        // Remover headers conflictivos
        header_remove( 'Permissions-Policy' );
        header_remove( 'Permission-Policy' );
        
        // Configurar Permissions-Policy seguro
        $permissions_policy = implode( ', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'fullscreen=(self)',
            'payment=()',
            'usb=()',
            'serial=()',
            'sync-xhr=(self)',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
            'xr-spatial-tracking=()',
            'clipboard-read=()',
            'clipboard-write=(self)',
            'encrypted-media=()',
            'picture-in-picture=(self)',
        ] );
        
        if ( ! headers_sent() ) {
            // Headers de seguridad
            header( "Permissions-Policy: $permissions_policy" );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
            
            // Solo agregar CSP si no hay conflictos
            if ( ! $this->has_csp_conflicts() ) {
                $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' https: http:; style-src 'self' 'unsafe-inline' https: http:; img-src 'self' data: https: http:; font-src 'self' https: http:;";
                header( "Content-Security-Policy: $csp" );
            }
        }
    }
    
    /**
     * Verifica conflictos con CSP
     */
    private function has_csp_conflicts() {
        // Lista de plugins conocidos que pueden tener conflictos
        $problematic_plugins = [
            'google-site-kit/google-site-kit.php',
            'autoptimize/autoptimize.php',
            'w3-total-cache/w3-total-cache.php',
            'wp-rocket/wp-rocket.php',
        ];
        
        foreach ( $problematic_plugins as $plugin ) {
            if ( is_plugin_active( $plugin ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Previene errores XML por salida previa
     */
    public function prevent_xml_errors() {
        // Solo en frontend
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }
        
        // Limpiar buffers de salida
        while ( ob_get_level() ) {
            ob_end_clean();
        }
        
        // Iniciar nuevo buffer
        ob_start();
    }
    
    /**
     * robots.txt personalizado
     */
    public function custom_robots_txt( $output, $public ) {
        if ( '0' === $public ) {
            return $output;
        }
        
        $output = "User-agent: *\n";
        $output .= "Disallow: /wp-login.php\n";
        $output .= "Disallow: /wp-admin/\n";
        $output .= "Disallow: /wp-includes/\n";
        $output .= "Disallow: /wp-content/plugins/\n";
        $output .= "Disallow: /wp-content/themes/except-hello-elementor/\n";
        $output .= "Disallow: /wp-content/uploads/cache/\n";
        $output .= "Disallow: /cgi-bin/\n";
        $output .= "Disallow: /*?*\n";
        $output .= "Disallow: /*?s=\n";
        $output .= "Disallow: /*/comments/feed/\n";
        $output .= "Disallow: /*/trackback/\n";
        $output .= "Disallow: /*/feed/\n";
        $output .= "Disallow: /*/*.php$\n";
        $output .= "Disallow: /*/*.inc$\n";
        $output .= "Disallow: /*/*.gz$\n";
        
        $output .= "\nAllow: /wp-content/uploads/\n";
        $output .= "Allow: /wp-admin/admin-ajax.php\n";
        $output .= "Allow: /wp-content/themes/hello-elementor/assets/\n";
        $output .= "Allow: /*.css$\n";
        $output .= "Allow: /*.js$\n";
        $output .= "Allow: /*.png$\n";
        $output .= "Allow: /*.jpg$\n";
        $output .= "Allow: /*.jpeg$\n";
        $output .= "Allow: /*.gif$\n";
        $output .= "Allow: /*.svg$\n";
        $output .= "Allow: /*.webp$\n";
        $output .= "Allow: /*.ico$\n";
        
        $output .= "\nUser-agent: Googlebot-Image\n";
        $output .= "Allow: /*\n";
        
        $output .= "\nUser-agent: Mediapartners-Google*\n";
        $output .= "Allow: /*\n";
        
        $output .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
        
        // Sitemaps adicionales - SOLO GOOGLE XML SITEMAPS
        if ( function_exists( 'sm_get_sitemap_manager' ) ) {
            $output .= "Sitemap: " . home_url( '/sitemap.xml' ) . "\n";
        }
        
        // Desactivar sitemaps de otros plugins para evitar conflictos
        /*
        if ( class_exists( 'RankMath' ) ) {
            $output .= "Sitemap: " . home_url( '/sitemap.xml' ) . "\n";
        }
        
        if ( class_exists( 'WPSEO_Options' ) ) {
            $output .= "Sitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
        }
        */
        
        return apply_filters( 'cirion_custom_robots_txt', $output );
    }
    
    /**
     * Remueve version numbers de CSS/JS
     */
    public function remove_version_numbers( $src ) {
        if ( strpos( $src, 'ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }
    
    /**
     * Filtro para WPML admin
     */
    public function wpml_admin_language_filter() {
        return 'all';
    }
}

// Inicializar el módulo - DESACTIVADO TEMPORALMENTE
// add_action( 'init', function() {
//     Cirion_Security::get_instance();
// } );
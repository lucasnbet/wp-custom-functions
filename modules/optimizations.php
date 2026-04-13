<?php
/**
 * Optimizations Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Optimizations {
    
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
        // Optimizaciones generales
        add_action( 'init', [ $this, 'general_optimizations' ], 1 );
        
        // Optimizaciones de jQuery - DESACTIVADO TEMPORALMENTE
        // add_action( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ] );
        
        // Optimizaciones de imágenes - DESACTIVADO TEMPORALMENTE
        // add_filter( 'wp_calculate_image_sizes', [ $this, 'optimize_image_sizes' ], 10, 4 );
        // add_filter( 'wp_get_attachment_image_attributes', [ $this, 'optimize_image_attributes' ], 10, 3 );
        
        // Preload de imágenes LCP - DESACTIVADO TEMPORALMENTE
        // add_action( 'wp_head', [ $this, 'preload_lcp_image' ], 1 );
        
        // Limpieza de emojis - DESACTIVADO TEMPORALMENTE
        // $this->cleanup_emojis();
        
        // Vimeo lazy load
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_vimeo_lazyload' ] );
        
        // Forzar update de sitemap - DESACTIVADO TEMPORALMENTE
        // add_action( 'save_post', [ $this, 'force_sitemap_update' ], 99, 1 );
    }
    
        /**
     * Optimizaciones generales
     */
    public function general_optimizations() {
        // Limitar revisiones de posts
        if ( ! defined( 'WP_POST_REVISIONS' ) ) {
            define( 'WP_POST_REVISIONS', 3 );
        }
        
        // Aumentar memoria si es necesario
        if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
            define( 'WP_MEMORY_LIMIT', '256M' );
        }
        
        // Deshabilitar heartbeats en el frontend - DESACTIVADO TEMPORALMENTE
        // if ( ! is_admin() ) {
        //     add_action( 'init', [ $this, 'disable_heartbeat' ], 1 );
        // }
    }
    
    /**
     * Deshabilita el heartbeat de WordPress en el frontend
     */
    public function disable_heartbeat() {
        wp_deregister_script( 'heartbeat' );
    }
    
    /**
     * Remueve jQuery Migrate del frontend
     */
    public function remove_jquery_migrate( $scripts ) {
        if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
            $script = $scripts->registered['jquery'];
            
            if ( $script->deps ) {
                $script->deps = array_diff( $script->deps, [ 'jquery-migrate' ] );
            }
        }
    }
    
    /**
     * Optimiza los atributos de tamaño de imágenes
     */
    public function optimize_image_sizes( $sizes, $src, $meta, $attachment_id ) {
        // Tamaño responsive por defecto
        return '(max-width: 768px) 100vw, (max-width: 1200px) 80vw, 1200px';
    }
    
    /**
     * Optimiza los atributos de las imágenes
     */
    public function optimize_image_attributes( $attr, $attachment, $size ) {
        // Añadir width y height si faltan
        if ( isset( $attr['src'] ) && ( ! isset( $attr['width'] ) || ! isset( $attr['height'] ) ) ) {
            $metadata = wp_get_attachment_metadata( $attachment->ID );
            if ( $metadata ) {
                if ( ! isset( $attr['width'] ) ) {
                    $attr['width'] = $metadata['width'];
                }
                if ( ! isset( $attr['height'] ) ) {
                    $attr['height'] = $metadata['height'];
                }
            }
        }
        
        // Optimizar LCP en homepage
        if ( is_front_page() || is_home() ) {
            if ( isset( $attr['loading'] ) && $attr['loading'] === 'lazy' ) {
                unset( $attr['loading'] );
            }
            $attr['fetchpriority'] = 'high';
        }
        
        // Optimizar imágenes en páginas/single
        if ( is_page() || is_single() ) {
            $attr['fetchpriority'] = 'high';
        }
        
        return $attr;
    }
    
    /**
     * Preload de la imagen LCP (Largest Contentful Paint)
     */
    public function preload_lcp_image() {
        // Solo en frontpage/home
        if ( ! is_front_page() && ! is_home() ) {
            return;
        }
        
        // Intentar obtener la imagen destacada
        $lcp_image_id = get_post_thumbnail_id( get_queried_object_id() );
        
        if ( $lcp_image_id ) {
            $lcp_image_url = wp_get_attachment_image_url( $lcp_image_id, 'full' );
            if ( $lcp_image_url ) {
                echo '<link rel="preload" href="' . esc_url( $lcp_image_url ) . '" as="image" />' . "\n";
            }
        }
        
        // También preload del logo si existe
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( $logo_url ) {
                echo '<link rel="preload" href="' . esc_url( $logo_url ) . '" as="image" />' . "\n";
            }
        }
    }
    
    /**
     * Limpia los emojis de WordPress
     */
    private function cleanup_emojis() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        
        // Remover del TinyMCE
        add_filter( 'tiny_mce_plugins', [ $this, 'disable_emojis_tinymce' ] );
        
        // Remover del DNS prefetch
        add_filter( 'wp_resource_hints', [ $this, 'disable_emojis_dns_prefetch' ], 10, 2 );
    }
    
    /**
     * Deshabilita emojis en TinyMCE
     */
    public function disable_emojis_tinymce( $plugins ) {
        if ( is_array( $plugins ) ) {
            return array_diff( $plugins, [ 'wpemoji' ] );
        }
        return $plugins;
    }
    
    /**
     * Deshabilita DNS prefetch para emojis
     */
    public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            $emoji_url = 'https://s.w.org/images/core/emoji/';
            foreach ( $urls as $key => $url ) {
                if ( strpos( $url, $emoji_url ) !== false ) {
                    unset( $urls[ $key ] );
                }
            }
        }
        return $urls;
    }
    
    /**
     * Carga el script de lazy load para Vimeo
     */
    public function enqueue_vimeo_lazyload() {
        wp_enqueue_script(
            'cirion-vimeo-lite',
            CIRION_CUSTOM_URL . 'assets/js/vimeo-lite.js',
            [],
            CIRION_CUSTOM_VERSION,
            true
        );
    }
    
    /**
     * Forzar actualización del sitemap al guardar posts
     */
    public function force_sitemap_update( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Verificar permisos
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Solo para posts y páginas publicados
        $post = get_post( $post_id );
        if ( ! in_array( $post->post_status, [ 'publish', 'future' ] ) ) {
            return;
        }
        
        // Forzar regeneración del sitemap si existe la función
        if ( function_exists( 'sm_get_sitemap_manager' ) ) {
            sm_get_sitemap_manager()->build_sitemap();
        }
        
        // También para Yoast SEO
        if ( class_exists( 'WPSEO_Sitemaps' ) ) {
            WPSEO_Sitemaps::ping_search_engines();
        }
        
        // Para Rank Math
        if ( function_exists( 'rank_math_get_sitemap_url' ) ) {
            do_action( 'rank_math/sitemap/invalidate_object_type', 'post', $post_id );
        }
    }
}

// Inicializar el módulo
add_action( 'init', function() {
    Cirion_Optimizations::get_instance();
} );
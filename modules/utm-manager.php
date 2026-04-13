<?php
/**
 * UTM Manager Module - Compatible con script GTM existente
 * 
 * Maneja la persistencia y propagación de parámetros UTM en toda la navegación del usuario
 * Compatible con arquitectura CDN → WAF → Balancer → Server
 * 
  * @package WPCustomFunctions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Custom_UTM_Manager {
    
    private static $instance = null;
    private $settings = [];
    private $utm_params = [];
    private $custom_domains = [];
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
        private function __construct() {
        $this->settings = wp_custom_config( 'modules.utm-manager.settings', [] );
        
        // Debug log
        if ( $this->settings['debug_mode'] ?? false ) {
            error_log( 'wp UTM Manager: Constructor llamado' );
            error_log( 'wp UTM Manager: Módulo habilitado: ' . ( $this->is_enabled() ? 'Sí' : 'No' ) );
        }
        
        if ( $this->is_enabled() ) {
            $this->init_utm_params();

            $this->init_custom_domains();
            $this->init_hooks();
            
            // Debug log
            if ( $this->settings['debug_mode'] ?? false ) {
                error_log( 'wp UTM Manager: Hooks inicializados' );
            }
        }
    }
    
        private function is_enabled() {
        $module_enabled = wp_custom_config( 'modules.utm-manager.enabled', false );
        $persistence_enabled = $this->settings['enable_utm_persistence'] ?? false;
        
        // Debug log
        if ( $this->settings['debug_mode'] ?? false ) {
            error_log( 'wp UTM Manager: Módulo habilitado en config: ' . ( $module_enabled ? 'Sí' : 'No' ) );
            error_log( 'wp UTM Manager: Persistencia habilitada: ' . ( $persistence_enabled ? 'Sí' : 'No' ) );
        }
        
        return $module_enabled && $persistence_enabled;
    }
    
    private function init_utm_params() {
        // Definir parámetros UTM estándar + parámetros adicionales
        $this->utm_params = $this->settings['utm_parameters'] ?? [
            'utm_source',
            'utm_medium', 
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid'
        ];
    }
    
        // Dominios personalizados para propagación de UTMs
+       $this->custom_domains = $this->settings['custom_domains'] ?? [
+           'example.com',
+           'www.example.com'
         ];
    }
    
    private function init_hooks() {
        // CAPTURA DE EMERGENCIA - Se ejecuta LO MÁS PRONTO POSIBLE
        add_action( 'plugins_loaded', [ $this, 'early_utm_capture' ], 1 );
        
        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'wp_footer', [ $this, 'output_utm_data' ] );
        
        // AJAX hooks para manejar UTM desde el frontend
        add_action( 'wp_ajax_wp_update_utm', [ $this, 'handle_utm_update' ] );
        add_action( 'wp_ajax_nopriv_wp_update_utm', [ $this, 'handle_utm_update' ] );
        
        // Hook para procesar formularios
        add_filter( 'gform_form_tag', [ $this, 'add_utm_to_forms' ], 10, 2 );
        add_filter( 'wpcf7_form_elements', [ $this, 'add_utm_to_cf7_forms' ] );
        add_filter( 'frm_form_fields', [ $this, 'add_utm_to_formidable_forms' ], 10, 2 );
        add_filter( 'forminator_form_fields', [ $this, 'add_utm_to_forminator_forms' ], 10, 2 );
        
        // Hook para shortcodes
        add_filter( 'the_content', [ $this, 'process_content_links' ], 20 );
        
        // Hook para formularios genéricos
        add_filter( 'the_content', [ $this, 'add_utm_to_all_forms_in_content' ], 30 );
        
        // Hook para manejar acceso directo sin parámetros
        add_action( 'wp_footer', [ $this, 'handle_direct_access' ] );
        
        // Cache hooks
        add_action( 'save_post', [ $this, 'clear_cache_on_utm_change' ] );
        add_filter( 'rocket_cache_reject_uri', [ $this, 'exclude_utm_pages_from_cache' ] );
        
        // API REST hooks
        add_action( 'rest_api_init', [ $this, 'register_utm_api_endpoints' ] );
        
        // Debug hook
        if ( $this->settings['debug_mode'] ?? false ) {
            add_action( 'wp_footer', [ $this, 'output_debug_info' ] );
        }
    }
    
    /**
     * Captura UTM en la fase más temprana posible para sobrevivir a redirecciones
     * Se ejecuta en el hook 'plugins_loaded' con prioridad máxima
     */
    public function early_utm_capture() {
        // Solo capturar si hay parámetros UTM en la URL actual
        $current_utm = $this->get_current_utm_params_from_server();
        
        if ( ! empty( $current_utm ) ) {
            // Guardar inmediatamente en cookie de dominio raíz
            $this->emergency_store_utm( $current_utm );
            
            // Opcional: también guardar en una variable de sesión transitoria
            $this->store_transient_utm( $current_utm );
            
            // Debug logging
            if ( $this->settings['debug_mode'] ?? false ) {
                error_log( 'UTM Early Capture: Parámetros detectados ANTES de redirección: ' . 
                          json_encode( $current_utm ) );
            }
        }
    }
    
    /**
     * Obtiene parámetros UTM directamente de $_SERVER (más rápido que $_GET)
     */
    private function get_current_utm_params_from_server() {
        $utm_data = [];
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        
        if ( ! empty( $query_string ) ) {
            parse_str( $query_string, $query_params );
            
            foreach ( $this->utm_params as $param ) {
                if ( isset( $query_params[ $param ] ) && ! empty( $query_params[ $param ] ) ) {
                    $utm_data[ $param ] = sanitize_text_field( $query_params[ $param ] );
                }
            }
        }
        
        return $utm_data;
    }
    
    /**
     * Almacenamiento de emergencia en cookie de dominio raíz
     * Se ejecuta SÍNCRONAMENTE en PHP
     */
    private function emergency_store_utm( $utm_data ) {
        if ( empty( $utm_data ) ) {
            return false;
        }
        
        // Crear estructura compatible con GTM
        $utm_structure = [
            'params' => $utm_data,
            'timestamp' => time() * 1000,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'original_page' => home_url( add_query_arg( null, null ) ),
            'session_count' => 0,
            'fingerprint' => $this->generate_utm_fingerprint( $utm_data ),
            'source' => 'php_emergency_capture'
        ];
        
        $json_data = json_encode( $utm_structure );
        $expiry_days = $this->settings['expiry_days'] ?? 10;
        $duration = $expiry_days * 24 * 60 * 60;
        
        // Configurar cookie en dominio raíz para todos los subdominios
        $this->set_root_domain_cookie( 'wp_utm_params_emergency', $json_data, time() + $duration );
        
        return true;
    }
    
    /**
     * Configura cookie en dominio raíz (.wptechnologies.com)
     */
    private function set_root_domain_cookie( $name, $value, $expiry ) {
        $root_domain = $this->get_root_domain();
        
        $cookie_options = [
            'expires'  => $expiry,
            'path'     => '/',
            'domain'   => $root_domain,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        if ( version_compare( PHP_VERSION, '7.3.0', '>=' ) ) {
            setcookie( $name, $value, $cookie_options );
        } else {
            setcookie(
                $name,
                $value,
                $expiry,
                $cookie_options['path'] . '; samesite=' . $cookie_options['samesite'],
                $cookie_options['domain'],
                $cookie_options['secure'],
                $cookie_options['httponly']
            );
        }
    }
    
    /**
     * Obtiene el dominio raíz para cookies (.wptechnologies.com)
     */
    private function get_root_domain() {
        $host = $_SERVER['HTTP_HOST'] ?? parse_url( home_url(), PHP_URL_HOST );
        
        // Extraer dominio raíz (ej: www.wptechnologies.com -> .wptechnologies.com)
        $parts = explode( '.', $host );
        
        if ( count( $parts ) > 2 ) {
            // Para subdominios: www.wptechnologies.com -> .wptechnologies.com
            $root = '.' . implode( '.', array_slice( $parts, -2 ) );
        } else {
            // Para dominios simples: wptechnologies.com -> .wptechnologies.com
            $root = '.' . $host;
        }
        
        return $root;
    }
    
    /**
     * Almacena UTM en transiente de WordPress (45 segundos) para recuperación inmediata
     */
    private function store_transient_utm( $utm_data ) {
        $transient_key = 'wp_utm_' . md5( json_encode( $utm_data ) . $_SERVER['REMOTE_ADDR'] );
        set_transient( $transient_key, $utm_data, 45 ); // 45 segundos es suficiente
    }
    
    /**
     * Intenta recuperar UTM desde cookie de emergencia
     */
    public function get_emergency_utm() {
        if ( isset( $_COOKIE['wp_utm_params_emergency'] ) ) {
            $cookie_data = json_decode( stripslashes( $_COOKIE['wp_utm_params_emergency'] ), true );
            if ( is_array( $cookie_data ) && isset( $cookie_data['params'] ) ) {
                return $cookie_data['params'];
            }
        }
        
        return [];
    }
    
    /**
     * Obtiene los parámetros UTM de la URL actual
     */
    public function get_current_utm_params() {
        $utm_data = [];
        
        foreach ( $this->utm_params as $param ) {
            if ( isset( $_GET[ $param ] ) ) {
                $utm_data[ $param ] = sanitize_text_field( $_GET[ $param ] );
            }
        }
        
        return $utm_data;
    }
    
    /**
     * Obtiene los parámetros UTM almacenados (compatible con GTM)
     */
    public function get_stored_utm_params() {
        $stored_utm = [];
        
        // 1. Primero intentar obtener de la cookie de emergencia (PHP)
        $emergency_utm = $this->get_emergency_utm();
        if ( ! empty( $emergency_utm ) ) {
            $stored_utm = $emergency_utm;
        }
        // 2. Si no hay emergencia, intentar obtener de localStorage vía JavaScript
        elseif ( isset( $_COOKIE['wp_utm_params'] ) ) {
            $cookie_data = json_decode( stripslashes( $_COOKIE['wp_utm_params'] ), true );
            if ( is_array( $cookie_data ) && isset( $cookie_data['params'] ) ) {
                $stored_utm = $cookie_data['params'];
            }
        }
        
        return $stored_utm;
    }
    
    /**
     * Verifica si debe procesarse en la página actual
     */
    private function should_process_on_current_page() {
        // Excluir ciertas páginas
        $excluded_pages = $this->settings['excluded_pages'] ?? [];
        $current_id = get_queried_object_id();
        
        if ( in_array( $current_id, $excluded_pages ) ) {
            return false;
        }
        
        // Solo ciertos post types
        $allowed_post_types = $this->settings['allowed_post_types'] ?? ['post', 'page'];
        $current_post_type = get_post_type();
        
        if ( $current_post_type && ! in_array( $current_post_type, $allowed_post_types ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Almacena parámetros UTM (compatible con estructura GTM)
     */
    public function store_utm_params( $utm_data ) {
        if ( empty( $utm_data ) ) {
            return false;
        }
        
        // Filtrar datos antes de almacenar
        $filtered_data = [];
        foreach ( $utm_data as $key => $value ) {
            if ( in_array( $key, $this->utm_params ) && is_string( $value ) && strlen( $value ) <= 255 ) {
                $filtered_data[ $key ] = substr( sanitize_text_field( $value ), 0, 255 );
            }
        }
        
        if ( empty( $filtered_data ) ) {
            return false;
        }
        
        // Crear estructura compatible con GTM
        $utm_structure = [
            'params' => $filtered_data,
            'timestamp' => time() * 1000, // Milisegundos como en JavaScript
            'referrer' => wp_get_referer() ?: '',
            'original_page' => home_url( add_query_arg( null, null ) ),
            'session_count' => 0,
            'fingerprint' => $this->generate_utm_fingerprint( $filtered_data )
        ];
        
        // Manejar límite de tamaño de cookie (4KB)
        $json_data = json_encode( $utm_structure );
        if ( strlen( $json_data ) > 4000 ) {
            // Limitar cantidad de parámetros si es muy grande
            $filtered_data = array_slice( $filtered_data, 0, 5 );
            $utm_structure['params'] = $filtered_data;
            $utm_structure['fingerprint'] = $this->generate_utm_fingerprint( $filtered_data );
            $json_data = json_encode( $utm_structure );
        }
        
        $expiry_days = $this->settings['expiry_days'] ?? 10;
        $duration = $expiry_days * 24 * 60 * 60; // Convertir a segundos
        
        // Almacenar en cookie usando el método mejorado
        $this->set_utm_cookie( 'wp_utm_params', $json_data, time() + $duration );
        
        // También almacenar en session transient para uso en backend
        if ( session_id() ) {
            $_SESSION['wp_utm_params'] = $utm_structure;
        }
        
        // Guardar en user meta si el usuario está logueado
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'wp_last_utm', $utm_structure );
        }
        
        // Log actividad
        $this->log_utm_activity( 'store', $filtered_data );
        
        return true;
    }
    
    /**
     * Configura cookie con opciones mejoradas
     */
    private function set_utm_cookie( $name, $value, $expiry ) {
        $cookie_options = [
            'expires'  => $expiry,
            'path'     => '/',
            'domain'   => parse_url( home_url(), PHP_URL_HOST ),
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        // PHP 7.3+ soporta array de opciones
        if ( version_compare( PHP_VERSION, '7.3.0', '>=' ) ) {
            setcookie( $name, $value, $cookie_options );
        } else {
            setcookie(
                $name,
                $value,
                $expiry,
                $cookie_options['path'] . '; samesite=' . $cookie_options['samesite'],
                $cookie_options['domain'],
                $cookie_options['secure'],
                $cookie_options['httponly']
            );
        }
    }
    
    /**
     * Genera fingerprint para UTMs
     */
    private function generate_utm_fingerprint( $utm_params ) {
        ksort( $utm_params );
        return md5( http_build_query( $utm_params ) );
    }
    
    /**
     * Actualiza parámetros UTM cuando cambian
     */
    public function update_utm_params( $new_utm_data ) {
        if ( ! $this->settings['update_on_change'] ?? true ) {
            return false;
        }
        
        $current_stored = $this->get_stored_utm_params();
        
        // Verificar si hay cambios significativos
        $has_changes = false;
        foreach ( $new_utm_data as $key => $value ) {
            if ( ! isset( $current_stored[ $key ] ) || $current_stored[ $key ] !== $value ) {
                $has_changes = true;
                break;
            }
        }
        
        if ( $has_changes ) {
            $result = $this->store_utm_params( $new_utm_data );
            if ( $result ) {
                $this->log_utm_activity( 'update', $new_utm_data );
            }
            return $result;
        }
        
        return false;
    }
    
    /**
     * Añade parámetros UTM a una URL (con filtrado inteligente)
     */
    public function add_utm_to_url( $url, $utm_data = null ) {
        if ( empty( $url ) || ! $this->is_wp_url( $url ) ) {
            return $url;
        }
        
        if ( $utm_data === null ) {
            $utm_data = $this->get_stored_utm_params();
        }
        
        if ( empty( $utm_data ) ) {
            return $url;
        }
        
        // Verificar si la URL ya tiene parámetros de tracking
        $url_has_tracking = $this->url_has_tracking_params( $url );
        if ( $url_has_tracking ) {
            return $url; // No modificar URLs que ya tienen tracking
        }
        
        // Filtrar parámetros según tiempo transcurrido
        $filtered_utm = $this->filter_utm_by_time( $utm_data );
        
        if ( empty( $filtered_utm ) ) {
            return $url;
        }
        
        $url_parts = parse_url( $url );
        $query_params = [];
        
        // Parsear query string existente
        if ( isset( $url_parts['query'] ) ) {
            parse_str( $url_parts['query'], $query_params );
        }
        
        // Combinar con UTM filtrados
        foreach ( $filtered_utm as $key => $value ) {
            if ( in_array( $key, $this->utm_params ) && ! empty( $value ) ) {
                $query_params[ $key ] = $value;
            }
        }
        
        // Reconstruir URL
        $url_parts['query'] = http_build_query( $query_params );
        
        return $this->build_url( $url_parts );
    }
    
    /**
     * Verifica si una URL es de wp (MEJORADA)
     */
    private function is_wp_url( $url ) {
        // Normalizar la URL primero
        $url = trim( $url );
        
        // Si es URL relativa, es nuestro dominio
        if ( strpos( $url, '//' ) === false ) {
            return true;
        }
        
        // Extraer el host de forma más segura usando wp_parse_url
        $parsed = wp_parse_url( $url );
        if ( ! isset( $parsed['host'] ) ) {
            return false;
        }
        
        $host = strtolower( $parsed['host'] );
        
        // Verificar contra lista de dominios permitidos
        foreach ( $this->wp_domains as $domain ) {
            if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica si una URL ya tiene parámetros de tracking
     */
    private function url_has_tracking_params( $url ) {
        $tracking_params = ['utm_', 'gclid', 'fbclid', 'msclkid'];
        
        foreach ( $tracking_params as $param ) {
            if ( strpos( $url, $param ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Filtra UTMs según tiempo transcurrido
     */
    private function filter_utm_by_time( $utm_data ) {
        $filtered = [];
        $is_same_day = true; // Por defecto, asumir mismo día
        
        // Verificar tiempo desde almacenamiento
        if ( isset( $_COOKIE['wp_utm_params'] ) ) {
            $cookie_data = json_decode( stripslashes( $_COOKIE['wp_utm_params'] ), true );
            if ( isset( $cookie_data['timestamp'] ) ) {
                $time_diff = ( time() * 1000 ) - $cookie_data['timestamp'];
                $is_same_day = $time_diff < ( 24 * 60 * 60 * 1000 ); // Menos de 24 horas
            }
        }
        
        foreach ( $utm_data as $key => $value ) {
            if ( ! empty( $value ) ) {
                // Si no es mismo día, filtrar parámetros específicos
                if ( ! $is_same_day && $this->settings['filter_params_after_day'] ?? true ) {
                    $filtered_params = $this->settings['filtered_params_after_day'] ?? ['gclid', 'fbclid', 'msclkid', 'utm_term', 'utm_content'];
                    if ( ! in_array( $key, $filtered_params ) ) {
                        $filtered[ $key ] = $value;
                    }
                } else {
                    $filtered[ $key ] = $value;
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Construye URL a partir de partes
     */
    private function build_url( $parts ) {
        $url = '';
        
        if ( isset( $parts['scheme'] ) ) {
            $url .= $parts['scheme'] . '://';
        }
        
        if ( isset( $parts['host'] ) ) {
            $url .= $parts['host'];
        }
        
        if ( isset( $parts['port'] ) ) {
            $url .= ':' . $parts['port'];
        }
        
        if ( isset( $parts['path'] ) ) {
            $url .= $parts['path'];
        }
        
        if ( isset( $parts['query'] ) ) {
            $url .= '?' . $parts['query'];
        }
        
        if ( isset( $parts['fragment'] ) ) {
            $url .= '#' . $parts['fragment'];
        }
        
        return $url;
    }
    
    /**
     * Procesa enlaces en el contenido (MEJORADA para rendimiento)
     */
    public function process_content_links( $content ) {
        if ( ! $this->should_process_on_current_page() ) {
            return $content;
        }
        
        if ( ! $this->settings['propagate_to_links'] ?? true ) {
            return $content;
        }
        
        if ( empty( $content ) || strpos( $content, '<a ' ) === false ) {
            return $content;
        }
        
        // Usar DOMDocument para procesar enlaces de forma segura
        if ( class_exists( 'DOMDocument' ) ) {
            return $this->process_links_with_dom( $content );
        } else {
            // Fallback a regex si DOMDocument no está disponible
            return $this->process_links_with_regex( $content );
        }
    }
    
    /**
     * Procesa enlaces usando DOMDocument
     */
    private function process_links_with_dom( $content ) {
        $dom = new DOMDocument();
        
        // Suprimir errores de HTML mal formado
        libxml_use_internal_errors( true );
        
        // Cargar contenido HTML
        $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors();
        
        // Procesar todos los enlaces
        $links = $dom->getElementsByTagName( 'a' );
        $utm_data = $this->get_stored_utm_params();
        
        foreach ( $links as $link ) {
            $href = $link->getAttribute( 'href' );
            
            if ( $href && $this->is_wp_url( $href ) ) {
                $new_href = $this->add_utm_to_url( $href, $utm_data );
                if ( $new_href !== $href ) {
                    $link->setAttribute( 'href', $new_href );
                }
            }
        }
        
        // Guardar el HTML modificado
        $content = $dom->saveHTML();
        
        // Extraer solo el body content si es necesario
        $body_start = strpos( $content, '<body>' );
        $body_end = strpos( $content, '</body>' );
        
        if ( $body_start !== false && $body_end !== false ) {
            $content = substr( $content, $body_start + 6, $body_end - $body_start - 6 );
        }
        
        return $content;
    }
    
    /**
     * Procesa enlaces usando regex (fallback)
     */
    private function process_links_with_regex( $content ) {
        $utm_data = $this->get_stored_utm_params();
        if ( empty( $utm_data ) ) {
            return $content;
        }
        
        return preg_replace_callback(
            '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            function( $matches ) use ( $utm_data ) {
                $url = $matches[1];
                $new_url = $this->add_utm_to_url( $url, $utm_data );
                
                if ( $new_url !== $url ) {
                    return str_replace( $url, $new_url, $matches[0] );
                }
                return $matches[0];
            },
            $content
        );
    }
    
    /**
     * Añade UTM a formularios Gravity Forms
     */
    public function add_utm_to_forms( $form_tag, $form ) {
        return $this->add_utm_to_form_tag( $form_tag );
    }
    
    /**
     * Añade UTM a formularios Contact Form 7
     */
    public function add_utm_to_cf7_forms( $form ) {
        $hidden_fields = $this->get_utm_hidden_fields();
        return $form . $hidden_fields;
    }
    
    /**
     * Añade UTM a formularios Formidable
     */
    public function add_utm_to_formidable_forms( $field, $field_name ) {
        // Formidable usa un sistema diferente, necesitamos modificar el HTML del formulario
        return $field;
    }
    
    /**
     * Añade UTM a formularios Forminator
     */
    public function add_utm_to_forminator_forms( $fields, $form_id ) {
        // Forminator también tiene su propio sistema
        $utm_fields = $this->get_utm_fields_array();
        return array_merge( $fields, $utm_fields );
    }
    
    /**
     * Añade UTM a todos los formularios en el contenido
     */
    public function add_utm_to_all_forms_in_content( $content ) {
        if ( ! $this->should_process_on_current_page() ) {
            return $content;
        }
        
        if ( ! $this->settings['propagate_to_forms'] ?? true ) {
            return $content;
        }
        
        // Buscar formularios en el contenido y agregar campos UTM
        return preg_replace_callback(
            '/(<form[^>]*>)/i',
            function( $matches ) {
                return $this->add_utm_to_form_tag( $matches[0] );
            },
            $content
        );
    }
    
    /**
     * Método genérico para añadir UTM a etiquetas form
     */
    private function add_utm_to_form_tag( $form_tag ) {
        $hidden_fields = $this->get_utm_hidden_fields();
        if ( empty( $hidden_fields ) ) {
            return $form_tag;
        }
        
        // Insertar después del opening form tag
        return preg_replace(
            '/(<form[^>]*>)/',
            '$1' . $hidden_fields,
            $form_tag,
            1
        );
    }
    
    /**
     * Genera campos hidden para UTM
     */
    private function get_utm_hidden_fields() {
        $utm_data = $this->get_stored_utm_params();
        if ( empty( $utm_data ) ) {
            return '';
        }
        
        $filtered_utm = $this->filter_utm_by_time( $utm_data );
        if ( empty( $filtered_utm ) ) {
            return '';
        }
        
        $hidden_fields = '';
        foreach ( $filtered_utm as $key => $value ) {
            if ( in_array( $key, $this->utm_params ) && ! empty( $value ) ) {
                $hidden_fields .= sprintf(
                    '<input type="hidden" name="%s" value="%s" />',
                    esc_attr( $key ),
                    esc_attr( $value )
                );
            }
        }
        
        return $hidden_fields;
    }
    
    /**
     * Genera array de campos para formularios que lo requieran
     */
    private function get_utm_fields_array() {
        $utm_data = $this->get_stored_utm_params();
        if ( empty( $utm_data ) ) {
            return [];
        }
        
        $filtered_utm = $this->filter_utm_by_time( $utm_data );
        if ( empty( $filtered_utm ) ) {
            return [];
        }
        
        $fields = [];
        foreach ( $filtered_utm as $key => $value ) {
            if ( in_array( $key, $this->utm_params ) && ! empty( $value ) ) {
                $fields[] = [
                    'type' => 'hidden',
                    'name' => $key,
                    'value' => $value
                ];
            }
        }
        
        return $fields;
    }
    
    /**
     * Maneja acceso directo sin parámetros
     */
    public function handle_direct_access() {
        $current_utm = $this->get_current_utm_params();
        $stored_utm = $this->get_stored_utm_params();
        
        // Si no hay parámetros en la URL pero hay UTMs almacenados
        if ( empty( $current_utm ) && ! empty( $stored_utm ) ) {
            // Verificar si es acceso directo (sin referrer o referrer externo)
            $referrer = wp_get_referer();
            $is_wp_referrer = $this->is_wp_url( $referrer );
            $is_direct_access = empty( $referrer ) || ! $is_wp_referrer;
            
            if ( $is_direct_access ) {
                if ( $this->settings['clear_on_direct_access'] ?? false ) {
                    // Limpiar UTMs en acceso directo
                    $this->set_utm_cookie(
                        'wp_utm_params',
                        '',
                        time() - 3600
                    );
                    
                    $this->log_utm_activity( 'clear_direct_access', $stored_utm );
                    
                    if ( $this->settings['debug_mode'] ?? false ) {
                        error_log( 'wp UTM: Acceso directo detectado, UTMs limpiados' );
                    }
                } elseif ( $this->settings['preserve_on_empty_params'] ?? true ) {
                    // Mantener UTMs existentes
                    if ( $this->settings['debug_mode'] ?? false ) {
                        error_log( 'wp UTM: Acceso directo detectado, UTMs preservados' );
                    }
                }
            }
        }
    }
    
        /**
     * Encola assets frontend
     */
    public function enqueue_frontend_assets() {
        // Debug log
        if ( $this->settings['debug_mode'] ?? false ) {
            error_log( 'wp UTM Manager: Encolando script frontend' );
        }
        
                wp_enqueue_script(
            'wp-custom-utm-manager',
            WP_CUSTOM_FUNCTIONS_URL . 'assets/js/utm-manager.js',
            [ 'jquery' ],
            WP_CUSTOM_FUNCTIONS_VERSION,
            true
        );
        
        // Obtener UTMs de emergencia también
        $emergency_utm = $this->get_emergency_utm();
        
        wp_localize_script( 'wp-custom-utm-manager', 'wpCustomUTM', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wp_utm_nonce' ),
            'settings' => [
                'storage_method' => $this->settings['storage_method'] ?? 'local_storage',
                'storage_key' => $this->settings['storage_key'] ?? 'wp_utm_params',
                'emergency_key' => 'wp_utm_params_emergency',
                'utm_params' => $this->utm_params,
                'update_on_change' => $this->settings['update_on_change'] ?? true,
                'preserve_on_empty_params' => $this->settings['preserve_on_empty_params'] ?? true,
                'clear_on_direct_access' => $this->settings['clear_on_direct_access'] ?? false,
                'debug_mode' => $this->settings['debug_mode'] ?? true,
                'wp_domains' => $this->wp_domains,
                'expiry_days' => $this->settings['expiry_days'] ?? 10,
                'filter_params_after_day' => $this->settings['filter_params_after_day'] ?? true,
                'filtered_params_after_day' => $this->settings['filtered_params_after_day'] ?? ['gclid', 'fbclid', 'msclkid', 'utm_term', 'utm_content'],
                'propagate_to_links' => $this->settings['propagate_to_links'] ?? true,
                'propagate_to_forms' => $this->settings['propagate_to_forms'] ?? true,
                'enable_logging' => $this->settings['enable_logging'] ?? false,
                'excluded_pages' => $this->settings['excluded_pages'] ?? [],
                'allowed_post_types' => $this->settings['allowed_post_types'] ?? ['post', 'page'],
            ],
            'current_utm' => $this->get_current_utm_params(),
            'stored_utm' => $this->get_stored_utm_params(),
            'emergency_utm' => $emergency_utm, // ← NUEVO: UTMs capturados por PHP
            'current_page' => [
                'id' => get_queried_object_id(),
                'type' => get_post_type(),
                'should_process' => $this->should_process_on_current_page(),
            ]
        ] );
    }
    
    /**
     * Maneja actualización de UTM vía AJAX (MEJORADA)
     */
    public function handle_utm_update() {
        // Validar que sea una solicitud POST
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            wp_send_json_error( [ 'message' => 'Invalid request method' ], 405 );
        }
        
        // Verificar nonce
        if ( ! check_ajax_referer( 'wp_utm_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
        }
        
        // Validar y sanitizar datos
        if ( ! isset( $_POST['utm_data'] ) || ! is_array( $_POST['utm_data'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid UTM data format' ], 400 );
        }
        
        $utm_data = map_deep( $_POST['utm_data'], 'sanitize_text_field' );
        
        // Limitar cantidad de parámetros
        if ( count( $utm_data ) > 10 ) {
            $utm_data = array_slice( $utm_data, 0, 10 );
        }
        
        // Filtrar solo parámetros UTM válidos
        $filtered_utm = [];
        foreach ( $utm_data as $key => $value ) {
            if ( in_array( $key, $this->utm_params ) && ! empty( $value ) ) {
                $filtered_utm[ $key ] = $value;
            }
        }
        
        if ( ! empty( $filtered_utm ) ) {
            $result = $this->update_utm_params( $filtered_utm );
            
            if ( $result ) {
                wp_send_json_success( [
                    'message' => 'UTM updated successfully',
                    'utm_data' => $filtered_utm,
                ] );
            } else {
                wp_send_json_error( [
                    'message' => 'No changes detected',
                ] );
            }
        } else {
            wp_send_json_error( [
                'message' => 'No valid UTM parameters provided',
            ] );
        }
    }
    
    /**
     * Limpia cache cuando hay cambios de UTM
     */
    public function clear_cache_on_utm_change() {
        // Limpiar cache si hay parámetros UTM en la URL
        if ( ! empty( $this->get_current_utm_params() ) ) {
            if ( function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
            if ( function_exists( 'w3tc_flush_all' ) ) {
                w3tc_flush_all();
            }
            if ( function_exists( 'wp_cache_clear_cache' ) ) {
                wp_cache_clear_cache();
            }
            
            $this->log_utm_activity( 'clear_cache', $this->get_current_utm_params() );
        }
    }
    
    /**
     * Excluye páginas con UTM del cache
     */
    public function exclude_utm_pages_from_cache( $uri ) {
        // Excluir páginas con UTM del cache completo
        if ( ! empty( $this->get_current_utm_params() ) ) {
            $current_uri = $_SERVER['REQUEST_URI'] ?? '';
            if ( $current_uri ) {
                $uri[] = $current_uri;
            }
        }
        return $uri;
    }
    
    /**
     * Registra endpoints de API REST
     */
    public function register_utm_api_endpoints() {
        register_rest_route( 'wp-custom/v1', '/utm', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_utm_api_request' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_utm_api_update' ],
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            ]
        ] );
    }
    
    /**
     * Maneja solicitudes GET a la API de UTM
     */
    public function handle_utm_api_request( $request ) {
        $utm_data = $this->get_stored_utm_params();
        
        return new WP_REST_Response( [
            'success'   => true,
            'data'      => $utm_data,
            'timestamp' => time()
        ] );
    }
    
    /**
     * Maneja actualizaciones POST a la API de UTM
     */
    public function handle_utm_api_update( $request ) {
        $params = $request->get_params();
        $utm_data = isset( $params['utm_data'] ) ? $params['utm_data'] : [];
        
        if ( empty( $utm_data ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'No UTM data provided'
            ], 400 );
        }
        
        $result = $this->update_utm_params( $utm_data );
        
        if ( $result ) {
            return new WP_REST_Response( [
                'success' => true,
                'message' => 'UTM data updated successfully',
                'data'    => $utm_data
            ] );
        } else {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'No changes detected'
            ] );
        }
    }
    
    /**
     * Registra actividad de UTM
     */
    private function log_utm_activity( $action, $data = [] ) {
        if ( ! $this->settings['enable_logging'] ?? false ) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time( 'mysql' ),
            'action'    => $action,
            'utm_data'  => $data,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url'       => home_url( add_query_arg( null, null ) )
        ];
        
        // Guardar en log file
        $log_file = WP_CONTENT_DIR . '/uploads/utm-logs/' . date( 'Y-m-d' ) . '.log';
        
        if ( ! file_exists( dirname( $log_file ) ) ) {
            wp_mkdir_p( dirname( $log_file ) );
        }
        
        file_put_contents( 
            $log_file, 
            json_encode( $log_entry ) . PHP_EOL,
            FILE_APPEND 
        );
    }
    
    /**
     * Output UTM data en el frontend
     */
    public function output_utm_data() {
        if ( ! $this->should_process_on_current_page() ) {
            return;
        }
        
        $utm_data = $this->get_stored_utm_params();
        
        if ( ! empty( $utm_data ) ) {
            echo '<script type="application/json" id="wp-utm-data">';
            echo json_encode( $utm_data );
            echo '</script>';
        }
    }
    
    /**
     * Output debug information
     */
    public function output_debug_info() {
        if ( ! $this->settings['debug_mode'] ?? false ) {
            return;
        }
        
        $current_utm = $this->get_current_utm_params();
        $stored_utm = $this->get_stored_utm_params();
        $emergency_utm = $this->get_emergency_utm();
        
        echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #f0f0f0; padding: 10px; border: 1px solid #ccc; z-index: 9999; font-size: 12px; max-width: 300px;">';
        echo '<strong>UTM Debug Info</strong><br>';
        echo 'Current URL UTM: ' . ( empty( $current_utm ) ? 'None' : json_encode( $current_utm ) ) . '<br>';
        echo 'Stored UTM: ' . ( empty( $stored_utm ) ? 'None' : json_encode( $stored_utm ) ) . '<br>';
        echo 'Emergency UTM: ' . ( empty( $emergency_utm ) ? 'None' : json_encode( $emergency_utm ) ) . '<br>';
        echo 'Storage Method: ' . ( $this->settings['storage_method'] ?? 'local_storage' ) . '<br>';
        echo 'Storage Key: ' . ( $this->settings['storage_key'] ?? 'wp_utm_params' ) . '<br>';
        echo 'Expiry Days: ' . ( $this->settings['expiry_days'] ?? 10 ) . '<br>';
        echo 'wp Domains: ' . json_encode( $this->wp_domains ) . '<br>';
        echo 'Should Process: ' . ( $this->should_process_on_current_page() ? 'Yes' : 'No' ) . '<br>';
        echo 'Current Page ID: ' . get_queried_object_id() . '<br>';
        echo 'Current Post Type: ' . get_post_type() . '<br>';
        echo '</div>';
    }
    
    /**
     * Obtiene UTM para uso en templates
     */
    public function get_utm_for_template() {
        return $this->get_stored_utm_params();
    }
    
    /**
     * Obtiene un parámetro UTM específico
     */
    public function get_utm_param( $param ) {
        $utm_data = $this->get_stored_utm_params();
        return $utm_data[ $param ] ?? '';
    }
}

// Inicializar el módulo
add_action( 'plugins_loaded', function() {
    if ( wp_custom_config( 'modules.utm-manager.enabled', false ) ) {
        WP_Custom_UTM_Manager::get_instance();
    }
}, 20 );

// Funciones helper para templates
if ( ! function_exists( 'wp_custom_get_utm' ) ) {
    function wp_custom_get_utm( $param = null ) {
        $utm_manager = WP_Custom_UTM_Manager::get_instance();
        
        if ( $param === null ) {
            return $utm_manager->get_utm_for_template();
        }
        
        return $utm_manager->get_utm_param( $param );
    }
}

if ( ! function_exists( 'wp_custom_add_utm_to_url' ) ) {
    function wp_custom_add_utm_to_url( $url ) {
        $utm_manager = WP_Custom_UTM_Manager::get_instance();
        return $utm_manager->add_utm_to_url( $url );
    }
}

if ( ! function_exists( 'wp_custom_get_utm_debug' ) ) {
    function wp_custom_get_utm_debug() {
        $utm_manager = WP_Custom_UTM_Manager::get_instance();
        return [
            'current' => $utm_manager->get_current_utm_params(),
            'stored' => $utm_manager->get_stored_utm_params(),
            'emergency' => $utm_manager->get_emergency_utm(),
            'settings' => wp_custom_config( 'modules.utm-manager.settings', [] ),
            'should_process' => $utm_manager->should_process_on_current_page(),
        ];
    }
}
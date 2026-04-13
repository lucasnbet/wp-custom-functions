<?php
/**
 * WPML Redirector Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_WPML_Redirector {
    
    private static $instance = null;
    private $geo;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Inicializar geolocalizaciÃ³n con fallback
        $this->geo = $this->initialize_geolocation();
        $this->init_hooks();
    }
    
    /**
     * Inicializa la geolocalizaciÃ³n con fallback robusto
     */
    private function initialize_geolocation() {
        // Verificar si la clase Cirion_Geolocation existe
        if ( class_exists( 'Cirion_Geolocation' ) && method_exists( 'Cirion_Geolocation', 'get_instance' ) ) {
            try {
                return Cirion_Geolocation::get_instance();
            } catch ( Exception $e ) {
                // Fallback en caso de error
                return $this->create_fallback_geo();
            }
        }
        // Si no existe, crear fallback
        return $this->create_fallback_geo();
    }
    
    /**
     * Crea un objeto fallback para geolocalizaciÃ³n
     */
    private function create_fallback_geo() {
        return new class {
            public function detect_country_for_wpml() {
                // Prioridad 1: Cloudflare header
                if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) && strlen( $_SERVER['HTTP_CF_IPCOUNTRY'] ) === 2 ) {
                    return strtoupper( $_SERVER['HTTP_CF_IPCOUNTRY'] );
                }
                
                // Prioridad 2: GeoIP header personalizado
                if ( isset( $_SERVER['HTTP_X_GEOIP_COUNTRY'] ) && strlen( $_SERVER['HTTP_X_GEOIP_COUNTRY'] ) === 2 ) {
                    return strtoupper( $_SERVER['HTTP_X_GEOIP_COUNTRY'] );
                }
                
                // Prioridad 3: Accept-Language header
                if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
                    $lang = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
                    if ( $lang === 'pt' ) return 'BR';
                    if ( $lang === 'en' ) return 'US';
                    if ( $lang === 'es' ) return 'ES';
                }
                
                // Default: EspaÃ±ol
                return 'ES';
            }
            
            public function get_client_ip_for_wpml() {
                $ip_keys = [
                    'HTTP_CLIENT_IP',
                    'HTTP_X_FORWARDED_FOR', 
                    'HTTP_X_FORWARDED',
                    'HTTP_X_CLUSTER_CLIENT_IP',
                    'HTTP_FORWARDED_FOR',
                    'HTTP_FORWARDED',
                    'REMOTE_ADDR'
                ];
                
                foreach ( $ip_keys as $key ) {
                    if ( isset( $_SERVER[ $key ] ) ) {
                        foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                            $ip = trim( $ip );
                            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                                return $ip;
                            }
                        }
                    }
                }
                
                return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            }
            
            public function debug_wpml_redirect() {
                $country = $this->detect_country_for_wpml();
                $ip = $this->get_client_ip_for_wpml();
                
                echo '<div style="padding:20px; background:#f5f5f5; border:2px solid #ccc; margin:20px; font-family:monospace;">';
                echo '<h2 style="color:#333;">ðŸŒ Cirion WPML Redirector - Debug Info</h2>';
                echo '<hr>';
                echo '<p><strong>Detected Country:</strong> <span style="color:blue; font-weight:bold;">' . esc_html( $country ) . '</span></p>';
                echo '<p><strong>Client IP:</strong> ' . esc_html( $ip ) . '</p>';
                echo '<p><strong>HTTP Headers:</strong></p>';
                echo '<pre style="background:#fff; padding:10px; border:1px solid #ddd; overflow:auto;">';
                
                $headers = [];
                foreach ( $_SERVER as $key => $value ) {
                    if ( strpos( $key, 'HTTP_' ) === 0 || in_array( $key, [ 'REMOTE_ADDR', 'REQUEST_URI' ] ) ) {
                        $headers[ $key ] = $value;
                    }
                }
                
                print_r( $headers );
                echo '</pre>';
                echo '<hr>';
                echo '<p><strong>Cookies:</strong></p>';
                echo '<pre style="background:#fff; padding:10px; border:1px solid #ddd; overflow:auto;">';
                print_r( $_COOKIE );
                echo '</pre>';
                echo '</div>';
                exit;
            }
        };
    }
    
    private function init_hooks() {
        // Hook de alta prioridad para redirecciÃ³n
        add_action( 'template_redirect', [ $this, 'handle_language_redirect' ], 1 );
        
        // Desactivar redirecciÃ³n automÃ¡tica de WPML si es problemÃ¡tica
        add_filter( 'wpml_should_redirect_by_ip', '__return_false' );
        
        // Debug para admins - USAR MÃ‰TODO PÃšBLICO
        if ( current_user_can( 'manage_options' ) ) {
            add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_debug' ], 100 );
            add_action( 'init', [ $this, 'handle_debug_endpoint_public' ] );
        }
        
        // Shortcode para forzar idioma
        add_shortcode( 'cirion_force_language', [ $this, 'shortcode_force_language' ] );
    }
    
    /**
     * MÃ©todo pÃºblico wrapper para el hook de debug
     */
    public function handle_debug_endpoint_public() {
        if ( isset( $_GET['cirion_wpml_debug'] ) && current_user_can( 'manage_options' ) ) {
            if ( method_exists( $this->geo, 'debug_wpml_redirect' ) ) {
                $this->geo->debug_wpml_redirect();
            } else {
                $this->debug_fallback();
            }
        }
    }
    
    /**
     * MÃ©todo privado original (mantenido para compatibilidad)
     */
    private function handle_debug_endpoint() {
        // Este mÃ©todo ya no se usa directamente desde hooks
        // Se mantiene por si hay llamadas directas en el cÃ³digo
        $this->handle_debug_endpoint_public();
    }
    
    /**
     * Fallback para debug
     */
    private function debug_fallback() {
        echo '<div style="padding:20px; background:#f5f5f5; border:2px solid #ccc; margin:20px;">';
        echo '<h2>Cirion WPML Debug (Fallback)</h2>';
        echo '<p><strong>Country Detection:</strong> ' . esc_html( $this->geo->detect_country_for_wpml() ) . '</p>';
        echo '<p><strong>Client IP:</strong> ' . esc_html( $this->geo->get_client_ip_for_wpml() ) . '</p>';
        echo '<p><strong>Current URI:</strong> ' . esc_html( $_SERVER['REQUEST_URI'] ?? '/' ) . '</p>';
        echo '<p><strong>Target Language:</strong> ' . esc_html( $this->country_to_wpml_language( $this->geo->detect_country_for_wpml() ) ) . '</p>';
        echo '</div>';
        exit;
    }
    
    /**
     * Mapeo de paÃ­s a idioma WPML
     */
    private function country_to_wpml_language( $country_code ) {
        $map = [
            // EspaÃ±ol (LatinoamÃ©rica completa)
            'AR' => 'es', 'CL' => 'es', 'CO' => 'es', 'EC' => 'es',
            'MX' => 'es', 'PE' => 'es', 'VE' => 'es', 'UY' => 'es',
            'PY' => 'es', 'BO' => 'es', 'CR' => 'es', 'CU' => 'es',
            'DO' => 'es', 'GT' => 'es', 'HN' => 'es', 'NI' => 'es',
            'PA' => 'es', 'PR' => 'es', 'SV' => 'es',
            
            // EspaÃ±ol (Europa)
            'ES' => 'es', 'AD' => 'es',
            
            // PortuguÃ©s Brasil
            'BR' => 'pt-br',
            
            // InglÃ©s (paÃ­ses clave)
            'US' => 'en', 'GB' => 'en', 'CA' => 'en', 'AU' => 'en',
            'NZ' => 'en', 'IE' => 'en', 'SG' => 'en', 'IN' => 'en',
            'ZA' => 'en', 'AE' => 'en',
            
            // Casos especiales - paÃ­ses que deben ir a inglÃ©s
            'PT' => 'en',  // Portugal â†’ inglÃ©s (no tenemos pt-pt)
            'DE' => 'en', 'FR' => 'en', 'IT' => 'en', 'NL' => 'en',
            'BE' => 'en', 'CH' => 'en', 'SE' => 'en', 'NO' => 'en',
            'DK' => 'en', 'FI' => 'en', 'PL' => 'en', 'RU' => 'en',
            'CN' => 'en', 'JP' => 'en', 'KR' => 'en',
            
            // Caribe inglÃ©s
            'JM' => 'en', 'BB' => 'en', 'BS' => 'en', 'GD' => 'en',
            'LC' => 'en', 'VC' => 'en', 'TT' => 'en',
        ];
        
        return $map[ $country_code ] ?? 'es'; // Default espaÃ±ol
    }
    
    /**
     * Maneja la redirecciÃ³n de idioma
     */
    public function handle_language_redirect() {
        // Solo procesar en la pÃ¡gina principal (raÃ­z)
        if ( ! is_front_page() || is_admin() ) {
            return;
        }
        
        // No redirigir si ya estamos en un idioma especÃ­fico
        $current_uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( preg_match( '#^/(es|pt-br|en)(/|$)#', $current_uri ) ) {
            return;
        }
        
        // No redirigir si ya hay cookie de idioma
        if ( isset( $_COOKIE['_icl_current_language'] ) || isset( $_COOKIE['cirion_lang_locked'] ) ) {
            return;
        }
        
        // No redirigir si viene de una redirecciÃ³n reciente
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer_host = parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST );
            $current_host = $_SERVER['HTTP_HOST'];
            if ( $referer_host === $current_host ) {
                return; // Ya venÃ­a de nuestro sitio
            }
        }
        
        // Obtener paÃ­s detectado usando mÃ©todo mejorado
        $country = $this->geo->detect_country_for_wpml();
        
        // Log de detecciÃ³n para debugging
        $this->log_detection( $country );
        
        // Si no se detecta paÃ­s, mostrar selector
        if ( $country === 'GLOBAL' ) {
            $this->show_language_selector();
            return;
        }
        
        // Convertir paÃ­s a idioma WPML
        $target_language = $this->country_to_wpml_language( $country );
        
        // Obtener idioma actual de WPML
        $current_language = apply_filters( 'wpml_current_language', 'es' );
        
        // Si ya estamos en el idioma correcto, no redirigir
        if ( $target_language === $current_language ) {
            // Pero sÃ­ setear cookie para evitar futuros cÃ¡lculos
            setcookie( 'cirion_detected_lang', $target_language, time() + 86400 * 7, '/', '', false, true );
            return;
        }
        
        // Redirigir
        $redirect_url = home_url( "/{$target_language}/" );
        
        // Log para debugging
        $this->log_redirect( $country, $target_language, $redirect_url );
        
        // RedirecciÃ³n 302 (temporal)
        wp_redirect( $redirect_url, 302 );
        exit;
    }
    
    /**
     * Muestra selector de idioma cuando no se puede detectar
     */
    private function show_language_selector() {
        // Solo mostrar en la raÃ­z
        if ( $_SERVER['REQUEST_URI'] !== '/' ) {
            return;
        }
        
        add_action( 'wp_footer', function() {
            ?>
            <div id="cirion-wpml-selector" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:99999;">
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:40px; border-radius:10px; text-align:center; max-width:500px; width:90%;">
                    <h3 style="margin-bottom:20px; color:#333;">ðŸŒ Select Your Language / Seleccione su Idioma</h3>
                    <p style="margin-bottom:30px; color:#666;">We couldn't detect your location automatically. Please choose your preferred language.</p>
                    
                    <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap; margin-bottom:30px;">
                        <a href="/es/" onclick="document.cookie='cirion_lang_locked=es; path=/; max-age=604800';" style="display:inline-flex; align-items:center; padding:15px 25px; background:#0073aa; color:white; text-decoration:none; border-radius:8px; font-weight:bold; min-width:150px; justify-content:center; transition:background 0.3s;">
                            <span style="margin-right:10px;">ðŸ‡ªðŸ‡¸</span> EspaÃ±ol
                        </a>
                        <a href="/pt-br/" onclick="document.cookie='cirion_lang_locked=pt-br; path=/; max-age=604800';" style="display:inline-flex; align-items:center; padding:15px 25px; background:#0073aa; color:white; text-decoration:none; border-radius:8px; font-weight:bold; min-width:150px; justify-content:center; transition:background 0.3s;">
                            <span style="margin-right:10px;">ðŸ‡§ðŸ‡·</span> PortuguÃªs
                        </a>
                        <a href="/en/" onclick="document.cookie='cirion_lang_locked=en; path=/; max-age=604800';" style="display:inline-flex; align-items:center; padding:15px 25px; background:#0073aa; color:white; text-decoration:none; border-radius:8px; font-weight:bold; min-width:150px; justify-content:center; transition:background 0.3s;">
                            <span style="margin-right:10px;">ðŸ‡ºðŸ‡¸</span> English
                        </a>
                    </div>
                    
                    <button onclick="document.getElementById('cirion-wpml-selector').style.display='none'; document.cookie='cirion_lang_locked=true; path=/; max-age=86400';" style="padding:10px 25px; background:#666; color:white; border:none; border-radius:5px; cursor:pointer; font-size:14px; transition:background 0.3s;">
                        Close / Cerrar
                    </button>
                </div>
            </div>
            
            <style>
            #cirion-wpml-selector a:hover {
                background: #005a87 !important;
            }
            #cirion-wpml-selector button:hover {
                background: #555 !important;
            }
            </style>
            
            <script>
            // Mostrar despuÃ©s de 2 segundos si no hubo redirecciÃ³n automÃ¡tica
            setTimeout(function() {
                if ( window.location.pathname === '/' && !document.cookie.includes('_icl_current_language') ) {
                    document.getElementById('cirion-wpml-selector').style.display = 'block';
                }
            }, 2000);
            </script>
            <?php
        });
    }
    
    /**
     * Registra detecciones en log
     */
    private function log_detection( $country ) {
        $log_file = WP_CONTENT_DIR . '/logs/cirion-wpml-detection.log';
        
        $message = sprintf(
            "[%s] IP: %s, Country: %s, User-Agent: %s, Headers: %s\n",
            date( 'Y-m-d H:i:s' ),
            $this->geo->get_client_ip_for_wpml(),
            $country,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            json_encode( [
                'HTTP_CF_IPCOUNTRY' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null,
                'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
                'HTTP_ACCEPT_LANGUAGE' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
            ] )
        );
        
        $this->write_log( $log_file, $message );
    }
    
    /**
     * Registra redirecciones en log
     */
    private function log_redirect( $country, $language, $redirect_url ) {
        $log_file = WP_CONTENT_DIR . '/logs/cirion-wpml-redirects.log';
        
        $message = sprintf(
            "[%s] IP: %s, Country: %s, Language: %s, Redirect: %s, User-Agent: %s\n",
            date( 'Y-m-d H:i:s' ),
            $this->geo->get_client_ip_for_wpml(),
            $country,
            $language,
            $redirect_url,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
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
     * Debug en admin bar
     */
    public function add_admin_bar_debug( $wp_admin_bar ) {
        if ( ! is_admin() ) {
            $country = $this->geo->detect_country_for_wpml();
            $language = $this->country_to_wpml_language( $country );
            
            $wp_admin_bar->add_node( [
                'id'    => 'cirion-geo-debug',
                'title' => 'ðŸŒ ' . $country . ' â†’ ' . $language,
                'href'  => add_query_arg( [ 'cirion_wpml_debug' => '1' ] ),
                'meta'  => [
                    'title' => 'Click for debug details',
                    'class' => 'cirion-geo-debug-node',
                ],
            ] );
        }
    }
    
    /**
     * Shortcode para forzar idioma (testing)
     */
    public function shortcode_force_language( $atts ) {
        $atts = shortcode_atts( [
            'country' => '',
            'text'    => 'Test Language Redirect',
        ], $atts );
        
        if ( empty( $atts['country'] ) ) {
            return '';
        }
        
        $country = strtoupper( $atts['country'] );
        $language = $this->country_to_wpml_language( $country );
        
        ob_start();
        ?>
        <div class="cirion-force-language" style="margin:20px 0; padding:15px; background:#f5f5f5; border-left:4px solid #0073aa;">
            <p><strong><?php echo esc_html( $atts['text'] ); ?>:</strong></p>
            <p>Country: <code><?php echo esc_html( $country ); ?></code></p>
            <p>Should redirect to: <code><?php echo esc_html( $language ); ?></code></p>
            <a href="/?country=<?php echo esc_attr( $country ); ?>"
                style="display:inline-block; padding:10px 15px; background:#0073aa; color:white; text-decoration:none; border-radius:3px; margin-top:10px;">
                Test <?php echo esc_html( $country ); ?> â†’ <?php echo esc_html( $language ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Inicializar el redireccionador WPML (solo si WPML estÃ¡ activo)
add_action( 'init', function() {
    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        Cirion_WPML_Redirector::get_instance();
    }
}, 5 );

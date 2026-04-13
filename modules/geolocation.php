<?php
/**
 * Geolocation Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Geolocation {
    
    private static $instance = null;
    private $cache_expiry;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->cache_expiry = get_option( 'cirion_geo_cache_expiry', 86400 );
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Inyectar cÃ³digo JS en el head
        add_action( 'wp_head', [ $this, 'inject_country_js' ], 5 );
        
        // SincronizaciÃ³n en el footer
        add_action( 'wp_footer', [ $this, 'footer_country_sync' ], 99 );
        
        // Debug para admins
        if ( current_user_can( 'manage_options' ) ) {
            add_action( 'wp_head', [ $this, 'debug_console' ], 10 );
        }
        
        // Shortcode para mostrar paÃ­s
        add_shortcode( 'show_country', [ $this, 'shortcode_show_country' ] );
        
        // API endpoint para geolocation
        add_action( 'wp_ajax_cirion_get_country', [ $this, 'ajax_get_country' ] );
        add_action( 'wp_ajax_nopriv_cirion_get_country', [ $this, 'ajax_get_country' ] );
    }
    
    /**
     * Detecta el paÃ­s del usuario con mÃºltiples fuentes
     *
     * @return string CÃ³digo ISO2 del paÃ­s o 'GLOBAL'
     */
    public function detect_country_server(): string {
        static $cached_country = null;
        
        // Usar cachÃ© estÃ¡tica para esta request
        if ( $cached_country !== null ) {
            return $cached_country;
        }
        
        // 0) Override manual para testing
        if ( isset( $_GET['country'] ) && strlen( $_GET['country'] ) === 2 ) {
            $cached_country = strtoupper( sanitize_text_field( $_GET['country'] ) );
            return $cached_country;
        }
        
        // 1) Cache por IP (usando transients)
        $ip = $this->get_client_ip();
        $cache_key = 'cirion_geo_' . md5( $ip );
        $cached = get_transient( $cache_key );
        
        if ( $cached !== false ) {
            $cached_country = $cached;
            return $cached_country;
        }
        
        // 2) Headers CDN
        $country = $this->detect_from_cdn_headers();
        if ( $country !== 'GLOBAL' ) {
            $this->save_to_cache( $ip, $country, $cache_key );
            $cached_country = $country;
            return $country;
        }
        
        // 3) Geotarget desde URL
        $country = $this->detect_from_url();
        if ( $country !== 'GLOBAL' ) {
            $this->save_to_cache( $ip, $country, $cache_key );
            $cached_country = $country;
            return $country;
        }
        
        // 4) Lookup por IP
        $country = $this->lookup_by_ip( $ip );
        if ( $country !== 'GLOBAL' ) {
            $this->save_to_cache( $ip, $country, $cache_key );
            $cached_country = $country;
            return $country;
        }
        
        // 5) Fallback a locale del navegador
        $country = $this->detect_from_locale();
        
        // Guardar en cache incluso si es GLOBAL
        $this->save_to_cache( $ip, $country, $cache_key );
        $cached_country = $country;
        
        return $country;
    }
    
    /**
     * Obtiene la IP real del cliente
     */
    private function get_client_ip(): string {
        $ip = '';
        
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $candidates = array_map( 'trim', explode( ',', $_SERVER[ $header ] ) );
                foreach ( $candidates as $candidate ) {
                    if ( filter_var( $candidate, FILTER_VALIDATE_IP ) && ! $this->is_private_ip( $candidate ) ) {
                        $ip = $candidate;
                        break 2;
                    }
                }
            }
        }
        
        return $ip ?: '127.0.0.1';
    }
    
    /**
     * Verifica si una IP es privada
     */
    private function is_private_ip( $ip ): bool {
        return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
    }
    
    /**
     * Detecta paÃ­s desde headers CDN
     */
    private function detect_from_cdn_headers(): string {
        $cdn_headers = [
            'HTTP_CF_IPCOUNTRY' => function( $value ) { 
                return $value !== 'XX' ? strtoupper( $value ) : 'GLOBAL'; 
            },
            'HTTP_FASTLY_COUNTRY_CODE' => function( $value ) { 
                return strtoupper( $value ); 
            },
            'HTTP_X_COUNTRY_CODE' => function( $value ) { 
                return strtoupper( $value ); 
            },
            'HTTP_CLOUDFRONT_VIEWER_COUNTRY' => function( $value ) { 
                return strtoupper( $value ); 
            },
        ];
        
        foreach ( $cdn_headers as $header => $callback ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $country = $callback( $_SERVER[ $header ] );
                if ( $country !== 'GLOBAL' && strlen( $country ) === 2 ) {
                    return $country;
                }
            }
        }
        
        return 'GLOBAL';
    }
    
    /**
     * Detecta paÃ­s desde URL (geotarget)
     */
    private function detect_from_url(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if ( preg_match( '/\/geot\/([A-Z0-9]+)/i', $uri, $matches ) ) {
            $geot_to_country = [
                'QUI2' => 'EC', // Ecuador
                'SAN1' => 'CL', // Chile
                'SAN2' => 'UY', // Uruguay
                'SAO1' => 'BR', // Brasil
                'BOG2' => 'CO', // Colombia
                'BUE1' => 'AR', // Argentina
                'CAR1' => 'VE', // Venezuela
                'CUR1' => 'CW', // Curazao
                'LIM1' => 'PE', // PerÃº
            ];
            
            $code = strtoupper( $matches[1] );
            if ( isset( $geot_to_country[ $code ] ) ) {
                return $geot_to_country[ $code ];
            }
        }
        
        return 'GLOBAL';
    }
    
    /**
     * Lookup de paÃ­s por IP usando servicios externos
     */
    private function lookup_by_ip( $ip ): string {
        if ( empty( $ip ) || $this->is_private_ip( $ip ) ) {
            return 'GLOBAL';
        }
        
        // Intentar con ipwho.is primero
        $country = $this->lookup_ipwhois( $ip );
        if ( $country !== 'GLOBAL' ) {
            return $country;
        }
        
        // Fallback a ipapi.co
        $country = $this->lookup_ipapi( $ip );
        if ( $country !== 'GLOBAL' ) {
            return $country;
        }
        
        return 'GLOBAL';
    }
    
    /**
     * Lookup usando ipwho.is
     */
    private function lookup_ipwhois( $ip ): string {
        $url = 'https://ipwho.is/' . rawurlencode( $ip );
        
        $response = wp_remote_get( $url, [
            'timeout' => 3,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress)',
                'Accept' => 'application/json',
            ],
        ] );
        
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return 'GLOBAL';
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! empty( $data['country_code'] ) ) {
            return strtoupper( $data['country_code'] );
        }
        
        return 'GLOBAL';
    }
    
    /**
     * Lookup usando ipapi.co
     */
    private function lookup_ipapi( $ip ): string {
        $url = 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/';
        
        $response = wp_remote_get( $url, [
            'timeout' => 3,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress)',
            ],
        ] );
        
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return 'GLOBAL';
        }
        
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! empty( $data['country_code'] ) ) {
            return strtoupper( $data['country_code'] );
        }
        
        return 'GLOBAL';
    }
    
    /**
     * Detecta paÃ­s desde locale del navegador
     */
    private function detect_from_locale(): string {
        $locale = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        if ( empty( $locale ) ) {
            return 'GLOBAL';
        }
        
        $locale_to_country = [
            'es-AR' => 'AR', // EspaÃ±ol Argentina
            'pt-BR' => 'BR', // PortuguÃ©s Brasil
            'es-CL' => 'CL', // EspaÃ±ol Chile
            'es-CO' => 'CO', // EspaÃ±ol Colombia
            'es-EC' => 'EC', // EspaÃ±ol Ecuador
            'es-MX' => 'MX', // EspaÃ±ol MÃ©xico
            'es-PE' => 'PE', // EspaÃ±ol PerÃº
            'es-VE' => 'VE', // EspaÃ±ol Venezuela
        ];
        
        // Buscar coincidencia exacta
        foreach ( $locale_to_country as $locale_code => $country_code ) {
            if ( stripos( $locale, $locale_code ) !== false ) {
                return $country_code;
            }
        }
        
        // Buscar por idioma principal
        if ( stripos( $locale, 'es' ) !== false ) {
            return 'AR'; // EspaÃ±ol -> Argentina por defecto
        } elseif ( stripos( $locale, 'pt' ) !== false ) {
            return 'BR'; // PortuguÃ©s -> Brasil por defecto
        }
        
        return 'GLOBAL';
    }
    
    /**
     * Guarda en cache el resultado
     */
    private function save_to_cache( $ip, $country, $cache_key ) {
        set_transient( $cache_key, $country, $this->cache_expiry );
        
        // TambiÃ©n guardar en base de datos para estadÃ­sticas
        global $wpdb;
        $table_name = $wpdb->prefix . 'cirion_geo_cache';
        
        $wpdb->replace( 
            $table_name,
            [
                'ip_address'   => $ip,
                'country_code' => $country,
                'data'         => maybe_serialize( [
                    'timestamp' => current_time( 'mysql' ),
                    'source'    => 'detected',
                ] ),
            ],
            [ '%s', '%s', '%s' ]
        );
    }
    
    /**
     * Inyecta el cÃ³digo JS con el paÃ­s detectado
     */
    public function inject_country_js() {
        $country = $this->detect_country_server();
        ?>
        <script>
        window.cirionGeolocation = window.cirionGeolocation || {};
        window.cirionGeolocation.detectedCountry = '<?php echo esc_js( $country ); ?>';
        window.detectedCountryCode = '<?php echo esc_js( $country ); ?>';
        
        // Evento para notificar que el paÃ­s estÃ¡ disponible
        document.addEventListener('DOMContentLoaded', function() {
            window.dispatchEvent(new CustomEvent('cirion:country-detected', {
                detail: { country: '<?php echo esc_js( $country ); ?>' }
            }));
        });
        </script>
        <?php
    }
    
    /**
     * SincronizaciÃ³n de paÃ­s en el footer
     */
    public function footer_country_sync() {
        ?>
        <script>
        (function() {
            window.cirionGeolocation = window.cirionGeolocation || {};
            window.cirionGeolocation.country = window.detectedCountryCode || 'GLOBAL';
            
            // Mapeo de formularios HubSpot
            window.hubspotFormMapping = {
                'AR': '2c30b38a-a2b9-45ae-a8eb-9478dbb79b41',
                'BR': '650afdcd-bdd2-4eaa-8955-24cf56441c57',
                'CL': '228e426c-769d-4a9a-bc04-dc16b86a0206', 
                'CO': '58af2ef7-b4ee-46b9-9942-b862b2914f9e',
                'EC': '63961e94-a55b-4973-8557-94c4b2d85de6',
                'MX': '4c6ecea9-c475-4453-83a2-ef495859c7c2',
                'PE': 'ec693200-03db-4390-963c-c358063461f9',
                'VE': '12c11085-a9d1-4f77-b714-dc9e55340b52'
            };
            
            window.hubspotGlobalForm = 'aede20b6-a916-476c-8f91-9454d0c20df6';
            
            // FunciÃ³n para obtener form ID
            window.getHubspotFormId = function(countryCode) {
                countryCode = countryCode || window.detectedCountryCode;
                return window.hubspotFormMapping[countryCode] || window.hubspotGlobalForm;
            };
            
            // Sincronizar paÃ­s desde input telefÃ³nico
            window.syncCountryFromTelInput = function() {
                try {
                    const tel = document.querySelector('#hubspot-form-container input[type="tel"].hs-input') || 
                                document.querySelector('input[type="tel"]');
                    if (!tel) return;
                    
                    if (window.intlTelInputGlobals) {
                        const iti = window.intlTelInputGlobals.getInstance(tel);
                        if (iti) {
                            const countryData = iti.getSelectedCountryData();
                            const iso2 = (countryData?.iso2 || '').toUpperCase();
                            
                            if (iso2 && iso2 !== (window.detectedCountryCode || '').toUpperCase()) {
                                window.detectedCountryCode = iso2;
                                document.dispatchEvent(new CustomEvent('cirion:country-changed', { 
                                    detail: { source: 'telinput', iso2 } 
                                }));
                                
                                // Notificar a otros scripts
                                document.dispatchEvent(new CustomEvent('hs:country-change', { 
                                    detail: { source: 'telinput', iso2 } 
                                }));
                            }
                        }
                    }
                } catch(e) { 
                    console.warn('Cirion: Error en syncCountryFromTelInput', e); 
                }
            };
            
            // Escuchar cambios en inputs telefÃ³nicos
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    const telInput = document.querySelector('input[type="tel"]');
                    if (telInput && window.intlTelInputGlobals) {
                        telInput.addEventListener('countrychange', function() {
                            setTimeout(function() {
                                window.syncCountryFromTelInput();
                            }, 100);
                        });
                    }
                }, 2000);
            });
            
            // Exponer funciÃ³n para otros scripts
            window.cirionGeolocation.getCountry = function() {
                return window.detectedCountryCode || 'GLOBAL';
            };
            
        })();
        </script>
        <?php
    }
    
    /**
     * Debug en consola para administradores
     */
    public function debug_console() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $country = $this->detect_country_server();
        $ip = $this->get_client_ip();
        
        ?>
        <script>
        console.group('ðŸŒ Cirion Geolocation Debug');
        console.log('IP detectada: <?php echo esc_js( $ip ); ?>');
        console.log('PaÃ­s detectado: <?php echo esc_js( $country ); ?>');
        console.log('Form ID asignado: <?php echo esc_js( $this->get_hubspot_form_id( $country ) ); ?>');
        console.log('Headers:', {
            'HTTP_CF_IPCOUNTRY': '<?php echo $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'NO'; ?>',
            'HTTP_CF_CONNECTING_IP': '<?php echo $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'NO'; ?>',
            'HTTP_X_FORWARDED_FOR': '<?php echo $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'NO'; ?>',
            'REMOTE_ADDR': '<?php echo $_SERVER['REMOTE_ADDR'] ?? 'NO'; ?>'
        });
        console.groupEnd();
        </script>
        <?php
    }
    
    /**
     * Shortcode para mostrar el paÃ­s
     */
    public function shortcode_show_country( $atts ) {
        $atts = shortcode_atts( [
            'format' => 'code', // 'code', 'name', 'full'
        ], $atts );
        
        $country_code = $this->detect_country_server();
        
        if ( $atts['format'] === 'name' ) {
            $countries = [
                'AR' => 'Argentina',
                'BR' => 'Brasil',
                'CL' => 'Chile',
                'CO' => 'Colombia',
                'EC' => 'Ecuador',
                'MX' => 'MÃ©xico',
                'PE' => 'PerÃº',
                'VE' => 'Venezuela',
                'GLOBAL' => 'Internacional',
            ];
            
            return $countries[ $country_code ] ?? 'Internacional';
        } elseif ( $atts['format'] === 'full' ) {
            return sprintf( '%s (%s)', 
                $this->shortcode_show_country( [ 'format' => 'name' ] ),
                $country_code
            );
        }
        
        return $country_code;
    }
    
    /**
     * Obtiene el form ID de HubSpot para un paÃ­s
     */
    public function get_hubspot_form_id( $country_code = null ) {
        if ( $country_code === null ) {
            $country_code = $this->detect_country_server();
        }
        
        $hubspot_forms = [
            'AR' => '2c30b38a-a2b9-45ae-a8eb-9478dbb79b41',
            'BR' => '650afdcd-bdd2-4eaa-8955-24cf56441c57', 
            'CL' => '228e426c-769d-4a9a-bc04-dc16b86a0206',
            'CO' => '58af2ef7-b4ee-46b9-9942-b862b2914f9e',
            'EC' => '63961e94-a55b-4973-8557-94c4b2d85de6',
            'MX' => '4c6ecea9-c475-4453-83a2-ef495859c7c2',
            'PE' => 'ec693200-03db-4390-963c-c358063461f9',
            'VE' => '12c11085-a9d1-4f77-b714-dc9e55340b52',
        ];
        
        $global_form = 'aede20b6-a916-476c-8f91-9454d0c20df6';
        
        return $hubspot_forms[ $country_code ] ?? $global_form;
    }
    
        /**
     * Endpoint AJAX para obtener el paÃ­s
     */
    public function ajax_get_country() {
        check_ajax_referer( 'cirion_geo_nonce', 'nonce' );
        
        $country = $this->detect_country_server();
        $form_id = $this->get_hubspot_form_id( $country );
        
        wp_send_json_success( [
            'country' => $country,
            'form_id' => $form_id,
            'country_name' => $this->shortcode_show_country( [ 'format' => 'name' ] ),
            'ip' => $this->get_client_ip(),
        ] );
    }
    
    /**
     * Detecta paÃ­s especÃ­fico para WPML (mÃ¡s agresivo)
     */
    public function detect_country_for_wpml(): string {
        $country = $this->detect_country_server();
        
        // Si es GLOBAL, intentar mÃ©todos adicionales
        if ( $country === 'GLOBAL' ) {
            // MÃ©todo extra: usar el header de Qwilt si estÃ¡ disponible
            if ( ! empty( $_SERVER['HTTP_X_QWILT_COUNTRY'] ) ) {
                $country = strtoupper( substr( $_SERVER['HTTP_X_QWILT_COUNTRY'], 0, 2 ) );
            }
            
            // MÃ©todo extra: Azure header especÃ­fico
            elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR_COUNTRY'] ) ) {
                $country = strtoupper( substr( $_SERVER['HTTP_X_FORWARDED_FOR_COUNTRY'], 0, 2 ) );
            }
        }
        
        return $country;
    }
    
    /**
     * Obtiene la IP real con headers especÃ­ficos de Azure
     */
    public function get_client_ip_for_wpml(): string {
        $headers = [
            'HTTP_X_QWILT_ORIGINAL_IP',  // Header personalizado de Qwilt
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                
                // Validar IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Debug endpoint para WPML
     */
    public function debug_wpml_redirect() {
        header( 'Content-Type: text/plain' );
        
        echo "=== CIRION WPML REDIRECT DEBUG ===\n\n";
        
        echo "IP Detection:\n";
        echo "get_client_ip(): " . $this->get_client_ip() . "\n";
        echo "get_client_ip_for_wpml(): " . $this->get_client_ip_for_wpml() . "\n\n";
        
        echo "Headers:\n";
        foreach ( $_SERVER as $key => $value ) {
            if ( strpos( $key, 'HTTP_' ) === 0 || strpos( $key, 'REMOTE_' ) === 0 ) {
                if ( strpos( $key, 'COUNTRY' ) !== false || strpos( $key, 'IP' ) !== false ||
                      strpos( $key, 'FORWARD' ) !== false || strpos( $key, 'LANGUAGE' ) !== false ) {
                    echo "$key: $value\n";
                }
            }
        }
        echo "\n";
        
        echo "Country Detection:\n";
        echo "detect_country_server(): " . $this->detect_country_server() . "\n";
        echo "detect_country_for_wpml(): " . $this->detect_country_for_wpml() . "\n\n";
        
        echo "WPML Status:\n";
        if ( function_exists( 'icl_object_id' ) ) {
            echo "Default language: " . apply_filters( 'wpml_default_language', null ) . "\n";
            echo "Current language: " . apply_filters( 'wpml_current_language', null ) . "\n";
        } else {
            echo "WPML not active\n";
        }
        
        echo "\nCookies:\n";
        foreach ( $_COOKIE as $key => $value ) {
            if ( strpos( $key, 'icl' ) !== false || strpos( $key, 'lang' ) !== false ) {
                echo "$key: $value\n";
            }
        }
        
        die();
    }
}

// Inicializar el mÃ³dulo
add_action( 'init', function() {
    Cirion_Geolocation::get_instance();
} );

// FunciÃ³n helper para uso global
if ( ! function_exists( 'detect_country_server' ) ) {
    function detect_country_server() {
        return Cirion_Geolocation::get_instance()->detect_country_server();
    }
}

if ( ! function_exists( 'detect_country_for_hubspot' ) ) {
    function detect_country_for_hubspot() {
        return detect_country_server();
    }
}

if ( ! function_exists( 'get_hubspot_form_by_country' ) ) {
    function get_hubspot_form_by_country( $country_code = null ) {
        return Cirion_Geolocation::get_instance()->get_hubspot_form_id( $country_code );
    }
}
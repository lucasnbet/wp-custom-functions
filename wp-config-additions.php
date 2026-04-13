"<?php
/**
 * Configuraciones adicionales para wp-config.php
 * Para el sistema de redirección WPML por geolocalización
 * 
 * Copiar y pegar estas definiciones en wp-config.php
 * ANTES de require_once(ABSPATH . 'wp-settings.php');
 */

// ============================================================================
// CONFIGURACIÓN ESPECÍFICA PARA STACK QWILT/AZURE
// ============================================================================

/**
 * Corrección de IP para infraestructura con múltiples proxies
 * Qwilt → WAF Radware → Azure App Gateway → Balanceador → WordPress
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $forwarded_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
    
    // Tomar la primera IP válida (no privada, no reservada)
    foreach ( $forwarded_ips as $ip ) {
        $ip = trim( $ip );
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            $_SERVER['REMOTE_ADDR'] = $ip;
            break;
        }
    }
}

// ============================================================================
// CONFIGURACIÓN WPML
// ============================================================================

/**
 * Desactivar redirección automática de WPML
 * Nuestro plugin maneja las redirecciones de forma más precisa
 */
define( 'WPML_REDIRECT_BY_IP', false );

/**
 * No cargar CSS del selector de idioma de WPML
 * Nuestro plugin tiene su propio selector estilizado
 */
define( 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS', true );

/**
 * Habilitar cache de parámetros del sitio para WPML
 * Mejora performance en sitios con mucho tráfico
 */
define( 'WPML_CACHE_SITE_PARAMS', true );

// ============================================================================
// CONFIGURACIÓN GEOLOCATION
// ============================================================================

/**
 * Habilitar modo debug para geolocalización
 * Solo en desarrollo/testing
 */
// define( 'WP_CUSTOM_GEO_DEBUG', true );

/**
 * Tiempo de cache para detección de país (en segundos)
 * Default: 86400 (24 horas)
 */
// define( 'WP_CUSTOM_GEO_CACHE_EXPIRY', 86400 );

/**
 * Headers personalizados para detección de país
 * Prioridad: 1. Qwilt, 2. Azure, 3. Cloudflare, 4. Otros
 */
if ( ! defined( 'WP_CUSTOM_GEO_HEADERS' ) ) {
    define( 'WP_CUSTOM_GEO_HEADERS', serialize( [
        'HTTP_X_QWILT_COUNTRY',          // Header específico de Qwilt
        'HTTP_X_FORWARDED_FOR_COUNTRY',  // Header específico de Azure
        'HTTP_CF_IPCOUNTRY',             // Cloudflare
        'HTTP_FASTLY_COUNTRY_CODE',      // Fastly
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY', // AWS CloudFront
        'HTTP_X_COUNTRY_CODE',           // CDN genérico
    ] ) );
}

// ============================================================================
// CONFIGURACIÓN DE LOGS
// ============================================================================

/**
 * Directorio para logs del plugin
 * Se crea automáticamente si no existe
 */
if ( ! defined( 'WP_CUSTOM_LOG_DIR' ) ) {
    define( 'WP_CUSTOM_LOG_DIR', WP_CONTENT_DIR . '/logs/' );
}

/**
 * Nivel de logging
 * 'error', 'warning', 'info', 'debug'
 */
if ( ! defined( 'WP_CUSTOM_LOG_LEVEL' ) ) {
    define( 'WP_CUSTOM_LOG_LEVEL', 'info' );
}

// ============================================================================
// CONFIGURACIÓN DE SEGURIDAD
// ============================================================================

/**
 * Validar IPs de proxies confiables
 * Lista de IPs/rangos de proxies de confianza
 */
if ( ! defined( 'WP_CUSTOM_TRUSTED_PROXIES' ) ) {
    define( 'WP_CUSTOM_TRUSTED_PROXIES', serialize( [
        // Azure IP ranges
        '20.0.0.0/8',
        '52.0.0.0/8',
        '104.0.0.0/8',
        '168.0.0.0/8',
        // Qwilt IP ranges (ejemplo)
        '45.0.0.0/8',
        // Cloudflare IP ranges
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '104.16.0.0/12',
    ] ) );
}

// ============================================================================
// CONFIGURACIÓN DE PRUEBAS
// ============================================================================

/**
 * Modo testing - desactivar redirecciones reales
 * Útil para desarrollo sin afectar usuarios
 */
// define( 'WP_CUSTOM_TEST_MODE', true );

/**
 * País forzado para testing
 * Anula toda detección automática
 */
// define( 'WP_CUSTOM_FORCE_COUNTRY', 'BR' ); // Brasil
// define( 'WP_CUSTOM_FORCE_COUNTRY', 'US' ); // USA
// define( 'WP_CUSTOM_FORCE_COUNTRY', 'AR' ); // Argentina

// ============================================================================
// NOTAS IMPORTANTES
// ============================================================================

/**
 * 1. Estas configuraciones deben ir ANTES de wp-settings.php
 * 2. Comentar/descomentar según necesidades
 * 3. En producción, mantener solo las configuraciones esenciales
 * 4. Monitorear logs periódicamente
 * 5. Actualizar rangos de IP según infraestructura
 */

// ============================================================================
// EJEMPLO DE CONFIGURACIÓN PARA PRODUCCIÓN
// ============================================================================
/*
// Corrección de IP para proxies
if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $forwarded_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
    foreach ( $forwarded_ips as $ip ) {
        $ip = trim( $ip );
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            $_SERVER['REMOTE_ADDR'] = $ip;
            break;
        }
    }
}

// Configuración WPML
define( 'WPML_REDIRECT_BY_IP', false );
define( 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS', true );
define( 'WPML_CACHE_SITE_PARAMS', true );

// Configuración logs
if ( ! defined( 'WP_CUSTOM_LOG_DIR' ) ) {
    define( 'WP_CUSTOM_LOG_DIR', WP_CONTENT_DIR . '/logs/' );
}
*/

// ============================================================================
// FIN DE CONFIGURACIONES
// ============================================================================
"
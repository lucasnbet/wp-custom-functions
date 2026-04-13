<?php
/**
 * Helper Functions
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Genera un UUID v4
 */
function cirion_generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff )
    );
}

/**
 * Sanitiza un array recursivamente
 */
function cirion_sanitize_array( $array ) {
    foreach ( $array as $key => $value ) {
        if ( is_array( $value ) ) {
            $array[ $key ] = cirion_sanitize_array( $value );
        } else {
            $array[ $key ] = sanitize_text_field( $value );
        }
    }
    return $array;
}

/**
 * Obtiene la URL actual sin parámetros de query
 */
function cirion_get_current_url() {
    global $wp;
    return home_url( $wp->request );
}

/**
 * Verifica si es una URL local
 */
function cirion_is_local_url( $url ) {
    $site_url = site_url();
    $url_host = parse_url( $url, PHP_URL_HOST );
    $site_host = parse_url( $site_url, PHP_URL_HOST );
    
    return $url_host === $site_host;
}

/**
 * Minifica código CSS
 */
function cirion_minify_css( $css ) {
    $css = preg_replace( '/\s+/', ' ', $css );
    $css = preg_replace( '/\/\*.*?\*\//', '', $css );
    $css = str_replace( '; ', ';', $css );
    $css = str_replace( ': ', ':', $css );
    $css = str_replace( ' {', '{', $css );
    $css = str_replace( '{ ', '{', $css );
    $css = str_replace( ', ', ',', $css );
    $css = str_replace( '} ', '}', $css );
    $css = str_replace( ';}', '}', $css );
    
    return trim( $css );
}

/**
 * Minifica código JavaScript
 */
function cirion_minify_js( $js ) {
    // Remover comentarios
    $js = preg_replace( '!/\*.*?\*/!s', '', $js );
    $js = preg_replace( '!//.*$!m', '', $js );
    
    // Remover espacios innecesarios
    $js = preg_replace( '/\s+/', ' ', $js );
    $js = preg_replace( '/\s*([=+\-\*\/\[\]\(\)\{\}:;,])\s*/', '$1', $js );
    
    return trim( $js );
}

/**
 * Obtiene el tamaño de un archivo en formato legible
 */
function cirion_get_file_size( $path ) {
    if ( ! file_exists( $path ) ) {
        return '0 B';
    }
    
    $bytes = filesize( $path );
    $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
    
    $bytes = max( $bytes, 0 );
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $units ) - 1 );
    
    $bytes /= pow( 1024, $pow );
    
    return round( $bytes, 2 ) . ' ' . $units[ $pow ];
}

/**
 * Log para debugging
 */
function cirion_log( $message, $data = null ) {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        return;
    }
    
    $log_entry = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;
    
    if ( $data !== null ) {
        $log_entry .= ' ' . print_r( $data, true );
    }
    
    error_log( '[Cirion] ' . $log_entry );
}

/**
 * Obtiene la memoria usada
 */
function cirion_get_memory_usage() {
    $memory = memory_get_usage( true );
    $units = [ 'B', 'KB', 'MB', 'GB' ];
    
    $memory = max( $memory, 0 );
    $pow = floor( ( $memory ? log( $memory ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $units ) - 1 );
    
    $memory /= pow( 1024, $pow );
    
    return round( $memory, 2 ) . ' ' . $units[ $pow ];
}

/**
 * Obtiene el tiempo de ejecución
 */
function cirion_get_execution_time() {
    if ( ! defined( 'CIRION_START_TIME' ) ) {
        define( 'CIRION_START_TIME', microtime( true ) );
    }
    
    return round( microtime( true ) - CIRION_START_TIME, 4 ) . 's';
}

/**
 * Verifica si una función está disponible
 */
function cirion_function_available( $function_name ) {
    if ( function_exists( $function_name ) ) {
        return true;
    }
    
    // Verificar si está deshabilitada
    $disabled_functions = explode( ',', ini_get( 'disable_functions' ) );
    return ! in_array( $function_name, $disabled_functions, true );
}

/**
 * Obtiene la lista de países soportados
 */
function cirion_get_supported_countries() {
    return [
        'AR' => [
            'code' => 'AR',
            'name' => 'Argentina',
            'currency' => 'ARS',
            'language' => 'es',
            'phone_code' => '+54',
        ],
        'BR' => [
            'code' => 'BR',
            'name' => 'Brasil',
            'currency' => 'BRL',
            'language' => 'pt',
            'phone_code' => '+55',
        ],
        'CL' => [
            'code' => 'CL',
            'name' => 'Chile',
            'currency' => 'CLP',
            'language' => 'es',
            'phone_code' => '+56',
        ],
        'CO' => [
            'code' => 'CO',
            'name' => 'Colombia',
            'currency' => 'COP',
            'language' => 'es',
            'phone_code' => '+57',
        ],
        'EC' => [
            'code' => 'EC',
            'name' => 'Ecuador',
            'currency' => 'USD',
            'language' => 'es',
            'phone_code' => '+593',
        ],
        'MX' => [
            'code' => 'MX',
            'name' => 'México',
            'currency' => 'MXN',
            'language' => 'es',
            'phone_code' => '+52',
        ],
        'PE' => [
            'code' => 'PE',
            'name' => 'Perú',
            'currency' => 'PEN',
            'language' => 'es',
            'phone_code' => '+51',
        ],
        'VE' => [
            'code' => 'VE',
            'name' => 'Venezuela',
            'currency' => 'USD',
            'language' => 'es',
            'phone_code' => '+58',
        ],
        'GLOBAL' => [
            'code' => 'GLOBAL',
            'name' => 'Global',
            'currency' => 'USD',
            'language' => 'en',
            'phone_code' => '+1',
        ],
    ];
}

<?php
/**
 * Additional Shortcodes Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_shortcodes();
    }
    
    private function init_shortcodes() {
        // Shortcode para mostrar año actual
        add_shortcode( 'current_year', [ $this, 'shortcode_current_year' ] );
        
        // Shortcode para mostrar nombre del sitio
        add_shortcode( 'site_name', [ $this, 'shortcode_site_name' ] );
        
        // Shortcode para mostrar URL del sitio
        add_shortcode( 'site_url', [ $this, 'shortcode_site_url' ] );
        
        // Shortcode para mostrar la URL actual
        add_shortcode( 'current_url', [ $this, 'shortcode_current_url' ] );
        
        // Shortcode para mostrar la IP del visitante
        add_shortcode( 'visitor_ip', [ $this, 'shortcode_visitor_ip' ] );
        
        // Shortcode para mostrar información del usuario
        add_shortcode( 'user_info', [ $this, 'shortcode_user_info' ] );
        
        // Shortcode para botón de WhatsApp directo
        add_shortcode( 'whatsapp_button', [ $this, 'shortcode_whatsapp_button' ] );
        
        // Shortcode para iframe responsivo
        add_shortcode( 'responsive_iframe', [ $this, 'shortcode_responsive_iframe' ] );
    }
    
    /**
     * Muestra el año actual
     */
    public function shortcode_current_year( $atts ) {
        $atts = shortcode_atts( [
            'format' => 'Y', // PHP date format
        ], $atts );
        
        return date_i18n( $atts['format'] );
    }
    
    /**
     * Muestra el nombre del sitio
     */
    public function shortcode_site_name() {
        return get_bloginfo( 'name' );
    }
    
    /**
     * Muestra la URL del sitio
     */
    public function shortcode_site_url( $atts ) {
        $atts = shortcode_atts( [
            'scheme' => '', // http, https, relative
        ], $atts );
        
        $url = get_bloginfo( 'url' );
        
        if ( $atts['scheme'] === 'relative' ) {
            $url = wp_parse_url( $url, PHP_URL_PATH );
        } elseif ( in_array( $atts['scheme'], [ 'http', 'https' ], true ) ) {
            $url = set_url_scheme( $url, $atts['scheme'] );
        }
        
        return esc_url( $url );
    }
    
    /**
     * Muestra la URL actual
     */
    public function shortcode_current_url() {
        global $wp;
        return home_url( $wp->request );
    }
    
    /**
     * Muestra la IP del visitante
     */
    public function shortcode_visitor_ip( $atts ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return '';
        }
        
        $ip = '';
        
        // Obtener IP real
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
                    if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                        $ip = $candidate;
                        break 2;
                    }
                }
            }
        }
        
        return $ip ?: 'IP no disponible';
    }
    
    /**
     * Muestra información del usuario actual
     */
    public function shortcode_user_info( $atts ) {
        $atts = shortcode_atts( [
            'field' => 'display_name', // display_name, user_email, user_login, ID
        ], $atts );
        
        $user = wp_get_current_user();
        
        if ( ! $user->exists() ) {
            return '';
        }
        
        if ( isset( $user->{$atts['field']} ) ) {
            return $user->{$atts['field']};
        }
        
        return '';
    }
    
    /**
     * Botón de WhatsApp directo
     */
    public function shortcode_whatsapp_button( $atts ) {
        $atts = shortcode_atts( [
            'phone'   => '', // Número de teléfono
            'text'    => 'Hola, me gustaría más información', // Texto del mensaje
            'label'   => 'WhatsApp', // Texto del botón
            'icon'    => 'true', // Mostrar icono
            'class'   => '',
            'style'   => '',
            'target'  => '_blank', // _blank, _self
        ], $atts );
        
        // Validar teléfono
        $phone = preg_replace( '/[^0-9]/', '', $atts['phone'] );
        if ( empty( $phone ) ) {
            return '<span class="cirion-error">Error: Número de WhatsApp requerido</span>';
        }
        
        // Codificar texto
        $text = rawurlencode( $atts['text'] );
        
        // Construir URL
        $url = "https://wa.me/{$phone}?text={$text}";
        
        // Clases
        $classes = 'cirion-whatsapp-direct-button ' . sanitize_html_class( $atts['class'] );
        
        // Estilos
        $style = ! empty( $atts['style'] ) ? ' style="' . esc_attr( $atts['style'] ) . '"' : '';
        
        // Icono
        $icon_html = '';
        if ( filter_var( $atts['icon'], FILTER_VALIDATE_BOOLEAN ) ) {
            $icon_html = '<i class="fab fa-whatsapp"></i> ';
        }
        
        // Construir botón
        return sprintf(
            '<a href="%s" class="%s" target="%s" rel="noopener noreferrer"%s>%s%s</a>',
            esc_url( $url ),
            esc_attr( $classes ),
            esc_attr( $atts['target'] ),
            $style,
            $icon_html,
            esc_html( $atts['label'] )
        );
    }
    
    /**
     * Iframe responsivo
     */
    public function shortcode_responsive_iframe( $atts ) {
        $atts = shortcode_atts( [
            'src'        => '',
            'width'      => '560',
            'height'     => '315',
            'title'      => 'Embedded content',
            'allow'      => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
            'frameborder'=> '0',
            'allowfullscreen' => 'true',
            'loading'    => 'lazy',
            'class'      => '',
        ], $atts );
        
        if ( empty( $atts['src'] ) ) {
            return '<span class="cirion-error">Error: URL del iframe requerida</span>';
        }
        
        // Clases
        $classes = 'cirion-responsive-iframe ' . sanitize_html_class( $atts['class'] );
        
        // Ratio de aspecto
        $ratio = ( intval( $atts['height'] ) / intval( $atts['width'] ) ) * 100;
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>" style="position: relative; padding-bottom: <?php echo esc_attr( $ratio ); ?>%; height: 0; overflow: hidden; max-width: 100%;">
            <iframe 
                src="<?php echo esc_url( $atts['src'] ); ?>"
                title="<?php echo esc_attr( $atts['title'] ); ?>"
                allow="<?php echo esc_attr( $atts['allow'] ); ?>"
                frameborder="<?php echo esc_attr( $atts['frameborder'] ); ?>"
                allowfullscreen="<?php echo esc_attr( $atts['allowfullscreen'] ); ?>"
                loading="<?php echo esc_attr( $atts['loading'] ); ?>"
                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;">
            </iframe>
        </div>
        <?php
        
        return ob_get_clean();
    }
}

// Inicializar el módulo
add_action( 'init', function() {
    Cirion_Shortcodes::get_instance();
} );
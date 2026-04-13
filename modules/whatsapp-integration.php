<?php
/**
 * WhatsApp Integration Module - Versión Corregida
 * 
 * @package wpCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class wp_WhatsApp_Integration {
    
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
        // Shortcodes
        add_shortcode( 'whatsapp_popup_invisible', [ $this, 'shortcode_popup_invisible' ] );
        add_shortcode( 'whatsapp_popup_trigger', [ $this, 'shortcode_popup_trigger' ] );
        add_shortcode( 'whatsapp_float_button', [ $this, 'shortcode_float_button' ] );
        
        // Cargar estilos
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        
        // Cargar Font Awesome condicionalmente
        add_action( 'wp_enqueue_scripts', [ $this, 'conditionally_load_font_awesome' ] );
    }
    
                    /**
     * Carga estilos del popup - VERSIÓN MEJORADA
     */
    public function enqueue_styles() {
        // Opción 1: Forzar carga desde configuración
        $config = wp_config( 'modules.whatsapp-integration.settings', [] );
        $force_load = $config['force_load_all_pages'] ?? false;
        
        if ( $force_load ) {
            $this->load_whatsapp_styles();
            return;
        }
        
        // Opción 2: Forzar carga desde constante
        if ( defined( 'FORCE_WHATSAPP_LOAD' ) && FORCE_WHATSAPP_LOAD ) {
            $this->load_whatsapp_styles();
            return;
        }
        
        // Opción 3: Detección automática
        $has_whatsapp = $this->detect_whatsapp_shortcodes();
        
        // Debug: resultado de detección
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'wp WhatsApp: Shortcodes detectados - ' . ( $has_whatsapp ? 'SÍ' : 'NO' ) );
            error_log( 'wp WhatsApp: Página actual - ' . ( is_a( $post, 'WP_Post' ) ? $post->post_name : 'N/A' ) );
        }
        
        // Cargar estilos si se detectó WhatsApp
        if ( $has_whatsapp ) {
            $this->load_whatsapp_styles();
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'wp WhatsApp: NO se cargarán estilos - no se detectaron shortcodes' );
            }
        }
    }
    
    /**
     * Función auxiliar para cargar estilos de WhatsApp
     */
    private function load_whatsapp_styles() {
        $css_url = wp_CUSTOM_URL . 'assets/css/whatsapp-popup.css';
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'wp WhatsApp: Cargando CSS desde - ' . $css_url );
        }
        
        wp_enqueue_style(
            'wp-whatsapp',
            $css_url,
            [],
            wp_CUSTOM_VERSION
        );
        
        // Debug: registrar en consola si es admin
        if ( current_user_can( 'manage_options' ) ) {
            add_action( 'wp_footer', function() use ( $css_url ) {
                echo '<script>';
                echo 'console.log("wp WhatsApp: Estilos cargados correctamente");';
                echo 'console.log("CSS URL: ' . esc_js( $css_url ) . '");';
                echo '</script>';
            }, 100 );
        }
    }
    
                /**
     * Detecta si hay shortcodes de WhatsApp en la página - VERSIÓN MEJORADA
     */
    private function detect_whatsapp_shortcodes() {
        global $post;
        $has_whatsapp = false;
        
        // Método 0: Verificar si ya se forzó la carga
        if ( defined( 'FORCE_WHATSAPP_LOAD' ) && FORCE_WHATSAPP_LOAD ) {
            return true;
        }
        
        // Método 1: Verificar en el contenido principal usando búsqueda directa
        if ( is_a( $post, 'WP_Post' ) ) {
            $content = $post->post_content;
            
            // Buscar shortcodes directamente en el texto
            $shortcodes = [ 'whatsapp_popup_invisible', 'whatsapp_popup_trigger', 'whatsapp_float_button' ];
            foreach ( $shortcodes as $shortcode ) {
                if ( strpos( $content, '[' . $shortcode ) !== false ) {
                    $has_whatsapp = true;
                    break;
                }
            }
            
            // También verificar usando has_shortcode()
            if ( ! $has_whatsapp ) {
                foreach ( $shortcodes as $shortcode ) {
                    if ( has_shortcode( $content, $shortcode ) ) {
                        $has_whatsapp = true;
                        break;
                    }
                }
            }
        }
        
        // Método 2: Verificar en widgets de texto
        if ( ! $has_whatsapp ) {
            $widget_texts = get_option( 'widget_text', [] );
            foreach ( $widget_texts as $widget ) {
                if ( isset( $widget['text'] ) ) {
                    $widget_content = $widget['text'];
                    $shortcodes = [ 'whatsapp_popup_invisible', 'whatsapp_popup_trigger', 'whatsapp_float_button' ];
                    foreach ( $shortcodes as $shortcode ) {
                        if ( strpos( $widget_content, '[' . $shortcode ) !== false || 
                             has_shortcode( $widget_content, $shortcode ) ) {
                            $has_whatsapp = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Método 3: Verificar en Elementor (si está activo)
        if ( ! $has_whatsapp && defined( 'ELEMENTOR_VERSION' ) ) {
            $elementor_data = get_post_meta( get_the_ID(), '_elementor_data', true );
            if ( $elementor_data && is_string( $elementor_data ) ) {
                $shortcodes = [ 'whatsapp_popup_invisible', 'whatsapp_popup_trigger', 'whatsapp_float_button' ];
                foreach ( $shortcodes as $shortcode ) {
                    if ( strpos( $elementor_data, $shortcode ) !== false ) {
                        $has_whatsapp = true;
                        break;
                    }
                }
            }
        }
        
        // Método 4: Forzar en páginas específicas
        if ( ! $has_whatsapp ) {
            $force_pages = [ 
                'contacto', 'contact', 'contato', 'whatsapp', 'chat', 
                'contact-us', 'contacto', 'contáctenos', 'contactenos',
                'soporte', 'support', 'ayuda', 'help', 'servicio', 'service'
            ];
            
            $current_slug = '';
            $current_title = '';
            
            if ( is_a( $post, 'WP_Post' ) ) {
                $current_slug = $post->post_name;
                $current_title = strtolower( $post->post_title );
            }
            
            foreach ( $force_pages as $page ) {
                if ( strpos( $current_slug, $page ) !== false || 
                     strpos( $current_title, $page ) !== false ||
                     ( is_page() && ( strpos( get_permalink(), $page ) !== false ) ) ) {
                    $has_whatsapp = true;
                    break;
                }
            }
        }
        
        // Método 5: Forzar en ciertos tipos de contenido
        if ( ! $has_whatsapp ) {
            // Forzar en todas las páginas si está en modo testing
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $has_whatsapp = true;
            }
            // Forzar en páginas de contacto/soporte
            elseif ( is_page( [ 'contacto', 'contact', 'contato', 'whatsapp', 'chat' ] ) ) {
                $has_whatsapp = true;
            }
        }
        
        return $has_whatsapp;
    }
    
                /**
     * Forzar carga de estilos cuando se usa cualquier shortcode
     */
    private function force_load_styles() {
        // Cargar CSS de WhatsApp si no está cargado
        if ( ! wp_style_is( 'wp-whatsapp', 'enqueued' ) ) {
            wp_enqueue_style(
                'wp-whatsapp',
                wp_CUSTOM_URL . 'assets/css/whatsapp-popup.css',
                [],
                wp_CUSTOM_VERSION
            );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'wp WhatsApp: Estilos forzados desde shortcode' );
            }
        }
        
        // Cargar Font Awesome si no está cargado
        if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                [],
                '6.4.0'
            );
        }
    }
    
    /**
     * Carga Font Awesome solo cuando es necesario
     */
    public function conditionally_load_font_awesome() {
        $has_whatsapp = $this->detect_whatsapp_shortcodes();
        
        // Forzar carga en páginas de contacto
        if ( ! $has_whatsapp ) {
            $contact_pages = [ 'contacto', 'contact', 'contato', 'whatsapp', 'chat' ];
            $current_slug = '';
            
            global $post;
            if ( is_a( $post, 'WP_Post' ) ) {
                $current_slug = $post->post_name;
            }
            
            if ( in_array( $current_slug, $contact_pages ) ) {
                $has_whatsapp = true;
            }
        }
        
        if ( $has_whatsapp ) {
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                [],
                '6.4.0'
            );
        }
    }
    
    /**
     * Detecta el idioma desde la URL
     */
    private function detect_language_from_url() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if ( strpos( $uri, '/pt-br/' ) !== false || strpos( $uri, '/pt/' ) !== false ) {
            return 'pt';
        } elseif ( strpos( $uri, '/en/' ) !== false ) {
            return 'en';
        }
        
        return 'es';
    }
    
        /**
     * Shortcode del popup invisible de WhatsApp - VERSIÓN CORREGIDA
     */
    public function shortcode_popup_invisible( $atts ) {
        // Forzar carga de estilos cuando se usa este shortcode
        $this->force_load_styles();
        // Detectar idioma
        $language = $this->detect_language_from_url();
        
        // Textos según idioma
        $texts = [
            'es' => [
                'titulo'    => 'WhatsApp Chat',
                'subtitulo' => 'Complete sus datos para iniciar la conversación por WhatsApp',
            ],
            'en' => [
                'titulo'    => 'WhatsApp Chat',
                'subtitulo' => 'Fill in your details to start the conversation on WhatsApp',
            ],
            'pt' => [
                'titulo'    => 'Chat do WhatsApp',
                'subtitulo' => 'Preencha seus dados para iniciar a conversa pelo WhatsApp',
            ],
        ];
        
        $t = $texts[ $language ] ?? $texts['es'];
        
        // Atributos del shortcode
        $atts = shortcode_atts( [
            'delay'      => '', // Milisegundos para auto-cerrar
            'auto_open'  => '', // Milisegundos para auto-abrir (0 = no auto)
            'form_id'    => '', // Override del form ID
        ], $atts );
        
        // Form ID (por defecto según país)
        if ( ! empty( $atts['form_id'] ) ) {
            $form_id = $atts['form_id'];
        } else {
            $form_id = get_hubspot_form_by_country();
        }
        
        // Convertir delays a enteros
        $close_delay = intval( $atts['delay'] );
        $auto_open_delay = intval( $atts['auto_open'] );
        
        ob_start();
        ?>
          <!-- Popup de WhatsApp - VERSIÓN CORREGIDA -->
  <div class="wp-whatsapp-popup-overlay" id="wp-whatsapp-popup" style="display: none;">
            <div class="wp-whatsapp-popup">
                <div class="wp-popup-header">
                    <button class="wp-popup-close" onclick="wpWhatsApp.closePopup()" aria-label="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>
                    <h2>
                        <i class="fab fa-whatsapp"></i>
                        <?php echo esc_html( $t['titulo'] ); ?>
                    </h2>
                    <p><?php echo esc_html( $t['subtitulo'] ); ?></p>
                </div>
                <div class="wp-popup-body">
                    <div id="wp-hubspot-form-container"></div>
                </div>
            </div>
        </div>
        
        <script>
        // Namespace para funciones de WhatsApp 
        window.wpWhatsApp = window.wpWhatsApp || {};
        
        window.wpWhatsApp.config = {
            closeDelay: <?php echo $close_delay ?: 0; ?>,
            autoOpenDelay: <?php echo $auto_open_delay ?: 0; ?>,
            formId: '<?php echo esc_js( $form_id ); ?>',
            portalId: '', // Add your HubSpot portal ID
            region: '', // Change to your region if needed
            isOpen: false
        };
        
        window.wpWhatsApp.popupElement = null;
        window.wpWhatsApp.closeTimer = null;
        window.wpWhatsApp.openTimer = null;
        
        // Función para abrir el popup - CORREGIDA
        window.wpWhatsApp.openPopup = function() {
            if (window.wpWhatsApp.isOpen) return;
            
            var popup = document.getElementById('wp-whatsapp-popup');
            if (!popup) {
                console.error('Popup element not found');
                return;
            }
            
            // Mostrar popup
            popup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            window.wpWhatsApp.isOpen = true;
            window.wpWhatsApp.popupElement = popup;
            
            // Forzar reflow para animación
            popup.offsetHeight;
            
            // Cargar formulario HubSpot
            window.wpWhatsApp.loadHubspotForm();
            
            // Configurar auto-cierre si está habilitado
            if (window.wpWhatsApp.config.closeDelay > 0) {
                window.wpWhatsApp.closeTimer = setTimeout(
                    window.wpWhatsApp.closePopup,
                    window.wpWhatsApp.config.closeDelay
                );
            }
            
            // Disparar evento
            var event = new CustomEvent('wp:whatsapp-popup-opened');
            document.dispatchEvent(event);
            
            // Enfocar el botón de cerrar para accesibilidad
            setTimeout(function() {
                var closeBtn = popup.querySelector('.wp-popup-close');
                if (closeBtn) closeBtn.focus();
            }, 100);
        };
        
        // Función para cerrar el popup - CORREGIDA
        window.wpWhatsApp.closePopup = function() {
            if (!window.wpWhatsApp.isOpen) return;
            
            var popup = window.wpWhatsApp.popupElement;
            if (!popup) return;
            
            // Ocultar popup
            popup.style.display = 'none';
            document.body.style.overflow = '';
            window.wpWhatsApp.isOpen = false;
            
            // Limpiar timers
            if (window.wpWhatsApp.closeTimer) {
                clearTimeout(window.wpWhatsApp.closeTimer);
                window.wpWhatsApp.closeTimer = null;
            }
            
            if (window.wpWhatsApp.openTimer) {
                clearTimeout(window.wpWhatsApp.openTimer);
                window.wpWhatsApp.openTimer = null;
            }
            
            // Disparar evento
            var event = new CustomEvent('wp:whatsapp-popup-closed');
            document.dispatchEvent(event);
        };
        
        // Función para cargar el formulario HubSpot 
        window.wpWhatsApp.loadHubspotForm = function() {
            // Permitir cargar HubSpot
            document.dispatchEvent(new Event('hs:allow'));
            
            // Esperar a que HubSpot esté disponible
            function initForm() {
                var container = document.getElementById('wp-hubspot-form-container');
                if (!container) {
                    setTimeout(initForm, 100);
                    return;
                }
                
                // Verificar si ya está cargado
                if (container.querySelector('form')) {
                    return;
                }
                
                // Limpiar contenedor
                container.innerHTML = '';
                
                // Crear formulario
                if (window.hbspt && window.hbspt.forms) {
                    try {
                        window.hbspt.forms.create({
                            region: window.wpWhatsApp.config.region,
                            portalId: window.wpWhatsApp.config.portalId,
                            formId: window.wpWhatsApp.config.formId,
                            target: '#wp-hubspot-form-container',
                            onFormReady: function() {
                                // Sincronizar país desde el input telefónico
                                if (window.syncCountryFromTelInput) {
                                    setTimeout(window.syncCountryFromTelInput, 300);
                                }
                                
                                // Disparar evento
                                var event = new CustomEvent('wp:hubspot-form-ready');
                                document.dispatchEvent(event);
                                
                                // Añadir clase a los botones de submit
                                var submitBtn = container.querySelector('input[type="submit"], button[type="submit"]');
                                if (submitBtn) {
                                    submitBtn.classList.add('wp-popup-submit-btn');
                                }
                            }
                        });
                    } catch (error) {
                        console.error('Error creating HubSpot form:', error);
                    }
                } else {
                    // HubSpot no cargado aún, reintentar
                    setTimeout(initForm, 500);
                }
            }
            
            // Iniciar carga
            setTimeout(initForm, 100);
        };
        
        // Auto-abrir solo si está configurado explícitamente (valor > 0)
        if (window.wpWhatsApp.config.autoOpenDelay > 0) {
            window.wpWhatsApp.openTimer = setTimeout(function() {
                window.wpWhatsApp.openPopup();
            }, window.wpWhatsApp.config.autoOpenDelay);
        }
        
        // Inicializar eventos - CORREGIDO
        document.addEventListener('DOMContentLoaded', function() {
            var popup = document.getElementById('wp-whatsapp-popup');
            if (popup) {
                // Cerrar al hacer clic fuera del popup
                popup.addEventListener('click', function(e) {
                    if (e.target === popup) {
                        window.wpWhatsApp.closePopup();
                    }
                });
                
                // También cerrar con Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && window.wpWhatsApp.isOpen) {
                        window.wpWhatsApp.closePopup();
                    }
                });
                
                // Prevenir que el clic dentro del popup cierre el overlay
                var popupContent = popup.querySelector('.wp-whatsapp-popup');
                if (popupContent) {
                    popupContent.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }
            }
            
            // Exponer función globalmente
            window.showInvisiblePopup = window.wpWhatsApp.openPopup;
            window.closeInvisiblePopup = window.wpWhatsApp.closePopup;
        });
        
        </script>
        <?php
        
        return ob_get_clean();
    }
    
        /**
     * Shortcode del botón trigger - VERSIÓN CORREGIDA
     */
    public function shortcode_popup_trigger( $atts ) {
        // Forzar carga de estilos cuando se usa este shortcode
        $this->force_load_styles();
        $atts = shortcode_atts( [
            'text'      => 'WhatsApp',
            'icon'      => 'true',
            'class'     => '',
            'style'     => '',
            'layout'    => 'vertical', // vertical, horizontal, icon-only
        ], $atts );
        
        // Sanitizar valores
        $text = sanitize_text_field( $atts['text'] );
        $show_icon = filter_var( $atts['icon'], FILTER_VALIDATE_BOOLEAN );
        $layout = sanitize_text_field( $atts['layout'] );
        $classes = 'wp-whatsapp-trigger ' . sanitize_html_class( $atts['class'] );
        $style = ! empty( $atts['style'] ) ? ' style="' . esc_attr( $atts['style'] ) . '"' : '';
        
        // Añadir clase de layout
        if ( $layout === 'horizontal' ) {
            $classes .= ' horizontal';
        } elseif ( $layout === 'icon-only' ) {
            $classes .= ' icon-only';
        }
        
        // Construir contenido
        $content = '';
        
        if ( $show_icon ) {
            $content .= '<span class="wp-whatsapp-icon">';
            $content .= '<i class="fab fa-whatsapp"></i>';
            $content .= '</span>';
        }
        
        if ( $layout !== 'icon-only' ) {
            $content .= '<span class="wp-whatsapp-text">' . esc_html( $text ) . '</span>';
        }
        
        // Asegurar que tenga un título accesible si es solo icono
        $title = $layout === 'icon-only' ? ' title="' . esc_attr( $text ) . '"' : '';
        
        return sprintf(
            '<button type="button" class="%s" onclick="if(window.wpWhatsApp) window.wpWhatsApp.openPopup(); else if(window.showInvisiblePopup) window.showInvisiblePopup();" %s %s aria-label="%s">%s</button>',
            esc_attr( $classes ),
            $title,
            $style,
            esc_attr( $text ),
            $content
        );
    }
    
        /**
     * Shortcode para botón flotante
     */
    public function shortcode_float_button( $atts ) {
        // Forzar carga de estilos cuando se usa este shortcode
        $this->force_load_styles();
        $atts = shortcode_atts( [
            'position' => 'right', // left, right
            'bottom'   => '30px',
            'pulse'    => 'false',
        ], $atts );
        
        $position = sanitize_text_field( $atts['position'] );
        $bottom = sanitize_text_field( $atts['bottom'] );
        $pulse = filter_var( $atts['pulse'], FILTER_VALIDATE_BOOLEAN );
        
        $classes = 'wp-whatsapp-float';
        if ( $pulse ) {
            $classes .= ' pulse';
        }
        
        $style = sprintf(
            '%s: 30px; bottom: %s;',
            $position === 'left' ? 'left' : 'right',
            $bottom
        );
        
        return sprintf(
            '<div class="%s" style="%s" onclick="if(window.wpWhatsApp) window.wpWhatsApp.openPopup(); else if(window.showInvisiblePopup) window.showInvisiblePopup();" aria-label="Chat de WhatsApp" role="button" tabindex="0">
                <i class="fab fa-whatsapp"></i>
            </div>',
            esc_attr( $classes ),
            esc_attr( $style )
        );
    }
}

// Inicializar el módulo
add_action( 'init', function() {
    wp_WhatsApp_Integration::get_instance();
} );
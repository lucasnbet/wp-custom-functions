<?php
/**
 * HubSpot Integration Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_HubSpot_Integration {
    
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
        // Shortcode principal
        add_shortcode( 'hubspot_form_by_country', [ $this, 'shortcode_hubspot_form' ] );
        
        // Cargar script de HubSpot de forma lazy
        add_action( 'wp_enqueue_scripts', [ $this, 'register_hubspot_script' ], 1 );
        
        // Permitir que otros scripts habiliten HubSpot
        add_action( 'wp_footer', [ $this, 'hubspot_loader_script' ], 5 );
    }
    
    /**
     * Registra el script de HubSpot
     */
    public function register_hubspot_script() {
        wp_register_script( 
            'hubspot-forms', 
            'https://js.hsforms.net/forms/embed/v2.js', 
            [], 
            null, 
            true 
        );
        
        // No encolar todavía, se cargará condicionalmente
    }
    
    /**
     * Shortcode para formulario HubSpot por país
     */
    public function shortcode_hubspot_form( $atts ) {
        $atts = shortcode_atts( [
            'form_id'   => '', // Override manual
            'portal_id' => '22204650',
            'region'    => 'na1',
            'class'     => '',
            'style'     => '',
        ], $atts );
        
        // Obtener país
        $country_code = detect_country_for_hubspot();
        
        // Obtener form ID
        if ( ! empty( $atts['form_id'] ) ) {
            $form_id = $atts['form_id'];
        } else {
            $form_id = get_hubspot_form_by_country( $country_code );
        }
        
        // Generar ID único para el contenedor
        $container_id = 'hs-form-' . uniqid();
        
        // Clases y estilos
        $classes = 'hubspot-form-container ' . sanitize_html_class( $atts['class'] );
        $style = ! empty( $atts['style'] ) ? ' style="' . esc_attr( $atts['style'] ) . '"' : '';
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr( $container_id ); ?>" 
             class="<?php echo esc_attr( $classes ); ?>" 
             data-country="<?php echo esc_attr( $country_code ); ?>"
             data-form-id="<?php echo esc_attr( $form_id ); ?>"
             <?php echo $style; ?>>
        </div>
        
        <script>
        (function() {
            var container = document.getElementById('<?php echo esc_js( $container_id ); ?>');
            if (!container) return;
            
            var config = {
                region: '<?php echo esc_js( $atts['region'] ); ?>',
                portalId: '<?php echo esc_js( $atts['portal_id'] ); ?>',
                formId: '<?php echo esc_js( $form_id ); ?>',
                target: '#<?php echo esc_js( $container_id ); ?>'
            };
            
            // Función para inicializar el formulario
            function initHubspotForm() {
                if (window.hbspt && window.hbspt.forms) {
                    window.hbspt.forms.create(config);
                    
                    // Disparar evento cuando el formulario esté listo
                    setTimeout(function() {
                        var event = new CustomEvent('cirion:hubspot-form-loaded', {
                            detail: { 
                                containerId: '<?php echo esc_js( $container_id ); ?>',
                                country: '<?php echo esc_js( $country_code ); ?>',
                                formId: '<?php echo esc_js( $form_id ); ?>'
                            }
                        });
                        document.dispatchEvent(event);
                    }, 500);
                }
            }
            
            // Función para cargar el script de HubSpot si es necesario
            function loadHubspotScript(callback) {
                if (window.hbspt && window.hbspt.forms) {
                    callback();
                    return;
                }
                
                var script = document.getElementById('hubspot-forms-script');
                if (!script) {
                    script = document.createElement('script');
                    script.id = 'hubspot-forms-script';
                    script.src = 'https://js.hsforms.net/forms/embed/v2.js';
                    script.async = true;
                    script.defer = true;
                    script.onload = callback;
                    document.head.appendChild(script);
                } else if (script.getAttribute('data-loaded') === 'true') {
                    callback();
                } else {
                    script.addEventListener('load', callback, { once: true });
                }
            }
            
            // Cargar cuando sea necesario (lazy load)
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries, obs) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            obs.unobserve(entry.target);
                            loadHubspotScript(initHubspotForm);
                        }
                    });
                }, { 
                    rootMargin: '100px',
                    threshold: 0.1 
                });
                
                observer.observe(container);
            } else {
                // Fallback para navegadores viejos
                loadHubspotScript(initHubspotForm);
            }
            
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Script loader para habilitar HubSpot
     */
    public function hubspot_loader_script() {
        ?>
        <script>
        // Sistema de permisos para cargar HubSpot
        window.__hsFormsAllowed = window.__hsFormsAllowed || false;
        
        // Evento para permitir la carga de HubSpot
        document.addEventListener('hs:allow', function() {
            window.__hsFormsAllowed = true;
            
            // Cargar script si no está cargado
            if (!document.getElementById('hubspot-forms-script')) {
                var script = document.createElement('script');
                script.id = 'hubspot-forms-script';
                script.src = 'https://js.hsforms.net/forms/embed/v2.js';
                script.async = true;
                script.defer = true;
                script.setAttribute('data-loaded', 'true');
                document.head.appendChild(script);
            }
        });
        
        // Evento para actualizar formularios cuando cambia el país
        document.addEventListener('cirion:country-changed', function(e) {
            var detail = e.detail;
            
            // Encontrar todos los formularios HubSpot en la página
            var forms = document.querySelectorAll('.hubspot-form-container');
            forms.forEach(function(form) {
                var currentFormId = form.getAttribute('data-form-id');
                var newFormId = window.getHubspotFormId(detail.iso2);
                
                // Si el form ID cambia, recargar el formulario
                if (currentFormId !== newFormId && window.hbspt && window.hbspt.forms) {
                    var containerId = form.id;
                    form.innerHTML = '';
                    
                    window.hbspt.forms.create({
                        region: 'na1',
                        portalId: '22204650',
                        formId: newFormId,
                        target: '#' + containerId
                    });
                    
                    // Actualizar atributo
                    form.setAttribute('data-form-id', newFormId);
                    form.setAttribute('data-country', detail.iso2);
                }
            });
        });
        </script>
        <?php
    }
}

// Inicializar el módulo
add_action( 'init', function() {
    Cirion_HubSpot_Integration::get_instance();
} );
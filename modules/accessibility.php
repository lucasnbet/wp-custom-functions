<?php
/**
 * Accessibility Module
 * 
 * @package CirionCustom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirion_Accessibility {
    
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
        // Cargar script de accesibilidad
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_accessibility_script' ] );
        
        // Añadir estilos de accesibilidad
        add_action( 'wp_head', [ $this, 'add_accessibility_styles' ] );
        
        // Corregir roles ARIA en menús
        add_filter( 'nav_menu_link_attributes', [ $this, 'fix_menu_aria_roles' ], 20, 3 );
        
        // Scripts de corrección ARIA
        add_action( 'wp_footer', [ $this, 'add_aria_fix_scripts' ], 9999 );
        
        // Retrasar carga de traducciones problemáticas
        add_action( 'plugins_loaded', [ $this, 'delay_plugin_translations' ], 1 );
    }
    
    /**
     * Carga el script de accesibilidad
     */
    public function enqueue_accessibility_script() {
        $script_path = CIRION_CUSTOM_PATH . 'assets/js/accessibility.js';
        
        if ( file_exists( $script_path ) ) {
            wp_enqueue_script(
                'cirion-accessibility',
                CIRION_CUSTOM_URL . 'assets/js/accessibility.js',
                [ 'jquery' ],
                filemtime( $script_path ),
                true
            );
        }
    }
    
    /**
     * Añade estilos CSS para accesibilidad
     */
    public function add_accessibility_styles() {
        ?>
        <style>
            /* Mejoras de foco */
            :focus {
                outline: 2px solid #001689 !important;
                outline-offset: 2px !important;
            }
            
            [aria-expanded="true"]:focus,
            [role="button"]:focus,
            [role="menuitem"]:focus {
                outline: 3px solid #001689 !important;
                outline-offset: 3px !important;
            }
            
            /* Indicadores de estado ARIA */
            [aria-expanded="true"] {
                position: relative;
            }
            
            [aria-expanded="true"]::after {
                content: " (expanded)";
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
            
            /* Contraste mejorado */
            .cirion-sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
                border: 0;
            }
            
            /* Mejoras para lectores de pantalla */
            [role="alert"],
            [role="status"] {
                border-left: 4px solid #001689;
                padding-left: 1rem;
                margin: 1rem 0;
            }
            
            /* Botones accesibles */
            button:not([disabled]):hover,
            button:not([disabled]):focus {
                transform: translateY(-2px);
                transition: transform 0.2s ease;
            }
            
            /* Links con subrayado claro */
            a:not(.btn):not(.button):not([class*="button"]) {
                text-decoration: underline;
                text-decoration-thickness: 2px;
                text-underline-offset: 3px;
            }
            
            a:not(.btn):not(.button):not([class*="button"]):hover,
            a:not(.btn):not(.button):not([class*="button"]):focus {
                text-decoration-thickness: 3px;
            }
        </style>
        <?php
    }
    
    /**
     * Corrige los roles ARIA en menús WPML
     */
    public function fix_menu_aria_roles( $atts, $item, $args ) {
        // Si ya tiene un role distinto de 'link', no modificar
        if ( isset( $atts['role'] ) && $atts['role'] !== 'link' ) {
            return $atts;
        }
        
        // Detectar si es un item de WPML
        $is_wpml_item = false;
        
        // Por clases
        if ( ! empty( $item->classes ) && is_array( $item->classes ) ) {
            foreach ( $item->classes as $class ) {
                if ( strpos( $class, 'wpml-ls' ) !== false ) {
                    $is_wpml_item = true;
                    break;
                }
            }
        }
        
        // Por ID de menú específico
        if ( ! $is_wpml_item && ! empty( $args->menu_id ) ) {
            $wpml_menu_ids = [ 'menu-1-79c6b4f', 'menu-1', 'wpml-menu' ];
            if ( in_array( $args->menu_id, $wpml_menu_ids, true ) ) {
                $is_wpml_item = true;
            }
        }
        
        // Si es item de WPML, corregir roles
        if ( $is_wpml_item ) {
            $atts['role'] = 'menuitem';
            
            // Asegurar tabindex para navegabilidad
            if ( ! isset( $atts['tabindex'] ) ) {
                $atts['tabindex'] = '0';
            }
            
            // Añadir aria-haspopup si tiene submenú
            if ( in_array( 'menu-item-has-children', $item->classes, true ) ) {
                $atts['aria-haspopup'] = 'true';
                $atts['aria-expanded'] = 'false';
            }
        }
        
        return $atts;
    }
    
    /**
     * Añade scripts para corregir problemas ARIA
     */
    public function add_aria_fix_scripts() {
        ?>
        <script>
        (function() {
            'use strict';
            
            // Función para limpiar roles ARIA incorrectos
            function cleanAriaRoles() {
                // Elementos que no deberían tener role="list" o role="listitem"
                document.querySelectorAll('[role="list"], [role="listitem"], [role="presentation"]').forEach(function(el) {
                    // Excluir menús WPML/SmartMenus
                    if (el.closest('.elementor-nav-menu')) {
                        return;
                    }
                    
                    // Solo limpiar si no es una lista real
                    var isRealList = (el.tagName === 'UL' || el.tagName === 'OL');
                    if (!isRealList) {
                        el.removeAttribute('role');
                    }
                });
                
                // Restaurar niveles ARIA en headings
                document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(function(heading) {
                    var level = heading.tagName.replace('H', '');
                    heading.setAttribute('aria-level', level);
                });
                
                // Configurar carruseles correctamente
                document.querySelectorAll('.elementor-loop-container, .swiper-container').forEach(function(container) {
                    container.setAttribute('role', 'region');
                    container.setAttribute('aria-roledescription', 'carousel');
                    container.setAttribute('aria-label', 'Carrusel de contenido');
                });
                
                // Configurar slides
                document.querySelectorAll('.swiper-slide, .elementor-slide').forEach(function(slide, index) {
                    slide.setAttribute('role', 'group');
                    slide.setAttribute('aria-label', 'Diapositiva ' + (index + 1));
                    slide.setAttribute('aria-roledescription', 'slide');
                });
                
                // Imágenes decorativas sin alt
                document.querySelectorAll('img:not([alt])').forEach(function(img) {
                    img.setAttribute('alt', '');
                    img.setAttribute('aria-hidden', 'true');
                });
            }
            
            // Corregir menús SmartMenus + WPML
            function fixSmartMenusRoles() {
                var menus = document.querySelectorAll('ul.elementor-nav-menu[role="menubar"]');
                
                menus.forEach(function(menu) {
                    // Todos los <li> deben ser role="none"
                    menu.querySelectorAll('li').forEach(function(li) {
                        li.setAttribute('role', 'none');
                    });
                    
                    // Todos los <a> deben ser role="menuitem"
                    menu.querySelectorAll('a').forEach(function(link) {
                        link.setAttribute('role', 'menuitem');
                        link.setAttribute('tabindex', '0');
                        
                        // Si tiene submenú
                        var parentLi = link.closest('li');
                        if (parentLi && parentLi.querySelector('.sub-menu')) {
                            link.setAttribute('aria-haspopup', 'true');
                            link.setAttribute('aria-expanded', 'false');
                        }
                    });
                });
            }
            
            // Ejecutar en DOMContentLoaded
            document.addEventListener('DOMContentLoaded', function() {
                cleanAriaRoles();
                fixSmartMenusRoles();
                
                // Ejecutar después de un delay para contenido dinámico
                setTimeout(function() {
                    cleanAriaRoles();
                    fixSmartMenusRoles();
                }, 500);
                
                setTimeout(function() {
                    cleanAriaRoles();
                    fixSmartMenusRoles();
                }, 2000);
            });
            
            // Observer para contenido dinámico
            if ('MutationObserver' in window) {
                var observer = new MutationObserver(function() {
                    setTimeout(function() {
                        cleanAriaRoles();
                        fixSmartMenusRoles();
                    }, 300);
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            
        })();
        </script>
        <?php
    }
    
    /**
     * Retrasa carga de traducciones de plugins problemáticos
     */
    public function delay_plugin_translations() {
        // Add Search To Menu plugin
        if ( class_exists( 'Add_Search_To_Menu' ) ) {
            remove_action( 'plugins_loaded', [ 'Add_Search_To_Menu', 'load_textdomain' ] );
            add_action( 'init', [ 'Add_Search_To_Menu', 'load_textdomain' ], 10 );
        }
        
        // Otros plugins problemáticos pueden añadirse aquí
    }
}

// Inicializar el módulo
add_action( 'init', function() {
    Cirion_Accessibility::get_instance();
} );
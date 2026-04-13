/**
 * Accessibility Script
 * Mejoras de accesibilidad para WordPress
 */

(function() {
    'use strict';
    
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. Mejorar navegación por teclado
        enhanceKeyboardNavigation();
        
        // 2. Mejorar formularios
        enhanceForms();
        
        // 3. Mejorar imágenes
        enhanceImages();
        
        // 4. Mejorar videos
        enhanceVideos();
        
        // 5. Añadir skip links si no existen
        addSkipLinks();
        
        // 6. Mejorar modales
        enhanceModals();
        
        // 7. Añadir live regions
        addLiveRegions();
        
    });
    
    /**
     * Mejora la navegación por teclado
     */
    function enhanceKeyboardNavigation() {
        // Asegurar que todos los elementos interactivos sean focusables
        document.querySelectorAll('[role="button"], [role="menuitem"], [role="tab"]').forEach(function(el) {
            if (!el.hasAttribute('tabindex')) {
                el.setAttribute('tabindex', '0');
            }
        });
        
        // Añadir soporte para Enter/Space en elementos con role="button"
        document.querySelectorAll('[role="button"]').forEach(function(el) {
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (e.key === ' ') {
                        e.preventDefault(); // Prevenir scroll con space
                    }
                    this.click();
                }
            });
        });
        
        // Mejorar foco en modales
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                handleModalFocus(e);
            }
        });
    }
    
    /**
     * Maneja el foco en modales
     */
    function handleModalFocus(e) {
        var modal = document.querySelector('[role="dialog"][aria-modal="true"]');
        if (!modal) return;
        
        var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var firstFocusable = focusable[0];
        var lastFocusable = focusable[focusable.length - 1];
        
        if (e.shiftKey) {
            if (document.activeElement === firstFocusable) {
                e.preventDefault();
                lastFocusable.focus();
            }
        } else {
            if (document.activeElement === lastFocusable) {
                e.preventDefault();
                firstFocusable.focus();
            }
        }
    }
    
    /**
     * Mejora la accesibilidad de formularios
     */
    function enhanceForms() {
        // Añadir labels a inputs que no los tengan
        document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"])').forEach(function(input) {
            if (!input.id) {
                var id = 'input-' + Math.random().toString(36).substr(2, 9);
                input.id = id;
            }
            
            if (!input.hasAttribute('aria-label') && !document.querySelector('label[for="' + input.id + '"]')) {
                var placeholder = input.getAttribute('placeholder');
                if (placeholder) {
                    input.setAttribute('aria-label', placeholder);
                }
            }
        });
        
        // Añadir required states
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(function(el) {
            var label = document.querySelector('label[for="' + el.id + '"]');
            if (label) {
                var requiredText = document.createElement('span');
                requiredText.className = 'required-asterisk';
                requiredText.innerHTML = ' *';
                requiredText.setAttribute('aria-hidden', 'true');
                label.appendChild(requiredText);
                
                var srText = document.createElement('span');
                srText.className = 'screen-reader-text';
                srText.textContent = ' (required)';
                label.appendChild(srText);
            }
        });
        
        // Mejorar validación
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('invalid', function(e) {
                e.preventDefault();
                showValidationError(e.target);
            }, true);
        });
    }
    
    /**
     * Muestra errores de validación
     */
    function showValidationError(input) {
        var errorId = 'error-' + input.id;
        var errorMessage = input.validationMessage || 'This field is required';
        
        // Remover error anterior
        var existingError = document.getElementById(errorId);
        if (existingError) {
            existingError.remove();
        }
        
        // Crear elemento de error
        var errorEl = document.createElement('span');
        errorEl.id = errorId;
        errorEl.className = 'validation-error';
        errorEl.textContent = errorMessage;
        errorEl.setAttribute('role', 'alert');
        
        // Insertar después del input
        input.parentNode.insertBefore(errorEl, input.nextSibling);
        
        // Añadir aria-invalid
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', errorId);
        
        // Enfocar el input
        input.focus();
    }
    
    /**
     * Mejora la accesibilidad de imágenes
     */
    function enhanceImages() {
        // Detectar imágenes decorativas
        document.querySelectorAll('img:not([alt])').forEach(function(img) {
            // Verificar si es decorativa por contexto
            var parent = img.parentElement;
            var isDecorative = false;
            
            // Si está dentro de un link con texto
            if (parent.tagName === 'A' && parent.textContent.trim()) {
                isDecorative = true;
            }
            
            // Si tiene clases específicas
            if (img.className.match(/decorative|bg|background|icon/i)) {
                isDecorative = true;
            }
            
            if (isDecorative) {
                img.setAttribute('alt', '');
                img.setAttribute('aria-hidden', 'true');
            } else {
                // Intentar generar un alt basado en el contexto
                var alt = generateAltFromContext(img);
                img.setAttribute('alt', alt);
            }
        });
        
        // Añadir soporte para imágenes lazy load
        document.querySelectorAll('img[data-src], img[data-srcset]').forEach(function(img) {
            img.addEventListener('load', function() {
                this.setAttribute('aria-busy', 'false');
            });
            img.setAttribute('aria-busy', 'true');
        });
    }
    
    /**
     * Genera texto alt para imágenes basado en contexto
     */
    function generateAltFromContext(img) {
        // Intentar obtener del título cercano
        var prevHeading = img.previousElementSibling;
        while (prevHeading && !prevHeading.matches('h1, h2, h3, h4, h5, h6')) {
            prevHeading = prevHeading.previousElementSibling;
        }
        
        if (prevHeading) {
            return 'Illustration for: ' + prevHeading.textContent.trim();
        }
        
        // Intentar obtener del texto cercano
        var parentText = img.parentElement.textContent.trim();
        if (parentText && parentText.length < 100) {
            return 'Illustration: ' + parentText;
        }
        
        // Fallback genérico
        return 'Relevant image';
    }
    
    /**
     * Mejora la accesibilidad de videos
     */
    function enhanceVideos() {
        document.querySelectorAll('video').forEach(function(video) {
            // Añadir controles si no existen
            if (!video.hasAttribute('controls')) {
                video.setAttribute('controls', '');
            }
            
            // Añadir título si no existe
            if (!video.hasAttribute('title')) {
                var source = video.querySelector('source');
                if (source && source.getAttribute('title')) {
                    video.setAttribute('title', source.getAttribute('title'));
                } else {
                    video.setAttribute('title', 'Video content');
                }
            }
        });
    }
    
    /**
     * Añade skip links si no existen
     */
    function addSkipLinks() {
        var skipLinks = document.querySelector('.skip-link');
        if (!skipLinks) {
            var skipLinkHTML = '<a href="#main" class="skip-link screen-reader-text">Skip to main content</a>';
            document.body.insertAdjacentHTML('afterbegin', skipLinkHTML);
        }
    }
    
    /**
     * Mejora la accesibilidad de modales
     */
    function enhanceModals() {
        document.querySelectorAll('[role="dialog"]').forEach(function(modal) {
            // Asegurar que tenga label
            if (!modal.hasAttribute('aria-labelledby') && !modal.hasAttribute('aria-label')) {
                var heading = modal.querySelector('h1, h2, h3, h4, h5, h6');
                if (heading) {
                    if (!heading.id) {
                        heading.id = 'modal-heading-' + Math.random().toString(36).substr(2, 9);
                    }
                    modal.setAttribute('aria-labelledby', heading.id);
                } else {
                    modal.setAttribute('aria-label', 'Dialog window');
                }
            }
            
            // Asegurar que sea modal
            modal.setAttribute('aria-modal', 'true');
            
            // Capturar foco
            modal.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    var closeBtn = modal.querySelector('[data-dismiss="modal"], .close-modal');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }
            });
        });
    }
    
    /**
     * Añade live regions para actualizaciones dinámicas
     */
    function addLiveRegions() {
        // Buscar elementos que actúan como live regions
        var possibleLiveRegions = document.querySelectorAll('.alert, .notice, .message, [role="alert"], [role="status"]');
        
        possibleLiveRegions.forEach(function(region) {
            if (!region.hasAttribute('aria-live')) {
                // Determinar el tipo de live region
                var isAlert = region.className.match(/alert|error|warning/i) || region.getAttribute('role') === 'alert';
                region.setAttribute('aria-live', isAlert ? 'assertive' : 'polite');
                
                if (!region.hasAttribute('aria-atomic')) {
                    region.setAttribute('aria-atomic', 'true');
                }
            }
        });
    }
    
    /**
     * Utility: Añadir evento una sola vez
     */
    function addEventListenerOnce(element, event, handler) {
        var onceHandler = function() {
            handler.apply(this, arguments);
            element.removeEventListener(event, onceHandler);
        };
        element.addEventListener(event, onceHandler);
    }
    
        // Hacer funciones disponibles globalmente
    window.wpCustomAccessibility = {
        enhanceKeyboardNavigation: enhanceKeyboardNavigation,
        enhanceForms: enhanceForms,
        enhanceImages: enhanceImages,
        enhanceVideos: enhanceVideos,
        addSkipLinks: addSkipLinks,
        enhanceModals: enhanceModals,
        addLiveRegions: addLiveRegions
    };
    
})();

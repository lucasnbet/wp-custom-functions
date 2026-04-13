/**
 * UTM Manager - Script 100% independiente
 * Reemplaza completamente la funcionalidad del Tag Manager
 * Versión: 2.0
 */

(function() {
    'use strict';
    
    // ===== CONFIGURACIÓN =====
    var CONFIG = {
        // Dominio raíz para cookies
        ROOT_DOMAIN: '.example.com',
        
                // Nombres de almacenamiento
        COOKIE_NAME: 'wp_custom_utm_params_cookie',
        LOCAL_STORAGE_KEY: 'wp_custom_utm_params',
        
                // Dominios personalizados
        CUSTOM_DOMAINS: [
            'example.com',
            'www.example.com'
        ],
        
        // Parámetros UTM a capturar
        UTM_KEYS: ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'],
        TRACKING_KEYS: ['gclid', 'fbclid', 'msclkid'],
        
        // Tiempos de expiración (en milisegundos)
        EXPIRATION_DAYS: 10,
        EXPIRATION_MS: 10 * 24 * 60 * 60 * 1000,
        SAME_DAY_MS: 24 * 60 * 60 * 1000,
        
        // Debug
        DEBUG: true
    };
    
    // ===== UTILIDADES =====
    
    // Logging condicional
    function log() {
        if (CONFIG.DEBUG && console && console.log) {
            console.log.apply(console, ['[UTM Manager]'].concat(Array.prototype.slice.call(arguments)));
        }
    }
    
    function error() {
        if (console && console.error) {
            console.error.apply(console, ['[UTM Manager ERROR]'].concat(Array.prototype.slice.call(arguments)));
        }
    }
    
        // Verificar si ya está cargado
    if (window.wpCustomUTMManagerLoaded || window.wpCustomUTMPersistenceLoaded) {
        log('Script ya cargado, saliendo...');
        return;
    }
    window.wpCustomUTMManagerLoaded = true;
    window.wpCustomUTMPersistenceLoaded = true; // Para compatibilidad con scripts antiguos
    
    // ===== FUNCIONES CORE =====
    
    // Parsear parámetros de URL
    function parseUrlParams(url) {
        var params = {};
        try {
            var urlObj = new URL(url);
            urlObj.searchParams.forEach(function(value, key) {
                if (!params.hasOwnProperty(key)) {
                    params[key] = value;
                }
            });
        } catch (e) {
            // Fallback para URLs relativas o mal formadas
            var queryIndex = url.indexOf('?');
            if (queryIndex !== -1) {
                var queryString = url.substring(queryIndex + 1);
                var pairs = queryString.split('&');
                for (var i = 0; i < pairs.length; i++) {
                    var pair = pairs[i].split('=');
                    if (pair.length === 2) {
                        var key = decodeURIComponent(pair[0].replace(/\+/g, ' '));
                        var value = decodeURIComponent(pair[1].replace(/\+/g, ' '));
                        if (!params.hasOwnProperty(key)) {
                            params[key] = value;
                        }
                    }
                }
            }
        }
        return params;
    }
    
    // Construir URL con parámetros
    function buildUrlWithParams(baseUrl, params) {
        try {
            var url = new URL(baseUrl);
            Object.keys(params).forEach(function(key) {
                if (params[key]) {
                    url.searchParams.set(key, params[key]);
                }
            });
            return url.toString();
        } catch (e) {
            // Fallback
            var base = baseUrl.split('?')[0];
            var paramKeys = Object.keys(params);
            if (paramKeys.length === 0) return base;
            
            var url = base + '?';
            var first = true;
            for (var i = 0; i < paramKeys.length; i++) {
                var key = paramKeys[i];
                var value = params[key];
                if (value && value !== '') {
                    if (!first) url += '&';
                    url += encodeURIComponent(key) + '=' + encodeURIComponent(value);
                    first = false;
                }
            }
            return url;
        }
    }
    
    // Manejo de cookies
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        var cookieString = name + '=' + encodeURIComponent(value) + expires + '; path=/; domain=' + CONFIG.ROOT_DOMAIN + '; SameSite=Lax';
        if (window.location.protocol === 'https:') {
            cookieString += '; Secure';
        }
        document.cookie = cookieString;
        log('Cookie establecida:', name);
    }
    
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }
    
        // Verificar si es dominio personalizado
    function isCustomDomain(hostname) {
        if (!hostname) return false;
        var lowerHostname = hostname.toLowerCase();
        for (var i = 0; i < CONFIG.CUSTOM_DOMAINS.length; i++) {
            var domain = CONFIG.CUSTOM_DOMAINS[i].toLowerCase();
            if (lowerHostname === domain || lowerHostname.endsWith('.' + domain)) {
                return true;
            }
        }
        return false;
    }
    
    // ===== LÓGICA PRINCIPAL =====
    
    function initUTMManager() {
        log('Inicializando UTM Manager...');
        
        try {
            var currentUrl = window.location.href;
            var currentParams = parseUrlParams(currentUrl);
            
            // Capturar parámetros UTM actuales
            var currentUtms = {};
            var hasNewUtms = false;
            
            // Capturar UTMs
            CONFIG.UTM_KEYS.forEach(function(key) {
                if (currentParams[key]) {
                    currentUtms[key] = currentParams[key];
                    hasNewUtms = true;
                    log('UTM encontrado:', key, '=', currentParams[key]);
                }
            });
            
            // Capturar tracking IDs
            CONFIG.TRACKING_KEYS.forEach(function(key) {
                if (currentParams[key]) {
                    currentUtms[key] = currentParams[key];
                    hasNewUtms = true;
                    log('Tracking ID encontrado:', key, '=', currentParams[key]);
                }
            });
            
            // Manejar almacenamiento
            handleStorage(currentUtms, hasNewUtms);
            
            // Aplicar a enlaces y formularios
            setTimeout(function() {
                applyToLinks();
                applyToForms();
            }, 100); // Pequeño delay para asegurar DOM completo
            
        } catch (err) {
            error('Error en initUTMManager:', err);
        }
    }
    
    function handleStorage(currentUtms, hasNewUtms) {
        var storedData = null;
        var storedString = localStorage.getItem(CONFIG.LOCAL_STORAGE_KEY);
        var cookieString = getCookie(CONFIG.COOKIE_NAME);
        
        // Parsear datos almacenados
        if (storedString) {
            try {
                storedData = JSON.parse(storedString);
                // Verificar expiración
                if (storedData.timestamp && (Date.now() - storedData.timestamp > CONFIG.EXPIRATION_MS)) {
                    log('Datos expirados, limpiando...');
                    localStorage.removeItem(CONFIG.LOCAL_STORAGE_KEY);
                    storedData = null;
                }
            } catch (e) {
                error('Error parseando localStorage:', e);
                storedData = null;
            }
        }
        
        // Si hay nuevos UTMs, actualizar
        if (hasNewUtms && Object.keys(currentUtms).length > 0) {
            var utmData = {
                params: currentUtms,
                timestamp: Date.now(),
                referrer: document.referrer || 'direct',
                originalUrl: window.location.href,
                storedAt: window.location.hostname
            };
            
            var utmDataString = JSON.stringify(utmData);
            
            // Guardar en localStorage
            localStorage.setItem(CONFIG.LOCAL_STORAGE_KEY, utmDataString);
            log('UTMs guardados en localStorage:', currentUtms);
            
            // Guardar en cookie
            setCookie(CONFIG.COOKIE_NAME, utmDataString, CONFIG.EXPIRATION_DAYS);
            log('UTMs guardados en cookie');
            
        } else if (cookieString && !storedString) {
            // Sincronizar cookie -> localStorage
            try {
                var cookieData = JSON.parse(cookieString);
                if (cookieData.timestamp && (Date.now() - cookieData.timestamp <= CONFIG.EXPIRATION_MS)) {
                    localStorage.setItem(CONFIG.LOCAL_STORAGE_KEY, cookieString);
                    log('UTMs sincronizados desde cookie');
                }
            } catch (e) {
                error('Error sincronizando cookie:', e);
            }
        }
    }
    
    function applyToLinks() {
        try {
            var storedString = localStorage.getItem(CONFIG.LOCAL_STORAGE_KEY);
            if (!storedString) {
                log('No hay UTMs almacenados para enlaces');
                return;
            }
            
            var utmData = JSON.parse(storedString);
            if (!utmData.params || Object.keys(utmData.params).length === 0) {
                return;
            }
            
            // Filtrar parámetros según tiempo
            var timeDiff = Date.now() - utmData.timestamp;
            var isSameDay = timeDiff < CONFIG.SAME_DAY_MS;
            var paramsToUse = {};
            
            Object.keys(utmData.params).forEach(function(key) {
                var value = utmData.params[key];
                if (value) {
                    if (isSameDay) {
                        paramsToUse[key] = value;
                    } else {
                        // Después de un día, excluir algunos
                        if (!['gclid', 'fbclid', 'msclkid', 'utm_term', 'utm_content'].includes(key)) {
                            paramsToUse[key] = value;
                        }
                    }
                }
            });
            
            if (Object.keys(paramsToUse).length === 0) {
                return;
            }
            
            // Procesar enlaces
            var links = document.getElementsByTagName('a');
            var modifiedCount = 0;
            
            for (var i = 0; i < links.length; i++) {
                var link = links[i];
                var href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:')) {
                    continue;
                }
                
                                // Determinar si es enlace personalizado
                var isCustomLink = false;
                var linkHostname = '';
                
                try {
                    var url = new URL(href, window.location.href);
                    linkHostname = url.hostname;
                    isCustomLink = isCustomDomain(linkHostname);
                } catch (e) {
                    // URL relativa, asumir mismo dominio
                    isCustomLink = true;
                }
                
                if (isCustomLink) {
                    var existingParams = parseUrlParams(href);
                    
                    // Verificar si ya tiene parámetros de tracking
                    var hasTrackingParams = Object.keys(existingParams).some(function(key) {
                        return key.startsWith('utm_') || CONFIG.TRACKING_KEYS.includes(key);
                    });
                    
                    if (hasTrackingParams) {
                        continue; // No modificar enlaces con tracking existente
                    }
                    
                    // Combinar parámetros
                    var finalParams = Object.assign({}, existingParams);
                    Object.keys(paramsToUse).forEach(function(key) {
                        if (!finalParams.hasOwnProperty(key) && paramsToUse[key]) {
                            finalParams[key] = paramsToUse[key];
                        }
                    });
                    
                    // Construir nueva URL
                    var baseUrl = href.split('?')[0];
                    var newHref = buildUrlWithParams(baseUrl, finalParams);
                    
                    if (newHref !== href) {
                        link.setAttribute('href', newHref);
                        link.setAttribute('data-utm-added', 'true');
                        modifiedCount++;
                    }
                }
            }
            
            if (modifiedCount > 0) {
                log(modifiedCount + ' enlaces modificados con UTMs');
            }
            
        } catch (err) {
            error('Error aplicando UTMs a enlaces:', err);
        }
    }
    
    function applyToForms() {
        try {
            var storedString = localStorage.getItem(CONFIG.LOCAL_STORAGE_KEY) || getCookie(CONFIG.COOKIE_NAME);
            if (!storedString) {
                log('No hay UTMs almacenados para formularios');
                return;
            }
            
            var utmData = JSON.parse(storedString);
            if (!utmData.params || Object.keys(utmData.params).length === 0) {
                return;
            }
            
            var forms = document.getElementsByTagName('form');
            var modifiedCount = 0;
            
            for (var i = 0; i < forms.length; i++) {
                var form = forms[i];
                var formModified = false;
                
                Object.keys(utmData.params).forEach(function(key) {
                    var value = utmData.params[key];
                    if (value) {
                        // Verificar si el campo ya existe
                        var existingField = form.querySelector('[name="' + key + '"]');
                        if (!existingField) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            form.appendChild(input);
                            formModified = true;
                        }
                    }
                });
                
                if (formModified) {
                    modifiedCount++;
                }
            }
            
            if (modifiedCount > 0) {
                log(modifiedCount + ' formularios modificados con UTMs');
            }
            
        } catch (err) {
            error('Error aplicando UTMs a formularios:', err);
        }
    }
    
    // ===== INICIALIZACIÓN =====
    
    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUTMManager);
    } else {
        initUTMManager();
    }
    
    // También ejecutar cuando se carga contenido dinámico
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    setTimeout(function() {
                        applyToLinks();
                        applyToForms();
                    }, 100);
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
        // Exponer funciones para debugging
    window.wpCustomUTM = {
        getStoredUTMs: function() {
            var stored = localStorage.getItem(CONFIG.LOCAL_STORAGE_KEY);
            return stored ? JSON.parse(stored) : null;
        },
        clearUTMs: function() {
            localStorage.removeItem(CONFIG.LOCAL_STORAGE_KEY);
            document.cookie = CONFIG.COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + CONFIG.ROOT_DOMAIN;
            log('UTMs limpiados');
        },
        debug: function() {
            CONFIG.DEBUG = true;
            log('Debug activado');
        }
    };
    
    log('UTM Manager cargado correctamente');
    
})();

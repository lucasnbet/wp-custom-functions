/**
 * Vimeo Lazy Load
 * Carga videos de Vimeo de forma diferida
 */

(function() {
    'use strict';
    
    // Configuración
    var config = {
        rootMargin: '100px',
        threshold: 0.1,
        vimeoAPI: 'https://player.vimeo.com/api/player.js'
    };
    
    // Esperar a que el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        
        // Buscar iframes de Vimeo
        var vimeoIframes = document.querySelectorAll('iframe[src*="vimeo.com"]');
        
        if (vimeoIframes.length === 0) {
            return;
        }
        
        // Cargar API de Vimeo
        loadVimeoAPI();
        
        // Configurar Intersection Observer
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(handleIntersection, config);
            
            vimeoIframes.forEach(function(iframe) {
                // Reemplazar src por data-src
                var src = iframe.getAttribute('src');
                iframe.setAttribute('data-src', src);
                iframe.removeAttribute('src');
                
                // Añadir placeholder
                iframe.style.background = '#f0f0f0';
                iframe.style.minHeight = '200px';
                
                // Observar
                observer.observe(iframe);
            });
        } else {
            // Fallback para navegadores viejos
            vimeoIframes.forEach(function(iframe) {
                loadIframe(iframe);
            });
        }
    });
    
    /**
     * Maneja la intersección
     */
    function handleIntersection(entries, observer) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var iframe = entry.target;
                loadIframe(iframe);
                observer.unobserve(iframe);
            }
        });
    }
    
    /**
     * Carga el iframe
     */
    function loadIframe(iframe) {
        var src = iframe.getAttribute('data-src');
        if (!src) return;
        
        // Añadir parámetros de optimización
        var url = new URL(src);
        url.searchParams.set('autoplay', '0');
        url.searchParams.set('background', '0');
        url.searchParams.set('muted', '1');
        
        // Actualizar src
        iframe.setAttribute('src', url.toString());
        
        // Quitar placeholder
        iframe.style.background = 'none';
        
        // Añadir event listeners para controles
        setupVimeoControls(iframe);
    }
    
    /**
     * Carga la API de Vimeo
     */
    function loadVimeoAPI() {
        if (window.Vimeo) return;
        
        var script = document.createElement('script');
        script.src = config.vimeoAPI;
        script.async = true;
        script.onload = initVimeoPlayers;
        document.head.appendChild(script);
    }
    
    /**
     * Inicializa los players de Vimeo
     */
    function initVimeoPlayers() {
        var iframes = document.querySelectorAll('iframe[src*="vimeo.com"]');
        
        iframes.forEach(function(iframe) {
            if (iframe.getAttribute('data-vimeo-initialized')) {
                return;
            }
            
            try {
                var player = new Vimeo.Player(iframe);
                
                // Añadir controles de accesibilidad
                iframe.setAttribute('title', 'Vimeo video player');
                iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                
                // Manejar eventos
                player.on('play', function() {
                    iframe.setAttribute('aria-busy', 'true');
                });
                
                player.on('pause', function() {
                    iframe.setAttribute('aria-busy', 'false');
                });
                
                player.on('ended', function() {
                    iframe.setAttribute('aria-busy', 'false');
                });
                
                // Marcar como inicializado
                iframe.setAttribute('data-vimeo-initialized', 'true');
                
            } catch (error) {
                console.warn('Error inicializando Vimeo player:', error);
            }
        });
    }
    
    /**
     * Configura controles personalizados para Vimeo
     */
    function setupVimeoControls(iframe) {
        var container = iframe.parentElement;
        
        // Verificar si ya tiene controles
        if (container.querySelector('.vimeo-controls')) {
            return;
        }
        
        // Crear contenedor de controles
        var controls = document.createElement('div');
        controls.className = 'vimeo-controls';
        controls.style.cssText = `
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            gap: 10px;
            align-items: center;
            z-index: 10;
            opacity: 0;
            transition: opacity 0.3s;
        `;
        
        // Crear botón de play/pause
        var playBtn = document.createElement('button');
        playBtn.className = 'vimeo-play-btn';
        playBtn.innerHTML = '▶';
        playBtn.style.cssText = `
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        // Crear barra de progreso
        var progress = document.createElement('div');
        progress.className = 'vimeo-progress';
        progress.style.cssText = `
            flex: 1;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            overflow: hidden;
        `;
        
        var progressBar = document.createElement('div');
        progressBar.className = 'vimeo-progress-bar';
        progressBar.style.cssText = `
            width: 0%;
            height: 100%;
            background: #00adef;
            transition: width 0.1s;
        `;
        progress.appendChild(progressBar);
        
        // Añadir controles al contenedor
        controls.appendChild(playBtn);
        controls.appendChild(progress);
        container.appendChild(controls);
        
        // Posicionar contenedor
        container.style.position = 'relative';
        
        // Mostrar controles al hover
        container.addEventListener('mouseenter', function() {
            controls.style.opacity = '1';
        });
        
        container.addEventListener('mouseleave', function() {
            controls.style.opacity = '0';
        });
        
        // Añadir controles de teclado
        container.setAttribute('tabindex', '0');
        container.addEventListener('keydown', function(e) {
            if (e.key === ' ') {
                e.preventDefault();
                playBtn.click();
            }
        });
    }
    
    // Hacer disponible globalmente
    window.cirionVimeoLazy = {
        init: initVimeoPlayers,
        loadVimeoAPI: loadVimeoAPI
    };
    
})();

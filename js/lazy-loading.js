/**
 * Sistema de Lazy Loading CORREGIDO para Revestika
 * Versión simplificada y más robusta
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Iniciando sistema de lazy loading...');

    // Configuración
    const config = {
        rootMargin: '50px 0px',
        threshold: 0.1,
        retryAttempts: 2,
        retryDelay: 1000
    };

    // Verificar soporte nativo de lazy loading
    const supportsNativeLazyLoading = 'loading' in HTMLImageElement.prototype;
    console.log('Soporte nativo lazy loading:', supportsNativeLazyLoading ? '✅' : '❌');
    
    // Variables globales
    let stats = {
        totalImages: 0,
        loadedImages: 0,
        failedImages: 0
    };

    // Función principal de inicialización
    function initLazyLoading() {
        console.log('📸 Inicializando lazy loading...');
        
        // Encontrar TODAS las imágenes
        const allImages = document.querySelectorAll('img');
        console.log(`Total de imágenes encontradas: ${allImages.length}`);

        // Separar imágenes críticas de lazy loading
        const lazyImages = document.querySelectorAll('img[data-src]');
        console.log(`Imágenes con lazy loading: ${lazyImages.length}`);

        stats.totalImages = lazyImages.length;

        if (lazyImages.length === 0) {
            console.log('✅ No hay imágenes para lazy loading');
            return;
        }

        // Procesar imágenes lazy
        lazyImages.forEach((img, index) => {
            processLazyImage(img, index);
        });

        // Configurar observer para navegadores sin soporte nativo
        if (!supportsNativeLazyLoading && lazyImages.length > 0) {
            setupIntersectionObserver(lazyImages);
        }

        // Verificación de emergencia después de 3 segundos
        setTimeout(emergencyCheck, 3000);
    }

    function processLazyImage(img, index) {
        // Asignar índice para debugging
        img.dataset.lazyIndex = index;
        img.dataset.lazyStatus = 'pending';
        
        // Si ya tiene src, está bien
        if (img.src && img.src !== window.location.href) {
            img.dataset.lazyStatus = 'loaded';
            stats.loadedImages++;
            return;
        }

        // Si tiene soporte nativo y data-src
        if (supportsNativeLazyLoading && img.dataset.src) {
            // Configurar loading lazy nativo
            img.loading = 'lazy';
            
            // Si no tiene src, usar data-src
            if (!img.src || img.src === window.location.href) {
                img.src = img.dataset.src;
            }
            
            // Event listeners
            img.addEventListener('load', () => {
                img.dataset.lazyStatus = 'loaded';
                stats.loadedImages++;
                console.log(`✅ Imagen ${index} cargada (nativo)`);
            });

            img.addEventListener('error', () => {
                img.dataset.lazyStatus = 'error';
                stats.failedImages++;
                console.error(`❌ Error imagen ${index}`);
                retryImageLoad(img);
            });
        }
    }

    function setupIntersectionObserver(images) {
        console.log('🔍 Configurando Intersection Observer...');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    loadImageManually(img);
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: config.rootMargin,
            threshold: config.threshold
        });

        images.forEach(img => {
            if (img.dataset.src && img.dataset.lazyStatus !== 'loaded') {
                observer.observe(img);
            }
        });
    }

    function loadImageManually(img) {
        if (!img.dataset.src || img.dataset.lazyStatus === 'loaded') return;

        const src = img.dataset.src;
        img.dataset.lazyStatus = 'loading';
        
        console.log(`📥 Cargando manualmente: ${src}`);

        // Crear imagen temporal para precargar
        const tempImg = new Image();
        
        tempImg.onload = () => {
            img.src = src;
            img.dataset.lazyStatus = 'loaded';
            stats.loadedImages++;
            console.log(`✅ Imagen ${img.dataset.lazyIndex} cargada manualmente`);
        };

        tempImg.onerror = () => {
            img.dataset.lazyStatus = 'error';
            stats.failedImages++;
            console.error(`❌ Error cargando: ${src}`);
            retryImageLoad(img);
        };

        tempImg.src = src;
    }

    function retryImageLoad(img, attempt = 1) {
        if (attempt > config.retryAttempts) {
            console.error(`💥 Falló definitivamente: ${img.dataset.src}`);
            return;
        }

        console.log(`🔄 Reintentando imagen ${img.dataset.lazyIndex} (${attempt}/${config.retryAttempts})`);
        
        setTimeout(() => {
            loadImageManually(img);
        }, config.retryDelay * attempt);
    }

    function emergencyCheck() {
        const brokenImages = document.querySelectorAll('img[data-src]:not([data-lazy-status="loaded"])');
        
        if (brokenImages.length > 0) {
            console.warn(`🚨 Verificación de emergencia: ${brokenImages.length} imágenes sin cargar`);
            
            brokenImages.forEach(img => {
                if (img.dataset.lazyStatus !== 'loading') {
                    console.log(`🔧 Forzando carga de: ${img.dataset.src}`);
                    loadImageManually(img);
                }
            });
        } else {
            console.log('✅ Todas las imágenes cargadas correctamente');
        }

        // Mostrar estadísticas
        showStats();
    }

    function showStats() {
        if (stats.totalImages === 0) return;

        const loadedPercentage = Math.round((stats.loadedImages / stats.totalImages) * 100);
        console.log(`📊 Estadísticas finales:
- Total: ${stats.totalImages}
- Cargadas: ${stats.loadedImages} (${loadedPercentage}%)
- Fallidas: ${stats.failedImages}
- Soporte nativo: ${supportsNativeLazyLoading ? 'Sí' : 'No'}`);
    }

    // ===============================================
    // FIX PARA NAVEGACIÓN HACIA ATRÁS
    // ===============================================

    // Detectar cuando el usuario regresa a la página
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            console.log('🔄 Página cargada desde caché - Reinicializando...');
            setTimeout(() => {
                // Reinicializar todo el sistema
                initLazyLoading();
                
                // Verificación adicional para imágenes problemáticas
                const problemImages = document.querySelectorAll('img[data-src]');
                problemImages.forEach(img => {
                    if (!img.src || img.src === window.location.href) {
                        console.log(`🔧 Reparando imagen: ${img.dataset.src}`);
                        img.src = img.dataset.src;
                    }
                });
            }, 100);
        }
    });

    // Detectar cambios de visibilidad
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            setTimeout(() => {
                const pendingImages = document.querySelectorAll('img[data-src]:not([data-lazy-status="loaded"])');
                if (pendingImages.length > 0) {
                    console.log(`🔄 Página visible - Procesando ${pendingImages.length} imágenes pendientes`);
                    pendingImages.forEach(img => loadImageManually(img));
                }
            }, 200);
        }
    });

    // Detectar navegación con botón atrás
    window.addEventListener('popstate', function() {
        console.log('⬅️ Navegación detectada - Verificando imágenes...');
        setTimeout(() => {
            initLazyLoading();
        }, 150);
    });

    // API pública
    window.LazyLoading = {
        reprocessImages: initLazyLoading,
        getStats: () => ({ ...stats }),
        forceLoadAll: function() {
            const allLazy = document.querySelectorAll('img[data-src]');
            allLazy.forEach(img => loadImageManually(img));
        }
    };

    // Ejecutar inicialización
    initLazyLoading();

    // Verificación final después de 5 segundos
    setTimeout(() => {
        const stillBroken = document.querySelectorAll('img[data-src]:not([data-lazy-status="loaded"])');
        if (stillBroken.length > 0) {
            console.warn(`🔧 Verificación final: Forzando ${stillBroken.length} imágenes restantes`);
            stillBroken.forEach(img => {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.dataset.lazyStatus = 'loaded';
                }
            });
        }
    }, 5000);
});
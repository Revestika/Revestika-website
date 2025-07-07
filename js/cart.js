/**
 * Revestika - Carrito con Integración Openpay SEGURO
 * VERSIÓN CORREGIDA - PRECIOS ARREGLADOS
 */

document.addEventListener('DOMContentLoaded', function() {
    // Variables globales
    let cart = [];
    const cartIcon = document.querySelector('.cart-icon');
    const cartCount = document.querySelector('.cart-count');

    // ====================================
    // 🔧 FUNCIÓN CRÍTICA: EXTRACCIÓN DE PRECIOS CORREGIDA
    // ====================================
    
    // ✅ FUNCIÓN EXTRACTPRICE MEJORADA PARA EVITAR ERRORES DE OPENPAY
    function extractPrice(productCard) {
        const priceElement = productCard.querySelector('.product-price');
        if (!priceElement) {
            console.error('❌ No se encontró .product-price en:', productCard);
            throw new Error('Elemento de precio no encontrado');
        }
        
        const priceText = priceElement.textContent.trim();
        console.log('🔍 Procesando precio:', priceText);
        
        // Tomar solo la parte antes del guión si existe
        const pricePerPlate = priceText.split('-')[0].trim();
        
        // 🔧 LIMPIEZA ROBUSTA DEL PRECIO
        let cleanPrice = pricePerPlate
            .replace(/\$/g, '')           // Remover símbolo $
            .replace(/\/placa/g, '')      // Remover "/placa"
            .replace(/\s+/g, '')          // Remover espacios
            .replace(/[^\d.,]/g, '')      // Solo números, comas y puntos
            .trim();
        
        // 🔧 MANEJAR DIFERENTES FORMATOS ARGENTINOS
        // Formato: 12.500 (punto como separador de miles)
        if (cleanPrice.includes('.') && !cleanPrice.includes(',')) {
            cleanPrice = cleanPrice.replace(/\./g, '');
        }
        // Formato: 12,500 (coma como separador de miles) 
        else if (cleanPrice.includes(',') && !cleanPrice.includes('.')) {
            cleanPrice = cleanPrice.replace(/,/g, '');
        }
        // Formato: 12.500,50 (punto miles, coma decimal)
        else if (cleanPrice.includes('.') && cleanPrice.includes(',')) {
            cleanPrice = cleanPrice.replace(/\./g, '').replace(',', '.');
        }
        
        const numericPrice = parseFloat(cleanPrice);
        
        // 🔧 VALIDACIÓN ESTRICTA
        if (isNaN(numericPrice) || numericPrice <= 0) {
            console.error('❌ Error procesando precio:', {
                original: priceText,
                cleaned: cleanPrice,
                result: numericPrice
            });
            throw new Error(`Precio inválido: ${priceText}`);
        }
        
        const finalPrice = Math.round(numericPrice);
        console.log(`✅ Precio procesado: "${priceText}" -> ${finalPrice}`);
        return finalPrice;
    }

    // ✅ AGREGAR función para limpiar carrito después del pago exitoso
    function clearCart() {
        cart = [];
        updateCartUI();
        saveCart();
        console.log('✅ Carrito limpiado después del pago exitoso');
    }

    function getProductImageSrc(productCard) {
        const imgElement = productCard.querySelector('img');
        if (!imgElement) {
            console.warn('No se encontró imagen en el producto');
            return 'images/default.jpg';
        }
        
        // Intentar diferentes fuentes de la imagen
        let imageSrc = '';
        
        // 1. Primero data-src (para lazy loading)
        if (imgElement.dataset.src) {
            imageSrc = imgElement.dataset.src;
        }
        // 2. Luego src normal
        else if (imgElement.src && imgElement.src !== window.location.href) {
            imageSrc = imgElement.src;
        }
        // 3. Fallback desde el atributo alt o nombre del producto
        else {
            const productName = productCard.querySelector('h3')?.textContent || '';
            if (productName.includes('SPC')) {
                imageSrc = 'images/blanco.jpeg'; // Default SPC
            } else if (productName.includes('MDF')) {
                imageSrc = 'images/mdfmarron.jpg'; // Default MDF
            } else {
                imageSrc = 'images/default.jpg';
            }
        }
        
        // Limpiar la URL si está codificada incorrectamente
        if (imageSrc.includes('&#x2F;') || imageSrc.includes('%2F')) {
            imageSrc = imageSrc.replace(/&#x2F;/g, '/').replace(/%2F/g, '/');
        }
        
        // Asegurar que empiece con 'images/' si es relativa
        if (!imageSrc.startsWith('http') && !imageSrc.startsWith('/') && !imageSrc.startsWith('images/')) {
            imageSrc = 'images/' + imageSrc;
        }
        
        console.log('Imagen seleccionada:', imageSrc);
        return imageSrc;
    }

    // ====================================
    // CLASES DE UTILIDAD
    // ====================================

    // CLASE: Rate Limiter para checkout
    class RateLimiter {
        constructor(maxAttempts = 5, windowMs = 60000) {
            this.maxAttempts = maxAttempts;
            this.windowMs = windowMs;
            this.storageKey = 'checkout_attempts';
        }
        
        canAttempt() {
            try {
                const attempts = JSON.parse(sessionStorage.getItem(this.storageKey) || '[]');
                const now = Date.now();
                
                const recentAttempts = attempts.filter(time => now - time < this.windowMs);
                sessionStorage.setItem(this.storageKey, JSON.stringify(recentAttempts));
                
                return recentAttempts.length < this.maxAttempts;
            } catch (error) {
                console.warn('Error checking rate limit:', error);
                return true;
            }
        }

        recordAttempt() {
            try {
                const attempts = JSON.parse(sessionStorage.getItem(this.storageKey) || '[]');
                attempts.push(Date.now());
                sessionStorage.setItem(this.storageKey, JSON.stringify(attempts));
            } catch (error) {
                console.warn('Error recording attempt:', error);
            }
        }
    }

    // CLASE: InputSanitizer
    class InputSanitizer {
        static sanitizeString(input, maxLength = 255) {
            if (typeof input !== 'string') {
                return String(input || '');
            }
            
            let sanitized = input.trim()
                .replace(/<[^>]*>/g, '')
                .replace(/javascript:/gi, '')
                .replace(/vbscript:/gi, '')
                .replace(/on\w+\s*=/gi, '')
                .replace(/data:/gi, '')
                .replace(/expression\s*\(/gi, '')
                .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
            
            sanitized = this.escapeHtml(sanitized);
            return sanitized.substring(0, maxLength);
        }

        static escapeHtml(text) {
            const htmlEscapeMap = {
                '&': '&amp;', '<': '&lt;', '>': '&gt;',
                '"': '&quot;', "'": '&#x27;', '/': '&#x2F;'
            };
            return text.replace(/[&<>"'\/]/g, match => htmlEscapeMap[match]);
        }

        static sanitizeEmail(email) {
            if (typeof email !== 'string') return '';
            let sanitized = email.trim().toLowerCase().replace(/[<>'"&]/g, '');
            const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
            return emailRegex.test(sanitized) && sanitized.length <= 254 ? sanitized : '';
        }

        static sanitizeProductData(rawData) {
            if (!rawData || typeof rawData !== 'object') {
                throw new Error('Datos de producto inválidos');
            }

            const sanitized = {
                id: this.sanitizeString(rawData.id, 50),
                name: this.sanitizeString(rawData.name, 200),
                price: typeof rawData.price === 'number' ? rawData.price : parseFloat(rawData.price) || 0,
                quantity: parseInt(rawData.quantity) || 1,
                image: this.sanitizeString(rawData.image, 500)
            };

            if (!sanitized.id || !sanitized.name || sanitized.price <= 0) {
                throw new Error('Datos de producto incompletos');
            }

            if (!/^[a-zA-Z0-9\-_]+$/.test(sanitized.id)) {
                throw new Error('ID de producto inválido');
            }

            return sanitized;
        }

        static sanitizeCustomerData(rawData) {
            if (!rawData || typeof rawData !== 'object') {
                throw new Error('Datos de cliente inválidos');
            }

            const sanitized = {
                name: this.sanitizeString(rawData.name, 100),
                email: this.sanitizeEmail(rawData.email),
                phone: this.sanitizeString(rawData.phone || '', 20),
                address: this.sanitizeString(rawData.address || '', 500)
            };

            if (sanitized.name.length < 2) {
                throw new Error('El nombre debe tener al menos 2 caracteres');
            }

            if (!sanitized.email) {
                throw new Error('Email inválido');
            }

            return sanitized;
        }
    }

    const rateLimiter = new RateLimiter();

    // ====================================
    // FUNCIONES PRINCIPALES DEL CARRITO
    // ====================================

    window.addToCart = function(id, name, price, image) {
        try {
            const rawData = { id, name, price, image, quantity: 1 };
            const sanitizedData = InputSanitizer.sanitizeProductData(rawData);
            
            const existingItem = cart.find(item => item.id === sanitizedData.id);
            
            if (existingItem) {
                if (existingItem.quantity >= 999) {
                    showNotification('Cantidad máxima alcanzada', 'warning');
                    return;
                }
                existingItem.quantity += 1;
            } else {
                cart.push(sanitizedData);
            }
            
            updateCartUI();
            saveCart();
            showNotification(`${sanitizedData.name} agregado al carrito`);
            trackAddToCart(sanitizedData.id, sanitizedData.name, sanitizedData.price);
            
        } catch (error) {
            console.error('Error en addToCart:', error);
            showNotification('Error agregando producto: ' + error.message, 'error');
        }
    };

    function updateCartUI() {
        const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
        cartCount.textContent = totalItems;
        
        if (document.querySelector('.cart-modal')) {
            renderCartModal();
        }
    }

    function validateCartBeforeCheckout() {
        if (cart.length === 0) {
            throw new Error('El carrito está vacío');
        }
        
        let total = 0;
        cart.forEach(item => {
            total += item.price * item.quantity;
        });
        
        if (total < 1000) {
            throw new Error('El monto mínimo de compra es $1,000');
        }
        
        if (total > 2000000) {
            throw new Error('El monto máximo de compra es $2,000,000');
        }
        
        console.log('✅ Carrito validado correctamente');
        return true;
    }

    // ====================================
    // SISTEMA DE ALMACENAMIENTO
    // ====================================

    function saveCart() {
        try {
            const cartData = {
                items: cart,
                timestamp: Date.now(),
                session_id: getSessionId()
            };
            sessionStorage.setItem('revestikaCart', JSON.stringify(cartData));
        } catch (error) {
            console.warn('Error guardando carrito:', error);
        }
    }

    function loadCart() {
        try {
            const savedCartData = sessionStorage.getItem('revestikaCart');
            if (savedCartData) {
                const cartData = JSON.parse(savedCartData);
                if (cartData.items && Array.isArray(cartData.items)) {
                    cart = cartData.items;
                    updateCartUI();
                    console.log('Carrito cargado desde sessionStorage');
                }
            }
        } catch (error) {
            console.warn('Error cargando carrito:', error);
            cart = [];
            updateCartUI();
        }
    }

    function getSessionId() {
        let sessionId = sessionStorage.getItem('revestika_session_id');
        if (!sessionId) {
            sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('revestika_session_id', sessionId);
        }
        return sessionId;
    }

    // ====================================
    // CHECKOUT Y MODAL DE CLIENTE
    // ====================================

    function showCustomerDataModal() {
        const modalHTML = `
            <div class="customer-modal" id="customerModal">
                <div class="customer-modal-content">
                    <div class="customer-modal-header">
                        <h3>Datos para el Pago</h3>
                        <button class="close-customer-modal">×</button>
                    </div>
                    <div class="customer-modal-body">
                        <form id="customerDataForm">
                            <div class="form-group">
                                <label for="customerName">Nombre Completo *</label>
                                <input type="text" id="customerName" required maxlength="100">
                            </div>
                            <div class="form-group">
                                <label for="customerEmail">Email *</label>
                                <input type="email" id="customerEmail" required maxlength="254">
                            </div>
                            <div class="form-group">
                                <label for="customerPhone">Teléfono</label>
                                <input type="tel" id="customerPhone" maxlength="20">
                            </div>
                            <div class="form-group">
                                <label for="customerAddress">Dirección de Entrega</label>
                                <textarea id="customerAddress" rows="3" maxlength="500"></textarea>
                            </div>
                            <div class="customer-actions">
                                <button type="button" class="btn btn-secondary cancel-checkout">Cancelar</button>
                                <button type="submit" class="btn" id="processPaymentBtn">Procesar Pago</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        document.body.classList.add('modal-open');

        document.getElementById('customerDataForm').addEventListener('submit', handleCustomerFormSubmit);
        document.querySelector('.close-customer-modal').addEventListener('click', closeCustomerModal);
        document.querySelector('.cancel-checkout').addEventListener('click', closeCustomerModal);
    }

    async function handleCustomerFormSubmit(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('processPaymentBtn');
        const originalText = submitBtn.textContent;
        
        try {
            if (!rateLimiter.canAttempt()) {
                throw new Error('Demasiados intentos. Espera un minuto.');
            }
            
            validateCartBeforeCheckout();

            submitBtn.disabled = true;
            submitBtn.textContent = 'Procesando...';
            
            const rawCustomerData = {
                name: document.getElementById('customerName').value.trim(),
                email: document.getElementById('customerEmail').value.trim(),
                phone: document.getElementById('customerPhone').value.trim(),
                address: document.getElementById('customerAddress').value.trim()
            };

            const customerData = InputSanitizer.sanitizeCustomerData(rawCustomerData);
            rateLimiter.recordAttempt();
            
            // 🔧 VALIDACIÓN ROBUSTA DE DATOS ANTES DEL ENVÍO
            const validatedCart = cart.map(item => {
                const price = parseFloat(item.price);
                const quantity = parseInt(item.quantity);
                
                if (isNaN(price) || price <= 0) {
                    throw new Error(`Precio inválido en ${item.name}: ${item.price}`);
                }
                
                if (isNaN(quantity) || quantity <= 0) {
                    throw new Error(`Cantidad inválida en ${item.name}: ${item.quantity}`);
                }
                
                return {
                    id: item.id,
                    name: item.name,
                    price: price,  // ASEGURAR QUE ES NÚMERO
                    quantity: quantity,  // ASEGURAR QUE ES NÚMERO
                    image: item.image
                };
            });
            
            const total = validatedCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            if (total <= 0) {
                throw new Error('El total debe ser mayor a 0');
            }
            
            if (total < 1000) {
                throw new Error('El monto mínimo es $1.000');
            }
            
            const paymentData = {
                cart: validatedCart,
                customer: customerData,
                total: total,
                timestamp: new Date().toISOString()
            };
            
            console.log('🔍 Datos validados para OpenPay:', paymentData);
            console.log('💰 Total calculado:', total);
            
            const response = await fetch('procesar-pago.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(paymentData)
            });
                
            const result = await response.json();
            console.log('Respuesta:', result);
            
            if (!result.success) {
                throw new Error(result.error || 'Error procesando el pago');
            }
            
            if (!result.checkout_url) {
                throw new Error('URL de pago no recibida');
            }
            
            // Éxito - redirigir
            trackInitiateCheckout(paymentData.total);
            closeCustomerModal();
            closeCartModal();
            
            showNotification('Redirigiendo a OpenPay...', 'success');
            setTimeout(() => {
                window.location.href = result.checkout_url;
            }, 1000);
            
        } catch (error) {
            console.error('Error checkout:', error);
            showNotification(error.message, 'error');
            
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }, 3000);
        }
    }

    function closeCustomerModal() {
        const modal = document.getElementById('customerModal');
        if (modal) {
            modal.remove();
            document.body.classList.remove('modal-open');
        }
    }

    // ====================================
    // MODAL DEL CARRITO
    // ====================================

    function renderCartModal() {
        let cartModal = document.querySelector('.cart-modal');
        
        if (!cartModal) {
            cartModal = document.createElement('div');
            cartModal.className = 'cart-modal';
            document.body.appendChild(cartModal);
        }
        
        const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        let cartHTML = `
            <div class="cart-modal-content">
                <div class="cart-modal-header">
                    <h3>Tu Carrito</h3>
                    <button class="close-cart">×</button>
                </div>
                <div class="cart-modal-body">
        `;
        
        if (cart.length === 0) {
            cartHTML += `
                <div class="empty-cart">
                    <p>Tu carrito está vacío</p>
                    <a href="#productos" class="btn">Ver Productos</a>
                </div>`;
        } else {
            cartHTML += `<ul class="cart-items">`;
            
            cart.forEach(item => {
                cartHTML += `
                    <li class="cart-item" data-id="${item.id}">
                        <div class="cart-item-img">
                            <img src="${item.image}" alt="${item.name}">
                        </div>
                        <div class="cart-item-details">
                            <h4>${item.name}</h4>
                            <p class="cart-item-price">$${Math.round(item.price).toLocaleString()}/placa</p>
                            <div class="cart-item-quantity">
                                <button class="quantity-btn minus">-</button>
                                <span>${item.quantity}</span>
                                <button class="quantity-btn plus">+</button>
                            </div>
                        </div>
                        <button class="remove-item">×</button>
                    </li>`;
            });
            
            cartHTML += `</ul>
                <div class="cart-summary">
                    <div class="cart-total">
                        <span>Total:</span>
                        <span>$${Math.round(total).toLocaleString()}</span>
                    </div>
                    <button class="checkout-btn">Pagar con Openpay</button>
                    <button class="continue-shopping-btn">Continuar Comprando</button>
                </div>`;
        }
        
        cartHTML += `</div></div>`;
        cartModal.innerHTML = cartHTML;
        document.body.classList.add('cart-open');
        
        // Event listeners
        cartModal.querySelector('.close-cart').addEventListener('click', closeCartModal);
        
        if (cart.length > 0) {
            cartModal.querySelectorAll('.quantity-btn.minus').forEach(btn => {
                btn.addEventListener('click', decrementQuantity);
            });
            
            cartModal.querySelectorAll('.quantity-btn.plus').forEach(btn => {
                btn.addEventListener('click', incrementQuantity);
            });
            
            cartModal.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', removeCartItem);
            });
            
            cartModal.querySelector('.continue-shopping-btn').addEventListener('click', function() {
                closeCartModal();
                document.querySelector('#productos').scrollIntoView({ behavior: 'smooth' });
            });
            
            cartModal.querySelector('.checkout-btn').addEventListener('click', checkout);
        }
    }

    function checkout() {
        try {
            validateCartBeforeCheckout();
            closeCartModal();
            showCustomerDataModal();
        } catch (error) {
            showNotification(error.message, 'warning');
        }
    }

    function incrementQuantity(e) {
        const itemElement = e.target.closest('.cart-item');
        const id = itemElement.dataset.id;
        const item = cart.find(item => item.id === id);
        
        if (item && item.quantity < 999) {
            item.quantity += 1;
            updateCartUI();
            saveCart();
        }
    }

    function decrementQuantity(e) {
        const itemElement = e.target.closest('.cart-item');
        const id = itemElement.dataset.id;
        const item = cart.find(item => item.id === id);
        
        if (item && item.quantity > 1) {
            item.quantity -= 1;
            updateCartUI();
            saveCart();
        }
    }

    function removeCartItem(e) {
        const itemElement = e.target.closest('.cart-item');
        const id = itemElement.dataset.id;
        
        cart = cart.filter(item => item.id !== id);
        updateCartUI();
        saveCart();
        showNotification('Producto eliminado del carrito');
    }

    function closeCartModal() {
        const cartModal = document.querySelector('.cart-modal');
        if (cartModal) {
            cartModal.remove();
            document.body.classList.remove('cart-open');
        }
    }

    // ====================================
    // FUNCIONES DE UTILIDAD
    // ====================================

    function showNotification(message, type = 'success') {
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        
        const timeout = type === 'error' ? 5000 : 3000;
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) notification.remove();
            }, 300);
        }, timeout);
    }

    function trackAddToCart(productId, productName, price) {
        try {
            if (typeof fbq !== 'undefined') {
                fbq('track', 'AddToCart', {
                    content_ids: [productId],
                    content_name: productName,
                    value: price,
                    currency: 'ARS'
                });
            }
        } catch (error) {
            console.warn('Error tracking AddToCart:', error);
        }
    }

    function trackInitiateCheckout(total) {
        try {
            if (typeof fbq !== 'undefined') {
                fbq('track', 'InitiateCheckout', {
                    value: total,
                    currency: 'ARS',
                    num_items: cart.length
                });
            }
        } catch (error) {
            console.warn('Error tracking InitiateCheckout:', error);
        }
    }

    // ====================================
    // 🔧 EVENT LISTENERS CORREGIDOS - UNA SOLA VEZ
    // ====================================

    cartIcon.addEventListener('click', function() {
        renderCartModal();
    });

    // ✅ EVENT LISTENER CORREGIDO - USAR LA FUNCIÓN extractPrice
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productCard = this.closest('.product-card');
            const productId = productCard.dataset.id;
            const productName = productCard.querySelector('h3').textContent;
            
            // ✅ USAR LA FUNCIÓN CORREGIDA
            const numericPrice = extractPrice(productCard);
            const productImage = getProductImageSrc(productCard);
            
            addToCart(productId, productName, numericPrice, productImage);
        });
    });

    // Función de debug para verificar precios
    function debugAllPrices() {
        console.log('🔍 Verificando todos los precios:');
        document.querySelectorAll('.product-card').forEach((card, index) => {
            try {
                const priceText = card.querySelector('.product-price').textContent;
                const extractedPrice = extractPrice(card);
                const productName = card.querySelector('h3').textContent;
                
                console.log(`${index + 1}. ${productName}: ${priceText} -> ${extractedPrice}`);
            } catch (error) {
                console.error(`Error en producto ${index + 1}:`, error);
            }
        });
    }

    // ====================================
    // INICIALIZACIÓN
    // ====================================

    loadCart();
    
    // Debug de precios al cargar
    setTimeout(debugAllPrices, 1000);

    // Agregar estilos CSS para el modal
    const customerModalStyles = `
        <style>
        .customer-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5); display: flex;
            justify-content: center; align-items: center; z-index: 1002;
        }
        .customer-modal-content {
            background-color: white; width: 90%; max-width: 500px;
            border-radius: 8px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .customer-modal-header {
            background-color: var(--primary-color); color: white; padding: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .customer-modal-header h3 { margin: 0; font-size: 18px; }
        .close-customer-modal {
            background: none; border: none; color: white;
            font-size: 24px; cursor: pointer;
        }
        .customer-modal-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; margin-bottom: 5px; font-weight: 500;
            color: var(--dark-gray);
        }
        .form-group input, .form-group textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--light-gray);
            border-radius: 4px; font-size: 14px;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: var(--accent-color);
        }
        .customer-actions {
            display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;
        }
        </style>
    `;
    
    document.head.insertAdjacentHTML('beforeend', customerModalStyles);

    // 🔧 AGREGAR AL FINAL DE cart.js - LÍNEA DE DEBUG
    console.log('🚀 CART.JS CORREGIDO CARGADO - Versión:', new Date().toISOString());

    // 🔧 TEST INMEDIATO DE FUNCIÓN extractPrice
    setTimeout(() => {
        const firstProduct = document.querySelector('.product-card');
        if (firstProduct) {
            try {
                const testPrice = extractPrice(firstProduct);
                console.log('🧪 TEST EXTRACTPRICE:', testPrice);
                console.log('✅ La función extractPrice está funcionando correctamente');
            } catch (error) {
                console.error('❌ ERROR EN EXTRACTPRICE:', error);
            }
        }
    }, 2000);

    // ✅ AGREGAR al final de cart.js para testing
    window.RevestikaDebug = {
        testPriceExtraction: function() {
            console.log('🧪 Testing price extraction...');
            document.querySelectorAll('.product-card').forEach((card, index) => {
                try {
                    const price = extractPrice(card);
                    const name = card.querySelector('h3').textContent;
                    console.log(`${index + 1}. ${name}: $${price.toLocaleString()}`);
                } catch (error) {
                    console.error(`❌ Error en producto ${index + 1}:`, error);
                }
            });
        },
        
        getCartTotal: function() {
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            console.log('💰 Total del carrito:', total.toLocaleString());
            return total;
        },
        
        simulateCheckout: function() {
            console.log('🛒 Simulando checkout con datos de prueba...');
            return {
                cart: cart,
                total: this.getCartTotal(),
                customer: {
                    name: 'Test Usuario',
                    email: 'test@example.com',
                    phone: '+54911234567'
                }
            };
        }
    };
});
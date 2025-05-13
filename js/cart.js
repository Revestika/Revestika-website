/**
 * Revestika - Funcionalidad del Carrito de Compras
 * Este script gestiona todas las funcionalidades del carrito de compras
 */

// Esperar a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', function() {
    // Variables globales
    let cart = [];
    const cartIcon = document.querySelector('.cart-icon');
    const cartCount = document.querySelector('.cart-count');

    // Estructura básica para un ítem del carrito
    class CartItem {
        constructor(id, name, price, quantity, image) {
            this.id = id;
            this.name = name;
            this.price = price;
            this.quantity = quantity;
            this.image = image;
        }
    }

    // Función para añadir producto al carrito
    window.addToCart = function(id, name, price, image) {
        // Verificar si el producto ya está en el carrito
        const existingItem = cart.find(item => item.id === id);
        
        if (existingItem) {
            // Incrementar cantidad si ya existe
            existingItem.quantity += 1;
        } else {
            // Añadir nuevo ítem si no existe
            const newItem = new CartItem(id, name, parseFloat(price), 1, image);
            cart.push(newItem);
        }
        
        // Actualizar la UI
        updateCartUI();
        
        // Guardar carrito en localStorage
        saveCart();
        
        // Mostrar mensaje de confirmación
        showNotification(`${name} agregado al carrito`);
    };

    // Función para actualizar la UI del carrito
    function updateCartUI() {
        // Actualizar contador
        const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
        cartCount.textContent = totalItems;
        
        // Si hay un modal de carrito abierto, actualizarlo también
        if (document.querySelector('.cart-modal')) {
            renderCartModal();
        }
    }

    // Función para guardar el carrito en localStorage
    function saveCart() {
        localStorage.setItem('revestikaCart', JSON.stringify(cart));
    }

    // Función para cargar el carrito desde localStorage
    function loadCart() {
        const savedCart = localStorage.getItem('revestikaCart');
        if (savedCart) {
            cart = JSON.parse(savedCart);
            updateCartUI();
        }
    }

    // Función para renderizar modal del carrito
    function renderCartModal() {
        // Crear modal si no existe
        let cartModal = document.querySelector('.cart-modal');
        
        if (!cartModal) {
            cartModal = document.createElement('div');
            cartModal.className = 'cart-modal';
            document.body.appendChild(cartModal);
        }
        
        // Calcular total
        const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        // Generar HTML del carrito
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
                    <button class="checkout-btn">Finalizar Compra</button>
                    <button class="continue-shopping-btn">Continuar Comprando</button>
                </div>`;
        }
        
        cartHTML += `
                </div>
            </div>`;
        
        cartModal.innerHTML = cartHTML;
        
        // Añadir overlay al body
        document.body.classList.add('cart-open');
        
        // Añadir event listeners
        cartModal.querySelector('.close-cart').addEventListener('click', closeCartModal);
        
        if (cart.length > 0) {
            // Event listeners para botones de cantidad
            cartModal.querySelectorAll('.quantity-btn.minus').forEach(btn => {
                btn.addEventListener('click', decrementQuantity);
            });
            
            cartModal.querySelectorAll('.quantity-btn.plus').forEach(btn => {
                btn.addEventListener('click', incrementQuantity);
            });
            
            // Event listeners para botones de eliminar
            cartModal.querySelectorAll('.remove-item').forEach(btn => {
                btn.addEventListener('click', removeCartItem);
            });
            
            // Event listener para continuar comprando
            cartModal.querySelector('.continue-shopping-btn').addEventListener('click', function() {
                closeCartModal();
                // Scroll hacia la sección de productos
                document.querySelector('#productos').scrollIntoView({
                    behavior: 'smooth'
                });
            });
            
            // Event listener para checkout
            cartModal.querySelector('.checkout-btn').addEventListener('click', checkout);
        }
    }

    // Función para cerrar el modal del carrito
    function closeCartModal() {
        const cartModal = document.querySelector('.cart-modal');
        if (cartModal) {
            cartModal.remove();
            document.body.classList.remove('cart-open');
        }
    }

    // Función para incrementar cantidad
    function incrementQuantity(e) {
        const itemElement = e.target.closest('.cart-item');
        const id = itemElement.dataset.id;
        const item = cart.find(item => item.id === id);
        
        if (item) {
            item.quantity += 1;
            updateCartUI();
            saveCart();
        }
    }

    // Función para decrementar cantidad
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

    // Función para eliminar un ítem del carrito
    function removeCartItem(e) {
        const itemElement = e.target.closest('.cart-item');
        const id = itemElement.dataset.id;
        
        cart = cart.filter(item => item.id !== id);
        updateCartUI();
        saveCart();
    }

    // Función para mostrar notificación
    function showNotification(message) {
        // Eliminar notificaciones existentes
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => {
            notification.remove();
        });
        
        // Crear nueva notificación
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Mostrar notificación
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Ocultar y eliminar después de 3 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    // Función para procesar el checkout
    function checkout() {
        // Preparar el mensaje para el formulario de contacto
        let checkoutMessage = "Solicitud de cotización:\n\n";
        
        // Añadir cada ítem al mensaje
        cart.forEach(item => {
            checkoutMessage += `- ${item.name}: ${item.quantity} placa(s) a $${Math.round(item.price).toLocaleString()} c/u\n`;
        });
        
        // Calcular total
        const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        checkoutMessage += `\nTotal estimado: $${Math.round(total).toLocaleString()}`;
        checkoutMessage += "\n\nPor favor contactarme para coordinar el pago y la entrega.";
        
        // Cerrar el modal
        closeCartModal();
        
        // Desplazarse al formulario de contacto
        document.getElementById('contacto').scrollIntoView({behavior: 'smooth'});
        
        // Rellenar el formulario
        setTimeout(() => {
            const subjectField = document.querySelector('#subject');
            const messageField = document.querySelector('#message');
            
            if (subjectField) subjectField.value = 'Solicitud de cotización';
            if (messageField) messageField.value = checkoutMessage;
        }, 1000); // Pequeño retraso para asegurar que el scroll haya terminado
        
        // Vaciar el carrito después
        cart = [];
        updateCartUI();
        saveCart();
        
        // Notificación de éxito
        showNotification('Los detalles de tu pedido se han transferido al formulario de contacto');
    }

    // Event listener para el icono del carrito
    cartIcon.addEventListener('click', function() {
        renderCartModal();
    });

    // Event listeners para botones "Añadir al carrito" en productos
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productCard = this.closest('.product-card');
            const productId = productCard.dataset.id;
            const productName = productCard.querySelector('h3').textContent;
            
            // Extraer solo el precio por placa de la cadena completa
            const priceText = productCard.querySelector('.product-price').textContent;
            const pricePerPlate = priceText.split('-')[0].trim(); // Obtiene "$XX.XXX/placa"
            const numericPrice = parseFloat(pricePerPlate.replace('$', '').replace('.', '').replace('/placa', ''));
            
            const productImage = productCard.querySelector('img').src;
            
            addToCart(productId, productName, numericPrice, productImage);
        });
    });

    // Cargar carrito al iniciar
    loadCart();
});
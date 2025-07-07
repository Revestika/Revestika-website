// Manejo del modal de detalles de producto
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar elementos del DOM
    const productModal = document.getElementById('product-modal');
    let productDetails = null;
    
    // Asegurarnos de que el modal esté oculto al cargar la página
    if (productModal) {
        productModal.style.display = 'none';
    }

    // Cargar detalles de productos
    fetch('data/product-details.json')
        .then(response => response.json())
        .then(data => {
            productDetails = data;
        })
        .catch(error => console.error('Error cargando detalles de productos:', error));

    // Función para abrir el modal
    function openProductModal(productId) {
        const product = productDetails[productId];
        if (!product) {
            console.error('Producto no encontrado');
            return;
        }

        // Antes de mostrar el modal, agregar clase para bloquear el scroll del body
        document.body.classList.add('modal-open');

        // Limitar características a 3-4 máximo
        const limitedCharacteristicas = product.caracteristicas.slice(0, 4);

        const modalContent = `
            <div class="product-modal-header">
                <h2>${product.name}</h2>
                <span class="product-modal-close">&times;</span>
            </div>
            <div class="product-modal-body">
                <div class="product-modal-images">
                    <img src="images/${product.images[0]}" alt="${product.name}">
                </div>
                <div class="product-modal-details">
                    <div class="product-details-section">
                        <h3>Características</h3>
                        <ul>
                            ${limitedCharacteristicas.map(car => `<li>${car}</li>`).join('')}
                        </ul>
                    </div>
                    <div class="product-details-section">
                        <h3>Especificaciones</h3>
                        <table>
                            <tr><td>Material:</td><td>${product.material}</td></tr>
                            <tr><td>Densidad:</td><td>${product.densidad}</td></tr>
                        </table>
                    </div>
                    <div class="product-details-section">
                        <h3>Medidas</h3>
                        <table>
                            <tr><td>Largo:</td><td>${product.medidas.largo}</td></tr>
                            <tr><td>Ancho:</td><td>${product.medidas.ancho}</td></tr>
                            <tr><td>Espesor:</td><td>${product.medidas.espesor}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        `;

        productModal.innerHTML = modalContent;
        productModal.classList.add('show');

        // Añadir event listener para cerrar
        document.querySelector('.product-modal-close').addEventListener('click', function() {
            closeModal();
        });

        // Cerrar al hacer click fuera
        productModal.addEventListener('click', function(event) {
            if (event.target === productModal) {
                closeModal();
            }
        });
        
        // Cerrar al presionar ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }
    
    function closeModal() {
        if (productModal) {
            productModal.classList.remove('show');
            productModal.style.display = 'none';
            // Quitar la clase que bloquea el scroll
            document.body.classList.remove('modal-open');
        }
    }

    // Event listeners para botones "Ver Detalle"
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function(event) {
            event.preventDefault();
            const productId = this.getAttribute('data-id');
            openProductModal(productId);
        });
    });
    
    // Cerrar modal si existe al cargar la página (por si acaso)
    closeModal();
});
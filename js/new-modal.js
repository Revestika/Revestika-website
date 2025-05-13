// Nuevo script para manejar el modal
document.addEventListener('DOMContentLoaded', function() {
    // Elementos DOM
    const productModal = document.getElementById('product-modal');
    let productDetails = {}; // Inicializar como objeto vacío

    // Función para simular detalles de productos (en caso de que no se cargue el JSON)
    function createFallbackProductDetails() {
        return {
            "product-1": {
                "name": "Panel SPC - Calacatta Blanco símil marmol",
                "images": ["blanco.jpeg"],
                "caracteristicas": [
                    "Resistente al agua",
                    "Resistente al calor",
                    "No decolora",
                    "Textura realista"
                ],
                "material": "Stone Polymer Composite (SPC)",
                "densidad": "1450 kg/m³",
                "medidas": {
                    "largo": "1220mm",
                    "ancho": "183mm",
                    "espesor": "4mm"
                }
            },
            // Añade otros productos si es necesario
        };
    }

    // Cargar detalles de productos
    fetch('data/product-details.json')
        .then(response => response.json())
        .then(data => {
            productDetails = data;
            console.log("Detalles de productos cargados correctamente");
        })
        .catch(error => {
            console.error('Error cargando detalles de productos:', error);
            productDetails = createFallbackProductDetails();
            console.log("Usando detalles de productos de respaldo");
        });

    // Función para abrir el modal
    function openProductModal(productId) {
        // Comprobar si tenemos detalles para este producto
        if (!productDetails || !productDetails[productId]) {
            console.error('Detalles de producto no disponibles para:', productId);
            // Usar datos de muestra si no hay detalles
            if (productId === 'product-1') {
                productDetails = createFallbackProductDetails();
            } else {
                alert('Información del producto no disponible. Intente más tarde.');
                return;
            }
        }

        const product = productDetails[productId];

        // Bloquear scroll del body
        document.body.classList.add('modal-open');

        // Crear contenido del modal
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
                            ${product.caracteristicas.map(item => `<li>${item}</li>`).join('')}
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

        // Actualizar contenido del modal
        const modalContentContainer = productModal.querySelector('.product-modal-content');
        modalContentContainer.innerHTML = modalContent;

        // Mostrar el modal
        productModal.style.display = 'block';

        // Manejar el cierre del modal
        const closeButton = productModal.querySelector('.product-modal-close');
        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        }

        // Cerrar al hacer clic en el backdrop
        const backdrop = productModal.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', closeModal);
        }

        // Cerrar con tecla ESC
        document.addEventListener('keydown', handleEscKey);
    }

    // Función para cerrar el modal
    function closeModal() {
        if (productModal) {
            productModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        // Eliminar el event listener de ESC
        document.removeEventListener('keydown', handleEscKey);
    }

    // Manejador para la tecla ESC
    function handleEscKey(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }

    // Añadir event listeners a los botones de "Ver Detalle"
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const productId = this.getAttribute('data-id');
            openProductModal(productId);
        });
    });

    // Asegurar que el modal esté cerrado al inicio
    if (productModal) {
        productModal.style.display = 'none';
    }
});
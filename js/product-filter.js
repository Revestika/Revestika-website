// Filtrado de productos
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar elementos
    const filterButtons = document.querySelectorAll('.filter-btn');
    const productCards = document.querySelectorAll('.product-card');

    // Función de filtrado
    function filterProducts(category) {
        productCards.forEach(card => {
            // Si la categoría es 'all' o coincide con la categoría del producto, mostrar
            if (category === 'all' || card.dataset.category === category) {
                card.style.display = 'block';
                // Animación de entrada
                card.classList.add('product-fade-in');
                card.classList.remove('product-fade-out');
            } else {
                card.style.display = 'none';
                card.classList.add('product-fade-out');
                card.classList.remove('product-fade-in');
            }
        });
    }

    // Event listeners para botones de filtro
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Quitar clase 'active' de todos los botones
            filterButtons.forEach(b => b.classList.remove('active'));
            
            // Añadir clase 'active' al botón clickeado
            this.classList.add('active');
            
            // Obtener categoría del filtro
            const category = this.dataset.filter;
            
            // Filtrar productos
            filterProducts(category);
        });
    });
});
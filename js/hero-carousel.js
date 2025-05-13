// Hero Carousel Script
document.addEventListener('DOMContentLoaded', function() {
    const heroSection = document.querySelector('.hero');
    const heroImages = [
        'hero-bg.jpg',
        'hero-bg-2.jpg',
        'hero-bg-3.jpg'
        // Añade aquí los nombres de tus imágenes adicionales
    ];
    let currentImageIndex = 0;

    // Función para cambiar la imagen de fondo
    function changeHeroBackground() {
        heroSection.style.backgroundImage = `
            linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), 
            url('../images/${heroImages[currentImageIndex]}')
        `;
        
        // Avanzar al siguiente índice, volver al inicio si llega al final
        currentImageIndex = (currentImageIndex + 1) % heroImages.length;
    }

    // Cambiar imagen cada 5 segundos
    setInterval(changeHeroBackground, 5000);

    // Opcional: añadir controles de navegación
    const prevButton = document.createElement('button');
    prevButton.innerHTML = '&#10094;';
    prevButton.classList.add('hero-nav', 'hero-prev');
    prevButton.addEventListener('click', () => {
        currentImageIndex = (currentImageIndex - 2 + heroImages.length) % heroImages.length;
        changeHeroBackground();
    });

    const nextButton = document.createElement('button');
    nextButton.innerHTML = '&#10095;';
    nextButton.classList.add('hero-nav', 'hero-next');
    nextButton.addEventListener('click', changeHeroBackground);

    heroSection.appendChild(prevButton);
    heroSection.appendChild(nextButton);
});
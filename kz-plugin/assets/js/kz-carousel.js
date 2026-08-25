/**
 * KZ Kraonige Zwaone Carousel JavaScript
 * Handles the post carousel functionality
 */

(function() {
    'use strict';

    // Initialize carousels when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeCarousels();
    });

    function initializeCarousels() {
        const carousels = document.querySelectorAll('.kz-post-doorscroll-swiper-container');
        
        carousels.forEach(function(container) {
            initializeCarousel(container);
        });
    }

    function initializeCarousel(container) {
        const uid = container.id;
        if (!uid) return;

        // Count slides
        const slides = container.querySelectorAll('.swiper-slide');
        const slideCount = slides.length;

        // Initialize Swiper
        const swiper = new Swiper(container.querySelector('.swiper'), {
            slidesPerView: 1.3,
            centeredSlides: true,
            spaceBetween: 20,
            loop: false,
            navigation: {
                nextEl: container.querySelector('.swiper-button-next'),
                prevEl: container.querySelector('.swiper-button-prev')
            },
            breakpoints: {
                700: { slidesPerView: 1.3 },
                1024: { slidesPerView: 1.5 }
            },
            on: {
                init: function() {
                    updateArrowVisibility(this, container);
                },
                slideChange: function() {
                    updateArrowVisibility(this, container);
                }
            }
        });

        // Hide arrows if only 1 slide
        if (slideCount <= 1) {
            hideAllArrows(container);
        }

        // Add event listeners for mobile arrows
        setupMobileNavigation(container, swiper);
    }

    function updateArrowVisibility(swiperInstance, container) {
        const prevArrows = container.querySelectorAll('.swiper-button-prev');
        const nextArrows = container.querySelectorAll('.swiper-button-next');
        const mobilePrevArrows = container.parentNode.querySelectorAll('.mobile-navigation .swiper-button-prev');
        const mobileNextArrows = container.parentNode.querySelectorAll('.mobile-navigation .swiper-button-next');

        const isAtBeginning = swiperInstance.isBeginning;
        const isAtEnd = swiperInstance.isEnd;

        // Handle prev arrows
        [...prevArrows, ...mobilePrevArrows].forEach(function(arrow) {
            if (isAtBeginning) {
                hideArrow(arrow);
            } else {
                showArrow(arrow);
            }
        });

        // Handle next arrows
        [...nextArrows, ...mobileNextArrows].forEach(function(arrow) {
            if (isAtEnd) {
                hideArrow(arrow);
            } else {
                showArrow(arrow);
            }
        });
    }

    function hideArrow(arrow) {
        arrow.style.display = 'none';
        arrow.style.visibility = 'hidden';
        arrow.style.opacity = '0';
        arrow.style.pointerEvents = 'none';
        arrow.classList.add('force-hidden');
    }

    function showArrow(arrow) {
        arrow.style.display = '';
        arrow.style.visibility = '';
        arrow.style.opacity = '';
        arrow.style.pointerEvents = '';
        arrow.classList.remove('force-hidden');
    }

    function hideAllArrows(container) {
        // Hide desktop arrows
        const desktopArrows = container.querySelectorAll('.mobile-hidden');
        desktopArrows.forEach(function(arrow) {
            hideArrow(arrow);
        });

        // Hide mobile navigation
        const mobileNav = container.nextElementSibling;
        if (mobileNav && mobileNav.classList.contains('mobile-navigation')) {
            mobileNav.style.display = 'none';
        }
    }

    function setupMobileNavigation(container, swiper) {
        const mobilePrev = container.parentNode.querySelector('.mobile-navigation .swiper-button-prev');
        const mobileNext = container.parentNode.querySelector('.mobile-navigation .swiper-button-next');

        if (mobilePrev) {
            mobilePrev.addEventListener('click', function(e) {
                e.preventDefault();
                swiper.slidePrev();
            });
        }

        if (mobileNext) {
            mobileNext.addEventListener('click', function(e) {
                e.preventDefault();
                swiper.slideNext();
            });
        }
    }

    // Export for potential external use
    window.KZCarousel = {
        initializeCarousels: initializeCarousels,
        initializeCarousel: initializeCarousel
    };

})();

/**
 * PhotoSwipe Fix for WooCommerce
 */
(function () {
    // Wait for DOM events
    document.addEventListener('DOMContentLoaded', initPhotoSwipeFix);
    window.addEventListener('load', initPhotoSwipeFix);
    document.addEventListener('wc_fragments_loaded', initPhotoSwipeFix);
    document.addEventListener('wc_fragments_refreshed', initPhotoSwipeFix);

    // Set up MutationObserver for dynamic galleries
    setupMutationObserver();

    function setupMutationObserver() {
        if (!window.MutationObserver)
            return;

        const observer = new MutationObserver((mutations) => {
            let shouldInit = false;

            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) {
                    for (let i = 0; i < mutation.addedNodes.length; i++) {
                        const node = mutation.addedNodes[i];
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.classList &&
                                    (node.classList.contains('woocommerce-product-gallery') ||
                                            node.querySelector('.woocommerce-product-gallery'))) {
                                shouldInit = true;
                                break;
                            }
                        }
                    }
                }
            });

            if (shouldInit) {
                initPhotoSwipeFix();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function initPhotoSwipeFix() {
        const gallerySelectors = [
            '.woocommerce-product-gallery__image a',
            '.woocommerce-product-gallery .woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image a'
        ];

        let galleryLinks = [];
        gallerySelectors.forEach(selector => {
            const links = document.querySelectorAll(selector);
            if (links.length) {
                galleryLinks = galleryLinks.concat(Array.from(links));
            }
        });

        if (!galleryLinks.length)
            return;

        galleryLinks.forEach((link) => {
            const img = link.querySelector('img');
            if (!img)
                return;

            // Get the large image URL specifically
            const largeImageUrl = img.getAttribute('data-large_image') || link.getAttribute('data-large_image') || link.getAttribute('href');
            if (largeImageUrl) {
                preloadAndUpdateDimensions(img, largeImageUrl);
            }
        });

        patchPhotoSwipe();
    }

    function preloadAndUpdateDimensions(img, imageUrl) {
        // Skip if the image already has valid dimensions
        const existingWidth = parseInt(img.getAttribute('data-large_image_width'), 10);
        const existingHeight = parseInt(img.getAttribute('data-large_image_height'), 10);

        if (existingWidth > 0 && existingHeight > 0) {
            return; // Don't override existing valid dimensions
        }

        const tempImg = new Image();

        tempImg.onload = function () {
            const width = tempImg.naturalWidth;
            const height = tempImg.naturalHeight;

            if (width && height) {
                img.setAttribute('data-large_image_width', width);
                img.setAttribute('data-large_image_height', height);
            } else {
                setFallbackDimensions(img);
            }
        };

        tempImg.onerror = function () {
            setFallbackDimensions(img);
        };

        tempImg.src = imageUrl;
    }

    function setFallbackDimensions(img) {
        // Only set fallback if no valid dimensions exist
        if (parseInt(img.getAttribute('data-large_image_width'), 10) > 0 &&
                parseInt(img.getAttribute('data-large_image_height'), 10) > 0) {
            return;
        }

        let width = null;
        let height = null;

        // Try to get dimensions from various sources
        if (img.hasAttribute('width') && img.hasAttribute('height')) {
            width = parseInt(img.getAttribute('width'), 10);
            height = parseInt(img.getAttribute('height'), 10);
        }

        if ((!width || !height) && img.style.width && img.style.height) {
            width = parseInt(img.style.width, 10);
            height = parseInt(img.style.height, 10);
        }

        if ((!width || !height) && img.naturalWidth && img.naturalHeight) {
            width = img.naturalWidth;
            height = img.naturalHeight;
        }

        if (width && height) {
            img.setAttribute('data-large_image_width', width);
            img.setAttribute('data-large_image_height', height);
        } else {
            img.setAttribute('data-large_image_width', 1024);
            img.setAttribute('data-large_image_height', 768);
        }
    }

    function patchPhotoSwipe() {
        if (window.photoswipePatched)
            return;

        const checkInterval = setInterval(() => {
            if (typeof window.PhotoSwipeLightbox !== 'undefined') {
                clearInterval(checkInterval);

                const originalAddFilterMethod = PhotoSwipeLightbox.prototype.addFilter;
                window.photoswipePatched = true;

                PhotoSwipeLightbox.prototype.addFilter = function (filterName, fn) {
                    if (filterName === 'domItemData') {
                        const originalFn = fn;
                        fn = function (itemData, element, linkEl) {
                            itemData = originalFn(itemData, element, linkEl);

                            if (!itemData.w || !itemData.h || itemData.w <= 0 || itemData.h <= 0) {
                                const img = linkEl.querySelector('img');

                                if (img) {
                                    // Use dimensions from data-large_image_width and data-large_image_height
                                    const width = img.getAttribute('data-large_image_width');
                                    const height = img.getAttribute('data-large_image_height');

                                    if (width && height) {
                                        itemData.w = parseInt(width, 10);
                                        itemData.h = parseInt(height, 10);
                                    }
                                }

                                // Fallback if we still don't have valid dimensions
                                if (!itemData.w || !itemData.h || itemData.w <= 0 || itemData.h <= 0) {
                                    itemData.w = 1024;
                                    itemData.h = 768;
                                }
                            }

                            return itemData;
                        };
                    }
                    return originalAddFilterMethod.call(this, filterName, fn);
                };
            }
        }, 100);

        setTimeout(() => clearInterval(checkInterval), 20000);
    }
})();
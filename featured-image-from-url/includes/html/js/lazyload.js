document.addEventListener("DOMContentLoaded", function () {
    let lazyImages = document.querySelectorAll("img[fifu-lazy='1']");

    function setSizesAttribute(image) {
        if (image.getAttribute('fifu-data-sizes') === 'auto') {
            let width = image.getAttribute('width') || image.clientWidth;
            image.setAttribute('sizes', `${width}px`);
        }
    }

    if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.getAttribute('fifu-data-src');
                    lazyImage.srcset = lazyImage.getAttribute('fifu-data-srcset');
                    setSizesAttribute(lazyImage);
                    lazyImage.removeAttribute('fifu-lazy');
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });

        lazyImages.forEach(function (lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    } else {
        // Fallback for browsers that do not support IntersectionObserver
        let lazyLoadThrottleTimeout;

        function debounce(func, wait) {
            return function () {
                clearTimeout(lazyLoadThrottleTimeout);
                lazyLoadThrottleTimeout = setTimeout(func, wait);
            };
        }

        function lazyLoad() {
            let scrollTop = window.scrollY;
            lazyImages.forEach(function (img) {
                if (img.offsetTop < (window.innerHeight + scrollTop)) {
                    img.src = img.getAttribute('fifu-data-src');
                    img.srcset = img.getAttribute('fifu-data-srcset');
                    setSizesAttribute(img);
                    img.removeAttribute('fifu-lazy');
                }
            });
            lazyImages = Array.prototype.filter.call(lazyImages, function (img) {
                return img.hasAttribute('fifu-lazy');
            });
            if (lazyImages.length == 0) {
                document.removeEventListener("scroll", lazyLoadHandler);
                window.removeEventListener("resize", lazyLoadHandler);
                window.removeEventListener("orientationchange", lazyLoadHandler);
            }
        }

        let lazyLoadHandler = debounce(lazyLoad, 20);

        document.addEventListener("scroll", lazyLoadHandler);
        window.addEventListener("resize", lazyLoadHandler);
        window.addEventListener("orientationchange", lazyLoadHandler);
    }
}, {once: true});
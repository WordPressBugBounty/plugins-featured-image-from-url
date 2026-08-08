// Shared helpers consumed by multiple FIFU admin scripts.
(function (window) {
    'use strict';

    /**
     * Normalize Google Drive URLs through the public CDN that the admin UI uses.
     *
     * @param {string} url
     * @returns {string}
     */
    function fifuCdnAdjust(url) {
        if (!url) return url;

        var isGDrive =
            url.indexOf('https://drive.google.com') !== -1 ||
            url.indexOf('https://drive.usercontent.google.com') !== -1;

        if (!isGDrive) return url;

        // Cloudinary fetch CDN
        return 'https://res.cloudinary.com/glide/image/fetch/' + encodeURIComponent(url);
    }

    window.fifu_cdn_adjust = fifuCdnAdjust;
}(window));

(function () {
    function applyFifuQuickBackgrounds() {
        document.querySelectorAll('.fifu-quick').forEach(function (el) {
            var original = el.getAttribute('image-url');
            if (!original) return;

            var adjust = window.fifu_cdn_adjust || function (value) { return value; };
            var cdn = adjust(original);
            if (cdn !== original) {
                el.style.backgroundImage = "url('" + cdn + "')";
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyFifuQuickBackgrounds);
    } else {
        applyFifuQuickBackgrounds();
    }
}());

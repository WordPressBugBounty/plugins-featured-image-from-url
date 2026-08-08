document.addEventListener("DOMContentLoaded", function () {
    const images = document.querySelectorAll('img');

    images.forEach(img => {
        img.addEventListener('load', function () {
            const width = img.naturalWidth;
            const height = img.naturalHeight;
            img.setAttribute('width', width);
            img.setAttribute('height', height);
        });

        // Trigger the load event manually if the image is already loaded
        if (img.complete) {
            img.dispatchEvent(new Event('load'));
        }
    });
});

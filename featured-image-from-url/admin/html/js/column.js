var FIFU_IMAGE_NOT_FOUND_URL = 'https://storage.googleapis.com/featuredimagefromurl/image-not-found-a.jpg';
var WC_PLACEHOLDER_IMAGE_URL = window.location.origin + '/wp-content/uploads/woocommerce-placeholder.webp';

jQuery(document).ready(function () {
    fifu_open_quick_lightbox();
    fifu_register_help_quick_edit();

    // Check all .fifu-quick thumbnails for invalid images
    fifu_check_image_validity();
});

// Extract the image validity checking into a separate function
function fifu_check_image_validity() {
    jQuery('div.fifu-quick').each(function () {
        var $div = jQuery(this);
        var imageUrl = $div.attr('image-url');
        var postId = $div.attr('post-id');

        // Skip if already processed
        if ($div.data('fifu-processed')) {
            return;
        }
        $div.data('fifu-processed', true);

        // IMAGE NOT FOUND: set background only, do NOT set placeholder for <img>
        if (imageUrl) {
            var img = new Image();
            img.onerror = function () {
                $div.css('background-image', 'url("' + FIFU_IMAGE_NOT_FOUND_URL + '")');
                // Update category thumbnail <img>
                jQuery(`tr#tag-${postId} td.thumb.column-thumb img[alt="Thumbnail"]`).each(function () {
                    if (!jQuery(this).attr('src').includes('woocommerce-placeholder')) {
                        this.src = WC_PLACEHOLDER_IMAGE_URL;
                    }
                });
                // Update product thumbnail <img>
                jQuery(`td.thumb.column-thumb a[href*="post=${postId}"] img`).each(function () {
                    if (!jQuery(this).attr('src').includes('woocommerce-placeholder')) {
                        this.src = WC_PLACEHOLDER_IMAGE_URL;
                    }
                });
            };
            img.src = imageUrl;
        }
    });
}

// Add a mutation observer to detect new .fifu-quick elements
var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) { // Element node
                    // Check if the added node is a .fifu-quick element
                    if (jQuery(node).hasClass('fifu-quick')) {
                        setTimeout(fifu_check_image_validity, 100);
                    }
                    // Check if the added node contains .fifu-quick elements
                    if (jQuery(node).find('.fifu-quick').length > 0) {
                        setTimeout(fifu_check_image_validity, 100);
                    }
                }
            });
        }
    });
});

// Start observing
observer.observe(document.body, {
    childList: true,
    subtree: true
});

var currentLightbox = null;

function fifu_open_quick_lightbox() {
    // Use delegated event binding to support dynamic elements
    jQuery(document).on('click', 'div.fifu-quick', function (evt) {
        evt.stopImmediatePropagation();
        let post_id = jQuery(this).attr('post-id');
        let image_url = jQuery(this).attr('image-url');
        let is_ctgr = jQuery(this).attr('is-ctgr');
        let is_variable = jQuery(this).attr('is-variable');

        if (is_variable) {
            let variable_box = `
                <div style="background: white; padding: 10px; border-radius: 1em;">
                    <div style="background-color:#32373c; text-align:center; width:100%; color:white; padding:6px; border-radius:5px;">
                        ${fifuColumnVars.labelVariable}
                    </div>
                    <table style="text-align:left; width:100%">
                        <tbody>
                            <tr class="color">
                                <th style="width:64px">ID</th>
                                <th style="min-width:100px">${fifuColumnVars.labelName}</th>
                                <th style="width:40px"><center><span class="dashicons dashicons-camera" style="font-size:20px;"></span></center></th>
                            </tr>
                            <tr class="color">
                                <th style="font-weight:unset">${post_id}</th>
                                <th style="font-weight:unset">${fifuQuickEditVars.posts[post_id]['title']}</th>
                                <th style="font-weight:unset">
                                    <div
                                        class="fifu-quick"
                                        post-id="${post_id}"
                                        video-url="${fifuQuickEditVars.parent[post_id]['video-url']}"
                                        video-src="${fifuQuickEditVars.parent[post_id]['video-src']}"
                                        is-ctgr="${fifuQuickEditVars.parent[post_id]['is-ctgr']}"
                                        image-url="${fifuQuickEditVars.parent[post_id]['image-url']}"
                                        is-variable=""
                                        style="height: ${fifuQuickEditVars.parent[post_id]['height']}px; width: ${fifuQuickEditVars.parent[post_id]['width']}px; background:url('${fifuQuickEditVars.parent[post_id]['image-url']}') no-repeat center center; background-size:cover; ${fifuQuickEditVars.parent[post_id]['border']}; cursor:pointer;">
                                    </div>
                                </th>
                            </tr>
                        </tbody>
                    </table>
                    <br>
                    <div style="background-color:#32373c; text-align:center; width:100%; color:white; padding:6px; border-radius:5px;">
                        ${fifuColumnVars.labelVariation}
                    </div>
                    ${fifuQuickEditVars.posts[post_id]['fifu_variable_table']}
                </div>
            `;
            jQuery.fancybox.open(variable_box, {
                touch: false,
                afterShow: function () {
                    console.log('show');
                    fifu_open_quick_lightbox();
                },
                beforeClose: function () {
                    let postParent = jQuery('table#fifu-variable-table').attr('post-parent');
                    fifuQuickEditVars.posts[postParent]['fifu_variable_table'] = jQuery('#fifu-variable-table')[0].outerHTML;
                },
                afterClose: function () {
                    console.log('close');
                },
            });
            return;
        }

        currentLightbox = post_id;

        // display
        let DISPLAY_NONE = 'display:none';
        let EMPTY = '';
        let showVideo = EMPTY;
        let showImageGallery = fifuColumnVars.onProductsPage ? EMPTY : DISPLAY_NONE;
        let showSlider = !fifuColumnVars.onCategoriesPage ? EMPTY : DISPLAY_NONE;
        let showVideoGallery = fifuColumnVars.onProductsPage ? EMPTY : DISPLAY_NONE;
        let showUploadButton = EMPTY;

        let url = image_url;
        url = (url == 'about:invalid' ? '' : url);
        let media, box;
        media = `<img loading="lazy" id="fifu-quick-preview" src="" post-id="${post_id}" style="max-height:600px; width:100%;">`;
        box = `
            <table>
                <tr>
                    <td id="fifu-left-column">${media}</td>
                    <td style="vertical-align:top; padding: 10px; background-color:#f6f7f7; width:250px; border-radius: 8px;">
                    <div class="fifu-pro" style="float:right;position:relative;top:-30px;left:35px"><a class="fifu-pro-link" href="https://fifu.app/" target="_blank" title="${fifuColumnVars.unlock}"><span class="dashicons dashicons-lock fifu-pro-icon"></span></a></div>
                        <div style="opacity:0.5;pointer-events:none;">
                            <div style="padding-bottom:5px">
                                <span class="dashicons dashicons-camera" style="font-size:20px;cursor:auto;" title="${fifuColumnVars.tipImage}"></span>
                                ${fifuColumnVars.labelImage}
                            </div>
                            <input id="fifu-quick-input-url" type="text" placeholder="${fifuColumnVars.urlImage}" value="" style="width:98%"/>
                            <br><br>

                            <div style="${showImageGallery}">
                                <div style="padding-bottom:5px">
                                    <span class="dashicons dashicons-format-gallery" style="font-size:20px;cursor:auto;"></span>
                                    ${fifuColumnVars.labelImageGallery}
                                </div>
                                <div id="gridDemoImage"></div>
                                <table>
                                    <tr>
                                        <th><img loading="lazy" src="https://storage.googleapis.com/featuredimagefromurl/icons/image.png" style="opacity: 0.3; width: 55px"></th>
                                        <th><img loading="lazy" src="https://storage.googleapis.com/featuredimagefromurl/icons/image.png" style="opacity: 0.3; width: 55px"></th>
                                        <th><img loading="lazy" src="https://storage.googleapis.com/featuredimagefromurl/icons/image.png" style="opacity: 0.3; width: 55px"></th>
                                        <th><img loading="lazy" src="https://storage.googleapis.com/featuredimagefromurl/icons/add.png" style="opacity: 0.3; width: 55px"></th>
                                    <tr>
                                </table>
                                <br>
                            </div>

                            <div style="padding-bottom:5px">
                                <span class="dashicons dashicons-search" style="font-size:20px;cursor:auto" title="${fifuColumnVars.tipSearch}"></span>
                                ${fifuColumnVars.labelSearch}
                                <span id="fifu_help_quick_edit" 
                                    class="dashicons dashicons-editor-help" 
                                    style="font-size:20px;cursor:pointer;">
                                </span>
                            </div>
                            <div>
                                <input id="fifu-quick-search-input-keywords" type="text" placeholder="${fifuColumnVars.keywords}" value="" style="width:75%"/>
                                <button id="fifu-search-button" class="fifu-quick-button" type="button" style="width:50px;border-radius:5px;height:30px;position:absolute;background-color:#3c434a"><span class="dashicons dashicons-search" style="font-size:16px"></span></button>
                            </div>
                            <br><br>
                        </div>
                        <div style="width:100%;opacity:0.5;pointer-events:none;">
                            <button id="fifu-clean-button" class="fifu-quick-button" type="button" style="background-color: #e7e7e7; color: black;">${fifuColumnVars.buttonClean}</button>
                            <button id="fifu-save-button" post-id="${post_id}" is-ctgr="${is_ctgr}" class="fifu-quick-button" type="button">${fifuColumnVars.buttonSave}</button>
                            <br>
                            <div style="${showUploadButton}">
                                <button id="fifu-upload-button" post-id="${post_id}" is-ctgr="${is_ctgr}" class="fifu-quick-button" style="background-color: #3c434a; width:97.5%; position:relative; top:2px" type="button">${fifuColumnVars.buttonUpload}</button>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        `;
        jQuery.fancybox.open(box, {
            touch: false,
            afterShow: async function () {
                if (currentLightbox) {
                    fifu_get_image_info(currentLightbox);
                }
            },
        });
        jQuery('#fifu-left-column').css('display', url ? 'table-cell' : 'none');
        jQuery('#fifu-quick-input-url').select();
        fifu_keypress_event();
    });
}

function fifu_keypress_event() {
    jQuery('div.fancybox-container.fancybox-is-open').keyup(function (e) {
        switch (e.which) {
            case 27:
                // esc
                jQuery.fancybox.close();
                break;
            default:
                break;
        }
    });
}

function fifu_get_image_info(post_id) {
    image_url = null;

    // Fix: Initialize category data if missing (new category case)
    if (fifuColumnVars.onCategoriesPage) {
        if (!fifuQuickEditCtgrVars.terms[post_id]) {
            // Try to get from DOM
            let $div = jQuery('.fifu-quick[post-id="' + post_id + '"]');
            let videoSrc = $div.attr('video-src') || '';
            fifuQuickEditCtgrVars.terms[post_id] = {
                fifu_image_url: videoSrc ? '' : ($div.attr('image-url') || ''),
                fifu_image_alt: '',
                fifu_video_url: $div.attr('video-url') || '',
                fifu_video_src: videoSrc
            };
        }
        image_url = fifuQuickEditCtgrVars.terms[post_id]['fifu_image_url'];
        image_alt = fifuQuickEditCtgrVars.terms[post_id]['fifu_image_alt'];
    } else {
        image_url = fifuQuickEditVars.posts[post_id]['fifu_image_url'];
    }

    if (image_url) {
        jQuery('input#fifu-quick-input-url').val(image_url);
        jQuery('#fifu-quick-input-url').select();
        let adjustedUrl = fifu_cdn_adjust(image_url);
        jQuery('img#fifu-quick-preview')
                .attr('src', adjustedUrl)
                .attr('onerror', `this.onerror=null;this.src='${FIFU_IMAGE_NOT_FOUND_URL}';`);
    }
}

function fifu_register_help_quick_edit() {
    jQuery(document).on('click', '#fifu_help_quick_edit', function () {
        jQuery.fancybox.open(`
            <div style="color:#1e1e1e;width:50%">
                <h1 style="background-color:whitesmoke;padding:20px;padding-left:0">${fifuColumnVars.txt_title_examples}</h1>                
                <h3>${fifuColumnVars.txt_title_keywords}</h3>
                <p style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px">sea,sun</p>
                <p>${fifuColumnVars.txt_desc_keywords}</p>
                <h3>${fifuColumnVars.txt_title_empty}</h3>
                <p style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px;height:40px"></p>
                <p>${fifuColumnVars.txt_desc_empty}</p>
            </div>`
                );
    });
}

function fifu_cdn_adjust(url) {
    if (url.includes("https://drive.google.com") || url.includes("https://drive.usercontent.google.com")) {
        let cdnUrl = 'https://res.cloudinary.com/glide/image/fetch/' + encodeURIComponent(url);
        return `https://i${Math.abs(crc32(cdnUrl) % 4)}.wp.com/${cdnUrl.replace(/^https?:\/\//, '')}`;
    }
    return url;
}

var crc32 = function (r) {
    for (var a, o = [], c = 0; c < 256; c++) {
        a = c;
        for (var f = 0; f < 8; f++)
            a = 1 & a ? 3988292384 ^ a >>> 1 : a >>> 1;
        o[c] = a
    }
    for (var n = -1, t = 0; t < r.length; t++)
        n = n >>> 8 ^ o[255 & (n ^ r.charCodeAt(t))];
    return(-1 ^ n) >>> 0
};

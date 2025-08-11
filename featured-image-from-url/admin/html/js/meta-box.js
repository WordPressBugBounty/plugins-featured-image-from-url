var FIFU_IMAGE_NOT_FOUND_URL = 'https://storage.googleapis.com/featuredimagefromurl/image-not-found-a.jpg';
function removeImage(fromUploadButton = false) {
    jQuery("#fifu_table_alt").hide();
    jQuery("#fifu_image").hide();
    jQuery("#fifu_link").hide();

    // Hide fallback image after removal
    jQuery("#fifu_image_fallback").hide();

    jQuery("#fifu_input_alt").val("");
    jQuery("#fifu_input_url").val("");
    jQuery("#fifu_keywords").val("");

    jQuery("#fifu_button").show();
    jQuery("#fifu_help").show(); // Show help icon when cleared

    if (fifuMetaBoxVars.is_sirv_active)
        jQuery("#fifu_sirv_button").show();

    fifu_show_wp_featured_image_section();

    // Only show WooCommerce placeholder if NOT triggered by upload button
    if (!fromUploadButton) {
        jQuery('#product_cat_thumbnail').find('img').attr('src', WC_PLACEHOLDER_IMAGE_URL);
        jQuery('#product_cat_thumbnail_id').val('');
        jQuery('.remove_image_button').hide();
    }

    // Set the local featured image to zero only if not triggered by upload button
    if (
            !fromUploadButton &&
            typeof wp !== 'undefined' &&
            wp.data &&
            wp.data.dispatch
            ) {
        const dispatchEditor = wp.data.dispatch('core/editor');
        if (dispatchEditor && typeof dispatchEditor.editPost === 'function') {
            dispatchEditor.editPost({featured_media: 0});
        }
    }

    // Trigger category thumbnail toggle when removing image
    if (typeof toggleCategoryThumbnail === 'function') {
        toggleCategoryThumbnail(true);
}
}

function previewImage() {
    var $url = jQuery("#fifu_input_url").val();

    if (jQuery("#fifu_input_url").val() && jQuery("#fifu_keywords").val())
        $message = fifuMetaBoxVars.wait;
    else
        $message = '';

    if (!$url.startsWith("http") && !$url.startsWith("//")) {
        jQuery("#fifu_keywords").val($url);
        if (!$url)
            jQuery("#fifu_keywords").val(' ');
    } else {
        runPreview($url);
    }
}

function runPreview($url) {
    $url = fifu_convert($url);

    jQuery("#fifu_lightbox").attr('href', $url);

    if ($url) {
        // Hide controls before validation, but DO NOT hide preview button
        jQuery("#fifu_table_alt").hide();
        jQuery("#fifu_link").hide();
        jQuery("#fifu_image").hide();

        // Clear previous background image to avoid showing old/bad image
        jQuery("#fifu_image").css('background-image', '');

        fifu_get_sizes();

        jQuery("#fifu_help").hide();
        jQuery("#fifu_premium").hide();

        if (fifuMetaBoxVars.is_sirv_active)
            jQuery("#fifu_sirv_button").hide();

        fifu_hide_wp_featured_image_section();
    }
}

jQuery(document).ready(function () {
    // help
    fifu_register_help();

    // lightbox
    fifu_open_lightbox();

    // start
    fifu_get_sizes();

    // input
    fifu_type_url();

    jQuery('.fifu-hover').on('mouseover', function (evt) {
        jQuery(this).css('color', '#23282e');
    });
    jQuery('.fifu-hover').on('mouseout', function (evt) {
        jQuery(this).css('color', 'white');
    });

    // title
    let text = jQuery("div#imageUrlMetaBox").find('h2').text();
    jQuery("div#imageUrlMetaBox").find('h2.hndle').text('');
    jQuery("div#imageUrlMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-camera"></span> ' + text + '</h4>');
    jQuery("div#imageUrlMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#imageUrlMetaBox").find('button.handle-order-lower').remove();

    text = jQuery("div#urlMetaBox").find('h2').text();
    jQuery("div#urlMetaBox").find('h2.hndle').text('');
    jQuery("div#urlMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-camera"></span> ' + text + '</h4>');
    jQuery("div#urlMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#urlMetaBox").find('button.handle-order-lower').remove();

    // Add click handler for preview button to open lightbox
    jQuery("#fifu_button").on('click', function () {
        var $url = jQuery("#fifu_input_url").val();
        if (!$url.startsWith("http") && !$url.startsWith("//")) {
            if ($url && $url != ' ') {
                fifu_start_lightbox($url, true, null, null, 'meta-box');
            }
        }
    });

    // Observe FIFU input and toggle WP featured image panel accordingly
    function updateWpFeaturedImagePanel() {
        var url = jQuery('#fifu_input_url').val();
        var $postImageDiv = jQuery('#postimagediv');
        var $toggleBtn = $postImageDiv.find('.handlediv');

        if (url && url.trim()) {
            fifu_hide_wp_featured_image_section();

            // Force closed and disable toggle
            $postImageDiv.addClass('closed');
            $toggleBtn.attr('aria-expanded', 'false').prop('disabled', true);
        } else {
            fifu_show_wp_featured_image_section();

            // Enable toggle and allow opening
            $toggleBtn.prop('disabled', false);
        }
    }

    // Initial check
    updateWpFeaturedImagePanel();

    // Listen for changes in the FIFU input (all user actions)
    jQuery('#fifu_input_url').on('input keyup paste', updateWpFeaturedImagePanel);

    // Fallback: poll for value changes (covers autocomplete by mouse)
    let lastFifuUrl = jQuery('#fifu_input_url').val();
    setInterval(function () {
        let current = jQuery('#fifu_input_url').val();
        if (current !== lastFifuUrl) {
            lastFifuUrl = current;
            updateWpFeaturedImagePanel();
        }
    }, 300);

    // Listen for successful category creation via AJAX and clear FIFU fields
    jQuery(document).ajaxComplete(function (event, xhr, settings) {
        // Check if this was a taxonomy add request (edit-tags.php)
        if (
                settings &&
                settings.data &&
                settings.data.indexOf('action=add-tag') !== -1 &&
                settings.data.indexOf('taxonomy=product_cat') !== -1
                ) {
            // Only clear if the response contains the new row (success)
            if (xhr && xhr.responseText && xhr.responseText.indexOf('class="level-0"') !== -1) {
                removeImage(false);
            }
        }
    });
});

// Block editor: auto-remove featured image if displayed URL is external (not this site's domain)
(function () {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.select || !wp.data.dispatch) {
        return;
    }

    // Guard against multiple registrations
    if (window.__fifuAuthorRemoveInit) {
        return;
    }
    window.__fifuAuthorRemoveInit = true;

    let scheduled = false;
    let lastCheckedMediaId = -1; // skip repeated same IDs within a bounce
    const processedIds = new Set(); // permanently skip IDs already checked this session
    let tickScheduled = false; // debounce wp.data churn

    const MAX_URL_RESOLVE_RETRIES = 10;
    const URL_RETRY_DELAY_MS = 200;

    function isInternalUrl(url) {
        if (!url || typeof url !== 'string')
            return false;
        try {
            const u = new URL(url, window.location.href);
            return u.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function getDisplayedFeaturedImageUrl() {
        // Try common Gutenberg selectors
        const selectors = [
            '.editor-post-featured-image img',
            '.editor-post-featured-image .components-responsive-wrapper__content',
            '.editor-post-featured-image__container img',
            '.components-panel__body .editor-post-featured-image img'
        ];
        for (const s of selectors) {
            const img = document.querySelector(s);
            if (img && (img.currentSrc || img.src)) {
                return img.currentSrc || img.src;
            }
        }
        return null;
    }

    async function resolveDisplayedUrlWithRetry() {
        for (let i = 0; i < MAX_URL_RESOLVE_RETRIES; i++) {
            const url = getDisplayedFeaturedImageUrl();
            if (url)
                return url;
            await new Promise((r) => setTimeout(r, URL_RETRY_DELAY_MS));
        }
        return null;
    }

    function clickWpRemoveButton() {
        const btns = document.querySelectorAll(
                '.editor-post-featured-image__actions button.editor-post-featured-image__action, button.components-button.editor-post-featured-image__action'
                );
        const removeBtn = btns[btns.length - 1];
        if (removeBtn) {
            removeBtn.click();
            return true;
        }
        return false;
    }

    wp.data.subscribe(function () {
        if (tickScheduled)
            return;
        tickScheduled = true;
        setTimeout(function () {
            tickScheduled = false;
            try {
                const sel = wp.data.select('core/editor');
                if (!sel || !sel.getEditedPostAttribute)
                    return;

                const mediaId = sel.getEditedPostAttribute('featured_media') || 0;

                // Skip if same ID already processed in last tick
                if (mediaId === lastCheckedMediaId)
                    return;

                // Track 0 as well to avoid repeated work when removed
                if (!mediaId) {
                    lastCheckedMediaId = 0;
                    return;
                }

                // Hard skip IDs already checked this session (prevents loops after save)
                if (processedIds.has(mediaId)) {
                    lastCheckedMediaId = mediaId;
                    return;
                }

                // From here we will process this new ID exactly once for this session
                lastCheckedMediaId = mediaId;

                if (scheduled)
                    return;
                scheduled = true;

                resolveDisplayedUrlWithRetry().then(function (url) {
                    // Mark as processed regardless of result to avoid repeated churn
                    processedIds.add(mediaId);

                    if (url && !isInternalUrl(url)) {
                        setTimeout(function () {
                            if (!clickWpRemoveButton()) {
                                const dispatch = wp.data.dispatch('core/editor');
                                if (dispatch && typeof dispatch.editPost === 'function') {
                                    dispatch.editPost({featured_media: 0});
                                }
                            }
                            scheduled = false;
                        }, 10);
                    } else {
                        scheduled = false;
                    }
                }).catch(function (e) {
                    console.log('[FIFU][domain-remove] error resolving displayed URL:', e);
                    processedIds.add(mediaId); // guard anyway
                    scheduled = false;
                });
            } catch (e) {
                console.log('[FIFU][domain-remove] subscribe handler error:', e);
                scheduled = false;
            }
        }, 100); // debounce
    });
})();

function fifu_get_sizes() {
    var image_url = jQuery("#fifu_input_url").val();
    if (!image_url || (!image_url.startsWith("http") && !image_url.startsWith("//"))) {
        // No image URL: reset to initial state, do NOT show fallback
        jQuery("#fifu_table_alt").hide();
        jQuery("#fifu_link").hide();
        jQuery("#fifu_image").hide();
        jQuery("#fifu_image_fallback").hide();
        jQuery("#fifu_button").show();
        jQuery("#fifu_help").show(); // Show help icon when empty/invalid
        return;
    }
    fifu_get_image(image_url);
}

function fifu_get_image(url) {
    var image = new Image();
    image.onload = function () {
        fifu_store_sizes(this);

        // Set background image only after validation
        let adjustedUrl = fifu_cdn_adjust(url);
        jQuery("#fifu_image").css('background-image', "url('" + adjustedUrl + "')");

        jQuery("#fifu_table_alt").show();
        jQuery("#fifu_link").show();
        jQuery("#fifu_image").show();
        ensureImageFallback().hide();
        jQuery("#fifu_button").hide();
        jQuery("#fifu_help").hide(); // Hide help icon when valid image
    };
    image.onerror = function () {
        showImageFallback();
    };
    jQuery(image).attr('src', url);
}

function fifu_store_sizes($) {
    jQuery("#fifu_input_image_width").val($.naturalWidth);
    jQuery("#fifu_input_image_height").val($.naturalHeight);
}

function fifu_open_lightbox() {
    jQuery("#fifu_image").on('click', function (evt) {
        evt.stopImmediatePropagation();

        // Do not open lightbox if the error image is set as background
        const errorImg = FIFU_IMAGE_NOT_FOUND_URL;
        const bg = jQuery("#fifu_image").css('background-image');
        if (bg && bg.includes(errorImg)) {
            return;
        }

        let url = fifu_convert(jQuery("#fifu_input_url").val());
        let adjustedUrl = fifu_cdn_adjust(url);
        jQuery.fancybox.open('<img loading="lazy" src="' + adjustedUrl + '" style="max-width:900px;width:100%;max-height:600px">');
    });
}

function fifu_type_url() {
    jQuery("#fifu_input_url").on('input', function (evt) {
        evt.stopImmediatePropagation();
        fifu_get_sizes();
    });
}

function fifu_register_help() {
    jQuery('#fifu_help').on('click', function () {
        jQuery.fancybox.open(`
            <div style="color:#1e1e1e;width:50%">
                <h1 style="background-color:whitesmoke;padding:20px;padding-left:0">${fifuMetaBoxVars.txt_title_examples}</h1>
                <h3>${fifuMetaBoxVars.txt_title_url}</h3>
                <div style="display:flex;align-items:center;gap:8px;width:100%;">
                    <p id="fifu-copy-url" style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px;margin:0;flex:1;">https://cdn.pixabay.com/photo/2014/12/28/13/20/wordpress-581849_960_720.jpg</p>
                    <button id="fifu-copy-url-btn" title="" style="background:none;border:none;cursor:pointer;padding:0;">
                        <span class="dashicons dashicons-admin-page" style="font-size:20px;color:#007cba;"></span>
                    </button>
                </div>
                <p>${fifuMetaBoxVars.txt_desc_url}</p>
                <h3>${fifuMetaBoxVars.txt_title_keywords}</h3>
                <p style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px">sea,sun</p>
                <p>${fifuMetaBoxVars.txt_desc_keywords}</p>
                <h3>${fifuMetaBoxVars.txt_title_empty}</h3>
                <div class="fifu-pro" style="position:relative;top:-45px;right:-15px;float:right;" title="${fifuMetaBoxVars.txt_unlock}"><span class="dashicons dashicons-lock fifu-pro-icon"></span></a></div>
                <p style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px;height:40px"></p>
                <p>${fifuMetaBoxVars.txt_desc_empty}</p>
                <h1 style="background-color:whitesmoke;padding:20px;padding-left:0">${fifuMetaBoxVars.txt_title_more}</h1>
                <p>${fifuMetaBoxVars.txt_desc_more}</p>
            </div>`
                );

        // Add copy functionality after Fancybox opens
        setTimeout(function () {
            jQuery('#fifu-copy-url-btn').on('click', function () {
                const url = jQuery('#fifu-copy-url').text();
                navigator.clipboard.writeText(url);
                jQuery(this).find('span').css('color', '#46b450'); // Visual feedback
            });
        }, 500);
    });
}

// Show/hide WP featured image section in block editor

function fifu_toggle_featured_image_panel(show) {
    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select) {
        const EDIT_POST_STORE = wp.data.select('core/editor') ? 'core/editor' : 'core/edit-post';
        const dispatchStore = wp.data.dispatch(EDIT_POST_STORE);
        const selectStore = wp.data.select(EDIT_POST_STORE);

        // Clear any pending timeouts
        if (window.fifuFeaturedImageTimer) {
            clearTimeout(window.fifuFeaturedImageTimer);
        }

        // Single timeout with all operations
        window.fifuFeaturedImageTimer = setTimeout(function () {
            // Get panel selectors
            const panelSelectors = [
                '[aria-label="Featured image"]',
                '[data-panel="featured-image"]',
                '.editor-post-featured-image',
                '.components-panel__body[data-title="Featured image"]'
            ];
            let panelSelectorFound = '';
            let panelExists = false;

            for (const sel of panelSelectors) {
                if (document.querySelector(sel)) {
                    panelExists = true;
                    panelSelectorFound = sel;
                    break;
                }
            }

            // Try WordPress API first
            const toggleEditorPanelEnabled = dispatchStore && dispatchStore.toggleEditorPanelEnabled;
            const isEditorPanelEnabled = selectStore && selectStore.isEditorPanelEnabled;

            if (toggleEditorPanelEnabled && isEditorPanelEnabled) {
                const enabled = isEditorPanelEnabled('featured-image') || false;

                if ((show && !enabled) || (!show && enabled)) {
                    toggleEditorPanelEnabled('featured-image');
                }
            }

            // Fallback to direct DOM manipulation
            if (panelSelectorFound) {
                if (show) {
                    jQuery(panelSelectorFound).show();
                } else {
                    jQuery(panelSelectorFound).hide();
                }
            }
        }, 150);
    }
}

// Hide WP featured image section in block editor
function fifu_hide_wp_featured_image_section() {
    fifu_toggle_featured_image_panel(false);
}

// Show WP featured image section in block editor
function fifu_show_wp_featured_image_section() {
    fifu_toggle_featured_image_panel(true);
}

function areInputsEmpty(selector) {
    var empty = true;
    jQuery(selector).each(function () {
        var val = jQuery(this).val().trim();
        if (val && val !== "undefined") {
            empty = false;
            return false; // break loop
        }
    });
    return empty;
}

function ensureImageFallback() {
    let $img = jQuery('#fifu_image_fallback');
    if (!$img.length) {
        $img = jQuery('<img>', {
            id: 'fifu_image_fallback',
            src: FIFU_IMAGE_NOT_FOUND_URL,
            style: 'max-width:100%;display:none;border-radius:3px;'
        });
        jQuery('#fifu_meta_box').prepend($img);
    }
    return $img;
}

function showImageFallback() {
    var image_url = jQuery("#fifu_input_url").val();
    if (!image_url) {
        // No image URL: do NOT show fallback
        jQuery("#fifu_image_fallback").hide();
        jQuery("#fifu_button").show();
        jQuery("#fifu_help").show(); // Show help icon when empty
        return;
    }
    // Hide all controls except preview button and fallback image
    jQuery("#fifu_table_alt").hide();
    jQuery("#fifu_link").hide();
    jQuery("#fifu_upload").hide();
    jQuery("#fifu_image").hide();

    // Show preview button only in fallback state
    jQuery("#fifu_button").show();

    // Show fallback image
    ensureImageFallback().attr("src", FIFU_IMAGE_NOT_FOUND_URL).show();

    jQuery("#fifu_help").show(); // Show help icon when fallback is shown
}

(function ($) {
    function toggleCategoryThumbnail(isUserAction = false) {
        var imgUrl = $('#fifu_input_url').val().trim();

        if (imgUrl) {
            $('.form-field.term-thumbnail-wrap').hide();
        } else {
            $('.form-field.term-thumbnail-wrap').show();

            // Only replace with placeholder if this is a user action (not page load)
            if (isUserAction) {
                $('#product_cat_thumbnail').find('img').attr('src', WC_PLACEHOLDER_IMAGE_URL);
                $('#product_cat_thumbnail_id').val('');
                $('.remove_image_button').hide();
            }
    }
    }

    $(document).ready(function () {
        // fire on any user edit - pass true to indicate user action
        $('#fifu_input_url')
                .on('input keyup paste', function () {
                    toggleCategoryThumbnail(true);
                });

        // initial state - pass false to indicate not user action
        toggleCategoryThumbnail(false);

        // also poll for programmatic .val() changes - pass false for polling
        var last = '';
        setInterval(function () {
            var curr = $('#fifu_input_url').val().trim();
            if (curr !== last) {
                last = curr;
                toggleCategoryThumbnail();
            }
        }, 250);
    });
})(jQuery);

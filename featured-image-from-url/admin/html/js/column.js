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
        var imageUrl = fifu_cdn_adjust($div.attr('image-url'));
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
if (document.body) {
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

var currentLightbox = null;
var fifuPreviousInputs = [];
var fifuQuickUploadInFlight = false;
var fifuVariableProductCache = {};
var fifuVariableProductInFlight = {};
var fifuQuickEditItemCache = {};
var fifuQuickEditItemInFlight = {};
var fifuActiveQuickEditContext = null;

function fifu_get_quick_hidden_input_value(inputId) {
    const $input = jQuery('#' + inputId);
    if (!$input.length) {
        return '';
    }

    const currentValue = $input.val();
    if (currentValue !== undefined && currentValue !== null && currentValue !== 'undefined') {
        return String(currentValue).trim();
    }

    const attrValue = $input.attr('value');
    if (attrValue !== undefined && attrValue !== null && attrValue !== 'undefined') {
        return String(attrValue).trim();
    }

    return '';
}

function fifu_set_copy_debug_button_state($button, label, cssClass, disabled) {
    if (!$button || !$button.length) {
        return;
    }

    $button.find('.fifu-copy-debug-data-label').text(label);
    $button.prop('disabled', !!disabled);
    $button.toggleClass('fifu-copy-debug-data-button--copied', cssClass === 'fifu-copy-debug-data-button--copied');
    $button.toggleClass('fifu-copy-debug-data-button--failed', cssClass === 'fifu-copy-debug-data-button--failed');
}

function fifu_escape_html(value) {
    return String(value === undefined || value === null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function fifu_load_variable_product(parentId) {
    const key = String(parentId);
    if (fifuVariableProductCache[key]) {
        return Promise.resolve(fifuVariableProductCache[key]);
    }
    if (fifuVariableProductInFlight[key]) {
        return fifuVariableProductInFlight[key];
    }
    fifuVariableProductInFlight[key] = new Promise(function (resolve, reject) {
        jQuery.ajax({
            method: 'GET',
            url: fifuColumnVars.restUrl + fifuColumnVars.restNamespaceV2 + '/quick_edit_variations_api/',
            data: { parent_id: parentId },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fifuColumnVars.nonce);
            },
            success: function (response) {
                fifuVariableProductCache[key] = response;
                resolve(response);
            },
            error: function (xhr) {
                reject(xhr);
            },
            complete: function () {
                delete fifuVariableProductInFlight[key];
            }
        });
    });
    return fifuVariableProductInFlight[key];
}

function fifu_load_quick_edit_item(postId) {
    const key = String(postId);
    if (fifuQuickEditItemCache[key]) {
        return Promise.resolve(fifuQuickEditItemCache[key]);
    }
    if (fifuQuickEditItemInFlight[key]) {
        return fifuQuickEditItemInFlight[key];
    }
    fifuQuickEditItemInFlight[key] = new Promise(function (resolve, reject) {
        jQuery.ajax({
            method: 'GET',
            url: fifuColumnVars.restUrl + fifuColumnVars.restNamespaceV2 + '/quick_edit_item_api/',
            data: { post_id: postId },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fifuColumnVars.nonce);
            },
            success: function (response) {
                fifuQuickEditItemCache[key] = response;
                resolve(response);
            },
            error: function (xhr) {
                reject(xhr);
            },
            complete: function () {
                delete fifuQuickEditItemInFlight[key];
            }
        });
    });
    return fifuQuickEditItemInFlight[key];
}

function fifu_invalidate_quick_edit_cache(postId, parentId) {
    delete fifuQuickEditItemCache[String(postId)];
    const normalizedParentId = parseInt(parentId, 10) || 0;
    if (normalizedParentId > 0) {
        delete fifuVariableProductCache[String(normalizedParentId)];
    }
}

function fifu_render_variable_product_selector(data) {
    const variations = Array.isArray(data && data.variations) ? data.variations : [];
    const parentDisplay = data && data.parent_display ? data.parent_display : {};
    const parentId = fifu_escape_html(data && data.parent_id ? data.parent_id : '');
    const title = fifu_escape_html(data && data.title ? data.title : '');
    const parentHeight = fifu_escape_html(parentDisplay.height || parentDisplay['height'] || 40);
    const parentWidth = fifu_escape_html(parentDisplay.width || parentDisplay['width'] || 40);
    const parentBorder = fifu_escape_html(parentDisplay.border || parentDisplay['border'] || '');
    const parentImageUrl = fifu_escape_html(parentDisplay.image_url || parentDisplay['image-url'] || '');
    const columnLayout = '<colgroup><col style="width:64px"><col><col style="width:40px"></colgroup>';
    let html = '<div id="fifu-variable-selector-content" class="fifu-variable-selector-content" data-variable-product="1" style="background:white; padding:10px; border-radius:1em;">';
    html += '<div style="background-color:#32373c; text-align:center; width:100%; color:white; padding:6px; border-radius:5px;">' + fifu_escape_html(fifuColumnVars.labelVariable) + '</div>';
    html += '<table style="text-align:left; width:100%">' + columnLayout + '<tbody>';
    html += '<tr class="color"><th>ID</th><th>' + fifu_escape_html(fifuColumnVars.labelName) + '</th><th><center><span class="dashicons dashicons-camera" style="font-size:20px;"></span></center></th></tr>';
    html += '<tr class="color">';
    html += '<th style="font-weight:unset">' + parentId + '</th>';
    html += '<th style="font-weight:unset">' + title + '</th>';
    html += '<th style="font-weight:unset"><div class="fifu-quick" post-id="' + parentId + '" is-ctgr="" image-url="' + parentImageUrl + '" is-variable="" data-fifu-lazy-item="1" data-parent-id="' + parentId + '" style="height:' + parentHeight + 'px;width:' + parentWidth + 'px;background:url(\'' + parentImageUrl + '\') no-repeat center center;background-size:cover;' + parentBorder + 'cursor:pointer;"></div></th>';
    html += '</tr></tbody></table><br>';
    html += '<div style="background-color:#32373c; text-align:center; width:100%; color:white; padding:6px; border-radius:5px;">' + fifu_escape_html(fifuColumnVars.labelVariation) + '</div>';
    html += '<table style="text-align:left; width:100%">' + columnLayout + '<tbody>';
    variations.forEach(function (variation) {
        const display = variation && variation.display ? variation.display : {};
        const attrs = variation && variation.attributes ? variation.attributes : {};
        const variationId = fifu_escape_html(variation && variation.post_id ? variation.post_id : '');
        const height = fifu_escape_html(display.height || 40);
        const width = fifu_escape_html(display.width || 40);
        const border = fifu_escape_html(display.border || '');
        const image = fifu_escape_html(display.image_url || '');
        const label = Object.values(attrs).filter(Boolean).join(' / ');
        html += '<tr class="color"><th style="font-weight:unset">' + variationId + '</th><th style="font-weight:unset">' + fifu_escape_html(label) + '</th><th style="font-weight:unset"><div class="fifu-quick" post-id="' + variationId + '" is-ctgr="" image-url="' + image + '" is-variable="" data-fifu-lazy-item="1" data-parent-id="' + parentId + '" data-fifu-readonly-variation="1" title="Variation" style="height:' + height + 'px;width:' + width + 'px;background:url(\'' + image + '\') no-repeat center center;background-size:cover;' + border + 'cursor:pointer;"></div></th></tr>';
    });
    html += '</tbody></table>';
    html += fifuColumnVars.isDebugEnabled ? '<button id="fifu-copy-debug-data-button" class="fifu-quick-button fifu-copy-debug-data-button" type="button" onclick="fifu_copy_quick_edit_debug_data(' + parentId + ', this, false)"><span class="dashicons dashicons-clipboard fifu-copy-debug-data-icon" aria-hidden="true"></span><span class="fifu-copy-debug-data-label">' + fifu_escape_html(fifuColumnVars.buttonCopyDebugData) + '</span></button>' : '';
    html += '</div>';
    return html;
}

function fifu_copy_quick_edit_debug_data(postId, button, isCtgr) {
    if (!fifuColumnVars.isDebugEnabled) {
        return;
    }

    const isCategory = isCtgr === true || isCtgr === '1' || isCtgr === 'true' || isCtgr === 1;
    const $button = jQuery(button);
    const originalLabel = $button.find('.fifu-copy-debug-data-label').text();
    let restored = false;

    const restoreButton = function () {
        if (restored) {
            return;
        }
        restored = true;
        setTimeout(function () {
            fifu_set_copy_debug_button_state($button, originalLabel, '', false);
        }, 1500);
    };

    fifu_set_copy_debug_button_state($button, 'Copying...', '', true);

    jQuery.ajax({
        method: 'GET',
        url: fifuColumnVars.quickEditDebugDataUrl,
        data: {
            post_id: postId,
            is_ctgr: isCategory ? 1 : 0,
            taxonomy: fifuColumnVars.taxonomy || ''
        },
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuColumnVars.nonce);
        },
        success: async function (data) {
            try {
                const json = JSON.stringify(data, null, 2);

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(json);
                } else {
                    const $textarea = jQuery('<textarea readonly></textarea>').css({position: 'fixed', left: '-9999px', top: '0'}).val(json);
                    jQuery('body').append($textarea);
                    $textarea[0].select();
                    document.execCommand('copy');
                    $textarea.remove();
                }

                fifu_set_copy_debug_button_state($button, 'Copied!', 'fifu-copy-debug-data-button--copied', true);
                restoreButton();
            } catch (error) {
                fifu_set_copy_debug_button_state($button, 'Copy failed', 'fifu-copy-debug-data-button--failed', true);
                restoreButton();
            }
        },
        error: function () {
            fifu_set_copy_debug_button_state($button, 'Copy failed', 'fifu-copy-debug-data-button--failed', true);
            restoreButton();
        }
    });
}

function fifu_open_quick_lightbox() {
    jQuery(document).on('click', 'div.fifu-quick', function (evt) {
        evt.stopImmediatePropagation();

        const $clicked = jQuery(this);
        let post_id = $clicked.attr('post-id');
        let image_url = $clicked.attr('image-url');
        let is_ctgr = $clicked.attr('is-ctgr');
        let is_variable = $clicked.attr('is-variable');
        const parentIdAttribute = $clicked.attr('data-parent-id');
        const isLazyItem = $clicked.attr('data-fifu-lazy-item') === '1';
        const isVariableProduct = !!is_variable || $clicked.closest('[data-variable-product="1"]').length > 0;

        if (parentIdAttribute !== undefined && parentIdAttribute !== null && parentIdAttribute !== '') {
            fifuActiveQuickEditContext = {
                postId: parseInt(post_id, 10) || 0,
                parentId: parseInt(parentIdAttribute, 10) || 0
            };
        } else {
            fifuActiveQuickEditContext = null;
        }

        if (isLazyItem) {
            if ($clicked.data('fifu-loading-item')) {
                return;
            }

            $clicked.data('fifu-loading-item', true);

            fifu_load_quick_edit_item(post_id)
                .then(function (response) {
                    window.fifuQuickEditVars = window.fifuQuickEditVars || {};
                    fifuQuickEditVars.posts = fifuQuickEditVars.posts || {};
                    fifuQuickEditVars.posts[post_id] = response.payload || {};

                    if (response.display) {
                        $clicked.attr('image-url', response.display.image_url || '');
                    }

                    $clicked.removeAttr('data-fifu-lazy-item');
                    $clicked.data('fifu-loading-item', false);
                    $clicked.trigger('click');
                })
                .catch(function (xhr) {
                    $clicked.data('fifu-loading-item', false);

                    const responseMessage = xhr && xhr.responseJSON && typeof xhr.responseJSON.message === 'string' ? xhr.responseJSON.message.trim() : '';
                    const message = responseMessage || 'Unable to load item.';

                    jQuery.fancybox.open(
                        '<div class="fifu-quick-edit-item-error">' + fifu_escape_html(message) + '</div>',
                        { touch: false }
                    );
                });

            return;
        }

        const is_readonly_variation = $clicked.attr('data-fifu-readonly-variation') === '1';

        if (is_variable) {
            fifu_load_variable_product(post_id)
                .then(function (response) {
                    response.parent_display = window.fifuQuickEditVars && fifuQuickEditVars.parent && fifuQuickEditVars.parent[post_id] ? fifuQuickEditVars.parent[post_id] : {};

                    jQuery.fancybox.open(
                        fifu_render_variable_product_selector(response),
                        { touch: false }
                    );
                })
                .catch(function (xhr) {
                    const responseMessage = xhr && xhr.responseJSON && typeof xhr.responseJSON.message === 'string' ? xhr.responseJSON.message.trim() : '';
                    const message = responseMessage || 'Unable to load variations.';

                    jQuery.fancybox.open(
                        '<div class="fifu-variable-selector-error">' + fifu_escape_html(message) + '</div>',
                        { touch: false }
                    );
                });

            return;
        }

        currentLightbox = post_id;

        let url = image_url;
        url = (url == 'about:invalid' ? '' : url);
        const fifuReadonlyAttr = is_readonly_variation ? 'disabled="disabled" readonly="readonly" aria-disabled="true"' : '';
        const fifuReadonlyButtonAttr = is_readonly_variation ? 'disabled="disabled" aria-disabled="true"' : '';
        const fifuReadonlyClass = is_readonly_variation ? ' fifu-quick-readonly-variation' : '';
        const media = `<img loading="lazy" id="fifu-quick-preview" src="" post-id="${post_id}" style="max-height:600px; width:100%;">`;
        const box = `
            <div ${is_readonly_variation ? 'data-fifu-readonly-variation-modal="1"' : ''} class="fifu-quick-modal${fifuReadonlyClass}">
            <table>
                <tr>
                    <td id="fifu-left-column">${media}</td>
                    <td style="vertical-align:top; padding: 10px; background-color:#f6f7f7; width:250px; border-radius: 8px;">
                        <div>
                            <div style="padding-bottom:5px">
                                <span class="dashicons dashicons-camera" style="font-size:20px;cursor:auto;" title="${fifuColumnVars.tipImage}"></span>
                                ${fifuColumnVars.labelImage}
                            </div>
                            <input id="fifu-quick-input-url" class="fifu-quick-input" type="text" placeholder="${fifuColumnVars.urlImage}" value="" style="width:98%" ${fifuReadonlyAttr}/>
                            <br><br>

                            <div style="padding-bottom:5px">
                                <span class="dashicons dashicons-search" style="font-size:20px;cursor:auto" title="${fifuColumnVars.tipSearch}"></span>
                                ${fifuColumnVars.labelSearch}
                                <span id="fifu_help_quick_edit" class="dashicons dashicons-editor-help" style="font-size:20px;cursor:pointer;"></span>
                            </div>
                            <div>
                                <input id="fifu-quick-search-input-keywords" class="fifu-quick-input" type="text" placeholder="${fifuColumnVars.keywords}" value="" style="width:75%" ${fifuReadonlyAttr}/>
                                <button id="fifu-search-button" class="fifu-quick-button" type="button" style="width:50px;border-radius:5px;height:40px;position:absolute;background-color:#3c434a" ${fifuReadonlyButtonAttr}><span class="dashicons dashicons-search" style="font-size:21px"></span></button>
                            </div>
                            <br><br>
                        </div>
                        <div style="width:100%">
                            ${fifuColumnVars.isDebugEnabled ? `<button id="fifu-copy-debug-data-button" class="fifu-quick-button fifu-copy-debug-data-button" type="button" onclick="fifu_copy_quick_edit_debug_data(${post_id}, this, '${is_ctgr}')"><span class="dashicons dashicons-clipboard fifu-copy-debug-data-icon" aria-hidden="true"></span><span class="fifu-copy-debug-data-label">${fifuColumnVars.buttonCopyDebugData}</span></button>` : ''}
                            <button id="fifu-clean-button" class="fifu-quick-button" type="button" style="background-color: #e7e7e7; color: black;" ${fifuReadonlyButtonAttr}>${fifuColumnVars.buttonClean}</button>
                            <button id="fifu-save-button" post-id="${post_id}" is-ctgr="${is_ctgr}" class="fifu-quick-button" type="button" ${fifuReadonlyButtonAttr}>${fifuColumnVars.buttonSave}</button>
                            <br>
                        </div>
                    </td>
                </tr>
            </table>
            </div>
        `;

        fifu_include_input_hidden(post_id);
        jQuery.fancybox.open(box, {
            touch: false,
            afterShow: async function () {
                if (currentLightbox) {
                    fifu_get_image_info(currentLightbox);
                }

            },
            afterClose: function () {}
        });
        jQuery('#fifu-left-column').css('display', url ? 'table-cell' : 'none');
        jQuery('#fifu-quick-input-url').select();
        fifu_change_image_event();
        fifu_save_event();
        fifu_keypress_event();
        fifu_toggle_search_controls();
        fifu_search_event();
    });
}

function fifu_toggle_search_controls() {
    if (fifu_is_readonly_variation_modal()) {
        return;
    }
    jQuery('#fifu-quick-search-input-keywords').prop('disabled', false);
    jQuery('#fifu-search-button').prop('disabled', false);
}

function fifu_is_readonly_variation_modal() {
    return jQuery('[data-fifu-readonly-variation-modal="1"]').length > 0;
}

function fifu_change_image_event() {
    // image
    jQuery('#fifu-quick-input-url').on('input', function () {
        if (fifu_is_readonly_variation_modal()) {
            return;
        }
        url = jQuery('#fifu-quick-input-url').val();
        post_id = jQuery('#fifu-save-button').attr('post-id');
        jQuery('#fifu-left-column').css('display', url ? 'table-cell' : 'none');
        jQuery('#fifu-quick-preview').remove();
        let adjustedUrl = fifu_cdn_adjust(url);
        jQuery('#fifu-left-column').append(
                `<img loading="lazy" id="fifu-quick-preview" src="${adjustedUrl}" post-id="${post_id}" style="max-height:600px; width:100%;"
                onerror="this.onerror=null;this.src='${FIFU_IMAGE_NOT_FOUND_URL}';">`
                );
    });
    // clean
    jQuery('#fifu-clean-button').on('click', function () {
        if (fifu_is_readonly_variation_modal()) {
            return;
        }
        jQuery('#fifu-left-column').css('display', 'none');
        jQuery('#fifu-quick-preview').remove();
        jQuery('#fifu-quick-input-url').val('');
        jQuery('#fifu-quick-search-input-keywords').val('');

        jQuery('[id^=fifu_input_]').each(function () {
            jQuery(this).val('');
        });
        jQuery('[id^=fifu-image-]').each(function () {
            jQuery(this).css('background', '');
            jQuery(this).css('opacity', '');
        });

    });
}

function fifu_save_event() {
    jQuery('#fifu-save-button').on('click', function () {
        if (fifu_is_readonly_variation_modal()) {
            return;
        }
        post_id = jQuery(this).attr('post-id');
        is_ctgr = jQuery(this).attr('is-ctgr');

        image_url = jQuery("#fifu-quick-input-url")[0].value;

        img = jQuery("img[post-id=" + post_id + "]")[0];
        width = height = null;

        if (image_url) {
            // Fix: Use the correct selector for the preview image
            img = jQuery("img#fifu-quick-preview")[0];
            if (img) {
                width = img.naturalWidth;
                height = img.naturalHeight;
            }
        }

        jQuery.ajax({
            method: "POST",
            url: fifuColumnVars.restUrl + fifuColumnVars.restNamespaceV2 + '/quick_edit_save_api/',
            data: {
                "post_id": post_id,
                "is_ctgr": is_ctgr,
                "width": width,
                "height": height,
                "image_url": image_url,
            },
            async: true,
            beforeSend: function (xhr) {
                xhr.setRequestHeader("X-WP-Nonce", fifuColumnVars.nonce);
            },
            success: function (data) {
                const cacheParentId = fifuActiveQuickEditContext && String(fifuActiveQuickEditContext.postId) === String(post_id) ? fifuActiveQuickEditContext.parentId : 0;
                fifu_invalidate_quick_edit_cache(post_id, cacheParentId);
                // featured image
                if (fifuColumnVars.onCategoriesPage) {
                    fifuQuickEditCtgrVars.terms[post_id]['fifu_image_url'] = image_url;
                    fifuQuickEditCtgrVars.terms[post_id]['fifu_image_alt'] = image_alt;
                } else {
                    fifuQuickEditVars.posts[post_id]['fifu_image_url'] = image_url;

                    if (fifuQuickEditVars.parent && fifuQuickEditVars.parent[post_id])
                        fifuQuickEditVars.parent[post_id]['image-url'] = image_url;
                }

                const responseJson = typeof data === 'string' ? JSON.parse(data) : data;
                let url = responseJson && typeof responseJson === 'object' ? responseJson['thumb_url'] ?? '' : '';
                url = url ? url : '';

                // If url contains #http, use the part after # as the image URL
                if (url && url.includes('#http')) {
                    url = url.substring(url.indexOf('#http') + 1);
                }

                if (!fifuColumnVars.onCategoriesPage) {
                    if (fifuQuickEditVars.parent && fifuQuickEditVars.parent[post_id]) {
                        fifuQuickEditVars.parent[post_id]['image-url'] = url;
                    }
                }

                const thumbs = jQuery('div.fifu-quick[post-id=' + post_id + ']');
                for (let i = 0; i < thumbs.length; i++) {
                    const thumb = thumbs[i];
                    jQuery(thumb).attr('image-url', url);

                    let adjustedUrl = fifu_cdn_adjust(url);
                    jQuery(thumb).css('background-image', 'url("' + adjustedUrl + '")');
                    url ? jQuery(thumb).css('border', 'none') : jQuery(thumb).css('color', '#ca4a1f').css('border', '2px').css('border-style', 'dotted').css('border-radius', '8px');

                    // Minimal addition: check if image loads, set fallback if not
                    if (url) {
                        let img = new window.Image();
                        img.onerror = function () {
                            jQuery(thumb).css('background-image', 'url("' + FIFU_IMAGE_NOT_FOUND_URL + '")');
                        };
                        img.src = adjustedUrl;
                    }
                }

                thumb = jQuery('div.fifu-quick[post-id=' + post_id + ']')[0];
                jQuery(thumb).attr('image-url', url);
                jQuery(thumb).css('background-image', 'url("' + fifu_cdn_adjust(url) + '")');
                url ? jQuery(thumb).css('border', 'none') : jQuery(thumb).css('color', '#ca4a1f').css('border', '2px').css('border-style', 'dotted').css('border-radius', '8px');

                // Also update the thumbnail <img> in the table cell to the new image URL (or placeholder if empty)
                const thumbImg = jQuery(`td.thumb.column-thumb a[href*="post=${post_id}"] img`);
                if (thumbImg.length) {
                    thumbImg
                            .attr('src', url ? url : WC_PLACEHOLDER_IMAGE_URL)
                            .removeAttr('srcset')
                            .removeAttr('sizes');
                    thumbImg.off('error.fifu').on('error.fifu', function () {
                        jQuery(this).attr('src', WC_PLACEHOLDER_IMAGE_URL);
                    });
                }

                if (fifuColumnVars.onCategoriesPage) {
                    // Update the category thumbnail <img> in the table cell
                    const catThumbImg = jQuery(`tr#tag-${post_id} td.thumb.column-thumb img[alt="Thumbnail"]`);
                    if (catThumbImg.length) {
                        catThumbImg
                                .attr('src', url ? url : WC_PLACEHOLDER_IMAGE_URL)
                                .off('error.fifu')
                                .on('error.fifu', function () {
                                    jQuery(this).attr('src', WC_PLACEHOLDER_IMAGE_URL);
                                });
                    }
                }

                jQuery.fancybox.close();
            },
            error: function (jqXHR, textStatus, errorThrown) {
                const response = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : {};
                const message = response.message || errorThrown || textStatus || 'FIFU Quick Edit could not save the media.';

                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);

                alert(message);
            },
        });
    });
}

function fifu_keypress_event() {
    jQuery('div.fancybox-container.fancybox-is-open').keyup(function (e) {
        if (fifu_is_readonly_variation_modal()) {
            return;
        }
        switch (e.which) {
            case 9:
                // tab (keyword)
                if (jQuery('#fifu-quick-search-input-keywords').val())
                    jQuery('#fifu-search-button').click();
                break;
            case 13:
                jQuery(this).blur();
                // enter (keyword)
                if (jQuery('#fifu-quick-search-input-keywords').val()) {
                    jQuery('#fifu-search-button').focus().click();
                    break;
                }
                // enter (save)
                jQuery('#fifu-save-button').focus().click();
                break;
            case 27:
                // esc
                jQuery.fancybox.close();
                break;
            default:
                break;
        }
    });
}

function fifu_search_event() {
    jQuery('#fifu-search-button').on('click', function () {
        if (fifu_is_readonly_variation_modal()) {
            return;
        }
        const keywords = jQuery('#fifu-quick-search-input-keywords')
            .val()
            .trim();

        if (!keywords) {
            return;
        }

        fifu_start_lightbox(keywords, 'quick-edit');
    });
}

function fifu_include_input_hidden(post_id) {
    hidden_input = `
        <input 
            post-id="${post_id}"
            type="hidden" 
            id="fifu-quick-input-hidden" 
            name="fifu-quick-input-hidden" 
            value="" >
    `;
    jQuery("div.fifu-quick").after(hidden_input);
}

function fifu_get_image_info(post_id) {
    image_url = null;

    // Fix: Initialize category data if missing (new category case)
    if (fifuColumnVars.onCategoriesPage) {
        if (!fifuQuickEditCtgrVars.terms[post_id]) {
            // Try to get from DOM
            let $div = jQuery('.fifu-quick[post-id="' + post_id + '"]');
            fifuQuickEditCtgrVars.terms[post_id] = {
                fifu_image_url: $div.attr('image-url') || '',
                fifu_image_alt: ''
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
                // Hide upload on error (not found)
                .attr('onerror', `this.onerror=null;this.src='${FIFU_IMAGE_NOT_FOUND_URL}';`);
        jQuery('#fifu-left-column').css('display', 'table-cell');
    } else {
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

// Function to dynamically load a script
function loadScriptWithJQuery(url, callback) {
    var script = jQuery('<script>', {type: 'text/javascript', src: url});
    script.on('load', callback);
    jQuery('head').append(script);
}

// Load resources when fancyBox is opened
jQuery(document).on('beforeShow.fb', function () {
    loadScriptWithJQuery(fifuColumnVars.convertUrlJs, function () {});
});

function fifu_get_search_lightbox_vars() {
    const candidates = [];

    if (typeof fifuColumnVars !== 'undefined') {
        candidates.push(fifuColumnVars);
    }

    if (typeof fifuMetaBoxVars !== 'undefined') {
        candidates.push(fifuMetaBoxVars);
    }

    if (typeof fifuScriptVars !== 'undefined') {
        candidates.push(fifuScriptVars);
    }

    for (const vars of candidates) {
        if (vars && vars.restUrl && vars.nonce) {
            return vars;
        }
    }

    return candidates.length ? candidates[0] : null;
}

function fifu_get_unsplash_urls(keywords, page) {
    const vars = fifu_get_search_lightbox_vars();
    const normalizedKeywords = typeof keywords === 'string' ? keywords.trim() : '';
    const normalizedPage = Math.max(1, parseInt(page, 10) || 1);

    if (!vars || !vars.restUrl || !vars.nonce) {
        return Promise.resolve([]);
    }

    if (!normalizedKeywords) {
        return Promise.resolve([]);
    }

    return new Promise((resolve) => {
        jQuery.ajax({
            method: 'POST',
            url: vars.restUrl + vars.restNamespaceV2 + '/unsplash_search/',
            data: {
                keywords: normalizedKeywords,
                page: normalizedPage,
            },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', vars.nonce);
            },
            success: function (data) {
                resolve(Array.isArray(data) ? data : []);
            },
            error: function () {
                resolve([]);
            },
        });
    });
}

function fifu_handle_image_error(imageElement) {
    imageElement.parentNode.remove();
}

function fifu_append_lightbox_images(urls) {
    urls.forEach(function (item) {
        if (!item || !item.thumbnail || !item.url) {
            return;
        }

        jQuery('div.masonry').append(
            '<div class="mItem" style="max-width:400px;object-fit:content">' +
            '<img loading="lazy" src="' + item.thumbnail + '" original="' + item.url + '" style="width:100%" onerror="fifu_handle_image_error(this);">' +
            '</div>'
        );
    });
}

function fifu_start_lightbox(keywords, context) {
    const vars = fifu_get_search_lightbox_vars();
    const normalizedKeywords = typeof keywords === 'string' ? keywords.trim() : '';

    if (!normalizedKeywords) {
        return;
    }

    let unsplashPage = 1;
    let unsplashLoading = false;
    let unsplashHasMore = true;

    fifu_register_lightbox_click_event(context);

    jQuery.fancybox.open('<div><div class="masonry"></div></div>');
    jQuery('div.masonry').after(
        '<center><div id="fifu-loading">' +
        '<img loading="lazy" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.lazyloadxt/1.1.0/loading.gif">' +
        '<div>' + ((vars && vars.txt_loading) ? vars.txt_loading : '') + '</div>' +
        '</div></center>'
    );

    const loadUnsplashPage = function () {
        if (unsplashLoading || !unsplashHasMore) {
            return;
        }

        unsplashLoading = true;
        fifu_get_unsplash_urls(normalizedKeywords, unsplashPage).then(function (urls) {
            if (Array.isArray(urls) && urls.length) {
                fifu_append_lightbox_images(urls);
                unsplashPage += 1;
            } else {
                unsplashHasMore = false;
            }
        }).finally(function () {
            unsplashLoading = false;
            jQuery('#fifu-loading').remove();
        });
    };

    loadUnsplashPage();

    jQuery(window)
        .off('scroll.fifuUnsplash')
        .on('scroll.fifuUnsplash', function () {
            if (!unsplashHasMore || unsplashLoading) {
                return;
            }

            const scrollBottom =
                jQuery(window).scrollTop() + jQuery(window).height();
            const nearBottom =
                scrollBottom >= jQuery(document).height() - 250;

            if (nearBottom) {
                loadUnsplashPage();
            }
        });
}

function fifu_register_lightbox_click_event(context) {
    jQuery('body').off('click', 'div.mItem > img');

    jQuery('body').on('click', 'div.mItem > img', function (evt) {
        evt.stopImmediatePropagation();

        const src = jQuery(this).attr('original') || jQuery(this).attr('src');

        if (context === 'meta-box') {
            if (jQuery('#fifu_input_url').length) {
                jQuery('#fifu_input_url').val(src);
                previewImage();
            }
        } else if (context === 'quick-edit') {
            if (jQuery('#fifu-quick-search-input-keywords').length) {
                jQuery('#fifu-quick-input-url').val(src);
                jQuery('#fifu-quick-search-input-keywords').val('');
                jQuery('#fifu-save-button').click();
            }
        }

        jQuery.fancybox.close();
    });
}

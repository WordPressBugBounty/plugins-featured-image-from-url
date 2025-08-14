var metaIntervalId = null;

jQuery(document).ready(function () {
    jQuery('link[href*="jquery-ui.css"]').attr("disabled", "true");
    jQuery('div.wrap div.header-box div.notice').hide();
    jQuery('div.wrap div.header-box div#message').hide();
    jQuery('div.wrap div.header-box div.updated').remove();
});

var restUrl = fifuScriptVars.restUrl;

function invert(id) {
    if (jQuery("#fifu_toggle_" + id).attr("class") == "toggleon") {
        jQuery("#fifu_toggle_" + id).attr("class", "toggleoff");
        jQuery("#fifu_input_" + id).val('off');
    } else {
        jQuery("#fifu_toggle_" + id).attr("class", "toggleon");
        jQuery("#fifu_input_" + id).val('on');
    }
}

jQuery(function () {
    var url = window.location.href;

    jQuery("#tabs-top").tabs();
    jQuery("#fifu_input_slider_speed").spinner({min: 0});
    jQuery("#fifu_input_slider_pause").spinner({min: 0});
    jQuery("#fifu_input_auto_set_width").spinner({min: 0});
    jQuery("#fifu_input_auto_set_height").spinner({min: 0});
    jQuery("#tabsApi").addClass("ui-tabs-vertical ui-helper-clearfix");
    jQuery("#tabsApi li").removeClass("ui-corner-top").addClass("ui-corner-left");
    jQuery("#tabsApi").tabs();
    jQuery("#tabsMedia").tabs();
    jQuery("#tabsPremium").tabs();
    jQuery("#tabsWooImport").tabs();
    jQuery("#tabsWpAllImport").tabs();
    jQuery("#tabsDefault").tabs();
    jQuery("#tabsHide").tabs();
    jQuery("#tabsPcontent").tabs();
    jQuery("#tabsJetpack").tabs();
    jQuery("#tabsJetpackSizes").tabs();
    jQuery("#tabsShortcode").tabs();
    jQuery("#tabsFifuShortcode").tabs();
    jQuery("#tabsAutoSet").tabs();
    jQuery("#tabsAutoSetSub").tabs();
    jQuery("#tabsTags").tabs();
    jQuery("#tabsScreenshot").tabs();
    jQuery("#tabsIsbn").tabs();
    jQuery("#tabsAsin").tabs();
    jQuery("#tabsCustomfield").tabs();
    jQuery("#tabsFinder").tabs();
    jQuery("#tabsVideo").tabs();
    jQuery("#tabsContent").tabs();
    jQuery("#tabsCli").tabs();
    jQuery("#tabsGallery").tabs();

    //forms with id started by...
    jQuery("form[id^=fifu_form]").each(function (i, el) {
        jQuery(this).find("input[type=text]").on("change", function () {
            save(this);
        });

        //onchange
        jQuery(this).change(function () {
            save(this);
        });
        if (isClickable(el.id)) {
            let timer;
            //onclick
            jQuery(this).click(function () {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    save(this);
                }, 500);
            });
        } else {
            //onsubmit
            jQuery(this).submit(function () {
                save(this);
            });
        }
    });

    // show settings
    window.scrollTo(0, 0);
    jQuery('.wrap').css('opacity', 1);
});

function isClickable(id) {
    return false;
}

function save(formName, url) {
    var frm = jQuery(formName);
    showMessage('Processing...', fifuScriptVars.saving + '...', 'processing');
    jQuery.ajax({
        type: frm.attr('method'),
        url: url,
        data: frm.serialize(),
        success: function (data) {
            updateMessage('Success', fifuScriptVars.saved, 'success');
        },
        error: function (jqXHR, textStatus, errorThrown) {
            updateMessage('Error', fifuScriptVars.error + ' ' + errorThrown, 'error');
        }
    });
}

function showMessage(title, message, state) {
    var dialog = jQuery("#dialog-message");
    jQuery("#dialog-message-content").text(message);

    // Add class based on state
    if (state === 'success') {
        dialog.removeClass('custom-dialog-error custom-dialog-processing').addClass('custom-dialog-success');
    } else if (state === 'error') {
        dialog.removeClass('custom-dialog-success custom-dialog-processing').addClass('custom-dialog-error');
    } else {
        dialog.removeClass('custom-dialog-success custom-dialog-error').addClass('custom-dialog-processing');
    }

    dialog.css({
        display: 'block'
    }).fadeIn(300);
}

function updateMessage(title, message, state) {
    var dialog = jQuery("#dialog-message");
    jQuery("#dialog-message-content").text(message);

    // Add class based on state
    if (state === 'success') {
        dialog.removeClass('custom-dialog-error custom-dialog-processing').addClass('custom-dialog-success');
    } else if (state === 'error') {
        dialog.removeClass('custom-dialog-success custom-dialog-processing').addClass('custom-dialog-error');
    }

    dialog.delay(2000).fadeOut(300);
}

function fifu_default_js() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    let toggle = jQuery("#fifu_toggle_enable_default_url").attr('class');
    switch (toggle) {
        case "toggleoff":
            option = "disable_default_api";
            break;
        default:
            let url = jQuery("#fifu_input_default_url").val();
            option = url ? "none_default_api" : "disable_default_api";
    }
    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/' + option + '/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function () {
            setTimeout(function () {
                jQuery('#tabs-top').unblock();
            }, 1000);
        },
        timeout: 0
    });
}

function fifu_sizes_js() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/load-sizes-api/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            const table = jQuery('#fifu-sizes-table');

            // Clear existing rows and footer
            table.find('tr:not(:first)').remove();
            jQuery('.fifu-sizes-footer').remove();

            // Build new rows
            jQuery.each(data, function (sizeName, sizeData) {
                const row = jQuery('<tr class="color">');

                // Format size name for display
                let displayName = sizeName;
                if (sizeName === 'empty') {
                    displayName = '(empty)';
                } else if (sizeName.match(/^\d+x\d+x\d+$/)) {
                    displayName = sizeName.replace(/^(\d+x\d+)x\d+$/, '($1)');
                }

                // Size Name
                row.append(jQuery('<td>').text(displayName));

                // Width Input
                row.append(jQuery('<td>').append(
                        jQuery('<input>')
                        .attr('type', 'number')
                        .attr('min', '0')
                        .attr('pattern', '[0-9]*')
                        .attr('onkeypress', 'return event.charCode >= 48 && event.charCode <= 57')
                        .attr('name', `fifu_size_${sizeName}_width`)
                        .attr('id', `fifu_size_${sizeName}_width`)
                        .attr('data-size', sizeName)
                        .attr('data-dimension', 'width')
                        .addClass('fifu-size-input')
                        .val(sizeData.w > 0 ? sizeData.w : '')
                        ));

                // Height Input
                row.append(jQuery('<td>').append(
                        jQuery('<input>')
                        .attr('type', 'number')
                        .attr('min', '0')
                        .attr('pattern', '[0-9]*')
                        .attr('onkeypress', 'return event.charCode >= 48 && event.charCode <= 57')
                        .attr('name', `fifu_size_${sizeName}_height`)
                        .attr('id', `fifu_size_${sizeName}_height`)
                        .attr('data-size', sizeName)
                        .attr('data-dimension', 'height')
                        .addClass('fifu-size-input')
                        .val(sizeData.h > 0 ? sizeData.h : '')
                        ));

                // Cropped Checkbox
                row.append(jQuery('<td>').append(
                        jQuery('<input>')
                        .attr('type', 'checkbox')
                        .attr('name', `fifu_size_${sizeName}_crop`)
                        .attr('id', `fifu_size_${sizeName}_crop`)
                        .attr('data-size', sizeName)
                        .attr('data-dimension', 'crop')
                        .addClass('fifu-crop-checkbox')
                        .prop('checked', sizeData.c)
                        ));

                // Pages
                row.append(jQuery('<td>').text(
                        sizeData.pages.join(', ')
                        ));

                table.append(row);
            });

            // Add footer with buttons
            const footer = jQuery('<div class="fifu-sizes-footer"></div>');

            // Style for the footer - align to right side
            footer.css({
                'padding-top': '5px',
                'display': 'flex',
                'justify-content': 'flex-end', // Right alignment
                'gap': '5px'
            });

            // Create buttons with appropriate names and icons
            const resetButton = jQuery('<button id="fifu-sizes-reset" class="button fifu-action-button">' +
                    '<i class="fa-solid fa-arrows-rotate"></i> ' + fifuScriptVars.reset + '</button>');
            const saveButton = jQuery('<button id="fifu-sizes-save" class="button fifu-action-button">' +
                    '<i class="fa-solid fa-floppy-disk"></i> ' + fifuScriptVars.save + '</button>');

            // Apply shared styles to buttons
            [resetButton, saveButton].forEach(button => {
                button.css({
                    'width': '10%',
                    'height': '28px',
                    'display': 'flex',
                    'align-items': 'center',
                    'justify-content': 'center',
                    'gap': '5px',
                    'color': '#333333',
                    'border-color': '#333333',
                });
            });

            // Enable/disable based on table content
            const hasRows = table.find('tr').length > 1;
            resetButton.prop('disabled', !hasRows);
            saveButton.prop('disabled', !hasRows);

            // Add click handlers with appropriate functions
            resetButton.on('click', fifu_reset_sizes);
            saveButton.on('click', fifu_save_sizes);

            footer.append(resetButton);
            footer.append(saveButton);
            table.after(footer);

            // Add shared button styles (only if not already added)
            if (!jQuery('#fifu-buttons-style').length) {
                jQuery('<style id="fifu-buttons-style">')
                        .text(`
                        .fifu-action-button {
                            background-color: #f0f0f1;
                            border: 1px solid #2271b1;
                            border-radius: 3px;
                            color: #2271b1;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        }
                        .fifu-action-button:hover {
                            background-color: #2271b1;
                            color: #fff;
                        }
                        .fifu-action-button:disabled {
                            opacity: 0.6;
                            cursor: not-allowed;
                        }
                        .fifu-action-button svg {
                            width: 14px;
                            height: 14px;
                            margin-right: 5px;
                        }`)
                        .appendTo('head');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function () {
            setTimeout(function () {
                jQuery('#tabs-top').unblock();
            }, 1000);
        },
        timeout: 0
    });
}

function fifu_reset_sizes() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/reset-sizes-api/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            fifu_sizes_js();
            save(null, null)
        },
        complete: function () {
            setTimeout(function () {
                jQuery('#tabs-top').unblock();
            }, 1000);
        },
        timeout: 0
    });
}

function fifu_save_sizes() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    const sizeData = {};
    jQuery('#fifu-sizes-table tr:not(:first)').each(function () {
        const row = jQuery(this);
        const sizeName = row.find('.fifu-size-input').first().data('size');
        sizeData[sizeName] = {
            width: parseInt(row.find('[data-dimension="width"]').val()) || 0,
            height: parseInt(row.find('[data-dimension="height"]').val()) || 0,
            crop: row.find('[data-dimension="crop"]').prop('checked')
        };
    });

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/save-sizes-api/',
        data: JSON.stringify(sizeData),
        contentType: 'application/json',
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (response) {
            save(null, null);
        },
        complete: function () {
            setTimeout(function () {
                jQuery('#tabs-top').unblock();
            }, 1000);
        },
    });
}

function fifu_fake_js() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    let toggle = jQuery("#fifu_toggle_fake").attr('class');
    switch (toggle) {
        case "toggleon":
            option = "enable_fake_api";
            break;
        case "toggleoff":
            option = "disable_fake_api";
            break;
        default:
            return;
    }

    setTimeout(function () {
        if (toggle == "toggleon") {
            metaIntervalId = setInterval(updateMetadataCounter.bind(null, true), 3000);
        } else {
            jQuery('#tabs-top').unblock();
            jQuery('#image_metadata_counter').text('');
        }
    }, 1000);

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/' + option + '/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function () {
        },
        timeout: 0
    });
}

function fifu_clean_js() {
    if (jQuery("#fifu_toggle_data_clean").attr('class') != 'toggleon')
        return;

    fifu_run_clean_js();
}

function fifu_run_clean_js() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    setTimeout(function () {
        metaIntervalId = setInterval(updateMetadataCounter.bind(null, true), 3000);
    }, 1000);

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/data_clean_api/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function () {
            setTimeout(function () {
                jQuery("#fifu_toggle_data_clean").attr('class', 'toggleoff');
                jQuery("#fifu_toggle_fake").attr('class', 'toggleoff');
            }, 1000);
        },
        timeout: 0
    });
}

function fifu_run_delete_all_js() {
    if (jQuery("#fifu_toggle_run_delete_all").attr('class') != 'toggleon')
        return;

    fifu_run_clean_js();

    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/run_delete_all_api/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function () {
            setTimeout(function () {
                jQuery("#fifu_toggle_run_delete_all").attr('class', 'toggleoff');
                jQuery('#tabs-top').unblock();
            }, 3000);
        },
        timeout: 0
    });
}

function updateMetadataCounter(transient) {
    jQuery.ajax({
        url: `${restUrl}featured-image-from-url/v2/metadata_counter_api/`,
        data: {
            "transient": transient,
        },
        method: 'POST',
        async: false,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (response) {
            jQuery('#image_metadata_counter').text(response);

            let metadataCounterValue = parseInt(jQuery('#image_metadata_counter').text().trim());
            if ((metadataCounterValue === 0 && jQuery('#fifu_toggle_data_clean').hasClass('toggleoff'))) {
                if (typeof metaIntervalId !== 'undefined')
                    clearInterval(metaIntervalId);
                jQuery('#tabs-top').unblock();
            }
        },
        error: function (xhr, status, error) {
            console.error('Error updating metadata counter:', error);
        },
        timeout: 60000
    });
}

jQuery(document).ready(function ($) {
    // Function to load JSON data into a container
    function loadJsonContent(containerId, jsonUrl, height) {
        const $container = $('#' + containerId);

        if ($container.data('loaded'))
            return;

        $container.html('<p>Loading JSON content...</p>');

        $.ajax({
            url: jsonUrl,
            dataType: 'json',
            success(data) {
                // Escapa HTML
                let jsonString = JSON.stringify(data, null, 2)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');

                // Blue
                jsonString = jsonString.replace(
                        /"([^"]+)"(\s*:\s*)/g,
                        '"<span style="color:#0066B3">$1</span>"$2'
                        );

                // Green
                jsonString = jsonString.replace(
                        /(:\s*|\[\s*|,\s*)"(?!(?:&lt;|<))([^"]*)"/g,
                        '$1"<span style="color:#008000">$2</span>"'
                        );

                // Red for pipe |
                jsonString = jsonString.replace(
                        /\|/g,
                        '<span style="color:#d60000">|</span>'
                        );

                $container.html('<pre style="margin:0">' + jsonString + '</pre>');
                $container.data('loaded', true);
            },
            error(_, __, err) {
                $container.html('<p style="color:red">Error loading JSON content: ' + err + '</p>');
                console.error('Error loading JSON:', err);
            }
        });
    }

    // Add click handler to the developer tab
    $('a[href="#tabs-q"]').on('click', function () {
        // Load all JSON files in the developer tab when clicked
        loadJsonContent('product-json', fifuScriptVars.pluginUrl + '/admin/html/txt/product.json', 275);
        loadJsonContent('product-variation-json', fifuScriptVars.pluginUrl + '/admin/html/txt/product-variation.json', 450);
        loadJsonContent('post-json', fifuScriptVars.pluginUrl + '/admin/html/txt/post.json', 175);
        loadJsonContent('product-category-json', fifuScriptVars.pluginUrl + '/admin/html/txt/product-category.json', 250);
        loadJsonContent('product-variable-json', fifuScriptVars.pluginUrl + '/admin/html/txt/product-variable.json', 590);
        loadJsonContent('batch-product-json', fifuScriptVars.pluginUrl + '/admin/html/txt/batch-product.json', 535);
        loadJsonContent('batch-category-json', fifuScriptVars.pluginUrl + '/admin/html/txt/batch-category.json', 535);
    });
});
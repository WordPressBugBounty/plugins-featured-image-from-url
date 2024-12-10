jQuery(document).ready(function () {
    jQuery('link[href*="jquery-ui.css"]').attr("disabled", "true");
    jQuery('div.wrap div.header-box div.notice').hide();
    jQuery('div.wrap div.header-box div#message').hide();
    jQuery('div.wrap div.header-box div.updated').remove();

    var metaIntervalId = null;
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
    jQuery("#tabsShortcode").tabs();
    jQuery("#tabsFifuShortcode").tabs();
    jQuery("#tabsAutoSet").tabs();
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

    toggle = jQuery("#fifu_toggle_enable_default_url").attr('class');
    switch (toggle) {
        case "toggleoff":
            option = "disable_default_api";
            break;
        default:
            url = jQuery("#fifu_input_default_url").val();
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

function fifu_fake_js() {
    jQuery('#tabs-top').block({message: fifuScriptVars.wait, css: {backgroundColor: 'none', border: 'none', color: 'white'}});

    toggle = jQuery("#fifu_toggle_fake").attr('class');
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
        timeout: 60
    });
}

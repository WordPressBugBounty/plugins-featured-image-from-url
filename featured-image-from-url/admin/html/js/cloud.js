// don't lose your images again
// don't torture your website anymore
// don't make your visitors wait

// lighthouse (perfect score)
// 1. lazy load enabled
// 2. new incognito tab
// 3. DevTools > Undock into a separate window
// 4. deactivate plugins
// 5. activate Astra

jQuery(document).ready(function () {
    jQuery('link[href*="jquery-ui.css"]').attr("disabled", "true");
    jQuery('div.wrap div.header-box div.notice').hide();
    jQuery('div.wrap div.header-box div#message').hide();
    jQuery('div.wrap div.header-box div.updated').remove();

    if (fifuScriptCloudVars.signUpComplete) {
        check_connection();
    } else {
        fifu_show_login();
    }

    jQuery("#availableImages").append(fifuScriptCloudVars.availableImages);
});

jQuery(function () {
    jQuery("#tabs-top").tabs();

    window.scrollTo(0, 0);
    jQuery('.wrap').css('opacity', 1);
});

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

    //forms with id started by...
    jQuery("form[id^=fifu_form]").each(function (i, el) {
        //onchange
        jQuery(this).change(function () {
            save(this);
        });
        //onsubmit
        jQuery(this).submit(function () {
            save(this);
        });
    });
});

function save(formName, url) {
    var frm = jQuery(formName);
    jQuery.ajax({
        type: frm.attr('method'),
        url: url,
        data: frm.serialize(),
        success: function (data) {
            //alert('saved');
        }
    });
}

function showFifuCloudDialog(text) {
    let dialog = jQuery("#dialog-message");

    if ([fifuScriptCloudVars.notConnected, fifuScriptCloudVars.down].includes(text)) {
        dialog.removeClass('custom-dialog-processing custom-dialog-success').addClass('custom-dialog-error').text(text);
        dialog.css({display: 'block'}).fadeIn(300).delay(2000).fadeOut(300);
    } else {
        dialog.removeClass('custom-dialog-error custom-dialog-success').addClass('custom-dialog-processing').text(text);
        dialog.css({display: 'block'}).fadeIn(300).delay(5000).fadeOut(300);
    }
}

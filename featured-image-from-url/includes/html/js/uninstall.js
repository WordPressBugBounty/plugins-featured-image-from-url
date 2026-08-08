jQuery(document).ready(function ($) {
    const vars = fifuUninstallVars;
    const deactivateLinkSelector = `tr[data-plugin="${vars.pluginBasename}"] .deactivate a`;

    function feedbackDescription() {
        const value = jQuery('textarea#fifu-description').val();
        return String(value || '').trim();
    }

    function deactivateHref() {
        return jQuery(deactivateLinkSelector).attr('href');
    }

    function redirectToDeactivation() {
        const href = deactivateHref();
        if (href) window.location.href = href;
    }

    function setRestNonce(xhr) {
        xhr.setRequestHeader('X-WP-Nonce', vars.nonce);
    }

    function logAjaxError(jqXHR, textStatus, errorThrown) {
        console.log(jqXHR);
        console.log(textStatus);
        console.log(errorThrown);
    }

    jQuery(deactivateLinkSelector).click(function (event) {
        event.preventDefault();
        const placeholder = [
            vars.textReasonConflict, vars.textReasonPro, vars.textReasonSeo,
            vars.textReasonLocal, vars.textReasonUnderstand, vars.textReasonOthers,
        ].join('&#10;');
        const box = `
            <table><tr>
                <td><button class="uninstall" style="background-color:#f44336" id="pre-deactivate">${vars.buttonTextClean}</button></td>
                <td><button class="uninstall" style="width:100%;background-color:#008CBA" id="deactivate">${vars.buttonTextDeactivate}</button></td>
            </tr><tr>
                <td style="color:black;text-align:center">${vars.buttonDescriptionClean}</td>
                <td style="color:black;text-align:center">${vars.buttonDescriptionDeactivate}</td>
            </tr></table><br><hr>
            <h4>${vars.textWhy} <span style="color:grey">${vars.textEmail}</span></h4>
            <textarea id="fifu-description" style="width:100%;height:135px;padding:10px;font-size:13px" placeholder="${placeholder}"></textarea>`;
        jQuery.fancybox.open(box);
    });

    jQuery(document).on('click', 'button#deactivate', function () {
        const description = feedbackDescription();
        if (description === '') {
            redirectToDeactivation();
            return;
        }
        jQuery('.fancybox-slide').block({message: '', css: {backgroundColor: 'none', border: 'none', color: 'white'}});
        jQuery.ajax({
            method: 'POST',
            url: vars.restUrl + vars.restNamespaceV2 + '/feedback/',
            data: {description, temporary: true},
            async: false,
            beforeSend: setRestNonce,
            error: logAjaxError,
            complete: redirectToDeactivation,
        });
    });

    jQuery(document).on('click', 'button#pre-deactivate', function () {
        const description = feedbackDescription();
        jQuery('.fancybox-slide').block({message: '', css: {backgroundColor: 'none', border: 'none', color: 'white'}});
        jQuery.ajax({
            method: 'POST',
            url: vars.restUrl + vars.restNamespaceV2 + '/pre_deactivate/',
            data: {description, temporary: false},
            async: false,
            beforeSend: setRestNonce,
            success: redirectToDeactivation,
            error: logAjaxError,
        });
    });
});

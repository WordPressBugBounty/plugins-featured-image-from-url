function fifu_get_rest_url() {
    var out = null;
    let error = false;
    jQuery.ajax({
        method: "POST",
        url: fifuScriptVars.homeUrl + '/wp-json/' + fifuScriptVars.restNamespaceV2 + '/rest_url_api/',
        async: false,
        success: function (data) {
            out = data;
        },
        error: function (jqXHR, textStatus, errorThrown) {
            let protocol = fifuScriptVars.homeUrl.includes('http:') ? 'https:' : 'http:';
            jQuery.ajax({
                method: "POST",
                url: fifuScriptVars.homeUrl.replace(/[^:]+:/, protocol) + '/wp-json/' + fifuScriptVars.restNamespaceV2 + '/rest_url_api/',
                async: false,
                success: function (data) {
                    out = data;
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    error = true;
                }
            });
        }
    });
    if (error) {
        jQuery.ajax({
            method: "POST",
            url: fifuScriptVars.homeUrl + '?rest_route=/' + fifuScriptVars.restNamespaceV2 + '/rest_url_api/',
            async: false,
            success: function (data) {
                out = data;
            },
            error: function (jqXHR, textStatus, errorThrown) {
                let protocol = fifuScriptVars.homeUrl.includes('http:') ? 'https:' : 'http:';
                jQuery.ajax({
                    method: "POST",
                    url: fifuScriptVars.homeUrl.replace(/[^:]+:/, protocol) + '?rest_route=/' + fifuScriptVars.restNamespaceV2 + '/rest_url_api/',
                    async: false,
                    success: function (data) {
                        out = data;
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        error = true;
                    }
                });
            }
        });
    }
    return out;
}

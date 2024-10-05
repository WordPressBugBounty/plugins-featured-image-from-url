var restUrl = fifuScriptVars.restUrl;

function signUp() {
    var email = jQuery('#su_email').val();
    var site = jQuery('#su_site').val();

    if (!email || !site)
        return;

    var code = null;

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/sign_up/',
        data: {
            "email": email,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];

            // duplicated
            if (code == -7 || code == -25)
                showFifuCloudDialog(data['message']);

            // activation code
            if (code == 3)
                showFifuCloudDialog(data['message']);

            if (code > 0)
                fifuScriptCloudVars.signUpComplete = true;

            fifu_unblock();
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
            fifu_unblock();
        }
    });
    return code;
}

function cancel() {
    jQuery("#su-dialog-cancel").dialog("open");
}

function payment_info() {
    fifu_block();
    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/payment_info/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            // not connected
            if (data['code'] == -20) {
                fifu_show_login();
                fifu_disable_edition_buttons();
            } else
                showFifuCloudDialog(data['message']);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });
}

function check_connection() {
    fifu_block();
    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/connected/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            if (data == null || data['code'] == 0) {
                data = new Object();
                fifu_disable_edition_buttons(fifuScriptCloudVars.down);
                fifu_show_login();
                jQuery('#su_reset_button').prop('disabled', true);

                fifu_unblock();
                return;
            } else {
                fifu_enable_edition_buttons();
                fifu_hide_log_in();
                jQuery('#su_reset_button').prop('disabled', false);
            }

            code = data['code'];

            if (code == 7) {
                fifu_hide_log_in();
                fifu_enable_edition_buttons();
            } else {
                fifu_show_login();
                fifu_disable_edition_buttons();
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            fifu_disable_edition_buttons();
            fifu_show_login();
            jQuery('#su_reset_button').prop('disabled', true);

            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });
}

function resetCredentials() {
    var email = jQuery('#su_email').val();
    var site = jQuery('#su_site').val();

    if (!email || !site)
        return;

    var code = null;

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/reset_credentials/',
        data: {
            "email": email
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];
            if (code > 0) {
                jQuery('#su_reset_button').attr('disabled', 'true');
            }
            showFifuCloudDialog(data['message']);
            fifu_unblock();
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
            fifu_unblock();
        }
    });
    return code;
}

function listAllSu(page) {
    if (!fifuScriptCloudVars.signUpComplete)
        fifu_disable_edition_buttons();
    else
        check_connection();

    update = false;

    var table = jQuery('#removeTable').DataTable({
        "language": {"emptyTable": fifuScriptCloudVars.noImages},
        destroy: true,
        "columns": [{"width": "64px"}, {"width": "85%"}, {"width": "15%"}, {"width": "64px"}, {"width": "64px"}],
        "autoWidth": false,
        "order": [[3, 'desc']],
        dom: 'lfrtBip',
        select: true,
        buttons: [
            {
                text: fifuScriptCloudVars.selectAll,
                titleAttr: fifuScriptCloudVars.limit,
                action: function () {
                    total_rows = table.rows().count();
                    amount = total_rows < MAX_ROWS ? total_rows : MAX_ROWS;
                    table.rows({search: 'applied'}, [...Array(amount).keys()]).select();
                    if (table.rows({selected: true}).count() == 0)
                        table.rows([...Array(amount).keys()]).select();
                }
            },
            {
                text: fifuScriptCloudVars.selectNone,
                action: function () {
                    table.rows().deselect();
                }
            },
            {
                text: '<i class="fas fa-folder-minus"></i> ' + fifuScriptCloudVars.delete,
                attr: {
                    id: 'cloud-del'
                },
                action: function () {
                    jQuery("#su-dialog-remove").dialog("open");
                    update = true;
                }
            },
            {
                text: fifuScriptCloudVars.load,
                action: function () {
                    if (table.rows().count() == MAX_ROWS || update)
                        listAllSu(page + 1);
                }
            },
        ]
    });

    table.clear();

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/list_all_su/',
        data: {
            "page": page,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];
            if (code > 0) {
                var bucket = data['bucket'];
                var photo_data = data['photo_data'];
                for (var i = 0; i < photo_data.length; i++) {
                    imgTag = '<img loading="lazy" id="' + photo_data[i]['storage_id'] + '" src="' + photo_data[i]['proxy_url'] + '" style="border-radius:5%; height:56px; width:56px; object-fit:cover; text-align:center">';

                    if (photo_data[i]['is_category'])
                        local = fifuScriptCloudVars.category;
                    else if (photo_data[i]['meta_key'].includes('slider'))
                        local = fifuScriptCloudVars.slider;
                    else if (photo_data[i]['meta_key'].includes('url_'))
                        local = fifuScriptCloudVars.gallery;
                    else
                        local = fifuScriptCloudVars.featured;

                    table.row.add([
                        imgTag,
                        photo_data[i]['title'],
                        photo_data[i]['date'],
                        photo_data[i]['post_id'],
                        local,
                        photo_data[i]['storage_id'],
                        photo_data[i]['meta_id'],
                        photo_data[i]['meta_key'],
                        photo_data[i]['is_category'],
                    ]);
                }
                table.draw(true);
            } else {
                // not connected
                if (data['code'] == -20) {
                    fifu_show_login();
                    fifu_disable_edition_buttons();
                }
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });

    jQuery("#su-dialog-remove").dialog({
        autoOpen: false,
        modal: true,
        width: "400px",
        buttons: {
            [fifuScriptCloudVars.dialogDelete]: function () {
                selected = table.rows({selected: true});
                count = selected.count();

                if (count == 0)
                    return;

                var arr = [];
                for (var i = 0; i < count; i++) {
                    data = selected.data()[i];
                    arr.push({
                        'storage_id': data[5],
                    });
                }
                fifu_block();
                jQuery(this).dialog("close");
                jQuery.ajax({
                    method: "POST",
                    url: restUrl + 'featured-image-from-url/v2/delete/',
                    data: {
                        "selected": arr,
                    },
                    async: true,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
                    },
                    success: function (data) {
                        table.rows().deselect();

                        // not connected
                        if (data['code'] == -20) {
                            fifu_show_login();
                            fifu_disable_edition_buttons();
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log(jqXHR);
                        console.log(textStatus);
                        console.log(errorThrown);
                    },
                    complete: function (data) {
                        selected.remove().draw(false);

                        if (table.rows().count() == 0)
                            listAllSu(0);

                        fifu_unblock();
                    }
                });
            },
            [fifuScriptCloudVars.dialogCancel]: function () {
                jQuery(this).dialog("close");
            }
        }
    });

    // limit number of rows selected
    table.on('select', function (e, dt, type, ix) {
        var selected = dt.rows({selected: true});
        if (selected.count() > MAX_ROWS)
            dt.rows(ix).deselect();
    });
}

jQuery(document).ready(function ($) {
    jQuery('#addTable tbody').on('click', 'tr', function () {
        jQuery(this).toggleClass('selected');
    });
});

const MAX_ROWS = 1000;
const MAX_ROWS_BY_REQUEST = MAX_ROWS / 10;

function listAllFifu(page) {
    if (!fifuScriptCloudVars.signUpComplete)
        fifu_disable_edition_buttons();
    else
        check_connection();

    update = false;

    var table = jQuery('#addTable').DataTable({
        "language": {"emptyTable": fifuScriptCloudVars.noImages},
        destroy: true,
        "columns": [{"width": "64px"}, {"width": "85%"}, {"width": "15%"}, {"width": "64px"}, {"width": "64px"}],
        "autoWidth": false,
        "order": [[3, 'desc']],
        dom: 'lfrtBip',
        select: true,
        buttons: [
            {
                text: fifuScriptCloudVars.selectAll,
                titleAttr: fifuScriptCloudVars.limit,
                action: function () {
                    total_rows = table.rows().count();
                    amount = total_rows < MAX_ROWS ? total_rows : MAX_ROWS;
                    table.rows({search: 'applied'}, [...Array(amount).keys()]).select();
                    if (table.rows({selected: true}).count() == 0)
                        table.rows([...Array(amount).keys()]).select();
                }
            },
            {
                text: fifuScriptCloudVars.selectNone,
                action: function () {
                    table.rows().deselect();
                }
            },
            {
                text: '<i class="fas fa-folder-plus"></i> ' + fifuScriptCloudVars.upload,
                attr: {
                    id: 'cloud-add'
                },
                action: function () {
                    addSu(table);
                    update = true;
                }
            },
            {
                text: fifuScriptCloudVars.load,
                action: function () {
                    if (table.rows().count() == MAX_ROWS || update)
                        listAllFifu(page + 1);
                }
            },
        ]
    });
    table.clear();

    fifu_block();
    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/list_all_fifu/',
        data: {
            "page": page,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            for (var i = 0; i < data.length; i++) {
                imgTag = '<img loading="lazy" id="' + data[i]['meta_id'] + '" src="' + data[i]['url'] + '" style="border-radius:5%; height:56px; width:56px; object-fit:cover; text-align:center">';

                if (data[i]['category'] == 1)
                    local = fifuScriptCloudVars.category;
                else if (data[i]['meta_key'].includes('slider'))
                    local = fifuScriptCloudVars.slider;
                else if (data[i]['meta_key'].includes('url_'))
                    local = fifuScriptCloudVars.gallery;
                else
                    local = fifuScriptCloudVars.featured;

                table.row.add([
                    imgTag,
                    data[i]['post_title'],
                    data[i]['post_date'],
                    data[i]['post_id'],
                    local,
                    data[i]['url'],
                    data[i]['meta_key'],
                    data[i]['meta_id'],
                    data[i]['category'],
                    data[i]['video_url']
                ]);
            }
            table.draw(true);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });

    // limit number of rows selected
    table.on('select', function (e, dt, type, ix) {
        var selected = dt.rows({selected: true});
        if (selected.count() > MAX_ROWS)
            dt.rows(ix).deselect();
    });
}

async function addSu(table) {
    selected = table.rows({selected: true});
    count = selected.count();

    if (count == 0)
        return;

    fifu_block_progress();

    var arr = [];
    var finished = 0;
    for (var i = 0; i < count; i++) {
        data = selected.data()[i];
        arr.push([
            data[3], // post_id
            data[5], // url
            data[6], // meta_key
            data[7], // meta_id
            data[8], // category
            data[9]  // video_url
        ]);
        if (i + 1 == count || (i > 0 && i % MAX_ROWS_BY_REQUEST == 0)) {
            jQuery.ajax({
                method: "POST",
                url: restUrl + 'featured-image-from-url/v2/create_thumbnails_list/',
                data: {
                    "selected": arr,
                },
                async: true,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
                },
                success: function (data) {
                    // not connected
                    if (data['code'] == -20) {
                        fifu_show_login();
                        fifu_disable_edition_buttons();
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log(jqXHR);
                    console.log(textStatus);
                    console.log(errorThrown);
                },
                complete: function (data) {
                    finished++;
                    progress = 100 * finished / (count / MAX_ROWS_BY_REQUEST);
                    jQuery('#progressBar').attr('value', progress);
                    jQuery('#progressBar').attr('text', progress);
                    if (finished >= count / MAX_ROWS_BY_REQUEST) {
                        if (data['responseJSON']['code'] == -24 || data['responseJSON']['code'] == -20) {
                            // none
                        } else {
                            // success
                            selected.remove().draw(false);

                            if (table.rows().count() == 0)
                                listAllFifu(0);
                        }
                        fifu_unblock();
                    }
                }
            });
            await sleep(2000);
            arr = [];
        }
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

jQuery(function () {
    jQuery("#su-dialog-cancel").dialog({
        autoOpen: false,
        modal: true,
        width: "400px",
        buttons: {
            [fifuScriptCloudVars.dialogYes]: function () {
                fifu_block();
                jQuery(this).dialog("close");
                jQuery.ajax({
                    method: "POST",
                    url: restUrl + 'featured-image-from-url/v2/cancel/',
                    async: true,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
                    },
                    success: function (data) {
                        // not connected
                        if (data['code'] == -20) {
                            fifu_show_login();
                            fifu_disable_edition_buttons();
                        } else
                            showFifuCloudDialog(data['message']);
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log(jqXHR);
                        console.log(textStatus);
                        console.log(errorThrown);
                    },
                    complete: function (data) {
                        fifu_unblock();
                    }
                });
            },
            [fifuScriptCloudVars.dialogNo]: function () {
                jQuery(this).dialog("close");
            }
        }
    });
});

function fifu_block() {
    jQuery('#tabs-top').block({message: '', css: {backgroundColor: 'none', border: 'none', color: 'white'}});
}

function fifu_block_progress() {
    jQuery('#tabs-top').block({message: '<progress id="progressBar" max="100" value="0" style="width:100%;height:32px;background-color:#23282d"></progress>', css: {backgroundColor: 'none', border: 'none', color: 'white'}});
}

function fifu_unblock() {
    jQuery('#tabs-top').unblock();
}

function fifu_show_login() {
    jQuery("#su-payment-info-button").attr('disabled', true);
    jQuery("#su-cancel-button").attr('disabled', true);
    jQuery("#upload-auto-box").hide();
    jQuery("#delete-auto-box").hide();
    jQuery("#hotlink-box").hide();
    jQuery("#su-sign-up-button").removeAttr('disabled');
}

function fifu_hide_log_in() {
    jQuery("#su-payment-info-button").removeAttr('disabled');
    jQuery("#su-cancel-button").removeAttr('disabled');
    jQuery("#upload-auto-box").show();
    jQuery("#delete-auto-box").show();
    jQuery("#hotlink-box").show();
    jQuery("#su-sign-up-button").attr('disabled', true);
}

function fifu_disable_edition_buttons(text) {
    jQuery("button#cloud-add").attr('disabled', 'true');
    jQuery("button#cloud-del").attr('disabled', 'true');
    showFifuCloudDialog(text ? text : fifuScriptCloudVars.notConnected);
}

function fifu_enable_edition_buttons() {
    jQuery("button#cloud-add").removeAttr('disabled');
    jQuery("button#cloud-del").attr('disabled');
}

function listAllMediaLibrary(page) {
    console.log(page);
    update = false;

    var table = jQuery('#mediaTable').DataTable({
        "language": {"emptyTable": fifuScriptCloudVars.noPosts},
        destroy: true,
        "columns": [{"width": "64px"}, {"width": "85%"}, {"width": "15%"}, {"width": "64px"}, {"width": "64px"}],
        "autoWidth": false,
        "order": [[3, 'desc']],
        dom: 'lfrtBip',
        select: true,
        buttons: [
            {
                text: fifuScriptCloudVars.selectAll,
                titleAttr: fifuScriptCloudVars.limit,
                action: function () {
                    total_rows = table.rows().count();
                    amount = total_rows < MAX_ROWS ? total_rows : MAX_ROWS;
                    table.rows({search: 'applied'}, [...Array(amount).keys()]).select();
                    if (table.rows({selected: true}).count() == 0)
                        table.rows([...Array(amount).keys()]).select();
                }
            },
            {
                text: fifuScriptCloudVars.selectNone,
                action: function () {
                    table.rows().deselect();
                }
            },
            {
                text: '<i class="fas fa-link"></i> ' + fifuScriptCloudVars.link,
                attr: {
                    id: 'cloud-link'
                },
                action: function () {
                    update = true;
                }
            },
            {
                text: fifuScriptCloudVars.load,
                action: function () {
                    if (table.rows().count() == MAX_ROWS || update)
                        listAllMediaLibrary(page + 1);
                }
            },
        ]
    });
    table.buttons().disable();
    table.clear();

    fifu_block();
    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/list_all_media_library/',
        data: {
            "page": page,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            for (var i = 0; i < data.length; i++) {
                imgTag = '<img loading="lazy" id="' + data[i]['meta_id'] + '" src="' + data[i]['url'] + '" style="border-radius:5%; height:56px; width:56px; object-fit:cover; text-align:center">';
                table.row.add([
                    imgTag,
                    data[i]['post_title'],
                    data[i]['post_date'],
                    data[i]['post_id'],
                    data[i]['gallery_ids'] ? data[i]['gallery_ids'].split(',').length : 0,
                    data[i]['url'],
                    data[i]['thumbnail_id'],
                    data[i]['gallery_ids'],
                    data[i]['category'],
                ]);
            }
            table.draw(true);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });

    // limit number of rows selected
    table.on('select', function (e, dt, type, ix) {
        var selected = dt.rows({selected: true});
        if (selected.count() > MAX_ROWS)
            dt.rows(ix).deselect();
    });
}

function listDailyCount() {
    if (!fifuScriptCloudVars.signUpComplete)
        fifu_disable_edition_buttons();
    else
        check_connection();

    var table = jQuery('#billingTable').DataTable({
        "language": {"emptyTable": fifuScriptCloudVars.noData},
        destroy: true,
        "columns": [{"width": "15%"}, {"width": "85%"}],
        "autoWidth": false,
        "order": [[0, 'desc']],
        dom: '',
        select: false,
        "iDisplayLength": 30,
    });

    table.clear();

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/list_daily_count/',
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];
            if (code > 0) {
                var dc_data = data['dc_data'];
                jQuery('#billing-start').html(data['start_date'].split('+')[0]);
                jQuery('#billing-end').html(data['end_date'].split('+')[0]);
                jQuery('#billing-average').html(data['quantity']);
                jQuery('#billing-cost').html('€ ' + data['amount_due']);
                for (var i = 0; i < dc_data.length; i++) {
                    table.row.add([
                        dc_data[i]['date'],
                        dc_data[i]['quantity'],
                    ]);
                }

                table.draw(true);
            } else {
                // not connected
                if (data['code'] == -20) {
                    fifu_show_login();
                    fifu_disable_edition_buttons();
                    showFifuCloudDialog(data['message']);
                }
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });
}

function set_upload_auto() {
    toggle = jQuery("#fifu_toggle_cloud_upload_auto").attr('class');

    var code = null;

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/cloud_upload_auto/',
        data: {
            "toggle": toggle,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });
    return code;
}

function set_delete_auto() {
    toggle = jQuery("#fifu_toggle_cloud_delete_auto").attr('class');

    var code = null;

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/cloud_delete_auto/',
        data: {
            "toggle": toggle,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });
    return code;
}

function set_hotlink() {
    toggle = jQuery("#fifu_toggle_cloud_hotlink").attr('class');

    var code = null;

    fifu_block();

    jQuery.ajax({
        method: "POST",
        url: restUrl + 'featured-image-from-url/v2/cloud_hotlink/',
        data: {
            "toggle": toggle,
        },
        async: true,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuScriptVars.nonce);
        },
        success: function (data) {
            code = data['code'];
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            fifu_unblock();
        }
    });
    return code;
}

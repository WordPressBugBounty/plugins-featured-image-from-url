<?php

function fifu_set_transient($transient, $value, $expiration = 0) {
    if (false === get_option($transient)) {
        add_option($transient, $value, '', 'no');
    } else {
        update_option($transient, $value);
    }

    if ($expiration > 0) {
        $expiration_time = time() + $expiration;
        if (false === get_option($transient . '_expiration')) {
            add_option($transient . '_expiration', $expiration_time, '', 'no');
        } else {
            update_option($transient . '_expiration', $expiration_time);
        }
    }
}

function fifu_get_transient($transient) {
    $expiration_time = get_option($transient . '_expiration');

    if ($expiration_time !== false && time() > $expiration_time) {
        delete_option($transient);
        delete_option($transient . '_expiration');
        return false;
    }

    return get_option($transient);
}

function fifu_delete_transient($transient) {
    delete_option($transient);
    delete_option($transient . '_expiration');
}

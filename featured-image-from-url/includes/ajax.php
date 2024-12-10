<?php

function fifu_shutdown() {
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (strpos($user_agent, 'Googlebot') !== false)
        return;

    global $FIFU_SESSION;

    if (isset($FIFU_SESSION['att_img_src']))
        unset($FIFU_SESSION['att_img_src']);
}

add_action('shutdown', 'fifu_shutdown');


<?php

function fifu_shutdown() {
    global $FIFU_SESSION;

    if (isset($FIFU_SESSION['att_img_src']))
        unset($FIFU_SESSION['att_img_src']);
}

add_action('shutdown', 'fifu_shutdown');
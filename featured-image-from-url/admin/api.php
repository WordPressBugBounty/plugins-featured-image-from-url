<?php

define('FIFU_NO_CREDENTIALS', json_encode(array('code' => 'no_credentials')));
define('FIFU_SU_ADDRESS', FIFU_CLOUD_DEBUG ? 'http://192.168.0.31:8080' : 'https://ws.fifu.app');
define('FIFU_SURVEY_ADDRESS', 'https://survey.featuredimagefromurl.com');
define('FIFU_CLIENT', 'featured-image-from-url');

function fifu_try_again_later() {
    $strings = fifu_get_strings_api();
    return json_encode(array('code' => 0, 'message' => $strings['info']['try'](), 'color' => 'orange'));
}

function fifu_is_local() {
    $query = 'http://localhost';
    return substr(get_home_url(), 0, strlen($query)) === $query || FIFU_CLOUD_DEBUG;
}

function fifu_remote_post($endpoint, $array) {
    return fifu_is_local() ? wp_remote_post($endpoint, $array) : wp_safe_remote_post($endpoint, $array);
}

function fifu_api_sign_up(WP_REST_Request $request) {
    $email = $request['email'];
    $site = fifu_get_home_url();

    fifu_cloud_log(['sign_up' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'public_key' => fifu_create_keys($email),
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 120,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/sign-up/', $array);
    if (is_wp_error($response) || $response['response']['code'] == 404) {
        fifu_delete_credentials();
        return json_decode(fifu_try_again_later());
    }

    $json = json_decode($response['http_response']->get_response_object()->body);
    if ($json->code <= 0) {
        fifu_delete_credentials();
        return $json;
    }

    return $json;
}

function fifu_delete_credentials() {
    delete_option('fifu_su_privkey');
    delete_option('fifu_su_email');
    delete_option('fifu_proxy_auth');
}

function fifu_api_cancel(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['cancel' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/cancel/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);

    return $json;
}

function fifu_api_payment_info(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['payment_info' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/payment-info/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);

    return $json;
}

function fifu_api_connected(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    fifu_cloud_log(['connected' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'proxy_auth' => get_option('fifu_proxy_auth') ? true : false,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/connected/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    // offline
    if ($response['http_response']->get_response_object()->status_code == 404)
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);

    if (isset($json->proxy_key)) {
        $privKey = openssl_decrypt(base64_decode(get_option('fifu_su_privkey')[0]), "AES-128-ECB", $email . $site);
        if ($privKey) {
            openssl_private_decrypt(base64_decode($json->proxy_key), $key, $privKey);
            openssl_private_decrypt(base64_decode($json->proxy_salt), $salt, $privKey);
            update_option('fifu_proxy_auth', array($key, $salt));
        }
    }

    return $json;
}

function fifu_get_ip() {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
                    return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'];
}

function fifu_api_create_thumbnails_list(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $images = $request['selected'];

    return fifu_create_thumbnails_list($images, false);
}

function fifu_create_thumbnails_list($images, $cron = false) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    if ($cron) {
        $code = get_option('fifu_cloud_upload_auto_code');
        if (!$code)
            return json_decode(FIFU_NO_CREDENTIALS);
    }

    $sent_urls = array();
    $saved_urls = array();

    $rows = array();
    $total = count($images);
    $url_sign = '';
    foreach ($images as $image) {
        if (!$cron) {
            // manual
            $post_id = $image[0];
            $url = $image[1];
            $meta_key = $image[2];
            $meta_id = $image[3];
            $is_category = $image[4] == 1;
            $video_url = $image[5];
        } else {
            // upload auto
            $post_id = $image->post_id;
            $url = $image->url;
            $meta_key = $image->meta_key;
            $meta_id = $image->meta_id;
            $is_category = $image->category == 1;
            $video_url = $image->video_url;

            if (fifu_db_get_attempts_invalid_media_su($url) >= 5)
                continue;
            array_push($sent_urls, $url);
        }

        if (!$url || !$post_id)
            continue;

        $encoded_url = base64_encode($url);
        $encoded_video_url = $video_url ? base64_encode($video_url) : '';
        array_push($rows, array($post_id, $encoded_url, $meta_key, $meta_id, $is_category, $encoded_video_url));
        $url_sign .= substr($encoded_url, -10);

        fifu_cloud_log(['create_thumbnails_list' => ['post_id' => $post_id, 'meta_key' => $meta_key, 'meta_id' => $meta_id, 'is_category' => $is_category, 'video_url' => $video_url, 'url' => $url]]);
    }
    $time = time();
    $ip = fifu_get_ip();
    $site = fifu_get_home_url();
    $signature = fifu_create_signature($url_sign . $site . $time . $ip);
    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'upload_auto' => $cron,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 300,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/create-thumbnails/', $array);
    if (is_wp_error($response))
        return;

    $json = json_decode($response['http_response']->get_response_object()->body);
    $code = $json->code;
    if ($code && $code > 0) {
        if (count((array) $json->thumbnails) > 0) {
            $category_images = array();
            $post_images = array();
            foreach ((array) $json->thumbnails as $thumbnail) {
                if ($thumbnail->is_category)
                    array_push($category_images, $thumbnail);
                else
                    array_push($post_images, $thumbnail);

                array_push($saved_urls, $thumbnail->meta_value);
            }
            if (count($category_images) > 0)
                fifu_ctgr_add_urls_su($json->bucket_id, $category_images);

            if (count($post_images) > 0)
                fifu_add_urls_su($json->bucket_id, $post_images);
        }

        // check invalid images
        if ($cron && count($sent_urls) > count($saved_urls)) {
            foreach ($sent_urls as $sent_url) {
                if (!in_array($sent_url, $saved_urls))
                    fifu_db_insert_invalid_media_su($sent_url);
                else
                    fifu_db_delete_invalid_media_su($sent_url);
            }
        }
    }

    return $json;
}

function fifu_delete_thumbnails($hex_ids) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $code = get_option('fifu_cloud_delete_auto_code');
    if (!$code)
        return json_decode(FIFU_NO_CREDENTIALS);

    // 1) verification
    $rows = array();
    $total = count($hex_ids);
    $hex_id_sign = '';
    foreach ($hex_ids as $hex_id) {
        array_push($rows, $hex_id);
        $hex_id_sign .= $hex_id;

        fifu_cloud_log(['delete_auto (send used)' => ['hex_id' => $hex_id]]);
    }
    $time = time();
    $ip = fifu_get_ip();
    $site = fifu_get_home_url();
    $signature = fifu_create_signature($hex_id_sign . $site . $time . $ip);
    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 300,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete-thumbnails/', $array);
    fifu_cloud_log(['delete_auto (response)' => ['json' => $json]]);
    if (is_wp_error($response))
        return;

    $json = json_decode($response['http_response']->get_response_object()->body);
    $code = $json->code;
    if ($code && $code > 0) {
        if (count((array) $json->hex_ids) > 0) {
            if (isset($json->hex_ids) && is_array($json->hex_ids)) {
                // Get the hex_ids and process them
                $hex_ids = (array) $json->hex_ids;

                if (count($hex_ids) > 0) {
                    $results = fifu_usage_verification_su($hex_ids);

                    // Remove matching hex_ids from the list
                    foreach ($results as $meta_value) {
                        foreach ($hex_ids as $key => $hex_id) {
                            if (strpos($meta_value, $hex_id) !== false) {
                                unset($hex_ids[$key]);
                                fifu_cloud_log(['found' => $hex_id]);
                            }
                        }
                    }
                }

                // Proceed with the remaining hex_ids
                foreach ($hex_ids as $hex_id) {
                    fifu_cloud_log(['delete' => $hex_id]);
                }

                // 2 delete
                $batches = array_chunk($hex_ids, 1000); // Split hex_ids into batches of 1,000
                foreach ($batches as $batch) {
                    $rows = array();
                    $id_sign = '';
                    foreach ($batch as $hex_id) {
                        array_push($rows, $hex_id);
                        $id_sign .= $hex_id;

                        fifu_cloud_log(['delete_auto (send unused back)' => ['hex_id' => $hex_id]]);
                    }
                    $time = time();
                    $signature = fifu_create_signature($id_sign . $site . $time . $ip);
                    $array = array(
                        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                        'body' => json_encode(
                                array(
                                    'rows' => $rows,
                                    'site' => $site,
                                    'signature' => $signature,
                                    'time' => $time,
                                    'ip' => $ip,
                                    'slug' => FIFU_CLIENT,
                                    'version' => fifu_version_number()
                                )
                        ),
                        'method' => 'POST',
                        'data_format' => 'body',
                        'blocking' => true,
                        'timeout' => 300,
                    );
                    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete-thumbnails-confirm/', $array);
                    if (is_wp_error($response))
                        return;

                    // Delay of 5 seconds between each batch
                    sleep(5);
                }
            }
        }
    }

    return $json;
}

function fifu_api_delete(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $rows = array();
    $images = $request['selected'];
    $total = count($images);
    $url_sign = '';
    foreach ($images as $image) {
        $storage_id = $image['storage_id'];
        if (!$storage_id)
            continue;

        array_push($rows, $storage_id);
        $url_sign .= $storage_id;
    }
    $time = time();
    $ip = fifu_get_ip();
    $site = fifu_get_home_url();
    $signature = fifu_create_signature($url_sign . $site . $time . $ip);

    fifu_cloud_log(['delete' => ['rows' => $rows]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 60,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);
    if (!$json)
        return null;

    $code = $json->code;
    if ($code && $code > 0) {
        if (count((array) $json->urls) > 0) {
            $map = array();
            $posts = fifu_get_posts_su($rows);
            foreach ($posts as $post)
                $map[$post->storage_id] = $post;

            $category_images = array();
            $post_images = array();
            foreach ($posts as $post) {
                if ($post->category)
                    array_push($category_images, $post);
                else
                    array_push($post_images, $post);
            }

            if (count($post_images) > 0)
                fifu_remove_urls_su($json->bucket_id, $post_images, (array) $json->urls, (array) $json->video_urls);

            if (count($category_images) > 0)
                fifu_ctgr_remove_urls_su($json->bucket_id, $category_images, (array) $json->urls, (array) $json->video_urls);

            return fifu_api_confirm_delete($rows, $site, $ip, $url_sign);
        }
    }

    return $json;
}

function fifu_api_confirm_delete($rows, $site, $ip, $url_sign) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $time = time();
    $signature = fifu_create_signature($url_sign . $site . $time . $ip);

    fifu_cloud_log(['confirm_delete' => ['rows' => $rows]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'rows' => $rows,
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 300,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/confirm-delete/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);
    return $json;
}

function fifu_api_reset_credentials(WP_REST_Request $request) {
    fifu_delete_credentials();
    $email = $request['email'];
    $site = fifu_get_home_url();

    fifu_cloud_log(['reset_credentials' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'public_key' => fifu_create_keys($email),
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/reset-credentials/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());
    else {
        $json = json_decode($response['http_response']->get_response_object()->body);

        # unknown site
        if ($json->code == -21)
            fifu_delete_credentials();

        return $json;
    }
}

function fifu_api_list_all_su(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $time = time();
    $site = fifu_get_home_url();
    $page = (int) $request['page'];
    $ip = fifu_get_ip();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['list_all_su' => ['site' => $site, 'page' => $page]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'page' => $page,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/list-all/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    // offline
    if ($response['http_response']->get_response_object()->status_code == 404)
        return json_decode(fifu_try_again_later());

    $map = array();
    $posts = fifu_get_posts_su(null);
    foreach ($posts as $post)
        $map[$post->storage_id] = $post;

    $json = json_decode($response['http_response']->get_response_object()->body);
    if ($json && $json->code > 0) {
        for ($i = 0; $i < count($json->photo_data); $i++) {
            $post = $json->photo_data[$i];
            if (isset($map[$post->storage_id])) {
                $post->title = $map[$post->storage_id]->post_title;
                $post->meta_id = $map[$post->storage_id]->meta_id;
                $post->post_id = $map[$post->storage_id]->post_id;
                $post->meta_key = $map[$post->storage_id]->meta_key;
            } else
                $post->title = $post->meta_id = $post->post_id = $post->meta_key = '';
            $is_video = strpos($post->meta_key, 'video') !== false;
            $url = 'https://cdn.fifu.app/' . $json->bucket_id . '/' . $post->storage_id;
            $post->proxy_url = fifu_speedup_get_signed_url($url, 128, 128, $json->bucket_id, $post->storage_id, $is_video);
        }
    }
    return $json;
}

function fifu_api_list_daily_count(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $time = time();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $signature = fifu_create_signature($site . $time . $ip);

    fifu_cloud_log(['list_daily_count' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );
    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/list-daily-count/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    // offline
    if ($response['http_response']->get_response_object()->status_code == 404)
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);
    return $json;
}

function fifu_api_cloud_upload_auto(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    $enabled = $request['toggle'] == 'toggleon';

    fifu_cloud_log(['cloud_upload_auto' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'enabled' => $enabled,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );

    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/upload-auto/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);
    $upload_auto_code = $json->upload_auto_code;

    if ($enabled)
        update_option('fifu_cloud_upload_auto_code', array($upload_auto_code));
    else
        delete_option('fifu_cloud_upload_auto_code');

    return $json;
}

function fifu_api_cloud_delete_auto(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    $enabled = $request['toggle'] == 'toggleon';

    fifu_cloud_log(['cloud_delete_auto' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'enabled' => $enabled,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );

    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/delete-auto/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);
    $delete_auto_code = $json->delete_auto_code;

    if ($enabled)
        update_option('fifu_cloud_delete_auto_code', array($delete_auto_code));
    else
        delete_option('fifu_cloud_delete_auto_code');

    return $json;
}

function fifu_api_cloud_hotlink(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return json_decode(FIFU_NO_CREDENTIALS);

    $email = fifu_su_get_email();
    $site = fifu_get_home_url();
    $ip = fifu_get_ip();
    $time = time();
    $signature = fifu_create_signature($site . $email . $time . $ip);

    $enabled = $request['toggle'] == 'toggleon';

    fifu_cloud_log(['cloud_hotlink' => ['site' => $site]]);

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'site' => $site,
                    'email' => $email,
                    'signature' => $signature,
                    'time' => $time,
                    'ip' => $ip,
                    'enabled' => $enabled,
                    'slug' => FIFU_CLIENT,
                    'version' => fifu_version_number()
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => true,
        'timeout' => 30,
    );

    $response = fifu_remote_post(FIFU_SU_ADDRESS . '/hotlink/', $array);
    if (is_wp_error($response))
        return json_decode(fifu_try_again_later());

    $json = json_decode($response['http_response']->get_response_object()->body);

    return $json;
}

function fifu_get_storage_id($hex_id, $width, $height) {
    return $hex_id . '-' . $width . '-' . $height;
}

function fifu_api_list_all_fifu(WP_REST_Request $request) {
    $page = (int) $request['page'];
    $urls = fifu_db_get_all_urls($page);
    return $urls;
}

function fifu_api_list_all_media_library(WP_REST_Request $request) {
    if (!fifu_su_sign_up_complete())
        return null;

    $page = (int) $request['page'];
    return fifu_db_get_posts_with_internal_featured_image($page);
}

function fifu_metadata_counter_api(WP_REST_Request $request) {
    $transient = filter_var($request['transient'], FILTER_VALIDATE_BOOLEAN);
    $total = $transient ? fifu_get_transient('fifu_metadata_counter') : null;
    if (!$total) {
        $total = fifu_db_count_metadata_operations();
        fifu_set_transient('fifu_metadata_counter', $total, 0);
    }
    return $total;
}

function fifu_enable_fake_api(WP_REST_Request $request) {
    update_option('fifu_fake_stop', false, 'no');
    fifu_enable_fake();
    return json_encode(array());
}

function fifu_disable_fake_api(WP_REST_Request $request) {
    update_option('fifu_fake_stop', true, 'no');
    return json_encode(array());
}

function fifu_data_clean_api(WP_REST_Request $request) {
    fifu_db_enable_clean();
    update_option('fifu_data_clean', 'toggleoff', 'no');
    fifu_set_author();
    return json_encode(array());
}

function fifu_run_delete_all_api(WP_REST_Request $request) {
    fifu_db_delete_all();
    update_option('fifu_run_delete_all', 'toggleoff', 'no');
    return json_encode(array());
}

function fifu_disable_default_api(WP_REST_Request $request) {
    fifu_db_delete_default_url();
    return json_encode(array());
}

function fifu_none_default_api(WP_REST_Request $request) {
    return json_encode(array());
}

function fifu_rest_url(WP_REST_Request $request) {
    return get_rest_url();
}

function fifu_api_meta_in(WP_REST_Request $request) {
    $id = $request->get_param('post_id');

    $type = fifu_db_get_type_meta_in($id);
    switch ($type) {
        case "post":
            fifu_db_insert_postmeta($id);
            break;
        case "term":
            fifu_db_insert_termmeta($id);
            break;
    }

    $total = fifu_db_count_metadata_operations();

    fifu_set_transient('fifu_metadata_counter', $total, 0);

    $result = fifu_db_get_meta_in_first();
    if (isset($result[0])) {
        $new_request = new WP_REST_Request();
        $new_request->set_param('post_id', $result[0]->post_id);
        return fifu_api_meta_in($new_request);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_meta_out(WP_REST_Request $request) {
    $id = $request->get_param('post_id');

    $type = fifu_db_get_type_meta_out($id);
    switch ($type) {
        case "att":
            fifu_db_delete_attmeta($id);
            break;
        case "term":
            fifu_db_delete_termmeta($id);
            break;
    }

    $total = fifu_db_count_metadata_operations();

    fifu_set_transient('fifu_metadata_counter', $total, 0);

    $result = fifu_db_get_meta_out_first();
    if (isset($result[0])) {
        $new_request = new WP_REST_Request();
        $new_request->set_param('post_id', $result[0]->post_id);
        return fifu_api_meta_out($new_request);
    }

    return new WP_REST_Response('', 200);
}

function fifu_api_pre_deactivate(WP_REST_Request $request) {
    $description = $request['description'];
    $temporary = filter_var($request['temporary'], FILTER_VALIDATE_BOOLEAN);
    fifu_send_feedback($description, $temporary);
    fifu_db_enable_clean();

    $total = fifu_db_count_metadata_operations();
    fifu_set_transient('fifu_metadata_counter', $total, 0);
    while ($total > 0) {
        wp_cache_flush();
        $total = fifu_get_transient('fifu_metadata_counter');
        sleep(3);
    }

    deactivate_plugins('featured-image-from-url/featured-image-from-url.php');
    return json_encode(array());
}

function fifu_api_feedback(WP_REST_Request $request) {
    $description = $request['description'];
    $temporary = filter_var($request['temporary'], FILTER_VALIDATE_BOOLEAN);
    fifu_send_feedback($description, $temporary);
    return json_encode(array());
}

function fifu_api_deactivate_itself(WP_REST_Request $request) {
    deactivate_plugins('featured-image-from-url/featured-image-from-url.php');
    return json_encode(array());
}

function fifu_send_feedback($description, $temporary) {
    if (!$description)
        return json_encode(array());

    $current_user = wp_get_current_user();
    $email = $current_user->exists() ? $current_user->user_email : null;

    $aux = fifu_db_get_last_image();
    $image = $aux ? fifu_db_get_last_image()[0]->meta_value : null;

    $array = array(
        'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
        'body' => json_encode(
                array(
                    'email' => $email,
                    'description' => $description,
                    'version' => fifu_version_number(),
                    'temporary' => $temporary,
                    'image' => $image,
                    'fifu_cdn_content' => fifu_is_on('fifu_cdn_content'),
                    'fifu_confirm_delete_all' => FIFU_DELETE_ALL_URLS,
                    'fifu_enable_default_url' => fifu_is_on('fifu_enable_default_url'),
                    'fifu_fake' => fifu_is_on('fifu_fake'),
                    'fifu_get_first' => fifu_is_on('fifu_get_first'),
                    'fifu_hide' => fifu_is_on('fifu_hide'),
                    'fifu_ovw_first' => fifu_is_on('fifu_ovw_first'),
                    'fifu_pcontent_add' => fifu_is_on('fifu_pcontent_add'),
                    'fifu_pcontent_remove' => fifu_is_on('fifu_pcontent_remove'),
                    'fifu_photon' => fifu_is_on('fifu_photon'),
                    'fifu_wc_lbox' => fifu_is_on('fifu_wc_lbox'),
                    'fifu_wc_zoom' => fifu_is_on('fifu_wc_zoom'),
                )
        ),
        'method' => 'POST',
        'data_format' => 'body',
        'blocking' => false,
        'timeout' => 30,
    );
    fifu_remote_post(FIFU_SURVEY_ADDRESS . '/deactivate/', $array);
}

function fifu_test_execution_time() {
    $start_time = microtime(true);
    for ($i = 0; $i <= 120; $i++) {
        error_log($i);
        sleep(1);
        //flush();
    }
    error_log(number_format(microtime(true) - $start_time, 4));
    return json_encode(array());
}

add_action('rest_api_init', function () {
    register_rest_route('featured-image-from-url/v2', '/metadata_counter_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_metadata_counter_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/enable_fake_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_enable_fake_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/disable_fake_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_disable_fake_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/data_clean_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_data_clean_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/run_delete_all_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_run_delete_all_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/disable_default_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_disable_default_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/none_default_api/', array(
        'methods' => 'POST',
        'callback' => 'fifu_none_default_api',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/pre_deactivate/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_pre_deactivate',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/feedback/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_feedback',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/deactivate_itself/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_deactivate_itself',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/rest_url_api/', array(
        'methods' => ['GET', 'POST'],
        'callback' => 'fifu_rest_url',
        'permission_callback' => 'fifu_public_permission',
    ));
    register_rest_route('featured-image-from-url/v2', '/metain/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_meta_in',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            $transient_token = fifu_get_transient('fifu_api_metain_auth_token');
            if ($token === $transient_token) {
                fifu_delete_transient('fifu_api_metain_auth_token');
                return true;
            }
            return false;
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
    register_rest_route('featured-image-from-url/v2', '/metaout/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_meta_out',
        'permission_callback' => function ($request) {
            $token = $request->get_header('X-FIFU-Authorization');
            $transient_token = fifu_get_transient('fifu_api_metaout_auth_token');
            if ($token === $transient_token) {
                fifu_delete_transient('fifu_api_metaout_auth_token');
                return true;
            }
            return false;
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    register_rest_route('featured-image-from-url/v2', '/create_thumbnails_list/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_create_thumbnails_list',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/sign_up/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_sign_up',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/connected/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_connected',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/reset_credentials/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_reset_credentials',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/list_all_su/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_all_su',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/list_all_fifu/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_all_fifu',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/list_all_media_library/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_all_media_library',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/list_daily_count/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_list_daily_count',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/delete/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_delete',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/cancel/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cancel',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/payment_info/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_payment_info',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/cloud_upload_auto/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cloud_upload_auto',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/cloud_delete_auto/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cloud_delete_auto',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
    register_rest_route('featured-image-from-url/v2', '/cloud_hotlink/', array(
        'methods' => 'POST',
        'callback' => 'fifu_api_cloud_hotlink',
        'permission_callback' => 'fifu_get_private_data_permissions_check',
    ));
});

function fifu_get_private_data_permissions_check() {
    if (!current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', __('Private'), array('status' => 401));
    }
    return true;
}

function fifu_public_permission() {
    return true;
}


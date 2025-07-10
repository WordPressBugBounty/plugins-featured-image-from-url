<?php

add_action('init', 'fifu_load_textdomain');

function fifu_load_textdomain() {
    $locale = fifu_get_language_code(get_locale());

    // Do nothing if the selected language is en_US
    if ($locale === 'en' || fifu_get_transient("fifu_language_$locale")) {
        return;
    }

    $mo_file_path = WP_LANG_DIR . "/plugins/featured-image-from-url/" . fifu_version_number() . "/featured-image-from-url-$locale.mo";

    // Check if the .mo file exists locally
    if (!file_exists($mo_file_path)) {
        fifu_download_translation_files($locale);
    }

    // Load the text domain if the file exists
    if (file_exists($mo_file_path)) {
        load_textdomain('featured-image-from-url', $mo_file_path);
    } else {
        // Keep the plugin in English (default behavior)
        error_log("FIFU: Translation file for $locale not found. Defaulting to English.");
    }
}

function fifu_download_translation_files($locale) {
    $remote_base_url = 'https://storage.googleapis.com/fifu-translations/featured-image-from-url/' . fifu_version_number();

    // Convert locale to lowercase and replace "_" with "-"
    $remote_locale = strtolower(str_replace('_', '-', $locale));
    $mo_url = "$remote_base_url/featured-image-from-url-$remote_locale.mo";

    // Target directory
    $target_dir = WP_LANG_DIR . '/plugins/featured-image-from-url/' . fifu_version_number() . '/';
    if (!is_dir($target_dir)) {
        wp_mkdir_p($target_dir);
    }

    // Download .mo file
    $mo_target = $target_dir . "featured-image-from-url-$locale.mo";
    $response = wp_remote_get($mo_url);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
        file_put_contents($mo_target, wp_remote_retrieve_body($response));
        error_log("FIFU: Successfully downloaded translation for $locale.");
    } else {
        // Log error and do nothing (default to English)
        error_log("FIFU: Failed to download translation for $locale. Keeping default language.");
        fifu_set_transient("fifu_language_$locale", new DateTime(), 12 * HOUR_IN_SECONDS);
    }
}

function fifu_get_language_code($locale) {
    $locale_to_lang_map = [
        "af" => "af",
        "am" => "am",
        "ar" => "ar",
        "arg" => "an",
        "arq" => "arq",
        "art_xemoji" => "art-xemoji",
        "art_xpirate" => "pirate",
        "ary" => "ary",
        "as" => "as",
        "ast" => "ast",
        "az" => "az",
        "azb" => "azb",
        "az_TR" => "az-tr",
        "ba" => "ba",
        "bal" => "bal",
        "bcc" => "bcc",
        "bel" => "bel",
        "bg_BG" => "bg",
        "bho" => "bho",
        "bn_BD" => "bn",
        "bn_IN" => "bn-in",
        "bo" => "bo",
        "bre" => "br",
        "brx" => "brx",
        "bs_BA" => "bs",
        "ca" => "ca",
        "ca_valencia" => "ca-val",
        "ceb" => "ceb",
        "ckb" => "ckb",
        "co" => "co",
        "cor" => "cor",
        "cs_CZ" => "cs",
        "cy" => "cy",
        "da_DK" => "da",
        "de_AT" => "de-at",
        "de_CH" => "de-ch",
        "de_DE" => "de",
        "dsb" => "dsb",
        "dv" => "dv",
        "dzo" => "dzo",
        "el" => "el",
        "en_AU" => "en-au",
        "en_CA" => "en-ca",
        "en_GB" => "en-gb",
        "en_NZ" => "en-nz",
        "en_ZA" => "en-za",
        "eo" => "eo",
        "es_AR" => "es-ar",
        "es_CL" => "es-cl",
        "es_CO" => "es-co",
        "es_CR" => "es-cr",
        "es_DO" => "es-do",
        "es_EC" => "es-ec",
        "es_ES" => "es",
        "es_GT" => "es-gt",
        "es_HN" => "es-hn",
        "es_MX" => "es-mx",
        "es_PE" => "es-pe",
        "es_PR" => "es-pr",
        "es_UY" => "es-uy",
        "es_VE" => "es-ve",
        "et" => "et",
        "eu" => "eu",
        "ewe" => "ee",
        "fa_AF" => "fa-af",
        "fa_IR" => "fa",
        "fi" => "fi",
        "fo" => "fo",
        "fon" => "fon",
        "fr_BE" => "fr-be",
        "fr_CA" => "fr-ca",
        "fr_FR" => "fr",
        "frp" => "frp",
        "fuc" => "fuc",
        "fur" => "fur",
        "fy" => "fy",
        "ga" => "ga",
        "gax" => "gax",
        "gd" => "gd",
        "gl_ES" => "gl",
        "gu" => "gu",
        "hat" => "hat",
        "hau" => "hau",
        "haw_US" => "haw",
        "haz" => "haz",
        "he_IL" => "he",
        "hi_IN" => "hi",
        "hr" => "hr",
        "hsb" => "hsb",
        "hu_HU" => "hu",
        "hy" => "hy",
        "ibo" => "ibo",
        "id_ID" => "id",
        "ido" => "ido",
        "is_IS" => "is",
        "it_IT" => "it",
        "ja" => "ja",
        "jv_ID" => "jv",
        "kaa" => "kaa",
        "kab" => "kab",
        "ka_GE" => "ka",
        "kal" => "kal",
        "kin" => "kin",
        "kir" => "kir",
        "kk" => "kk",
        "km" => "km",
        "kmr" => "kmr",
        "kn" => "kn",
        "ko_KR" => "ko",
        "la" => "la",
        "lb_LU" => "lb",
        "lij" => "lij",
        "li" => "li",
        "lin" => "lin",
        "lmo" => "lmo",
        "lo" => "lo",
        "lt_LT" => "lt",
        "lug" => "lug",
        "lv" => "lv",
        "mai" => "mai",
        "me_ME" => "me",
        "mfe" => "mfe",
        "mg_MG" => "mg",
        "mk_MK" => "mk",
        "ml_IN" => "ml",
        "mlt" => "mlt",
        "mn" => "mn",
        "mri" => "mri",
        "mr" => "mr",
        "ms_MY" => "ms",
        "my_MM" => "mya",
        "nb_NO" => "nb",
        "ne_NP" => "ne",
        "nl_BE" => "nl-be",
        "nl_NL" => "nl",
        "nn_NO" => "nn",
        "nqo" => "nqo",
        "oci" => "oci",
        "ory" => "ory",
        "os" => "os",
        "pa_IN" => "pa",
        "pap_AW" => "pap-aw",
        "pap_CW" => "pap-cw",
        "pcd" => "pcd",
        "pcm" => "pcm",
        "pl_PL" => "pl",
        "ps" => "ps",
        "pt_AO" => "pt-ao",
        "pt_BR" => "pt-br",
        "pt_PT" => "pt",
        "rhg" => "rhg",
        "roh" => "roh",
        "ro_RO" => "ro",
        "ru_RU" => "ru",
        "sah" => "sah",
        "sa_IN" => "sa-in",
        "scn" => "scn",
        "si_LK" => "si",
        "skr" => "skr",
        "sk_SK" => "sk",
        "sl_SI" => "sl",
        "sna" => "sna",
        "snd" => "snd",
        "so_SO" => "so",
        "sq" => "sq",
        "sq_XK" => "sq-xk",
        "srd" => "srd",
        "sr_RS" => "sr",
        "ssw" => "ssw",
        "su_ID" => "su",
        "sv_SE" => "sv",
        "sw" => "sw",
        "syr" => "syr",
        "szl" => "szl",
        "tah" => "tah",
        "ta_IN" => "ta",
        "ta_LK" => "ta-lk",
        "te" => "te",
        "tg" => "tg",
        "th" => "th",
        "tir" => "tir",
        "tl" => "tl",
        "tr_TR" => "tr",
        "tt_RU" => "tt",
        "tuk" => "tuk",
        "twd" => "twd",
        "tzm" => "tzm",
        "ug_CN" => "ug",
        "uk" => "uk",
        "ur" => "ur",
        "uz_UZ" => "uz",
        "vec" => "vec",
        "vi" => "vi",
        "wol" => "wol",
        "xho" => "xho",
        "yor" => "yor",
        "zgh" => "zgh",
        "zh_CN" => "zh-cn",
        "zh_HK" => "zh-hk",
        "zh_SG" => "zh-sg",
        "zh_TW" => "zh-tw",
        "zul" => "zul",
    ];

    // Return the language code if it exists in the mapping
    if (isset($locale_to_lang_map[$locale])) {
        return $locale_to_lang_map[$locale];
    }

    // Dynamically handle locales not in the mapping
    $parts = explode('_', $locale);
    return !empty($parts) ? $parts[0] : $locale; // Return the first part of the locale (e.g., "es" from "es_ES")
}

=== Featured Image from URL (FIFU) ===
Contributors: marceljm
Donate link: https://www.paypal.com/donate/?hosted_button_id=KY7MRYTANZN9A
Tags: featured, image, url, woocommerce, remote
Requires at least: 5.6
Tested up to: 7.1
Stable tag: 6.0.4
Requires PHP: 8.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Use remote media as the featured image and beyond.

== Description ==

### WordPress plugin for remote featured images and more

FIFU has helped thousands of websites worldwide save storage, processing resources, and time since 2015.

If you are tired of wasting time and resources with thumbnail regeneration, image optimization, and never-ending imports, this plugin is for you.

#### Featured image

Use a remote image as the featured image of your post, page, custom post type, or WooCommerce product.

* Remote featured image
* Featured image alternative text
* Optimized images
* Make all images square
* Image search with Unsplash
* Default featured image
* Hide featured media
* Modify post content
* Auto set image title
* Featured image column
* Quick Edit
* **[PRO]** Image search with a search engine
* **[PRO]** Disable right-click
* **[PRO]** Save in the media library
* **[PRO]** Replace not found image
* **[PRO]** Custom popup
* **[PRO]** bbPress and BuddyBoss Platform integration
* **[PRO]** Page redirection

#### Category image

Use a remote image for categories and other supported taxonomy terms.

* Remote category image
* Category image alternative text

#### Automatic featured media

* Auto set featured media from post content
* **[PRO]** Auto set featured image using post title and a search engine
* **[PRO]** Auto set featured media using web page address
* **[PRO]** Auto set product images from ASIN
* **[PRO]** Auto set featured media from custom field
* **[PRO]** Auto set screenshot as featured image
* **[PRO]** Auto-share on social media

#### Automation

* WP-CLI integration
* Developer functions
* **[PRO]** Add-on for WP All Import
* **[PRO]** WooCommerce import
* **[PRO]** Advanced REST API integrations

#### WooCommerce

* Remote product image
* Lightbox and zoom
* Remote product category image
* **[PRO]** Gallery for remote images
* **[PRO]** Gallery for remote videos
* **[PRO]** Category images auto set
* **[PRO]** Variable product image tools
* **[PRO]** Variation image tools
* **[PRO]** Gallery for variation images
* **[PRO]** Save in the media library
* **[PRO]** FIFU product gallery
* **[PRO]** Quick Buy
* **[PRO]** Add image to order email

#### Featured video

The PRO version supports featured videos from services and external video files.

* **[PRO]** Featured video
* **[PRO]** Watch later
* **[PRO]** Video thumbnail
* **[PRO]** Play button
* **[PRO]** Minimum width
* **[PRO]** Video controls
* **[PRO]** Autoplay on mouseover
* **[PRO]** Autoplay
* **[PRO]** Playback loop
* **[PRO]** Mute

#### Widgets for Elementor

* Featured image
* **[PRO]** Featured video

#### Fields for Gravity Forms

* Featured image
* **[PRO]** Featured video
* **[PRO]** Featured slider

#### Fields for Dokan

* Featured image
* **[PRO]** Product gallery

#### Other PRO features

* **[PRO]** Featured audio
* **[PRO]** Featured slider
* **[PRO]** Image and video galleries
* **[PRO]** Shortcodes
* **[PRO]** Advanced WooCommerce media features

#### Functions for developers

* **Featured image:** `fifu_dev_set_image($post_id, $image_url)`
* **Product category image:** `fifu_dev_set_category_image($term_id, $image_url)`
* **[PRO] Featured video:** `fifu_dev_set_video($post_id, $video_url)`
* **[PRO] Featured slider:** `fifu_dev_set_slider($post_id, $url_list, $alt_list)`
* **[PRO] Product image + Image gallery:** `fifu_dev_set_image_list($post_id, $image_url_list)`
* **[PRO] Product video + Video gallery:** `fifu_dev_set_video_list($post_id, $video_url_list)`
* **[PRO] Product category video:** `fifu_dev_set_category_video($term_id, $video_url)`

#### FIFU Cloud

* Cloud storage
* Global CDN
* Optimized thumbnails
* Automatic cloud upload and deletion scheduling
* Hotlink protection

#### Links

* **<a href="https://fifu.app/">FIFU PRO</a>**
* **<a href="https://tastewp.com/new?pre-installed-plugin-slug=featured-image-from-url&redirect=admin.php%3Fpage%3Dfeatured-image-from-url&ni=true">Dummy site for testing</a>**
* **<a href="https://chrome.google.com/webstore/detail/fifu-scraper/pccimcccbkdeeadhejdmnffmllpicola">Extension for Google Chrome</a>**
* **<a href="https://plugintests.com/plugins/wporg/featured-image-from-url/latest">Smoke Test</a>**

== Installation ==

### Install FIFU from within WordPress

1. Visit the Plugins page in your WordPress dashboard and select "Add New".
2. Search for "FIFU".
3. Install and activate FIFU.

### Install FIFU manually

1. Upload the `featured-image-from-url` folder to `/wp-content/plugins/`.
2. Activate FIFU through the Plugins menu in WordPress.

== Frequently Asked Questions ==

= Why isn't the preview button working? =

Your image URL may be invalid. Check Settings → Getting started.

= Does FIFU save images in the WordPress media library? =

No. FIFU is designed to work with external images. Features that save remote images in the WordPress media library are available in FIFU PRO.

= Why is the featured image displayed twice? =

Check whether the option that adds featured media to the post content is enabled unnecessarily.

= Why is the featured image not displayed? =

Check whether Hide Featured Media is enabled.

= Why are there no changes after updating settings? =

Clear any page, object, or CDN caches used by the site.

= Is any action necessary before removing FIFU? =

If you no longer need FIFU-generated metadata, use the cleanup tools available in FIFU settings before removing the plugin.

= What metadata does FIFU create? =

FIFU creates database records that allow WordPress components and integrations to work with remote images.

= What are the disadvantages of remote images? =

Remote images do not automatically have the same locally generated thumbnails as WordPress media-library images. FIFU provides features such as Optimized Images to handle this use case.

= What are the advantages of remote images? =

Remote images can reduce local storage requirements and make large imports significantly faster because the original images do not need to be downloaded into the WordPress media library.

= Do remote images affect SEO? =

Search engines can index remote images. As with local images, image accessibility, performance, structured data, alternative text, and the reliability of the source URL can affect results.

== Screenshots ==

1. Featured image
2. Image search
3. Featured image settings
4. WooCommerce remote product image
5. Quick Edit
6. Elementor integration
7. Category image
8. Settings → Help
9. Settings → Image
10. Settings → Automatic
11. Settings → WooCommerce
12. Settings → Metadata
13. Settings → Developers
14. FIFU Cloud

== Changelog ==

= 6.0.4 =
* Fix: Fixed featured image synchronization in the block editor (Gutenberg) when setting, changing, or removing a remote featured image.

= 6.0.3 =
* Fix: Restored remote featured images correctly in the Classic Editor, including when changing or removing an existing featured image.
* Fix: Rank Math social image URLs now remain secure HTTPS URLs instead of being changed to HTTP.
* Fix: Fixed database setup and upgrade compatibility with MariaDB 10.3.
* Fix: Featured image width and height are now saved correctly on the first editor save.
* Performance: Prevented repeated database upgrade work on WordPress Multisite installations.
* Compatibility: WordPress 7.1.

= 6.0.2 =
* Fix: Improved compatibility with page builders and third-party plugins by safely handling unexpected data passed through WordPress hooks.
* Fix: Fixed remote featured images when cloning posts with Yoast Duplicate Post.
* Performance: Improved database upgrade performance and fixed database initialization on WordPress Multisite installations.
* Compatibility: WordPress 7.0.4.

= 6.0.1 =
* Fix: Improved compatibility with third-party plugins and page builders, including cases that could prevent editors such as Divi from loading.
* Fix: Fixed an upgrade issue from FIFU 6.0.0 that could cause featured images to stop updating or disappear in some cases.
* Performance: Improved post and page saving performance by avoiding unnecessary featured image processing.
* Compatibility: WooCommerce 11.0.1.

= 6.0.0 =
* Completely refactored version with an AI-integrated development workflow for faster development, maintenance, and troubleshooting; FIFU now stores remote image URLs in its own database tables instead of relying only on WordPress metadata, improving performance especially on websites with many posts or products; compatibility with PHP 8.1+, WordPress 7.0.3, and WooCommerce 11.0.0.

= 5.3.3 =
* Fix: Integration with WPML not working; Fix: Deprecated notices.

= 5.3.2 =
* Fix: vulnerability reported by Wordfence team.

= 5.3.1 =
* New feature: Auto-share on social media; Fix: Featured image might not be displaying on X.

= 5.3.0 =
* Enhancement: Quick Edit column (PRO feature) not displayed initially for new users to avoid confusion; Enhancement: bbPress and BuddyBoss Platform (can now add images to activities).

= 5.2.9 =
* New: multisite network menu; Enhancement: integration with the WPML Multilingual CMS plugin (when a post or product is duplicated, FIFU now duplicates its image data); Fix: Optimized Images (URLs not being included in structured data); Fix: possible syntax error on sites with very old PHP versions.

= 5.2.8 =
* Fixes: vulnerabilities reported by the Wordfence Security team.

= 5.2.7 =
* New: Notice to rate the plugin; Enhancement: Auto set featured media from post content (now supports local relative URLs); Fix: Incomplete product data generated for Rich Results.

= 5.2.6 =
* Enhancement: improved integration with Rich Results from Google; Enhancement: alternative text can now be displayed as captions at the bottom of the image; Fix: images defined by other plugins were being displayed on social media instead of the remote featured image; Fix: Elementor widget not working with newer Elementor versions.

= 5.2.5 =
* Enhancement: Improved translations and support for 40 new languages; Enhancement: Alternative Text field now opens in the lightbox for easier editing; Enhancement: Redirects to the plugin settings after activation; Fix: Quick Edit not working for variable products with multiple attributes; Fix: Google Drive images not displaying in the admin area.

= 5.2.4 =
* New: Optimized Images > Sizes > Make all images square; Enhancement: Collection of anonymous stats is not necessary for now and has been disabled; Fix: Resolved conflict with Rank Math SEO plugin (fatal error).

= others =
* [more](https://fifu.app/changelog)

== Upgrade Notice ==

= 6.0.4 =
* Fixes remote featured image synchronization in the block editor when images are set, changed, or removed.

= 6.0.3 =
* Fixes Classic Editor featured images, Rank Math HTTPS social images, MariaDB 10.3 database upgrades, first-save image dimensions, improves Multisite upgrade performance, and adds WordPress 7.1 compatibility.

= 6.0.2 =
* Improves compatibility with third-party plugins and page builders, fixes remote featured image cloning, improves Multisite upgrades, and adds compatibility with WordPress 7.0.4.

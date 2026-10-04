=== Featured Image from URL (FIFU) ===
Contributors: marceljm
Donate link: https://www.paypal.com/donate/?hosted_button_id=KY7MRYTANZN9A
Tags: featured, image, url, woocommerce, remote
Requires at least: 5.6
Tested up to: 7.1.2
Stable tag: 6.1.0
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

= 6.1.0 =
* Fix: Preserved query-dependent featured image URLs in Rank Math Facebook and Twitter social metadata.
* Enhancement: Removed the outdated Troubleshooting page from the FIFU menu.

= 6.0.9 =
* Enhancement: Unified the Featured Media editor with the new Premium-style layout and improved the Quick Editor experience.
* Enhancement: Improved featured image preview, alternative text editing, and locked PRO media controls in the editor.
* Enhancement: Unified the product category featured media editor.
* Enhancement: Added WP Automatic Pro integration for FIFU remote featured images.
* Fix: Rank Math now preserves query parameters in social image URLs.
* Fix: Rank Math now detects FIFU alternative text correctly for featured images.
* Fix: Eliminated repeated FIFU attachment-author database queries to improve performance.
* Compatibility: Added support for WordPress 7.1.2 and WooCommerce 11.1.2.

= 6.0.8 =
* Fix: Security issue reported by the Patchstack team.

= 6.0.7 =
* Fix: Database updates can now retry after a failed update.

= 6.0.6 =
* Enhancement: Manual metadata generation and cleanup now use a faster browser-driven workflow.
* Fix: Improved the Clear Metadata tool with more reliable processing and progress reporting.
* Performance: Improved compatibility with FacetWP to avoid unnecessary image processing.
* Fix: Remote product images are now handled correctly when duplicating WooCommerce products.
* Fix: Improved compatibility with themes and plugins that provide WordPress object IDs as numeric strings.
* Fix: Signed CDN image URLs now work correctly with Unicode characters.
* Fix: Improved compatibility with block-based editors by preventing conflicting WordPress editor scripts from being loaded.
* Fix: Improved WoodMart compatibility by preventing remote FIFU images from being processed as local thumbnails.

= 6.0.5 =
* Fix: Featured images are now synchronized correctly with third-party plugins that process posts immediately after they are saved.
* Fix: WooCommerce product featured images no longer disappear after changing a product permalink.

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

= others =
* [more](https://fifu.app/changelog)

== Upgrade Notice ==

= 6.1.0 =
* Improves Rank Math compatibility for remote featured images with query-dependent URLs and removes the outdated Troubleshooting page.
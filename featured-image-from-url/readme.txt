=== Featured Image from URL (FIFU) ===
Contributors: marceljm
Donate link: https://www.paypal.com/donate/?hosted_button_id=KY7MRYTANZN9A
Tags: featured, image, remote, url, product image
Requires at least: 5.6
Tested up to: 7.1.3
Stable tag: 6.1.3
Requires PHP: 8.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add remote featured images and WooCommerce product images from external URLs. Save storage, speed up imports, and use featured videos with FIFU PRO.

== Description ==

### Remote featured images from external URLs

FIFU (Featured Image from URL) lets you use a remote or external image URL as the featured image for WordPress posts, pages, custom post types, and WooCommerce products — without downloading the original image to the Media Library.

Use remote featured images, external featured images, WooCommerce product images, category images, and post thumbnails directly from their source URLs.

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
* Disable right-click
* Replace not found image
* **[PRO]** Image search with a search engine
* **[PRO]** Save in the media library
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

= Can I use an external image URL as the featured image? =

Yes. FIFU lets you use a remote or external image URL as the featured image for posts, pages, custom post types, and WooCommerce products without downloading the original image to the WordPress Media Library.

= Can I use a remote image as a WooCommerce product image? =

Yes. FIFU supports remote product images and remote product category images for WooCommerce. Additional product gallery and advanced media features are available in FIFU PRO.

= Is a post thumbnail the same as a featured image? =

In WordPress, the post thumbnail API is used for featured images. FIFU integrates remote featured images with WordPress components and supported themes and plugins.

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

1. Remote featured image
2. Remote image search
3. Remote featured video (Premium-only feature screenshot)
4. Remote product gallery
5. Remote variable product images
6. Quick editor for remote featured media
7. Settings → Help
8. Settings → Image
9. Settings → Video / Audio
10. Settings → Slider
11. Settings → Automatic
12. Settings → WooCommerce
13. Settings → WP All Import
14. Settings → Metadata
15. Settings → Developers

== Changelog ==

= 6.1.3 =
* New free feature: Replace Not Found Image to display a fallback image when a remote image fails to load.
* Enhancement: Improved Quick Editor image preview sizing for a more compact and responsive dialog.
* Fix: Improved remote image attachment cleanup to protect local media and images still in use.
* Fix: Improved remote image synchronization and recovery.
* Enhancement: Refreshed the FIFU administration interface.

= 6.1.2 =
* New: Disable right-click on frontend images from FIFU settings.
* Fix: Resolved PHP 8.5 and newer deprecation warnings during remote image URL validation.
* Compatibility: Improved compatibility with PHP 8.5 and PHP 8.6.

= 6.1.1 =
* Fix: Explicit FIFU featured image removals and replacements are no longer overwritten by later Content Egg synchronization.
* Fix: The Status page now shows DB2-backed featured image URLs correctly even when the site is using legacy mode.
* Enhancement: Combined the PRO Featured Video and Featured Audio settings preview into a single Video / Audio section.
* Compatibility: Added support for WordPress 7.1.3.

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

== Upgrade Notice ==

= 6.1.3 =
* Adds replacement images for broken remote image URLs and improves Quick Editor previews.
* Improves the safety and reliability of remote image synchronization and cleanup.

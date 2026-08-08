<?php

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fifu_Cloud_Strings.
 *
 * @package Fifu_Free
 */
class Fifu_Cloud_Strings {

    /**
     * @return array<string, mixed>
     */
    public static function get_strings(): array {
        $fifu = array();

        // title
        $fifu['title']['price'] = function () {
            return _e("Pricing", FIFU_SLUG);
        };
        $fifu['title']['account'] = function () {
            return _e("Account", FIFU_SLUG);
        };
        $fifu['title']['hotlink'] = function () {
            return _e("Hotlink protection", FIFU_SLUG);
        };
        $fifu['title']['payment'] = function () {
            return _e("Payment and billing information", FIFU_SLUG);
        };
        $fifu['title']['add'] = function () {
            return _e("Upload to Cloud", FIFU_SLUG);
        };
        $fifu['title']['delete'] = function () {
            return _e("Delete from Cloud", FIFU_SLUG);
        };
        $fifu['title']['media'] = function () {
            return _e("Link local image URLs to FIFU plugin", FIFU_SLUG);
        };
        $fifu['title']['billing'] = function () {
            return _e("Billing", FIFU_SLUG);
        };

        // tabs
        $fifu['tabs']['welcome'] = function () {
            return _e("Welcome", FIFU_SLUG);
        };
        $fifu['tabs']['send'] = function () {
            return _e("Upload", FIFU_SLUG);
        };
        $fifu['tabs']['delete'] = function () {
            return _e("Delete", FIFU_SLUG);
        };
        $fifu['tabs']['media'] = function () {
            return _e("Local images", FIFU_SLUG);
        };
        $fifu['tabs']['trash'] = function () {
            return _e("Trash", FIFU_SLUG);
        };
        $fifu['tabs']['account'] = function () {
            return _e("Account", FIFU_SLUG);
        };

        // info
        $fifu['ws']['down'] = function () {
            return __("Web service is down", FIFU_SLUG);
        };
        $fifu['ws']['connection']['ok'] = function () {
            return __("Connected", FIFU_SLUG);
        };
        $fifu['ws']['connection']['fail'] = function () {
            return __("Not connected", FIFU_SLUG);
        };

        // table
        $fifu['table']['no']['images'] = function () {
            return __("No images available", FIFU_SLUG);
        };
        $fifu['table']['no']['posts'] = function () {
            return __("No posts available", FIFU_SLUG);
        };
        $fifu['table']['no']['data'] = function () {
            return __("No data available", FIFU_SLUG);
        };
        $fifu['table']['select']['all'] = function () {
            return __("select all", FIFU_SLUG);
        };
        $fifu['table']['select']['none'] = function () {
            return __("select none", FIFU_SLUG);
        };
        $fifu['table']['load'] = function () {
            return __("load more", FIFU_SLUG);
        };
        $fifu['table']['limit'] = function () {
            return __("1,000 rows limit", FIFU_SLUG);
        };
        $fifu['table']['delete'] = function () {
            return __("delete", FIFU_SLUG);
        };
        $fifu['table']['upload'] = function () {
            return __("upload", FIFU_SLUG);
        };
        $fifu['table']['link'] = function () {
            return __("link", FIFU_SLUG);
        };
        $fifu['table']['dialog']['delete'] = function () {
            return __("Delete", FIFU_SLUG);
        };
        $fifu['table']['dialog']['cancel'] = function () {
            return __("Cancel", FIFU_SLUG);
        };
        $fifu['table']['dialog']['yes'] = function () {
            return __("Yes", FIFU_SLUG);
        };
        $fifu['table']['dialog']['no'] = function () {
            return __("No", FIFU_SLUG);
        };
        $fifu['table']['category'] = function () {
            return __("category", FIFU_SLUG);
        };
        $fifu['table']['slider'] = function () {
            return __("slider", FIFU_SLUG);
        };
        $fifu['table']['gallery'] = function () {
            return __("gallery", FIFU_SLUG);
        };
        $fifu['table']['featured'] = function () {
            return __("featured media", FIFU_SLUG);
        };
        $fifu['table']['filter'] = function () {
            return __("Filter results", FIFU_SLUG);
        };
        $fifu['table']['show'] = function () {
            return __("Show results", FIFU_SLUG);
        };

        // support
        $fifu['support']['whats'] = function () {
            _e("FIFU Cloud is a cloud-based service that securely stores your images within the robust infrastructure of Google Cloud. Not only does FIFU Cloud ensure image preservation, but it also optimizes and rapidly delivers them through Google's global Edge Network. Additionally, FIFU Cloud automatically generates thumbnails for each image and serves them in the efficient webp format, enhancing the overall performance of your website.", FIFU_SLUG);
        };
        $fifu['support']['save'] = function () {
            _e("Never lose an image again", FIFU_SLUG);
        };
        $fifu['support']['fast'] = function () {
            _e("Images load much faster", FIFU_SLUG);
        };
        $fifu['support']['process'] = function () {
            _e("Images processed in the cloud", FIFU_SLUG);
        };
        $fifu['support']['price'] = function () {
            _e("Pay per stored image", FIFU_SLUG);
        };
        $fifu['support']['smart'] = function () {
            _e("Smart cropping", FIFU_SLUG);
        };
        $fifu['support']['hotlink'] = function () {
            _e("Hotlink protection", FIFU_SLUG);
        };
        $fifu['support']['save-desc'] = function () {
            _e("Image sources sometimes remove or change the URLs of their images, either due to internal restructuring or to prevent their embedding on other websites. This can cause significant problems for websites that had previously embedded these images, as they become lost and cannot be retrieved. However, FIFU Cloud offers a solution to this issue. It saves your embedded images in the cloud and provides stable URLs to access them. By replacing the existing URLs with FIFU Cloud URLs, you eliminate the problem. Additionally, if needed, you have the option to revert back to the original URLs.", FIFU_SLUG);
        };
        $fifu['support']['fast-desc'] = function () {
            _e("One major drawback of embedding remote images on your website is the lack of thumbnails. Without thumbnails, your website loads the same large image file regardless of whether it's viewed on desktop or mobile phone, on a post or homepage. Additionally, there are instances where the image may not be optimized or hosted on a slow server. FIFU Cloud addresses all these concerns by storing and serving optimized thumbnails through a fast content delivery network (CDN). This means that when visitors access your pages, they receive only the smallest image files required to display the images without any loss in quality. The smaller the file size, the faster the images are rendered, resulting in improved loading times for your website.", FIFU_SLUG);
        };
        $fifu['support']['process-desc'] = function () {
            _e("Your website was not designed for image processing. However, when you save an image in the media library, the WordPress core, along with your theme and plugins, initiate multiple tasks to process the image locally. These tasks include conversions, duplications, rotations, resizing, cropping, compression, and more. Depending on the number of images, this process can take weeks, and eventually, the website needs to repeat the entire process again. This consumes significant storage, memory, and processing power, which can result in slow website performance for users. In contrast, FIFU Cloud eliminates the need to use your own computing resources. We process your images entirely on Google Cloud servers. By leveraging the power of the cloud, we can efficiently process and store thousands of images simultaneously within seconds.", FIFU_SLUG);
        };
        $fifu['support']['price-desc2'] = function () {
            _e("Similar cloud services often charge based on the number of accesses to images or sell static plans where you pay for the allocated storage, even if it remains unused. However, FIFU Cloud takes a different approach. It only charges for the daily average of stored images over a 30-day period, excluding thumbnails from the billing. Let's consider an example: on the first day, you stored 1000 images; ten days later, you deleted all of them; and then, ten days after that, you added 1100 images, which were stored for ten days. Thus, the average usage over the 30-day period would be 700 images per day, and you will only be charged for that amount. If there are no changes in the next period, the average would be 1100, resulting in an increased cost. However, if you remove all the images in the subsequent period, there will be no charge incurred.", FIFU_SLUG);
        };
        $fifu['support']['smart-desc'] = function () {
            _e("WordPress themes and social media platforms often crop the central area of images, which can be problematic as the main object is often not centered. For example, Facebook, Twitter, and LinkedIn display featured images at ~1200×630 pixels in landscape orientation. However, sharing a full-body portrait photo may result in the cropped person losing their head and feet. FIFU Cloud, on the other hand, utilizes face and object detection to intelligently crop images, showcasing what truly matters without compromising style or information.", FIFU_SLUG);
        };
        $fifu['support']['hotlink-desc'] = function () {
            _e("Protecting your website's content, including text and image URLs, from unauthorized access and extraction by bots can be a challenging task. Once this data is obtained, it can be replicated elsewhere, diverting the rightful visitors to other platforms. Fortunately, FIFU Cloud offers a solution with hotlink protection. This feature restricts other websites (excluding social media platforms) from displaying your images. While it may not completely solve the problem, it significantly hinders the unauthorized usage of your content, as posts with blocked images are less appealing to those attempting to extract information.", FIFU_SLUG);
        };

        // pricing
        $fifu['pricing']['table']['quantity'] = function () {
            _e("Quantity of images", FIFU_SLUG);
        };
        $fifu['pricing']['desc'] = function () {
            _e("€0.001 per image. Payment is based on the daily average of stored images in FIFU Cloud, billed every 30 days.", FIFU_SLUG);
        };
        $fifu['pricing']['thumbnails'] = function () {
            _e("You don't pay for the multiple thumbnails generated for each image.", FIFU_SLUG);
        };
        $fifu['pricing']['example'] = function () {
            _e("Price calculation example", FIFU_SLUG);
        };
        $fifu['pricing']['table']['interval'] = function () {
            _e("30-day period interval", FIFU_SLUG);
        };
        $fifu['pricing']['table']['days'] = function () {
            _e("Number of days", FIFU_SLUG);
        };
        $fifu['pricing']['table']['stored'] = function () {
            _e("Quantity of images in FIFU Cloud", FIFU_SLUG);
        };
        $fifu['pricing']['table']['average'] = function () {
            _e("30-day average usage", FIFU_SLUG);
        };
        $fifu['pricing']['table']['price'] = function () {
            _e("Price per image", FIFU_SLUG);
        };
        $fifu['pricing']['table']['total'] = function () {
            _e("Total price", FIFU_SLUG);
        };

        // upload
        $fifu['upload']['desc'] = function () {
            _e("Costs start from the upload date.", FIFU_SLUG);
        };
        $fifu['upload']['automatic']['title'] = function () {
            _e("Automatic upload", FIFU_SLUG);
        };
        $fifu['upload']['automatic']['desc'] = function () {
            _e("Automatically uploads remote images to the cloud.", FIFU_SLUG);
        };

        // delete
        $fifu['delete']['desc'] = function () {
            _e("When an image is deleted from the cloud, you are no longer charged from the next day.", FIFU_SLUG);
        };
        $fifu['delete']['automatic']['title'] = function () {
            _e("Automatic delete", FIFU_SLUG);
        };
        $fifu['delete']['automatic']['desc'] = function () {
            _e("Automatically delete images from the cloud when they are no longer in use on the site, for example, due to a deleted post.", FIFU_SLUG);
        };

        // media
        $fifu['media']['desc'] = function () {
            _e("To have local images listed on the 'Upload' tab, you should first select them here and click the 'link' button, which will copy the local image URLs to the FIFU custom field, making FIFU responsible for displaying the images. Then you should be able to upload the local images to the cloud. Do not delete any image from the media library before doing this.", FIFU_SLUG);
        };

        // billing
        $fifu['billing']['desc'] = function () {
            _e("FIFU Cloud charges based on the average number of stored images within each 30-day period. The data below is updated hourly.", FIFU_SLUG);
        };
        $fifu['billing']['current'] = function () {
            _e("Current 30-day period", FIFU_SLUG);
        };
        $fifu['billing']['column']['start'] = function () {
            _e("Start date", FIFU_SLUG);
        };
        $fifu['billing']['column']['end'] = function () {
            _e("End date", FIFU_SLUG);
        };
        $fifu['billing']['column']['average'] = function () {
            _e("Daily average of stored images", FIFU_SLUG);
        };
        $fifu['billing']['column']['cost'] = function () {
            _e("Current cost", FIFU_SLUG);
        };

        // keys
        $fifu['keys']['header'] = function () {
            _e("Multiple image selection", FIFU_SLUG);
        };
        $fifu['keys']['adjacent'] = function () {
            _e("Adjacent", FIFU_SLUG);
        };
        $fifu['keys']['non-adjacent'] = function () {
            _e("Non-adjacent", FIFU_SLUG);
        };
        $fifu['keys']['shift'] = function () {
            _e("To select multiple images adjacent to each other, click the first image, press <b>SHIFT</b> and click the last image.", FIFU_SLUG);
        };
        $fifu['keys']['ctrl'] = function () {
            _e("To select multiple non-adjacent images, click the first image, press the <b>CTRL</b> key, and click each desired image.", FIFU_SLUG);
        };

        // label
        $fifu['label']['email'] = function () {
            _e("Email", FIFU_SLUG);
        };
        $fifu['label']['website'] = function () {
            _e("Site", FIFU_SLUG);
        };
        $fifu['label']['title']['email'] = function () {
            _e("Enter your email", FIFU_SLUG);
        };

        // pro
        $fifu['unlock'] = function () {
            _e("Upgrade to PRO", FIFU_SLUG);
        };

        // reset
        $fifu['reset']['button'] = function () {
            _e("Reset credentials", FIFU_SLUG);
        };

        // signup
        $fifu['signup']['email']['message'] = function () {
            _e("Please enter your email", FIFU_SLUG);
        };
        $fifu['signup']['button'] = function () {
            _e("Sign up", FIFU_SLUG);
        };

        // column
        $fifu['column']['image'] = function () {
            _e("Image", FIFU_SLUG);
        };
        $fifu['column']['title'] = function () {
            _e("Post title", FIFU_SLUG);
        };
        $fifu['column']['published'] = function () {
            _e("Post date", FIFU_SLUG);
        };
        $fifu['column']['id'] = function () {
            _e("Post ID", FIFU_SLUG);
        };
        $fifu['column']['location'] = function () {
            _e("Image location", FIFU_SLUG);
        };
        $fifu['column']['upload'] = function () {
            _e("Upload date", FIFU_SLUG);
        };
        $fifu['column']['featured'] = function () {
            _e("Featured image", FIFU_SLUG);
        };
        $fifu['column']['gallery'] = function () {
            _e("Gallery images", FIFU_SLUG);
        };
        $fifu['column']['date'] = function () {
            _e("Date", FIFU_SLUG);
        };
        $fifu['column']['number'] = function () {
            _e("Number of images", FIFU_SLUG);
        };

        // search
        $fifu['search']['url'] = function () {
            _e("Image URL", FIFU_SLUG);
        };
        $fifu['search']['search'] = function () {
            _e("Search", FIFU_SLUG);
        };

        // update
        $fifu['update']['button'] = function () {
            _e("Update payment method", FIFU_SLUG);
        };

        // close
        $fifu['close']['button'] = function () {
            _e("Close account", FIFU_SLUG);
        };
        $fifu['close']['title'] = function () {
            _e("Close account", FIFU_SLUG);
        };
        $fifu['close']['delete'] = function () {
            _e("All the images you uploaded to FIFU Cloud will be deleted. Are you sure?", FIFU_SLUG);
        };

        // delete dialog
        $fifu['delete']['dialog']['title'] = function () {
            _e("Remove selected image(s)", FIFU_SLUG);
        };
        $fifu['delete']['dialog']['sure'] = function () {
            _e("The selected images will be permanently removed from FIFU Cloud and cannot be recovered. Are you sure?", FIFU_SLUG);
        };

        $fifu['message']['new'] = function () {
            _e("Please wait, a much better FIFU Cloud is coming...", FIFU_SLUG);
        };
        $fifu['message']['waitlist'] = function () {
            _e("During the 2 years that FIFU Cloud has been operating, we have learned a lot and received valuable feedback from our users, allowing us to now develop a much better product. The new FIFU Cloud will be much easier to use, will give you full access to the image server, provide friendly titles to image files, and, most importantly, will be capable of storing and delivering tens of thousands of optimized images at an extremely low price, as the infrastructure will be based on free internet services. If you are already a user, you can continue using the current version of FIFU Cloud normally until the migration, when we will get in touch. And if you are not yet a user but are interested in the service, please send an email to cloud@fifu.app with the subject 'WAITLIST.' The project is expected to be completed in a few months.", FIFU_SLUG);
        };

        return $fifu;
    }
}

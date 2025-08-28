<?php

class Elementor_FIFU_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'fifu-elementor';
    }

    public function get_title() {
        $strings = fifu_get_strings_elementor();
        return '(FIFU) ' . $strings['title']['image']();
    }

    public function get_icon() {
        return 'eicon-featured-image';
    }

    public function get_categories() {
        return ['basic'];
    }

    // Use the current API method name (no underscore)
    protected function register_controls() {
        $strings = fifu_get_strings_elementor();

        $this->start_controls_section(
                'content_section_image',
                [
                    'label' => $strings['section']['image'](),
                    'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
        );
        $this->add_control(
                'fifu_input_url',
                [
                    'label' => $strings['control']['image'](),
                    'show_label' => true,
                    'label_block' => true,
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'input_type' => 'url',
                    'placeholder' => 'https://example.com/image.jpg',
                ]
        );
        $this->add_control(
                'fifu_input_alt',
                [
                    'label' => $strings['control']['alt'](),
                    'show_label' => true,
                    'label_block' => true,
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'input_type' => 'text',
                    'placeholder' => '',
                    'description' => $strings['help']['alt'](),
                ]
        );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $image_url = esc_url($settings['fifu_input_url'] ?? '');
        $alt = esc_attr($settings['fifu_input_alt'] ?? '');
        if ($image_url) {
            $image_url = fifu_convert($image_url);
            echo '<div style="width:100%;text-align:center;">';
            echo '<img class="oembed-elementor-widget fifu-elementor-image" src="' . $image_url . '" alt="' . $alt . '" onerror="this.onerror=null;this.src=\'https://storage.googleapis.com/featuredimagefromurl/image-not-found-a.jpg\';"/>';
            echo '</div>';
        }
    }
}

// Recursively traverse the editor data tree and apply logic to FIFU widgets
function fifu_walk_elements_and_apply($elements, $post_id) {
    if (!is_array($elements))
        return;

    foreach ($elements as $el) {
        if (
                isset($el['elType']) && $el['elType'] === 'widget' &&
                isset($el['widgetType'])
        ) {
            $widgetType = $el['widgetType'];
            $settings = $el['settings'] ?? [];

            if ($widgetType == 'fifu-elementor') {
                if (isset($settings['fifu_input_url'])) {
                    $image_url = $settings['fifu_input_url'];
                    if ($image_url && filter_var($image_url, FILTER_VALIDATE_URL) === false)
                        $image_url = '';
                    fifu_dev_set_image($post_id, $image_url);
                    $att_id = get_post_thumbnail_id($post_id);
                    if ($att_id && $image_url) {
                        $image_sizes = getimagesize($image_url);
                        if ($image_sizes && isset($image_sizes[0], $image_sizes[1])) {
                            fifu_save_dimensions($att_id, $image_sizes[0], $image_sizes[1]);
                        }
                    }
                }
                // Save alternative text
                if (isset($settings['fifu_input_alt'])) {
                    $alt = esc_html(wp_strip_all_tags($settings['fifu_input_alt']));
                    fifu_update_or_delete_value($post_id, 'fifu_image_alt', $alt);
                }
            }
        }

        // Recursively process child elements
        if (!empty($el['elements']) && is_array($el['elements'])) {
            fifu_walk_elements_and_apply($el['elements'], $post_id);
        }
    }
}

function fifu_image_after_save_elementor_data($post_id, $editor_data) {
    if (!is_array($editor_data))
        return;
    fifu_walk_elements_and_apply($editor_data, $post_id);
}

add_action('elementor/editor/after_save', 'fifu_image_after_save_elementor_data', 10, 2);


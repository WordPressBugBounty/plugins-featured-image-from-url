<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_FIFU_Widget extends \Elementor\Widget_Base {
    private const DEFAULT_ERROR_IMAGE_URL = 'https://storage.googleapis.com/featuredimagefromurl/image-not-found-a.jpg';

    public function get_name() {
        return 'fifu-elementor';
    }

    public function get_title() {
        $strings = Fifu_Elementor_Strings::get_strings();
        return '(FIFU) ' . $strings['title']['image']();
    }

    public function get_icon() {
        return 'eicon-featured-image';
    }

    public function get_categories() {
        return ['basic'];
    }

    protected function register_controls() {
        $strings = Fifu_Elementor_Strings::get_strings();

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
            echo '<img class="oembed-elementor-widget fifu-elementor-image" src="' . $image_url . '" alt="' . $alt . '"/>';
            echo '</div>';
        }
    }

}

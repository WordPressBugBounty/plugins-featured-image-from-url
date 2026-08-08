<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class Fifu_Gravity_Forms_Addon extends GFAddOn
{
    protected $_version = '1.0';
    protected $_slug = 'fifufieldaddon';
    protected $_path = 'featured-image-from-url/featured-image-from-url.php';
    protected $_title = '';
    protected $_short_title = '';

    /**
     * @var self|null
     */
    private static $_instance = null;

    public static function get_instance(): self
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function pre_init(): void
    {
        parent::pre_init();

        if (!$this->is_gravityforms_supported() || !class_exists('GF_Field')) {
            return;
        }

        require_once __DIR__ . '/fields/class-fifu-gf-image-field.php';
        Fifu_GF_Image_Field::register();
    }

    public function init(): void
    {
        parent::init();

        $strings = Fifu_Gravity_Forms_Strings::get_strings();
        $addon_title = $strings['title']['addon']();

        $this->_title = $addon_title;
        $this->_short_title = $addon_title;

        if (class_exists(Fifu_GF_Image_Field::class)) {
            add_filter('gform_add_field_buttons', [Fifu_GF_Image_Field::class, 'add_field_button']);
            add_filter('gform_field_types', [Fifu_GF_Image_Field::class, 'register_field_type']);
        }

        add_filter('gform_tooltips', [$this, 'tooltips']);
        add_action('gform_field_appearance_settings', [$this, 'field_appearance_settings'], 10, 2);
    }

    public function scripts(): array
    {
        return [];
    }

    public function styles(): array
    {
        return [];
    }

    public function tooltips(array $tooltips): array
    {
        $strings = Fifu_Gravity_Forms_Strings::get_strings();

        $simple_tooltips = [
            'input_class_setting' => sprintf('<h6>%s</h6>%s', $strings['css']['title'](), $strings['css']['desc']()),
        ];

        return array_merge($tooltips, $simple_tooltips);
    }

    public function field_appearance_settings(int $position, int $form_id): void
    {
        if ($position !== 250) {
            return;
        }

        $strings = Fifu_Gravity_Forms_Strings::get_strings();
        ?>
        <li class="input_class_setting field_setting">
            <label for="input_class_setting">
                <?php $strings['css']['settings']() ?>
                <?php gform_tooltip('input_class_setting') ?>
            </label>
            <input id="input_class_setting" type="text" class="fieldwidth-1" onkeyup="SetInputClassSetting(jQuery(this).val());" onchange="SetInputClassSetting(jQuery(this).val());"/>
        </li>
        <?php
    }
}

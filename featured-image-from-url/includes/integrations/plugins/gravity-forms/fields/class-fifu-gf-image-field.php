<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Fifu_GF_Image_Field extends GF_Field
{
    private const ICON = 'dashicons-camera';
    private const TYPE = 'fifu_image';

    /**
     * @var string $type The field type.
     */
    public $type = self::TYPE;

    public static function register(): void
    {
        GF_Fields::register(new self());
    }

    public static function add_field_button(array $field_groups): array
    {
        foreach ($field_groups as &$group) {
            if ($group['name'] !== 'post_fields') {
                continue;
            }

            $group['fields'][] = [
                'class' => 'button',
                'value' => self::get_fifu_field_label(),
                'icon' => self::ICON,
                'onclick' => sprintf("StartAddField('%s');", self::TYPE),
            ];

            break;
        }

        return $field_groups;
    }

    public static function register_field_type(array $field_types): array
    {
        $field_types[self::TYPE] = [
            'name' => self::TYPE,
            'label' => self::get_fifu_field_label(),
            'icon' => self::ICON,
            'class' => self::class,
        ];

        return $field_types;
    }

    public function get_form_editor_field_title(): string
    {
        return self::get_fifu_field_label();
    }

    public function get_form_editor_button(): array
    {
        return [
            'group' => 'post_fields',
            'text' => $this->get_form_editor_field_title(),
            'icon' => self::ICON,
        ];
    }

    public function get_form_editor_field_settings(): array
    {
        return [
            'label_setting',
            'description_setting',
            'rules_setting',
            'placeholder_setting',
            'input_class_setting',
            'css_class_setting',
            'size_setting',
            'admin_label_setting',
            'default_value_setting',
            'visibility_setting',
            'conditional_logic_field_setting',
        ];
    }

    public function is_conditional_logic_supported(): bool
    {
        return true;
    }

    public function get_form_editor_inline_script_on_page_render(): string
    {
        $script = sprintf("function SetDefaultValues_simple(field) {field.label = '%s';}", $this->get_form_editor_field_title()) . PHP_EOL;
        $script .= "jQuery(document).bind('gform_load_field_settings', function (event, field, form) {" .
            "var inputClass = field.inputClass == undefined ? '' : field.inputClass;" .
            "jQuery('#input_class_setting').val(inputClass);" .
            "});" . PHP_EOL;
        $script .= "function SetInputClassSetting(value) {SetFieldProperty('inputClass', value);}" . PHP_EOL;

        return $script;
    }

    public function get_field_input($form, $value = '', $entry = null)
    {
        $strings = Fifu_Gravity_Forms_Strings::get_strings();

        $id = absint($this->id);
        $form_id = absint($form['id']);
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor = $this->is_form_editor();

        $field_id = $is_entry_detail || $is_form_editor || $form_id === 0 ? "input_$id" : 'input_' . $form_id . "_$id";
        $value = esc_attr($value);

        $input_class = $this->inputClass;
        $size = $this->size;
        $class_suffix = $is_entry_detail ? '_admin' : '';
        $class = $size . $class_suffix . ' ' . $input_class;

        $tabindex = $this->get_tabindex();
        $logic_event = !$is_form_editor && !$is_entry_detail ? $this->get_conditional_logic_event('keyup') : '';
        $placeholder_attribute = 'placeholder="' . $strings['placeholder']['image']() . '"';
        $required_attribute = $this->isRequired ? 'aria-required="true"' : '';
        $invalid_attribute = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
        $disabled_text = $is_form_editor ? 'disabled="disabled"' : '';

        $input = "<input name='input_{$id}' id='{$field_id}' type='text' value='{$value}' class='{$class}' {$tabindex} {$logic_event} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text}/>";

        return sprintf("<div class='ginput_container ginput_container_%s'>%s</div>", $this->type, $input);
    }

    public function get_value_save_entry($value, $form, $input_name, $lead_id, $lead)
    {
        if ($this->phoneFormat == 'standard' && preg_match('/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $value, $matches)) {
            $value = sprintf('(%s) %s-%s', $matches[1] ?? '', $matches[2] ?? '', $matches[3] ?? '');
        }

        $_POST['fifu_input_url'] = $value;

        return $value;
    }

    /**
     * @return string
     */
    private static function get_fifu_field_label(): string
    {
        $strings = Fifu_Gravity_Forms_Strings::get_strings();
        return $strings['field']['image']() . ' (FIFU)';
    }
}

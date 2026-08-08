<?php
$rows_label = '';
ob_start();
$fifu['word']['rows']();
$rows_label = (string) ob_get_clean();

$columns_label = '';
ob_start();
$fifu['word']['columns']();
$columns_label = (string) ob_get_clean();

$escape_attr = static function (string $value): string {
    return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$escape_html = static function (string $value): string {
    return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};
?>
<p>
    <label for="<?php echo $escape_attr($this->get_field_id('rows')); ?>"><?php echo $escape_html($rows_label); ?> </label><br>
    <input type="number" id="<?php echo $escape_attr($this->get_field_id('rows')); ?>" name="<?php echo $escape_attr($this->get_field_name('rows')); ?>" min="1" value="<?php echo $escape_attr((string) $rows); ?>">
</p>
<p>
    <label for="<?php echo $escape_attr($this->get_field_id('columns')); ?>"><?php echo $escape_html($columns_label); ?> </label><br>
    <input type="number" id="<?php echo $escape_attr($this->get_field_id('columns')); ?>" name="<?php echo $escape_attr($this->get_field_name('columns')); ?>" min="1" value="<?php echo $escape_attr((string) $columns); ?>">
</p>

<?php
/**
 * Template Partial: Message
 *
 * @package SagaManagerDisplay
 * @var string $message Message text
 * @var string $type Message type (error, warning, info, success)
 */

defined('ABSPATH') || exit;

$type = $type ?? 'info';
$icon = match ($type) {
    'error' => 'warning',
    'warning' => 'info-outline',
    'success' => 'yes-alt',
    default => 'info',
};
?>

<div class="saga-message saga-message--<?php echo esc_attr($type); ?>" role="alert">
    <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
    <span><?php echo esc_html($message); ?></span>
</div>

<?php
defined('ABSPATH') || exit;
if (is_user_logged_in()) { nocache_headers(); }
// Render before wp_head so assets are enqueued in time.
$app = OI_App::render();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html(get_the_title().' — '.get_bloginfo('name')); ?></title><?php wp_head(); ?></head>
<body <?php body_class('oi-page'); ?>><?php wp_body_open(); echo $app; wp_footer(); ?></body>
</html>

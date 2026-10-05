<?php
defined('ABSPATH') || exit;
nocache_headers();
$view = OI_App::view();
OI_App::assets();
$app = match ($view) {
    'home' => OI_App::landing(),
    'register' => OI_Auth::render('register'),
    'login' => OI_Auth::render('login'),
    default => OI_App::render(),
};
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('oi-page oi-view-' . $view); ?>><?php wp_body_open(); echo $app; wp_footer(); ?></body>
</html>

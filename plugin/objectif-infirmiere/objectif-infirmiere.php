<?php
/**
 * Plugin Name: Objectif Infirmière
 * Description: Application de révision et contenus premium pour étudiants infirmiers.
 * Version: 0.2.1
 * Requires PHP: 8.3
 * Requires at least: 6.8
 * Text Domain: objectif-infirmiere
 */
defined('ABSPATH') || exit;
define('OI_VERSION', '0.2.1');
define('OI_DIR', plugin_dir_path(__FILE__));
define('OI_URL', plugin_dir_url(__FILE__));
require_once OI_DIR . 'includes/class-log.php';
require_once OI_DIR . 'includes/class-model.php';
require_once OI_DIR . 'includes/class-admin.php';
register_activation_hook(__FILE__, ['OI_Model', 'activate']);
add_action('init', ['OI_Model', 'register']);
add_action('admin_menu', ['OI_Admin', 'menu']);
add_action('add_meta_boxes', ['OI_Admin', 'boxes']);
add_action('save_post_oi_pack', ['OI_Admin', 'save_pack']);
add_action('show_user_profile', ['OI_Admin', 'user_packs']);
add_action('edit_user_profile', ['OI_Admin', 'user_packs']);
add_action('personal_options_update', ['OI_Admin', 'save_user']);
add_action('edit_user_profile_update', ['OI_Admin', 'save_user']);
require_once OI_DIR . 'includes/class-api.php';
require_once OI_DIR . 'includes/class-app.php';
add_action('rest_api_init', ['OI_API', 'register']);
add_shortcode('objectif_infirmiere', ['OI_App', 'render']);
add_shortcode('objectif_infirmiere_home', ['OI_App', 'landing']);
register_activation_hook(__FILE__, ['OI_App', 'install_pages']);

require_once OI_DIR . 'includes/class-storage.php';
require_once OI_DIR . 'includes/class-limit.php';
require_once OI_DIR . 'includes/class-auth.php';
OI_Auth::register();
add_shortcode('objectif_infirmiere_register', fn()=>OI_Auth::render('register'));
add_shortcode('objectif_infirmiere_login', fn()=>OI_Auth::render('login'));
require_once OI_DIR . 'includes/class-stripe.php';
add_action('rest_api_init', ['OI_Stripe', 'register']);
add_action('init', function () { if ((int)get_option('oi_schema_version') !== 2) { OI_Storage::migrate(); } });
require_once OI_DIR . 'includes/class-ai.php';
add_action('rest_api_init', ['OI_AI', 'register']);
add_action('save_post_oi_fiche', ['OI_AI', 'queue']);
add_action('oi_sync_fiche', ['OI_AI', 'sync']);
add_action('trashed_post', ['OI_AI', 'queue']);

require_once OI_DIR . 'includes/class-revision.php';
add_action('rest_api_init', ['OI_Revision', 'register']);
require_once OI_DIR . 'includes/class-settings.php';
add_action('admin_menu', ['OI_Settings', 'menu']);
add_action('admin_init', ['OI_Settings', 'register']);
add_action('admin_post_oi_sync', ['OI_Settings', 'sync']);
add_action('add_meta_boxes', function () { add_meta_box('oi-quiz', 'Quiz de la fiche', ['OI_Settings', 'quiz_box'], 'oi_fiche'); });
add_action('save_post_oi_fiche', ['OI_Settings', 'save_quiz']);
add_filter('template_include', function ($template) {
    if (OI_App::view()) {
        return OI_DIR . 'templates/app.php';
    }
    return $template;
});

add_filter('pre_delete_post', ['OI_AI', 'before_delete'], 10, 2);

add_filter('show_admin_bar', function ($show) { return in_array('oi_etudiant', wp_get_current_user()->roles, true) ? false : $show; });
add_action('admin_init', function () {
    if (!wp_doing_ajax() && ($GLOBALS['pagenow'] ?? '') !== 'admin-post.php' && in_array('oi_etudiant', wp_get_current_user()->roles, true)) { wp_safe_redirect(OI_App::url('member')); exit; }
});

add_action('after_password_reset', function ($user) { delete_user_meta($user->ID, 'oi_account_setup_pending'); });

add_action('updated_post_meta', function ($meta_id, $post_id, $key, $value) {
    if ($key === 'oi_ai_status' && str_ends_with((string)$value, '_failed')) { OI_Log::event('ai_sync_error', ['fiche_id'=>$post_id]); }
}, 10, 4);

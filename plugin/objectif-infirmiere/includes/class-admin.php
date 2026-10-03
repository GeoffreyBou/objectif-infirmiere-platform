<?php
defined('ABSPATH') || exit;
final class OI_Admin {
    public static function menu(): void {
        add_menu_page('Objectif Infirmière', 'Objectif Infirmière', 'manage_options', 'objectif-infirmiere', [self::class, 'home'], 'dashicons-welcome-learn-more');
        add_submenu_page('objectif-infirmiere', 'Utilisateurs', 'Utilisateurs', 'manage_options', 'users.php');
        foreach (OI_Model::TAX as $tax) {
            add_submenu_page('objectif-infirmiere', ucfirst($tax), ucfirst($tax), 'manage_categories', 'edit-tags.php?taxonomy=oi_' . $tax . '&post_type=oi_fiche');
        }
    }
    public static function home(): void {
        echo '<div class="wrap"><h1>Objectif Infirmière</h1><p>Créez vos fiches, organisez les taxonomies puis associez les fiches aux packs. Attribuez les packs depuis les profils utilisateurs.</p><p>Placez le shortcode <code>[objectif_infirmiere]</code> dans une page pour ouvrir l’espace étudiant.</p></div>';
    }
    public static function boxes(): void {
        add_meta_box('oi-pack', 'Contenus et produit Stripe TEST', [self::class, 'pack_box'], 'oi_pack');
    }
    public static function pack_box(WP_Post $post): void {
        wp_nonce_field('oi_pack', 'oi_nonce');
        echo '<p>Fiches accessibles (IDs séparés par des virgules)</p><input class="widefat" name="oi_fiches" value="' . esc_attr(implode(',', OI_Model::ids(get_post_meta($post->ID, 'oi_fiches', true)))) . '">';
        foreach (['price' => 'Stripe Price ID', 'product' => 'Stripe Product ID'] as $key => $label) {
            echo '<p>' . esc_html($label) . '</p><input class="widefat" name="oi_' . esc_attr($key) . '" value="' . esc_attr(get_post_meta($post->ID, 'oi_' . $key, true)) . '">';
        }
    }
    public static function save_pack(int $id): void {
        if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('manage_options') || !isset($_POST['oi_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oi_nonce'])), 'oi_pack')) { return; }
        $ids = OI_Model::ids(explode(',', sanitize_text_field(wp_unslash($_POST['oi_fiches'] ?? ''))));
        $ids = array_values(array_filter($ids, fn($id) => get_post_type($id) === 'oi_fiche'));
        update_post_meta($id, 'oi_fiches', $ids);
        foreach (['price', 'product'] as $key) {
            update_post_meta($id, 'oi_' . $key, sanitize_text_field(wp_unslash($_POST['oi_' . $key] ?? '')));
        }
    }
    public static function user_packs(WP_User $user): void {
        if (!current_user_can('manage_options')) { return; }
        wp_nonce_field('oi_user_packs', 'oi_user_nonce');
        echo '<h2>Objectif Infirmière — Packs manuels</h2><p>IDs des packs accordés :</p><input name="oi_packs" value="' . esc_attr(implode(',', OI_Model::packs($user->ID))) . '">';
    }
    public static function save_user(int $id): void {
        if (!current_user_can('manage_options') || !current_user_can('edit_user', $id) || !isset($_POST['oi_user_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oi_user_nonce'])), 'oi_user_packs')) { return; }
        $packs = OI_Model::ids(explode(',', sanitize_text_field(wp_unslash($_POST['oi_packs'] ?? ''))));
        $next = array_values(array_filter($packs, fn($pack) => get_post_type($pack) === 'oi_pack'));
        $before = OI_Model::ids(get_user_meta($id, 'oi_packs', true));
        update_user_meta($id, 'oi_packs', $next);
        foreach (array_diff($next, $before) as $pack) { OI_Log::event('pack_granted_manual', ['user_id'=>$id, 'pack_id'=>$pack]); }
        foreach (array_diff($before, $next) as $pack) { OI_Log::event('pack_revoked_manual', ['user_id'=>$id, 'pack_id'=>$pack]); }
    }
}

<?php
defined('ABSPATH') || exit;
final class OI_Model {
    public const TAX = ['formation', 'semestre', 'domaine', 'enseignement', 'theme'];
    public static function register(): void {
        foreach (['oi_fiche' => 'Fiches', 'oi_pack' => 'Packs'] as $type => $label) {
            register_post_type($type, [
                'label' => $label, 'public' => false, 'show_ui' => true,
                'show_in_menu' => 'objectif-infirmiere', 'show_in_rest' => false,
                'publicly_queryable' => false, 'exclude_from_search' => true,
                'rewrite' => false, 'query_var' => false,
                'supports' => ['title', 'editor', 'revisions'],
                'capability_type' => 'post', 'map_meta_cap' => true,
            ]);
        }
        foreach (self::TAX as $taxonomy) {
            register_taxonomy('oi_' . $taxonomy, 'oi_fiche', [
                'label' => ucfirst($taxonomy), 'public' => false,
                'show_ui' => true, 'show_admin_column' => true,
                'hierarchical' => true, 'show_in_rest' => false,
                'rewrite' => false,
            ]);
        }
    }
    public static function activate(): void {
        add_role('oi_etudiant', 'Étudiant Objectif Infirmière', ['read' => true]);
        self::register();
        OI_Storage::migrate();
    }
    public static function ids(mixed $value): array {
        if (!is_array($value)) { return []; }
        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }
    public static function packs(int $user): array {
        return self::ids(array_merge(self::ids(get_user_meta($user, 'oi_packs', true)), class_exists('OI_Storage') ? OI_Storage::paid_packs($user) : []));
    }
    public static function allowed(int $user): array {
        if (!$user) { return []; }
        if (user_can($user, 'manage_options')) {
            return get_posts(['post_type' => 'oi_fiche', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
        }
        $ids = [];
        foreach (self::packs($user) as $pack) {
            if (get_post_type($pack) === 'oi_pack' && get_post_status($pack) === 'publish') {
                $ids = array_merge($ids, self::ids(get_post_meta($pack, 'oi_fiches', true)));
            }
        }
        return self::ids($ids);
    }
    public static function can_read(int $user, int $fiche): bool {
        return $user > 0 && get_post_type($fiche) === 'oi_fiche'
            && get_post_status($fiche) === 'publish'
            && in_array($fiche, self::allowed($user), true);
    }
}

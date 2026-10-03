<?php
defined('ABSPATH') || exit;
final class OI_Storage {
    public static function migrate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_payments (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(191) NOT NULL,
            event_id varchar(191) NOT NULL,
            customer_id varchar(191) NOT NULL DEFAULT '',
            user_id bigint unsigned NOT NULL,
            pack_id bigint unsigned NOT NULL,
            amount bigint unsigned NOT NULL DEFAULT 0,
            currency varchar(10) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'paid',
            notified tinyint NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id)
        ) $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_ai_usage (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint unsigned NOT NULL,
            day date NOT NULL,
            requests int unsigned NOT NULL DEFAULT 0,
            input_tokens bigint unsigned NOT NULL DEFAULT 0,
            output_tokens bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY user_day (user_id,day)
        ) $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}oi_rates (
            bucket varchar(64) NOT NULL,
            hits int unsigned NOT NULL DEFAULT 1,
            expires_at bigint unsigned NOT NULL,
            PRIMARY KEY  (bucket),
            KEY expires_at (expires_at)
        ) $charset;");
        foreach (['oi_payments', 'oi_ai_usage', 'oi_rates'] as $suffix) {
            $name = $wpdb->prefix . $suffix;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name))) !== $name) { throw new RuntimeException('Migration Objectif Infirmière impossible : vérifier les droits SQL.'); }
        }
        update_option('oi_schema_version', 2);
    }
    public static function paid_packs(int $user): array {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT DISTINCT pack_id FROM {$wpdb->prefix}oi_payments WHERE user_id=%d AND status='paid'", $user)));
    }
    public static function reserve(int $user, int $quota): bool {
        global $wpdb;
        $day = gmdate('Y-m-d');
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}oi_ai_usage (user_id,day) VALUES (%d,%s)", $user, $day));
        return $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_ai_usage SET requests=requests+1 WHERE user_id=%d AND day=%s AND requests<%d", $user, $day, $quota)) === 1;
    }
    public static function tokens(int $user, int $input, int $output): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_ai_usage SET input_tokens=input_tokens+%d,output_tokens=output_tokens+%d WHERE user_id=%d AND day=%s", $input, $output, $user, gmdate('Y-m-d')));
    }
}

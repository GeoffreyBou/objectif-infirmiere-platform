<?php
defined('ABSPATH') || exit;
final class OI_Limit {
    public static function take(string $scope, int $max, int $seconds): bool {
        global $wpdb;
        $identity = get_current_user_id() ? 'user:' . get_current_user_id() : 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $key = hash('sha256', $scope . '|' . $identity . '|' . intdiv(time(), $seconds));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}oi_rates WHERE expires_at<%d LIMIT 500", time()));
        $written = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}oi_rates (bucket,hits,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE hits=hits+1", $key, time()+$seconds));
        if ($written === false) { return false; }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT hits FROM {$wpdb->prefix}oi_rates WHERE bucket=%s", $key)) <= $max;
    }
}

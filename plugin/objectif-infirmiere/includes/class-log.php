<?php
defined('ABSPATH') || exit;
final class OI_Log {
    public static function event(string $event, array $context=[]): void {
        $safe=[];
        foreach(['user_id','pack_id','fiche_id'] as $key) if(isset($context[$key])) $safe[$key]=absint($context[$key]);
        error_log('Objectif Infirmière '.wp_json_encode(['event'=>sanitize_key($event),'context'=>$safe]));
    }
}

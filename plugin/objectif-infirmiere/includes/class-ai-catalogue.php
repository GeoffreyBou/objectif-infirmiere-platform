<?php
defined('ABSPATH') || exit;
final class OI_AI_Catalogue {
    public static function register(): void {
        foreach(['start'=>'POST','index'=>'POST','status'=>'GET'] as $action=>$method)register_rest_route('oi/v1','/ai-catalogue/'.$action,['methods'=>$method,'permission_callback'=>fn()=>current_user_can('manage_options'),'callback'=>[self::class,$action]]);
    }
    public static function status(): array {
        $store=get_option('oi_vector_store','');$items=[];$ready=0;
        foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']) as $p){
            $valid=$store && get_post_meta($p->ID,'oi_ai_store',true)===$store && get_post_meta($p->ID,'oi_ai_file',true) && get_post_meta($p->ID,'oi_ai_hash',true)===OI_AI::hash($p->ID);
            if($valid)$ready++;
            $status=get_post_meta($p->ID,'oi_ai_status',true)?:'pending';
            $items[]=['id'=>$p->ID,'title'=>$p->post_title,'status'=>$valid?'completed':($status==='completed'?'outdated':$status)];
        }
        return ['total'=>count($items),'ready'=>$ready,'permanent'=>(bool)get_option('oi_ai_catalogue_permanent',false),'enabled'=>(bool)get_option('oi_ai_enabled',false),'items'=>$items];
    }
    public static function start(): array|WP_Error {
        $store=get_option('oi_vector_store','');
        if(!$store)return new WP_Error('oi_store','Configure d’abord la base documentaire.',['status'=>400]);
        $result=OI_AI::request('POST','vector_stores/'.rawurlencode($store),['name'=>'Objectif Infirmière — catalogue pédagogique','expires_after'=>null]);
        if(is_wp_error($result))return $result;
        if(!array_key_exists('expires_after',$result)||$result['expires_after']!==null)return new WP_Error('oi_store','La suppression de l’expiration n’a pas été confirmée.',['status'=>502]);
        update_option('oi_ai_catalogue_permanent',true,false);
        return ['permanent'=>true,'enabled'=>(bool)get_option('oi_ai_enabled',false)];
    }
    public static function index(WP_REST_Request $r): array|WP_Error {
        $id=absint($r['fiche_id']);
        if(!get_option('oi_ai_catalogue_permanent',false)||get_post_type($id)!=='oi_fiche'||get_post_status($id)!=='publish')return new WP_Error('oi_fiche','Fiche publiée ou catalogue durable indisponible.',['status'=>400]);
        OI_AI::sync($id);
        return ['id'=>$id,'status'=>get_post_meta($id,'oi_ai_status',true)];
    }
}

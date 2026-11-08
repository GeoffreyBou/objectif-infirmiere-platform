<?php
/**
 * Plugin Name: Objectif Infirmière — migration temporaire
 * Description: Import sélectif du catalogue ; à désactiver et supprimer après la bascule.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;
final class OI_Migration {
    const TYPES = ['oi_fiche','oi_fiche_art','oi_pack'];
    const META = ['oi_docx_images','oi_source_key','oi_drive_file_id','oi_drive_modified','oi_source_hash','oi_source_code','oi_word_title','oi_title_custom','oi_quiz','oi_qcm_source','oi_art_code','oi_art_data','oi_art_source','oi_art_hash'];
    public static function allowed(): bool { return current_user_can('manage_options') && current_user_can('install_plugins'); }
    public static function activate(): void {
        if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['objectif-infirmiere.fr','localhost','127.0.0.1'], true)) { wp_die('Migration réservée au domaine cible ou à une recette locale.'); }
        add_option('oi_migration_before', ['show_on_front'=>get_option('show_on_front'), 'page_on_front'=>get_option('page_on_front'), 'blog_public'=>get_option('blog_public')], '', false);
        add_option('oi_migration_pending', true, '', false);
    }
    public static function register(): void {
        foreach (['batch'=>'import_batch','status'=>'status'] as $route=>$callback) {
            register_rest_route('oi-migration/v1', '/'.$route, ['methods'=>$route==='status'?'GET':'POST', 'permission_callback'=>[self::class,'allowed'], 'callback'=>[self::class,$callback]]);
        }
    }
    private static function plain(mixed $value): bool {
        if (is_object($value) || is_resource($value)) { return false; }
        if (is_array($value)) { foreach ($value as $child) { if (!self::plain($child)) { return false; } } }
        return true;
    }
    public static function import_batch(WP_REST_Request $request): array|WP_Error {
        if (!class_exists('OI_Model') || !get_option('oi_migration_pending')) { return new WP_Error('migration_state','Migration non préparée.', ['status'=>409]); }
        $records=$request->get_param('records');
        if ($request->get_param('source')!=='https://app-dev.objectif-infirmiere.fr' || !is_array($records) || !$records || count($records)>10 || strlen((string)$request->get_body())>16000000) { return new WP_Error('migration_input','Lot invalide.', ['status'=>400]); }
        global $wpdb;
        $lock='oi_migration_'.md5(DB_NAME.$wpdb->prefix);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1) { return new WP_Error('migration_busy','Un import est en cours.', ['status'=>409]); }
        $out=[];$touched=[];$terms_touched=[];
        remove_action('save_post_oi_fiche',[OI_AI::class,'queue']);
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($records as $row) {
                if (!is_array($row) || !in_array($row['type']??'',self::TYPES,true) || !is_int($row['source_id']??null) || $row['source_id']<1 || !is_string($row['title']??null) || !is_string($row['content']??null) || !is_array($row['meta']??null) || !is_array($row['terms']??null)) { throw new RuntimeException('Enregistrement invalide.'); }
                $type=$row['type'];$source='app-dev:'.$row['source_id'];
                $hash=hash('sha256',wp_json_encode($row));
                $found=get_posts(['post_type'=>$type,'post_status'=>'any','numberposts'=>2,'meta_key'=>'oi_migration_source','meta_value'=>$source,'fields'=>'ids']);
                if(count($found)>1)throw new RuntimeException('Doublon de migration.');
                if($found){
                    $id=(int)$found[0];
                    if(get_post_meta($id,'oi_migration_hash',true)!==$hash)throw new RuntimeException('Source modifiée : vérifier avant de remplacer.');
                    $out[]=['source_id'=>$row['source_id'],'id'=>$id,'reused'=>true];continue;
                }
                $meta=[];
                foreach($row['meta'] as $key=>$raw){
                    if(!in_array($key,self::META,true))throw new RuntimeException('Métadonnée non autorisée.');
                    if(!is_string($raw)||strlen($raw)>12000000)throw new RuntimeException('Métadonnée invalide.');
                    $value=is_serialized($raw)?unserialize($raw,['allowed_classes'=>false]):$raw;
                    if(!self::plain($value))throw new RuntimeException('Objet sérialisé interdit.');
                    $meta[$key]=$value;
                }
                $code=$meta[$type==='oi_fiche_art'?'oi_art_code':'oi_source_code']??'';
                if($type!=='oi_pack' && (!is_string($code)||$code===''))throw new RuntimeException('Code source manquant.');
                if($code && get_posts(['post_type'=>$type,'post_status'=>'any','numberposts'=>1,'meta_key'=>$type==='oi_fiche_art'?'oi_art_code':'oi_source_code','meta_value'=>$code,'fields'=>'ids']))throw new RuntimeException('Ce code existe déjà : aucun écrasement automatique.');
                $id=wp_insert_post(wp_slash(['post_type'=>$type,'post_status'=>$type==='oi_fiche_art'?'private':'publish','post_title'=>$row['title'],'post_content'=>$row['content'],'post_author'=>get_current_user_id()]),true);
                if(is_wp_error($id))throw new RuntimeException('Création impossible.');
                $touched[]=$id;
                foreach($meta as $key=>$value){update_post_meta($id,$key,wp_slash($value));if($wpdb->last_error)throw new RuntimeException('Métadonnée non enregistrée.');}
                foreach($row['terms'] as $term){
                    $taxonomy=$term['taxonomy']??'';
                    if($type!=='oi_fiche'||!in_array($taxonomy,array_map(fn($v)=>'oi_'.$v,OI_Model::TAX),true))throw new RuntimeException('Taxonomie interdite.');
                    $existing=get_term_by('slug',$term['slug']??'',$taxonomy);
                    if(!$existing && is_string($term['name']??null))$existing=get_term_by('name',$term['name'],$taxonomy);
                    if(!$existing && $taxonomy==='oi_theme' && preg_match('/^([A-E][1-9]) - (.+)$/u',$term['name']??'',$match) && OI_Curriculum::uses_drive($match[1])){
                        $term_id=OI_Curriculum::drive_theme($match[1],$match[2]);
                        if($term_id){$terms_touched[]=$term_id;$existing=get_term($term_id,$taxonomy);}
                    }
                    if(!$existing || is_wp_error($existing))throw new RuntimeException('Terme absent du référentiel : '.sanitize_key($term['slug']??''));
                    $result=wp_set_object_terms($id,[(int)$existing->term_id],$taxonomy,true);
                    if(is_wp_error($result))throw new RuntimeException('Classement impossible.');
                }
                update_post_meta($id,'oi_migration_source',$source);update_post_meta($id,'oi_migration_hash',$hash);
                if($wpdb->last_error)throw new RuntimeException('Trace de migration non enregistrée.');
                $out[]=['source_id'=>$row['source_id'],'id'=>$id,'reused'=>false];
            }
            if($wpdb->query('COMMIT')===false)throw new RuntimeException('Validation transaction impossible.');
            return ['records'=>$out];
        }catch(Throwable $e){
            $wpdb->query('ROLLBACK');foreach($touched as $id)clean_post_cache($id);foreach($terms_touched as $term_id)clean_term_cache($term_id,'oi_theme');
            return new WP_Error('migration_import',$e->getMessage(),['status'=>409]);
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));add_action('save_post_oi_fiche',[OI_AI::class,'queue']);}
    }
    public static function status(): array {
        $counts=[];foreach(self::TYPES as $type)$counts[$type]=(array)wp_count_posts($type);
        return ['pending'=>(bool)get_option('oi_migration_pending'),'front'=>(int)get_option('page_on_front'),'before'=>get_option('oi_migration_before'),'counts'=>$counts,'stripe_mode'=>class_exists('OI_Stripe')?OI_Stripe::mode():null];
    }
}
register_activation_hook(__FILE__,[OI_Migration::class,'activate']);
add_filter('oi_activate_home_on_install',fn($activate)=>get_option('oi_migration_pending')?false:$activate);
add_action('plugins_loaded',function(){if(get_option('oi_migration_pending')&&class_exists('OI_Legal'))remove_action('init',[OI_Legal::class,'install']);},100);
add_action('rest_api_init',[OI_Migration::class,'register']);
add_action('template_redirect',function(){
    if(get_option('oi_migration_pending')&&class_exists('OI_App')&&OI_App::view()&&!current_user_can('manage_options')){
        nocache_headers();header('Retry-After: 3600');wp_die('Cet espace est en cours de préparation. Les articles du site restent accessibles.','Préparation de la plateforme',['response'=>503]);
    }
},0);

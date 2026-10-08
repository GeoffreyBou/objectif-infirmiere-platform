<?php
defined('ABSPATH') || exit;
final class OI_QCM_Imports {
    public static function types(): void {foreach(['oi_qcm_import','oi_qcm_history','oi_quiz_session'] as $type)register_post_type($type,['public'=>false,'show_ui'=>false,'rewrite'=>false]);}
    public static function routes(): void {
        foreach([''=>['GET','listing'],'/upload'=>['POST','upload'],'/(?P<id>\d+)/preview'=>['POST','preview'],'/publish'=>['POST','publish_batch'],'/(?P<id>\d+)/discard'=>['POST','discard'],'/history'=>['GET','history'],'/history/(?P<id>\d+)/restore'=>['POST','restore']] as $path=>[$method,$callback])register_rest_route('oi/v1','/qcm-imports'.$path,['methods'=>$method,'permission_callback'=>[OI_Imports::class,'auth'],'callback'=>[self::class,$callback]]);
    }
    public static function target(string $code): int {
        if(!$code)throw new RuntimeException('Le nom doit commencer par le code de la fiche, par exemple B1-UGR-002_Sante-sexuelle_qcm.xlsx.');
        $target=OI_Imports::source_target(['code'=>$code]);if(!$target||get_post_status($target)!=='publish')throw new RuntimeException('Publie d’abord la fiche '.$code.' pour lui rattacher ses QCM.');return $target;
    }
    public static function fingerprint(int $id): string {return hash('sha256',wp_json_encode([get_post_status($id),get_post_meta($id,'oi_quiz',true),get_post_meta($id,'oi_qcm_source',true)]));}
    private static function merge(int $target,array $questions): array {
        $bank=[];foreach((array)get_post_meta($target,'oi_quiz',true) as $i=>$q){if(!is_array($q))continue;$q['code']=$q['code']??'legacy-'.$target.'-'.$i;$bank[$q['code']]=$q;}
        foreach($questions as $q)$bank[$q['code']]=$q;
        if(count($bank)>200)throw new RuntimeException('La banque de cette fiche dépasserait 200 questions.');return array_values($bank);
    }
    public static function stage(string $file,string $name,array $source=[]): int {
        if(!preg_match('/_qcm(?:[_ -]v\d+(?:\.\d+)*)?\.xlsx$/i',$name))throw new RuntimeException('Nom attendu : code-fiche_titre_qcm.xlsx.');
        $code=OI_Imports::code($name);$target=self::target($code);$rows=OI_Xlsx::read($file);$questions=OI_Xlsx::questions($rows,$code);self::merge($target,$questions);
        $source+=['name'=>$name,'code'=>$code];$source['hash']=hash_file('sha256',$file);
        $old=get_post_meta($target,'oi_qcm_source',true);if(is_array($old)&&($old['hash']??'')===$source['hash'])return 0;
        foreach(get_posts(['post_type'=>'oi_qcm_import','post_status'=>'draft','numberposts'=>-1,'post_parent'=>$target]) as $p)if((get_post_meta($p->ID,'oi_source',true)['hash']??'')===$source['hash'])return $p->ID;
        $id=wp_insert_post(['post_type'=>'oi_qcm_import','post_status'=>'draft','post_parent'=>$target,'post_title'=>get_the_title($target),'post_author'=>get_current_user_id()],true);if(is_wp_error($id))throw new RuntimeException('Préparation impossible.');
        update_post_meta($id,'oi_source',$source);update_post_meta($id,'oi_qcm_rows',$rows);update_post_meta($id,'oi_questions',$questions);return $id;
    }
    public static function listing(): array {
        return ['drive'=>OI_Drive::status(),'template'=>OI_URL.'assets/modele-qcm.xlsx','stages'=>array_map(fn($p)=>['id'=>$p->ID,'title'=>get_the_title($p->post_parent),'target'=>$p->post_parent,'source'=>get_post_meta($p->ID,'oi_source',true),'rows'=>get_post_meta($p->ID,'oi_qcm_rows',true),'count'=>count((array)get_post_meta($p->ID,'oi_questions',true))],get_posts(['post_type'=>'oi_qcm_import','post_status'=>'draft','numberposts'=>100]))];
    }
    public static function upload(WP_REST_Request $r): array|WP_Error {
        $f=$r->get_file_params()['file']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))return new WP_Error('oi_xlsx','Choisis un Excel valide.',['status'=>400]);
        try{return ['id'=>self::stage($f['tmp_name'],sanitize_text_field(wp_basename($f['name'])))];}catch(Throwable $e){return new WP_Error('oi_xlsx',$e->getMessage(),['status'=>400]);}
    }
    public static function preview(WP_REST_Request $r): array|WP_Error {
        try{
            $p=get_post(absint($r['id']));if(!$p||$p->post_type!=='oi_qcm_import'||$p->post_status!=='draft')throw new RuntimeException('Préparation introuvable.');
            $source=get_post_meta($p->ID,'oi_source',true);if(self::target($source['code'])!==$p->post_parent)throw new RuntimeException('Le rattachement de la fiche a changé.');
            $rows=$r['rows']??get_post_meta($p->ID,'oi_qcm_rows',true);if(!is_array($rows)||!$rows||count($rows)>200)throw new RuntimeException('Sélection de questions invalide.');
            $questions=OI_Xlsx::questions($rows,$source['code']);$merged=self::merge($p->post_parent,$questions);
            update_post_meta($p->ID,'oi_qcm_rows',$rows);update_post_meta($p->ID,'oi_questions',$questions);
            $token=wp_generate_password(32,false);update_post_meta($p->ID,'oi_review',['token'=>hash('sha256',$token),'user'=>get_current_user_id(),'base'=>self::fingerprint($p->post_parent),'stage'=>hash('sha256',wp_json_encode($questions))]);
            return ['token'=>$token,'before'=>get_post_meta($p->post_parent,'oi_quiz',true)?:[],'after'=>$merged,'incoming'=>count($questions),'total'=>count(array_filter($merged,fn($q)=>($q['active']??true)))];
        }catch(Throwable $e){return new WP_Error('oi_qcm_preview',$e->getMessage(),['status'=>400]);}
    }
    private static function snapshot(int $target): int {
        $id=wp_insert_post(['post_type'=>'oi_qcm_history','post_status'=>'private','post_parent'=>$target,'post_title'=>get_the_title($target),'post_author'=>get_current_user_id()],true);if(is_wp_error($id))throw new RuntimeException('Sauvegarde QCM impossible.');
        update_post_meta($id,'oi_questions',get_post_meta($target,'oi_quiz',true)?:[]);update_post_meta($id,'oi_source',get_post_meta($target,'oi_qcm_source',true)?:[]);return $id;
    }
    public static function publish_one(int $id,string $token): array {
        global $wpdb;$lock='oi_import_'.md5(DB_NAME.$wpdb->prefix);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)',$lock))!==1)throw new RuntimeException('Publication en cours, réessaie.');$target=0;$history=0;
        try{
            $p=get_post($id);if(!$p||$p->post_type!=='oi_qcm_import'||$p->post_status!=='draft')throw new RuntimeException('Préparation déjà publiée ou retirée.');$target=$p->post_parent;
            $review=get_post_meta($id,'oi_review',true);$questions=get_post_meta($id,'oi_questions',true);$source=get_post_meta($id,'oi_source',true);
            if(!$review||$review['user']!==get_current_user_id()||!hash_equals($review['token'],hash('sha256',$token))||$review['base']!==self::fingerprint($target)||$review['stage']!==hash('sha256',wp_json_encode($questions)))throw new RuntimeException('Relance l’aperçu : la fiche a changé ou n’a pas été vérifiée.');
            if(self::target($source['code'])!==$target)throw new RuntimeException('Rattachement modifié.');$bank=self::merge($target,$questions);
            $wpdb->query('START TRANSACTION');$history=self::snapshot($target);update_post_meta($target,'oi_quiz',$bank);update_post_meta($target,'oi_qcm_source',$source);update_post_meta($history,'oi_expected',self::fingerprint($target));wp_update_post(['ID'=>$id,'post_status'=>'private']);delete_post_meta($id,'oi_review');$wpdb->query('COMMIT');return ['id'=>$id,'ok'=>true,'target'=>$target];
        }catch(Throwable $e){$wpdb->query('ROLLBACK');foreach(array_filter([$id,$target,$history]) as $pid)clean_post_cache($pid);throw $e;}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function publish_batch(WP_REST_Request $r): array|WP_Error {
        $items=$r['items'];if(!is_array($items)||!$items||count($items)>20)return new WP_Error('oi_batch','Sélectionne de 1 à 20 séries.',['status'=>400]);$result=[];foreach($items as $item){try{$result[]=self::publish_one(absint($item['id']??0),(string)($item['token']??''));}catch(Throwable $e){$result[]=['id'=>absint($item['id']??0),'ok'=>false,'message'=>$e->getMessage()];}}return $result;
    }
    public static function discard(WP_REST_Request $r): array|WP_Error {$id=absint($r['id']);if(get_post_type($id)!=='oi_qcm_import'||get_post_status($id)!=='draft')return new WP_Error('oi_missing','Préparation introuvable.',['status'=>404]);wp_delete_post($id,true);return ['ok'=>true];}
    public static function history(): array {return array_map(fn($p)=>['id'=>$p->ID,'title'=>$p->post_title,'date'=>$p->post_date_gmt,'restorable'=>get_post_meta($p->ID,'oi_expected',true)===self::fingerprint($p->post_parent)],get_posts(['post_type'=>'oi_qcm_history','post_status'=>'private','numberposts'=>50]));}
    public static function restore(WP_REST_Request $r): array|WP_Error {
        global $wpdb;$lock='oi_import_'.md5(DB_NAME.$wpdb->prefix);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)',$lock))!==1)return new WP_Error('oi_busy','Publication en cours.',['status'=>409]);$target=0;$backup=0;
        try{$p=get_post(absint($r['id']));if(!$p||$p->post_type!=='oi_qcm_history'||!get_post_meta($p->ID,'oi_expected',true)||get_post_meta($p->ID,'oi_expected',true)!==self::fingerprint($p->post_parent))throw new RuntimeException('La série a changé depuis cette version.');$target=$p->post_parent;$wpdb->query('START TRANSACTION');$backup=self::snapshot($target);update_post_meta($target,'oi_quiz',get_post_meta($p->ID,'oi_questions',true));update_post_meta($target,'oi_qcm_source',get_post_meta($p->ID,'oi_source',true));update_post_meta($backup,'oi_expected',self::fingerprint($target));delete_post_meta($p->ID,'oi_expected');$wpdb->query('COMMIT');return ['ok'=>true];}
        catch(Throwable $e){$wpdb->query('ROLLBACK');foreach(array_filter([$target,$backup]) as $pid)clean_post_cache($pid);return new WP_Error('oi_restore',$e->getMessage(),['status'=>409]);}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
}

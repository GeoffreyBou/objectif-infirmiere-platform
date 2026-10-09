<?php
defined('ABSPATH') || exit;
final class OI_Imports {
    public static function types(): void {
        foreach(['oi_import','oi_import_history'] as $type)register_post_type($type,['public'=>false,'show_ui'=>false,'rewrite'=>false,'supports'=>['title','editor']]);
    }
    public static function auth(): bool {return current_user_can('manage_options');}
    public static function routes(): void {
        foreach([
            '/imports'=>['GET','listing'], '/imports/upload'=>['POST','upload'], '/imports/publish'=>['POST','publish_batch'],
            '/imports/(?P<id>\d+)/preview'=>['POST','preview'], '/imports/(?P<id>\d+)/discard'=>['POST','discard'],
            '/imports/history'=>['GET','history'], '/imports/history/(?P<id>\d+)/restore'=>['POST','restore'],
        ] as $route=>[$method,$callback])register_rest_route('oi/v1',$route,['methods'=>$method,'permission_callback'=>[self::class,'auth'],'callback'=>[self::class,$callback]]);
    }
    public static function fingerprint(int $id): string {
        $p=get_post($id);if(!$p)return '';
        return hash('sha256',wp_json_encode([$p->post_title,$p->post_content,$p->post_status,wp_get_object_terms($id,'oi_enseignement',['fields'=>'ids']),wp_get_object_terms($id,'oi_theme',['fields'=>'ids']),get_post_meta($id,'oi_docx_images',true)]));
    }
    public static function code(string $name): string {
        return preg_match('/^([A-E][1-9]-[A-Z][A-Z0-9]*-\d{3,})(?=[_ .-]|$)/i',$name,$m)?strtoupper($m[1]):'';
    }
    public static function source_target(array $source): int {
        $ids=[];
        foreach(['code'=>'oi_source_code','key'=>'oi_source_key','file_id'=>'oi_drive_file_id'] as $field=>$meta){
            if(empty($source[$field]))continue;
            foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>['publish','draft'],'numberposts'=>-1,'fields'=>'ids','meta_key'=>$meta,'meta_value'=>$source[$field]]) as $id)$ids[]=(int)$id;
        }
        // Compatibility with imports published before stable codes were stored.
        if(!empty($source['code']))foreach(get_posts(['post_type'=>'oi_fiche','post_status'=>['publish','draft'],'numberposts'=>-1]) as $p){
            if(!get_post_meta($p->ID,'oi_source_code',true)&&self::code($p->post_title)===$source['code'])$ids[]=$p->ID;
        }
        $ids=array_values(array_unique($ids));
        if(count($ids)>1)throw new RuntimeException('Plusieurs fiches correspondent à cet identifiant. Vérifie les doublons avant de préparer.');
        return $ids[0]??0;
    }
    public static function stage(string $file,string $name,array $source=[]): int {
        $converted=OI_Docx::convert($file);$fallback=sanitize_text_field(pathinfo($name,PATHINFO_FILENAME));
        $title=$converted['title']?:$fallback;$source['hash']=hash_file('sha256',$file);
        $source['code']=self::code($name);$source['name']=$name;$source['word_title']=$title;
        $source['key']=$source['key']??($source['code']?'code:'.$source['code']:'upload:'.hash('sha256',mb_strtolower(preg_replace('/[_ -]v\d+(?:\.\d+)+$/i','',$fallback))));
        $key=$source['key'];$target=self::source_target($source);
        if(!$target&&empty($source['code'])&&empty($source['file_id'])){
            $matches=get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','title'=>$fallback,'numberposts'=>2]);
            if(count($matches)>1)throw new RuntimeException('Plusieurs fiches portent ce titre. Attribue un code unique au fichier.');
            if(count($matches)===1)$target=$matches[0]->ID;
        }
        if(!$converted['title'])$converted['warnings'][]='Aucun titre Word identifié : nom du fichier proposé. Utilise le style Titre ou un premier paragraphe Titre 1 dans Word.';
        if($target){
            $current=get_post_field('post_title',$target);$previous=get_post_meta($target,'oi_word_title',true);
            $custom=get_post_meta($target,'oi_title_custom',true)==='1'||($previous!==''&&$current!==$previous)||($previous===''&&!self::code($current));
            if($custom){$title=$current;$converted['warnings'][]='Le titre personnalisé de la plateforme est conservé.';}
        }
        if($target&&get_post_meta($target,'oi_source_hash',true)===$source['hash'])return 0;
        $stages=get_posts(['post_type'=>'oi_import','post_status'=>'draft','numberposts'=>-1,'meta_key'=>'oi_file_hash','meta_value'=>$source['hash']]);
        foreach($stages as $staged){
            $old=get_post_meta($staged->ID,'oi_source',true)?:[];
            if(($old['key']??'')!==$key&&(empty($source['file_id'])||($old['file_id']??'')!==$source['file_id']))continue;
            if(isset($old['word_title'])&&$staged->post_title===$old['word_title']&&$staged->post_title!==$title){wp_update_post(['ID'=>$staged->ID,'post_title'=>$title]);delete_post_meta($staged->ID,'oi_review');}
            if(!isset($old['word_title'])){
                if($staged->post_title===$fallback)wp_update_post(['ID'=>$staged->ID,'post_title'=>$title]);
                update_post_meta($staged->ID,'oi_source',$source);update_post_meta($staged->ID,'oi_stage_source',$key);update_post_meta($staged->ID,'oi_warnings',$converted['warnings']);delete_post_meta($staged->ID,'oi_review');
            }
            return $staged->ID;
        }
        $id=wp_insert_post(wp_slash(['post_type'=>'oi_import','post_status'=>'draft','post_title'=>$title,'post_content'=>$converted['html'],'post_author'=>get_current_user_id()]),true);
        if(is_wp_error($id))throw new RuntimeException('Impossible de préparer la fiche.');
        update_post_meta($id,'oi_docx_images',$converted['images']);update_post_meta($id,'oi_file_hash',$source['hash']);update_post_meta($id,'oi_stage_source',$key);update_post_meta($id,'oi_source',$source);update_post_meta($id,'oi_warnings',$converted['warnings']);update_post_meta($id,'oi_target',$target);
        $unit=$source['unit']??0;$theme=$source['theme']??0;
        if($target){$unit=wp_get_object_terms($target,'oi_enseignement',['fields'=>'ids'])[0]??$unit;$theme=wp_get_object_terms($target,'oi_theme',['fields'=>'ids'])[0]??$theme;}
        update_post_meta($id,'oi_unit',(int)$unit);update_post_meta($id,'oi_theme',(int)$theme);return (int)$id;
    }
    private static function row(WP_Post $p): array {
        $source=get_post_meta($p->ID,'oi_source',true)?:[];$unit=(int)get_post_meta($p->ID,'oi_unit',true);$theme=(int)get_post_meta($p->ID,'oi_theme',true);
        if(!$unit||!$theme){[$suggestedUnit,$suggestedTheme]=OI_Drive::classification($source['path_parts']??explode(' / ',$source['path']??''));if(!$unit)$unit=$suggestedUnit;if(!$theme&&$unit===$suggestedUnit)$theme=$suggestedTheme;}
        return ['id'=>$p->ID,'title'=>$p->post_title,'target'=>(int)get_post_meta($p->ID,'oi_target',true),'unit'=>$unit,'theme'=>$theme,'source'=>$source,'warnings'=>get_post_meta($p->ID,'oi_warnings',true)?:[]];
    }
    public static function listing(): array {
        $terms=fn($tax)=>array_map(fn($t)=>['id'=>$t->term_id,'name'=>$t->name,'unit'=>(int)get_term_meta($t->term_id,'oi_programme_unit',true)],get_terms(['taxonomy'=>$tax,'hide_empty'=>false]));
        return ['total_stages'=>(int)(wp_count_posts('oi_import')->draft ?? 0),'stages'=>array_map([self::class,'row'],get_posts(['post_type'=>'oi_import','post_status'=>'draft','numberposts'=>100,'orderby'=>'date','order'=>'DESC'])),'targets'=>array_map(fn($p)=>['id'=>$p->ID,'title'=>$p->post_title],get_posts(['post_type'=>'oi_fiche','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC'])),'units'=>$terms('oi_enseignement'),'themes'=>$terms('oi_theme'),'drive'=>OI_Drive::status()];
    }
    public static function upload(WP_REST_Request $r): array|WP_Error {
        $f=$r->get_file_params()['file']??null;
        if(!$f||$f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name'])||strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='docx')return new WP_Error('oi_word','Choisis un fichier Word .docx valide.',['status'=>400]);
        try{return ['id'=>self::stage($f['tmp_name'],sanitize_text_field(wp_basename($f['name'])))];}catch(Throwable $e){return new WP_Error('oi_word',$e->getMessage(),['status'=>400]);}
    }
    public static function preview(WP_REST_Request $r): array|WP_Error {
        $p=get_post(absint($r['id']));if(!$p||$p->post_type!=='oi_import'||$p->post_status!=='draft')return new WP_Error('oi_import','Préparation introuvable.',['status'=>404]);
        $target=absint($r['target']);if($target&&(get_post_type($target)!=='oi_fiche'||!in_array(get_post_status($target),['publish','draft'],true)))return new WP_Error('oi_target','Fiche cible invalide.',['status'=>400]);
        $unit=absint($r['unit']);$theme=absint($r['theme']);$title=sanitize_text_field($r['title']);
        if(!$title||!term_exists($unit,'oi_enseignement')||!term_exists($theme,'oi_theme')||(($parent=(int)get_term_meta($theme,'oi_programme_unit',true))&&$parent!==$unit))return new WP_Error('oi_classification','Choisis un titre, une UE et un thème cohérents.',['status'=>400]);
        wp_update_post(['ID'=>$p->ID,'post_title'=>$title]);foreach(['oi_target'=>$target,'oi_unit'=>$unit,'oi_theme'=>$theme] as $key=>$value)update_post_meta($p->ID,$key,$value);
        $token=wp_generate_password(32,false);update_post_meta($p->ID,'oi_review',['token'=>hash('sha256',$token),'user'=>get_current_user_id(),'base'=>$target?self::fingerprint($target):'','stage'=>self::fingerprint($p->ID)]);
        $before=$target?get_post_field('post_content',$target):'';
        return ['id'=>$p->ID,'token'=>$token,'before'=>$target?OI_Docx::content($before,$target):'','after'=>OI_Docx::content($p->post_content,$p->ID),'warnings'=>get_post_meta($p->ID,'oi_warnings',true)?:[],'changed'=>$before!==$p->post_content,'title'=>$title];
    }
    private static function snapshot(int $target): int {
        $p=get_post($target);$id=wp_insert_post(wp_slash(['post_type'=>'oi_import_history','post_status'=>'private','post_parent'=>$target,'post_title'=>$p->post_title,'post_content'=>$p->post_content,'post_author'=>get_current_user_id()]),true);
        if(is_wp_error($id))throw new RuntimeException('Sauvegarde de version impossible.');
        update_post_meta($id,'oi_snapshot',['status'=>$p->post_status,'unit'=>wp_get_object_terms($target,'oi_enseignement',['fields'=>'ids']),'theme'=>wp_get_object_terms($target,'oi_theme',['fields'=>'ids']),'source_key'=>get_post_meta($target,'oi_source_key',true),'source_hash'=>get_post_meta($target,'oi_source_hash',true),'drive_file_id'=>get_post_meta($target,'oi_drive_file_id',true),'drive_modified'=>get_post_meta($target,'oi_drive_modified',true),'source_code'=>get_post_meta($target,'oi_source_code',true),'word_title'=>get_post_meta($target,'oi_word_title',true),'title_custom'=>get_post_meta($target,'oi_title_custom',true)]);update_post_meta($id,'oi_docx_images',get_post_meta($target,'oi_docx_images',true)?:[]);return $id;
    }
    public static function publish_one(int $id,string $token): array {
        global $wpdb;$lock='oi_import_'.md5(DB_NAME.$wpdb->prefix);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1)throw new RuntimeException('Une publication est en cours. Réessaie.');
        $target=0;$pack=0;$history=0;
        try{
            $p=get_post($id);if(!$p||$p->post_type!=='oi_import'||$p->post_status!=='draft')throw new RuntimeException('Cette préparation a déjà été publiée ou retirée.');
            $review=get_post_meta($id,'oi_review',true);$target=(int)get_post_meta($id,'oi_target',true);
            if(!$review||$review['user']!==get_current_user_id()||!hash_equals($review['token'],hash('sha256',$token))||$review['stage']!==self::fingerprint($id)||($target&&$review['base']!==self::fingerprint($target)))throw new RuntimeException('Le contenu a changé ou n’a pas été prévisualisé. Relance la prévisualisation.');
            $source=get_post_meta($id,'oi_source',true)?:[];$matched=self::source_target($source);
            if($matched&&$matched!==$target)throw new RuntimeException('Cet identifiant appartient déjà à une autre fiche. Reprépare la mise à jour.');
            if($target&&!empty($source['code'])&&($existingCode=get_post_meta($target,'oi_source_code',true))&&$existingCode!==$source['code'])throw new RuntimeException('Le code de la fiche choisie ne correspond pas au Word.');
            $wpdb->query('START TRANSACTION');
            if($target)$history=self::snapshot($target);
            $result=wp_insert_post(wp_slash(['ID'=>$target,'post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>$p->post_title,'post_content'=>$p->post_content]),true);
            if(is_wp_error($result))throw new RuntimeException('Publication impossible.');$target=(int)$result;
            foreach(['oi_enseignement'=>'oi_unit','oi_theme'=>'oi_theme'] as $tax=>$meta){$result=wp_set_object_terms($target,[(int)get_post_meta($id,$meta,true)],$tax);if(is_wp_error($result))throw new RuntimeException('Classement impossible.');}
            update_post_meta($target,'oi_docx_images',get_post_meta($id,'oi_docx_images',true));$source=get_post_meta($id,'oi_source',true);
            if(!empty($source['key']))update_post_meta($target,'oi_source_key',$source['key']);if(!empty($source['file_id'])){update_post_meta($target,'oi_drive_file_id',$source['file_id']);update_post_meta($target,'oi_drive_modified',$source['modified']??'');}update_post_meta($target,'oi_source_hash',get_post_meta($id,'oi_file_hash',true));
            if(!empty($source['code']))update_post_meta($target,'oi_source_code',$source['code']);
            if(isset($source['word_title'])){update_post_meta($target,'oi_word_title',$source['word_title']);update_post_meta($target,'oi_title_custom',$p->post_title!==$source['word_title']?'1':'0');}
            $pack=OI_Offer::pack();if($pack){$ids=OI_Model::ids(get_post_meta($pack,'oi_fiches',true));$ids[]=$target;update_post_meta($pack,'oi_fiches',OI_Model::ids($ids));}
            if($history)update_post_meta($history,'oi_expected',self::fingerprint($target));
            wp_update_post(['ID'=>$id,'post_status'=>'private']);update_post_meta($id,'oi_published_target',$target);delete_post_meta($id,'oi_review');
            $wpdb->query('COMMIT');return ['id'=>$id,'target'=>$target,'ok'=>true];
        }catch(Throwable $e){$wpdb->query('ROLLBACK');foreach(array_filter([$id,$target,$history,$pack]) as $pid){clean_post_cache($pid);wp_cache_delete($pid,'post_meta');}throw $e;}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function publish_batch(WP_REST_Request $r): array|WP_Error {
        $items=$r['items'];if(!is_array($items)||!$items||count($items)>20)return new WP_Error('oi_batch','Sélectionne entre 1 et 20 fiches.',['status'=>400]);$results=[];
        foreach($items as $item){$id=absint($item['id']??0);try{
            $token=(string)($item['token']??'');
            if($token===''&&($item['direct']??false)===true){
                $check=new WP_REST_Request('POST');$check->set_param('id',$id);
                foreach(['title','target','unit','theme'] as $field)$check->set_param($field,$item[$field]??'');
                $validated=self::preview($check);if(is_wp_error($validated))throw new RuntimeException($validated->get_error_message());
                $token=$validated['token'];
            }
            $results[]=self::publish_one($id,$token);
        }catch(Throwable $e){$results[]=['id'=>$id,'ok'=>false,'message'=>$e->getMessage()];}}return $results;
    }
    public static function discard(WP_REST_Request $r): array|WP_Error {
        $id=absint($r['id']);if(get_post_type($id)!=='oi_import'||get_post_status($id)!=='draft')return new WP_Error('oi_import','Préparation introuvable.',['status'=>404]);wp_delete_post($id,true);return ['ok'=>true];
    }
    public static function history(): array {
        return array_map(fn($p)=>['id'=>$p->ID,'target'=>$p->post_parent,'title'=>$p->post_title,'date'=>$p->post_date_gmt,'restorable'=>get_post_meta($p->ID,'oi_expected',true)===self::fingerprint($p->post_parent)],get_posts(['post_type'=>'oi_import_history','post_status'=>'private','numberposts'=>50]));
    }
    public static function restore(WP_REST_Request $r): array|WP_Error {
        global $wpdb;$lock='oi_import_'.md5(DB_NAME.$wpdb->prefix);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1)return new WP_Error('oi_busy','Publication en cours.',['status'=>409]);
        $target=0;$backup=0;
        try{
            $p=get_post(absint($r['id']));if(!$p||$p->post_type!=='oi_import_history')throw new RuntimeException('Version introuvable.');$target=$p->post_parent;
            if(!get_post_meta($p->ID,'oi_expected',true)||get_post_meta($p->ID,'oi_expected',true)!==self::fingerprint($target))throw new RuntimeException('La fiche a changé depuis cette version. Restauration automatique refusée.');
            $wpdb->query('START TRANSACTION');$backup=self::snapshot($target);$meta=get_post_meta($p->ID,'oi_snapshot',true);
            $result=wp_update_post(wp_slash(['ID'=>$target,'post_title'=>$p->post_title,'post_content'=>$p->post_content,'post_status'=>$meta['status']]),true);if(is_wp_error($result))throw new RuntimeException('Restauration impossible.');
            foreach(['oi_enseignement'=>'unit','oi_theme'=>'theme'] as $tax=>$key)wp_set_object_terms($target,array_map('intval',$meta[$key]),$tax);
            foreach(['source_key','source_hash','drive_file_id','drive_modified','source_code','word_title','title_custom'] as $key)update_post_meta($target,'oi_'.$key,$meta[$key]??'');update_post_meta($target,'oi_docx_images',get_post_meta($p->ID,'oi_docx_images',true));update_post_meta($backup,'oi_expected',self::fingerprint($target));delete_post_meta($p->ID,'oi_expected');$wpdb->query('COMMIT');return ['ok'=>true];
        }catch(Throwable $e){$wpdb->query('ROLLBACK');foreach(array_filter([$target,$backup]) as $pid){clean_post_cache($pid);wp_cache_delete($pid,'post_meta');}return new WP_Error('oi_restore',$e->getMessage(),['status'=>409]);}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
}

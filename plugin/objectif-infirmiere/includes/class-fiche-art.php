<?php
defined('ABSPATH') || exit;
/** Private, versioned fiche illustrations, linked by permanent code rather than title. */
final class OI_Fiche_Art {
    private static ?array $index=null;
    public static function types(): void {register_post_type('oi_fiche_art',['public'=>false,'show_ui'=>false,'show_in_rest'=>false,'rewrite'=>false,'supports'=>['title']]);}
    public static function routes(): void {
        foreach(['scan','apply','history'] as $action)register_rest_route('oi/v1','/imports/images/'.$action,['methods'=>$action==='history'?'GET':'POST','permission_callback'=>[OI_Imports::class,'auth'],'callback'=>[self::class,$action]]);
        register_rest_route('oi/v1','/imports/images/(?P<id>\d+)/restore',['methods'=>'POST','permission_callback'=>[OI_Imports::class,'auth'],'callback'=>[self::class,'restore']]);
    }
    private static function scan_key(): string {return 'oi_art_scan_'.get_current_user_id();}
    public static function scan(WP_REST_Request $r): array|WP_Error {
        try{
            $job=get_transient(self::scan_key());
            if($r['restart']||!$job)$job=['queue'=>[['id'=>OI_Drive::image_root(),'path'=>[],'token'=>'']],'visited'=>[],'count'=>0,'files'=>[],'done'=>false];
            for($step=0;$step<3&&$job['queue'];$step++){
                $folder=array_shift($job['queue']);$data=OI_Drive::image_list($folder['id'],$folder['token']);if(!isset($data['files']))throw new RuntimeException('Liste Drive incomplète.');
                foreach($data['files'] as $file){
                    if(++$job['count']>20000)throw new RuntimeException('Plus de 20 000 éléments : limiter le dossier d’analyse.');
                    if($file['mimeType']==='application/vnd.google-apps.folder'){
                        $path=array_merge($folder['path'],[$file['name']]);if(OI_Drive::archived($path))continue;
                        if(count($path)>16)throw new RuntimeException('Arborescence trop profonde.');
                        if(empty($job['visited'][$file['id']])){$job['visited'][$file['id']]=true;$job['queue'][]=['id'=>$file['id'],'path'=>$path,'token'=>''];}
                    }elseif(preg_match('/\.(png|jpe?g|webp)$/i',$file['name'])&&in_array($file['mimeType'],['image/png','image/jpeg','image/webp'],true)){$file['path']=$folder['path'];$job['files'][]=$file;}
                }
                if(!empty($data['nextPageToken'])){$folder['token']=$data['nextPageToken'];array_unshift($job['queue'],$folder);}
            }
            $job['done']=!$job['queue'];set_transient(self::scan_key(),$job,6*HOUR_IN_SECONDS);
            return ['done'=>$job['done'],'visited'=>$job['count'],'detected'=>count($job['files']),'files'=>$job['done']?self::candidates($job['files']):[]];
        }catch(Throwable $e){return new WP_Error('oi_images',$e->getMessage(),['status'=>400]);}
    }
    private static function assets(): array {
        global $wpdb;
        $rows=$wpdb->get_results("SELECT p.ID,p.post_status,c.meta_value AS code,h.meta_value AS hash,s.meta_value AS source FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} c ON c.post_id=p.ID AND c.meta_key='oi_art_code' LEFT JOIN {$wpdb->postmeta} h ON h.post_id=p.ID AND h.meta_key='oi_art_hash' LEFT JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='oi_art_source' WHERE p.post_type='oi_fiche_art' AND p.post_status IN ('private','draft')",ARRAY_A);
        $out=[];foreach($rows as $row)$out[$row['code']][]=$row;return $out;
    }
    /** Identical bytes duplicated in Drive are one illustration, not a choice of artwork. */
    private static function unique_candidates(array $files): array {
        $groups=[];foreach(OI_Drive::candidates($files) as $file)$groups[$file['key']][]=$file;
        $out=[];foreach($groups as $group){
            $hashes=array_unique(array_column($group,'md5Checksum'));
            if(count($group)>1&&count($hashes)===1&&reset($hashes)&&count(array_column($group,'md5Checksum'))===count($group)){
                usort($group,fn($a,$b)=>strcmp($a['name'],$b['name'])?:strcmp($a['id'],$b['id']));
                $first=$group[0];$first['conflict']=false;$first['identical_copies']=count($group);$out[]=$first;
            }else foreach($group as $file)$out[]=$file;
        }return $out;
    }
    public static function candidates(array $files): array {
        global $wpdb;$assets=self::assets();$targets=[];
        $posts=$wpdb->get_results("SELECT p.ID,p.post_title,c.meta_value AS code FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} c ON c.post_id=p.ID AND c.meta_key='oi_source_code' WHERE p.post_type='oi_fiche' AND p.post_status='publish'");
        foreach($posts as $post){$code=$post->code?:OI_Imports::code($post->post_title);if($code)$targets[$code][]=['id'=>(int)$post->ID,'title'=>$post->post_title];}
        $out=[];
        foreach(self::unique_candidates($files) as $file){
            $code=$file['code'];$found=$code?($assets[$code]??[]):[];$source=count($found)===1?maybe_unserialize($found[0]['source']):[];
            $file['targets']=$targets[$code]??[];$file['asset_id']=count($found)===1?(int)$found[0]['ID']:0;
            $file['reason']='';
            if(!$code)$file['reason']='Code permanent absent du nom de l’image.';
            elseif($file['conflict'])$file['reason']='Plusieurs images pour ce code et cette version : choisir un seul fichier dans Drive.';
            elseif(count($found)>1||count($file['targets'])>1)$file['reason']='Code associé à plusieurs fiches ou illustrations : rapprochement à vérifier.';
            elseif((int)($file['size']??0)>10*1024*1024)$file['reason']='Image de plus de 10 Mo.';
            $file['state']=$file['reason']?'blocked':($source&&$found[0]['post_status']==='private'&&self::same_source($source,$file)?'unchanged':($source?'modified':'new'));
            $out[]=$file;
        }
        return $out;
    }
    private static function same_source(array $source,array $file): bool {
        return !empty($file['md5Checksum'])?($source['md5']??'')===$file['md5Checksum']:(($source['file_id']??'')===$file['id']&&($source['modified']??'')===$file['modifiedTime']);
    }
    public static function code(int $fiche): string {return (string)(get_post_meta($fiche,'oi_source_code',true)?:OI_Imports::code((string)get_post_field('post_title',$fiche)));}
    public static function url(int $fiche,bool $thumbnail=false): string {
        if(self::$index===null)self::$index=self::assets();$code=self::code($fiche);$rows=self::$index[$code]??[];
        if(count($rows)!==1||$rows[0]['post_status']!=='private'||!$rows[0]['hash'])return '';
        return add_query_arg(['action'=>'oi_fiche_cover','fiche'=>$fiche,'v'=>$rows[0]['hash']]+($thumbnail?['size'=>'thumb']:[]),admin_url('admin-post.php'));
    }
    /** Decode locally, preserve transparency, and never publish a Drive URL or original file. */
    public static function optimize(string $bytes,int $max=1024): array {
        if(strlen($bytes)>10*1024*1024)throw new RuntimeException('Image de plus de 10 Mo.');
        $info=@getimagesizefromstring($bytes);
        if(!$info||!in_array($info['mime'],['image/png','image/jpeg','image/webp'],true)||$info[0]>8192||$info[1]>8192||$info[0]*$info[1]>16000000)throw new RuntimeException('PNG, JPEG ou WebP valide requis (16 mégapixels maximum).');
        require_once ABSPATH.'wp-admin/includes/file.php';$tmp=wp_tempnam('oi-art');$output='';
        try{
            if(!$tmp||file_put_contents($tmp,$bytes)!==strlen($bytes))throw new RuntimeException('Fichier temporaire indisponible.');
            $editor=wp_get_image_editor($tmp);
            // Some hosts advertise Imagick without a decoder for PNG; GD is a safe local fallback.
            if(is_wp_error($editor)){require_once ABSPATH.WPINC.'/class-wp-image-editor-gd.php';if(WP_Image_Editor_GD::test()){$editor=new WP_Image_Editor_GD($tmp);$loaded=$editor->load();if(is_wp_error($loaded))$editor=$loaded;}}
            if(is_wp_error($editor))throw new RuntimeException('Conversion de l’image indisponible sur le serveur.');
            if($info[0]>$max||$info[1]>$max){$resized=$editor->resize($max,$max,false);if(is_wp_error($resized))throw new RuntimeException($resized->get_error_message());}
            $editor->set_quality(82);$output=$tmp.'.webp';$saved=$editor->save($output,'image/webp');
            if(is_wp_error($saved)||($saved['mime-type']??'')!=='image/webp')throw new RuntimeException('La conversion WebP a échoué.');
            $optimized=file_get_contents($saved['path']);
            if(!$optimized||strlen($optimized)>768*1024)throw new RuntimeException('Image encore trop volumineuse après optimisation (768 Ko maximum).');
            return ['mime'=>'image/webp','data'=>base64_encode($optimized),'hash'=>hash('sha256',$optimized),'bytes'=>strlen($optimized),'width'=>$saved['width'],'height'=>$saved['height']];
        }finally{if($tmp&&is_file($tmp))unlink($tmp);if($output&&is_file($output))unlink($output);}
    }
    /** One atomic asset per permanent code; text, classifications and student progress are untouched. */
    public static function store(array $file,array $image): array {
        global $wpdb;$code=$file['code'];if(!$code||OI_Imports::code($code)!==$code)throw new RuntimeException('Code permanent invalide.');
        $lock='oi_art_'.md5(DB_NAME.$wpdb->prefix.$code);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1)throw new RuntimeException('Cette image est déjà en cours de traitement.');
        $id=0;
        try{
            $rows=self::assets()[$code]??[];if(count($rows)>1)throw new RuntimeException('Plusieurs illustrations portent ce code.');
            $id=$rows?(int)$rows[0]['ID']:0;
            $source=['file_id'=>$file['id'],'name'=>$file['name'],'modified'=>$file['modifiedTime']??'','md5'=>$file['md5Checksum']??'','version'=>$file['version']??'0','path'=>$file['path_label']??'','code'=>$code];
            if($id&&$rows[0]['post_status']==='private'&&get_post_meta($id,'oi_art_hash',true)===$image['hash']){update_post_meta($id,'oi_art_source',$source);return ['asset_id'=>$id,'unchanged'=>true];}
            $previous=$id?['status'=>$rows[0]['post_status'],'data'=>get_post_meta($id,'oi_art_data',true),'source'=>get_post_meta($id,'oi_art_source',true)]:['status'=>'draft','data'=>[],'source'=>[]];
            $wpdb->query('START TRANSACTION');
            if(!$id){$inserted=wp_insert_post(['post_type'=>'oi_fiche_art','post_status'=>'private','post_title'=>$code],true);if(is_wp_error($inserted))throw new RuntimeException('Stockage de l’illustration impossible.');$id=(int)$inserted;}
            foreach(['oi_art_code'=>$code,'oi_art_previous'=>$previous,'oi_art_data'=>$image,'oi_art_source'=>$source,'oi_art_hash'=>$image['hash']] as $key=>$value){update_post_meta($id,$key,$value);if($wpdb->last_error)throw new RuntimeException('Enregistrement de l’image impossible.');}
            $updated=wp_update_post(['ID'=>$id,'post_status'=>'private'],true);if(is_wp_error($updated))throw new RuntimeException('Activation de l’image impossible.');
            $wpdb->query('COMMIT');clean_post_cache($id);self::$index=null;
            return ['asset_id'=>$id,'unchanged'=>false];
        }catch(Throwable $e){$wpdb->query('ROLLBACK');if($id)clean_post_cache((int)$id);throw $e;}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function apply(WP_REST_Request $r): array|WP_Error {
        $job=get_transient(self::scan_key());$ids=$r['ids'];
        if(!$job||empty($job['done'])||!is_array($ids)||!$ids||count($ids)>3)return new WP_Error('oi_scan','Termine l’analyse puis importe jusqu’à 3 images par lot.',['status'=>400]);
        foreach($ids as $id)if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,200}$/',$id))return new WP_Error('oi_id','Identifiant d’image invalide.',['status'=>400]);
        set_transient(self::scan_key(),$job,6*HOUR_IN_SECONDS);$files=array_column(self::candidates($job['files']),null,'id');$out=[];
        foreach(array_unique($ids) as $id){
            $file=$files[$id]??null;
            try{
                if(!$file)throw new RuntimeException('Image absente de cette analyse.');
                if($file['reason'])throw new RuntimeException($file['reason']);
                if($file['state']==='unchanged'){$out[]=['file'=>$file['name'],'id'=>$id,'ok'=>true,'unchanged'=>true,'asset_id'=>$file['asset_id']];continue;}
                $bytes=OI_Drive::image_bytes($id);
                if(!empty($file['md5Checksum'])&&!hash_equals($file['md5Checksum'],md5($bytes)))throw new RuntimeException('L’image a changé depuis l’analyse : relance l’analyse.');
                $result=self::store($file,self::optimize($bytes));
                $out[]=['file'=>$file['name'],'id'=>$id,'ok'=>true,'targets'=>$file['targets']]+$result;
            }catch(Throwable $e){$out[]=['file'=>$file['name']??$id,'id'=>$id,'ok'=>false,'message'=>$e->getMessage()];}
        }
        return $out;
    }
    public static function history(): array {
        global $wpdb;$items=[];foreach(self::assets() as $code=>$rows)foreach($rows as $row){$id=(int)$row['ID'];$source=maybe_unserialize($row['source']);$items[]=['id'=>$id,'code'=>$code,'name'=>$source['name']??'','active'=>$row['post_status']==='private','hash'=>$row['hash'],'preview'=>add_query_arg(['action'=>'oi_fiche_cover','asset'=>$id,'v'=>$row['hash']],admin_url('admin-post.php')),'restorable'=>(bool)$wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='oi_art_previous' LIMIT 1",$id))];}return $items;
    }
    public static function restore(WP_REST_Request $r): array|WP_Error {
        global $wpdb;$id=absint($r['id']);
        if(get_post_type($id)!=='oi_fiche_art')return new WP_Error('oi_art','Illustration introuvable.',['status'=>404]);
        $lock='oi_art_'.md5(DB_NAME.$wpdb->prefix.get_post_meta($id,'oi_art_code',true));
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1)return new WP_Error('oi_busy','Image en cours de modification.',['status'=>409]);
        try{
            if(!is_string($r['hash'])||!hash_equals((string)get_post_meta($id,'oi_art_hash',true),$r['hash']))return new WP_Error('oi_changed','L’image a changé : recharge la liste avant de restaurer.',['status'=>409]);
            $previous=get_post_meta($id,'oi_art_previous',true);if(!is_array($previous))return new WP_Error('oi_history','Aucune version précédente.',['status'=>400]);
            $current=['status'=>get_post_status($id),'data'=>get_post_meta($id,'oi_art_data',true),'source'=>get_post_meta($id,'oi_art_source',true)];
            $wpdb->query('START TRANSACTION');
            foreach(['oi_art_previous'=>$current,'oi_art_data'=>$previous['data'],'oi_art_source'=>$previous['source'],'oi_art_hash'=>$previous['data']['hash']??''] as $key=>$value){update_post_meta($id,$key,$value);if($wpdb->last_error)throw new RuntimeException('Restauration impossible.');}
            $updated=wp_update_post(['ID'=>$id,'post_status'=>$previous['status']],true);if(is_wp_error($updated))throw new RuntimeException('Restauration impossible.');
            $wpdb->query('COMMIT');clean_post_cache($id);self::$index=null;return ['ok'=>true];
        }catch(Throwable $e){$wpdb->query('ROLLBACK');clean_post_cache($id);return new WP_Error('oi_art',$e->getMessage(),['status'=>500]);}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    public static function can_view(int $user,int $fiche): bool {
        return $user>0&&!get_user_meta($user,'oi_email_pending',true)&&get_post_type($fiche)==='oi_fiche'&&get_post_status($fiche)==='publish'&&(user_can($user,'manage_options')||in_array($fiche,OI_Offer::catalogue(),true)||OI_Model::can_read($user,$fiche));
    }
    public static function image(): void {
        $id=absint($_GET['asset']??0);$fiche=absint($_GET['fiche']??0);
        if($id){if(!current_user_can('manage_options')){status_header(403);exit;}}
        else{
            if(!self::can_view(get_current_user_id(),$fiche)){status_header(403);exit;}
            $rows=self::assets()[self::code($fiche)]??[];$id=count($rows)===1?(int)$rows[0]['ID']:0;
        }
        if(!$id||get_post_type($id)!=='oi_fiche_art'||get_post_status($id)!=='private'){status_header(404);exit;}
        $image=get_post_meta($id,'oi_art_data',true);
        if(!is_array($image)||($image['mime']??'')!=='image/webp'){status_header(404);exit;}
        // Small cards do not download the full illustration. Cache once per source hash.
        if(($_GET['size']??'')==='thumb'){
            $thumb=get_post_meta($id,'oi_art_thumbnail',true);
            if(!is_array($thumb)||($thumb['source_hash']??'')!==$image['hash']){
                try{$thumb=self::optimize(base64_decode($image['data'],true),256);$thumb['source_hash']=$image['hash'];update_post_meta($id,'oi_art_thumbnail',$thumb);}catch(Throwable $e){$thumb=$image;}
            }
            $image=$thumb;
        }
        $etag='"'.$image['hash'].'"';header('Cache-Control: private, no-cache');header('Vary: Cookie');header('ETag: '.$etag);header('X-Content-Type-Options: nosniff');header('Content-Type: image/webp');
        if(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){status_header(304);exit;}
        echo base64_decode($image['data'],true);exit;
    }
}

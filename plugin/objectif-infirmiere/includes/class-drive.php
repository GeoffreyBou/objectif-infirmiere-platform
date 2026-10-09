<?php
defined('ABSPATH') || exit;
final class OI_Drive {
    private const ROOT='131LsjTxr9RswdWDWY_l9-wfx_hmnAs30';
    public static function routes(): void {
        foreach(['/imports/drive/service-account'=>'service_account','/imports/drive/config'=>'config','/imports/drive/disconnect'=>'disconnect','/imports/drive/scan'=>'scan','/imports/drive/prepare'=>'prepare','/imports/drive/prepare-qcm'=>'prepare_qcm'] as $path=>$callback)register_rest_route('oi/v1',$path,['methods'=>'POST','permission_callback'=>[OI_Imports::class,'auth'],'callback'=>[self::class,$callback]]);
    }
    private static function seal(array $value): string {
        $iv=random_bytes(12);$tag='';$data=openssl_encrypt(wp_json_encode($value),'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,$iv,$tag);if($data===false)throw new RuntimeException('Chiffrement impossible.');return base64_encode($iv.$tag.$data);
    }
    private static function unseal(string $encrypted): array {
        $raw=base64_decode($encrypted,true);if(!$raw||strlen($raw)<29)return [];$data=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));return $data?json_decode($data,true)?:[]:[];
    }
    private static function secret(): array {return self::unseal((string)get_option('oi_drive_credentials'));}
    public static function status(): array {
        $c=self::secret();$service=($c['type']??'')==='service_account';return ['mode'=>$service?'service_account':'oauth','email'=>$service?$c['client_email']:'','configured'=>$service||(!empty($c['client_id'])&&!empty($c['client_secret'])),'connected'=>$service||!empty($c['refresh_token']),'root'=>self::ROOT,'redirect'=>admin_url('admin-post.php?action=oi_drive_callback'),'connect_url'=>wp_nonce_url(admin_url('admin-post.php?action=oi_drive_connect'),'oi_drive_connect')];
    }
    public static function service_account(WP_REST_Request $r): array|WP_Error {
        $file=$r->get_file_params()['file']??null;
        if(!$file||$file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>32768)return new WP_Error('oi_google_key','Choisis la clé JSON du compte de service (32 Ko maximum).',['status'=>400]);
        try{
            $c=self::service_credentials((string)file_get_contents($file['tmp_name']));
            $token=self::service_token($c);
            $check=wp_remote_get('https://www.googleapis.com/drive/v3/files/'.self::ROOT.'?fields=id,mimeType&supportsAllDrives=true',['headers'=>['Authorization'=>'Bearer '.$token['access_token']],'timeout'=>25,'redirection'=>0,'limit_response_size'=>32768]);
            if(is_wp_error($check)||wp_remote_retrieve_response_code($check)!==200)throw new RuntimeException('Partage le dossier racine avec '.$c['client_email'].' en rôle Lecteur, vérifie que Google Drive API est activée, puis réessaie.');
            $folder=json_decode(wp_remote_retrieve_body($check),true);if(($folder['id']??'')!==self::ROOT||($folder['mimeType']??'')!=='application/vnd.google-apps.folder')throw new RuntimeException('Le dossier configuré n’est pas accessible.');
            update_option('oi_drive_credentials',self::seal($c),false);delete_transient('oi_drive_access');delete_transient('oi_drive_scan_'.get_current_user_id());return self::status();
        }catch(Throwable $e){return new WP_Error('oi_google_key',$e->getMessage(),['status'=>400]);}
        finally{if(is_file($file['tmp_name']))unlink($file['tmp_name']);}
    }
    public static function service_credentials(string $json): array {
        $c=json_decode($json,true);
        if(!is_array($c)||($c['type']??'')!=='service_account'||!preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.iam\.gserviceaccount\.com$/',$c['client_email']??'')||!is_string($c['private_key']??null))throw new RuntimeException('Ce JSON n’est pas une clé de compte de service Google.');
        $key=openssl_pkey_get_private($c['private_key']);$details=$key?openssl_pkey_get_details($key):false;
        if(!$details||$details['type']!==OPENSSL_KEYTYPE_RSA||$details['bits']<2048)throw new RuntimeException('Clé privée RSA invalide.');
        return ['type'=>'service_account','client_email'=>$c['client_email'],'private_key'=>$c['private_key']];
    }
    private static function service_token(array $c): array {
        $b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');$now=time();
        $jwt=$b64(wp_json_encode(['alg'=>'RS256','typ'=>'JWT'])).'.'.$b64(wp_json_encode(['iss'=>$c['client_email'],'scope'=>'https://www.googleapis.com/auth/drive.readonly','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]));
        $signature='';if(!openssl_sign($jwt,$signature,$c['private_key'],OPENSSL_ALGO_SHA256))throw new RuntimeException('Impossible de signer la connexion Google.');
        return self::token(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt.'.'.$b64($signature)]);
    }
    public static function config(WP_REST_Request $r): array|WP_Error {
        $id=trim((string)$r['client_id']);$secret=trim((string)$r['client_secret']);if(!preg_match('/^[a-zA-Z0-9._-]+\.apps\.googleusercontent\.com$/',$id)||strlen($secret)<8||strlen($secret)>500)return new WP_Error('oi_google_config','Identifiant client OAuth Google et secret client requis.',['status'=>400]);
        update_option('oi_drive_credentials',self::seal(['client_id'=>$id,'client_secret'=>$secret]),false);delete_transient('oi_drive_access');return self::status();
    }
    public static function disconnect(): array {delete_option('oi_drive_credentials');delete_transient('oi_drive_access');delete_transient('oi_drive_scan_'.get_current_user_id());return self::status();}
    public static function connect(): void {
        if(!current_user_can('manage_options'))wp_die('Accès refusé.',403);check_admin_referer('oi_drive_connect');$c=self::secret();if(empty($c['client_id']))wp_die('Configure la connexion Google dans Mise à jour Fiches.');
        $state=bin2hex(random_bytes(24));set_transient('oi_drive_state_'.hash('sha256',$state),get_current_user_id(),600);
        wp_redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$c['client_id'],'redirect_uri'=>self::status()['redirect'],'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/drive.readonly','access_type'=>'offline','prompt'=>'consent','state'=>$state]));exit;
    }
    public static function callback(): void {
        if(!current_user_can('manage_options'))wp_die('Accès refusé.',403);
        $state=sanitize_text_field($_GET['state']??'');$key='oi_drive_state_'.hash('sha256',$state);$user=get_transient($key);delete_transient($key);if(!$user||(int)$user!==get_current_user_id())wp_die('Connexion expirée : recommence depuis ton espace.');
        try{$c=self::secret();$token=self::token(['code'=>sanitize_text_field($_GET['code']??''),'grant_type'=>'authorization_code','redirect_uri'=>self::status()['redirect']]+['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret']]);if(empty($token['refresh_token']))throw new RuntimeException('Autorise l’accès en lecture puis reconnecte Google.');$c['refresh_token']=$token['refresh_token'];update_option('oi_drive_credentials',self::seal($c),false);delete_transient('oi_drive_access');wp_safe_redirect(OI_App::url('member').'?view=imports');exit;}catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
    }
    private static function token(array $body): array {
        $r=wp_remote_post('https://oauth2.googleapis.com/token',['body'=>$body,'timeout'=>25,'redirection'=>0]);if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)throw new RuntimeException('Google n’a pas autorisé la connexion. Vérifie la clé du compte de service ou reconnecte ton compte OAuth.');$data=json_decode(wp_remote_retrieve_body($r),true);if(empty($data['access_token']))throw new RuntimeException('Réponse Google incomplète.');return $data;
    }
    private static function access(): string {
        $cached=self::unseal((string)get_transient('oi_drive_access'));if(!empty($cached['token']))return $cached['token'];
        $c=self::secret();if(($c['type']??'')==='service_account')$data=self::service_token($c);else {if(empty($c['refresh_token']))throw new RuntimeException('Connecte ton Drive avant de lancer l’analyse.');
        $data=self::token(['grant_type'=>'refresh_token','refresh_token'=>$c['refresh_token'],'client_id'=>$c['client_id'],'client_secret'=>$c['client_secret']]);}
        set_transient('oi_drive_access',self::seal(['token'=>$data['access_token']]),max(60,min(3000,(int)($data['expires_in']??3600)-60)));return $data['access_token'];
    }
    private static function request(string $path,array $query=[]): array {
        $r=wp_remote_get('https://www.googleapis.com/drive/v3/'.$path.'?'.http_build_query($query),['headers'=>['Authorization'=>'Bearer '.self::access()],'timeout'=>30,'redirection'=>0,'limit_response_size'=>13*1024*1024]);
        if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)throw new RuntimeException('Lecture Drive impossible. Vérifie l’accès au dossier et la connexion Google.');return $r;
    }
    public static function scan(WP_REST_Request $r): array|WP_Error {
        try{
            $qcm=$r['kind']==='qcm';$key='oi_drive_scan_'.get_current_user_id().($qcm?'_qcm':'');$job=get_transient($key);
            if($r['restart']||!$job)$job=['queue'=>[['id'=>self::ROOT,'path'=>[],'token'=>'']],'files'=>[],'visited'=>[],'count'=>0,'done'=>false];
            for($step=0;$step<3&&$job['queue'];$step++){
                $folder=array_shift($job['queue']);$data=json_decode(wp_remote_retrieve_body(self::request('files',['q'=>"'".$folder['id']."' in parents and trashed = false",'fields'=>'nextPageToken,files(id,name,mimeType,modifiedTime,size,md5Checksum)','pageSize'=>100,'pageToken'=>$folder['token'],'supportsAllDrives'=>'true','includeItemsFromAllDrives'=>'true'])),true);
                if(!isset($data['files']))throw new RuntimeException('Liste Drive incomplète.');
                foreach($data['files'] as $f){
                    if(++$job['count']>3000)throw new RuntimeException('Dossier trop volumineux pour une seule analyse (3 000 éléments).');
                    if($f['mimeType']==='application/vnd.google-apps.folder'){
                        if(count($folder['path'])>=12)throw new RuntimeException('Arborescence trop profonde.');
                        if(!isset($job['visited'][$f['id']])){$job['visited'][$f['id']]=true;$job['queue'][]=['id'=>$f['id'],'path'=>array_merge($folder['path'],[$f['name']]),'token'=>''];}
                    }elseif((!$qcm&&preg_match('/\.docx$/i',$f['name'])&&$f['mimeType']==='application/vnd.openxmlformats-officedocument.wordprocessingml.document')||($qcm&&preg_match('/_qcm(?:[_ -]v\d+(?:\.\d+)*)?\.xlsx$/i',$f['name'])&&$f['mimeType']==='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')){
                        $f['path']=$folder['path'];$job['files'][]=$f;
                    }
                }
                if(!empty($data['nextPageToken'])){$folder['token']=$data['nextPageToken'];array_unshift($job['queue'],$folder);}
            }
            $job['done']=!$job['queue'];set_transient($key,$job,3600);
            $candidates=$job['done']?self::candidates($job['files']):[];$missing=[];
            if($job['done']&&!$qcm){
                $known=get_posts(['post_type'=>'oi_fiche','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'oi_drive_file_id']);
                foreach($known as $post){$key=get_post_meta($post->ID,'oi_source_key',true);$fileId=get_post_meta($post->ID,'oi_drive_file_id',true);$found=false;foreach($candidates as &$candidate){if($candidate['key']===$key||$candidate['id']===$fileId||(!empty($candidate['code'])&&$candidate['code']===(get_post_meta($post->ID,'oi_source_code',true)?:OI_Imports::code($post->post_title)))){$found=true;if($candidate['id']===$fileId&&!empty($candidate['code'])&&!get_post_meta($post->ID,'oi_source_code',true))update_post_meta($post->ID,'oi_source_code',$candidate['code']);$candidate['state']=$candidate['id']===$fileId&&$candidate['modifiedTime']===get_post_meta($post->ID,'oi_drive_modified',true)?'unchanged':'modified';}}unset($candidate);if(!$found)$missing[]=['id'=>$post->ID,'title'=>$post->post_title];}
            }
            return ['done'=>$job['done'],'visited'=>$job['count'],'files'=>$candidates,'missing'=>$missing];
        }catch(Throwable $e){return new WP_Error('oi_drive',$e->getMessage(),['status'=>400]);}
    }
    public static function candidates(array $files): array {
        $groups=[];foreach($files as $f){$version='0';$path=[];foreach($f['path'] as $part){if(preg_match('/^(?:version\s*|v\s*)?(\d+(?:\.\d+)+)$/i',trim($part),$m))$version=$m[1];else $path[]=$part;}
            $stem=preg_replace('/_qcm$/i','',pathinfo($f['name'],PATHINFO_FILENAME));if($version==='0'&&preg_match('/[_ -]v(\d+(?:\.\d+)+)$/i',$stem,$m))$version=$m[1];$stem=preg_replace('/[_ -]v\d+(?:\.\d+)+$/i','',$stem);$f['code']=OI_Imports::code($f['name']);$key=$f['code']?'code:'.$f['code']:hash('sha256',mb_strtolower(implode('/',$path).'/'.$stem));$f['key']=$key;$f['version']=$version;$f['path_label']=implode(' / ',$f['path']);
            if(!isset($groups[$key])||version_compare($version,$groups[$key][0]['version'],'>'))$groups[$key]=[$f];elseif(version_compare($version,$groups[$key][0]['version'],'='))$groups[$key][]=$f;
        }
        $result=[];foreach($groups as $group)foreach($group as $file){$file['conflict']=count($group)>1;$result[]=$file;}return $result;
    }
    public static function prepare_qcm(WP_REST_Request $r): array|WP_Error {
        $job=get_transient('oi_drive_scan_'.get_current_user_id().'_qcm');$ids=$r['ids'];if(!$job||empty($job['done'])||!is_array($ids)||!$ids||count($ids)>10)return new WP_Error('oi_scan','Analyse le Drive puis sélectionne de 1 à 10 Excel.',['status'=>400]);
        $result=[];foreach(self::candidates($job['files']) as $file){if(!in_array($file['id'],$ids,true))continue;$tmp='';
            try{if($file['conflict'])throw new RuntimeException('Deux Excel portent le même code et la même version. Corrige le doublon.');if((int)($file['size']??0)>5*1024*1024)throw new RuntimeException('Excel de plus de 5 Mo.');
                $body=wp_remote_retrieve_body(self::request('files/'.rawurlencode($file['id']),['alt'=>'media','supportsAllDrives'=>'true']));require_once ABSPATH.'wp-admin/includes/file.php';$tmp=wp_tempnam('oi-qcm');if(!$tmp||file_put_contents($tmp,$body)!==strlen($body))throw new RuntimeException('Fichier temporaire impossible.');
                $id=OI_QCM_Imports::stage($tmp,$file['name'],['file_id'=>$file['id'],'version'=>$file['version'],'modified'=>$file['modifiedTime'],'path'=>$file['path_label']]);$result[]=['file'=>$file['name'],'id'=>$id,'ok'=>true,'unchanged'=>$id===0];
            }catch(Throwable $e){$result[]=['file'=>$file['name'],'ok'=>false,'message'=>$e->getMessage()];}finally{if($tmp&&is_file($tmp))unlink($tmp);}
        }return $result;
    }
    public static function classification(array $path): array {
        $unit=0;$code='';
        foreach($path as $part)if(preg_match('/^([A-E])\.?([1-9])\b/i',$part,$m)){
            $candidate=strtoupper($m[1].$m[2]);$term=get_term_by('slug','programme-2026-'.strtolower($candidate),'oi_enseignement');
            if($term){if($unit&&$unit!==(int)$term->term_id)return [0,0];$unit=(int)$term->term_id;$code=$candidate;}
        }
        if(!$unit)return [0,0];
        $themes=get_terms(['taxonomy'=>'oi_theme','hide_empty'=>false,'meta_key'=>'oi_programme_unit','meta_value'=>$unit]);
        if(is_wp_error($themes))return [$unit,0];
        $matches=[];
        foreach($path as $part){
            $label=preg_replace('/^'.preg_quote($code,'/').'\s*[-–—]\s*/ui','',$part);
            foreach($themes as $t){
                $name=preg_replace('/^'.preg_quote($code,'/').'\s*[-–—]\s*/ui','',$t->name);
                $exact=sanitize_title($part)===sanitize_title($t->name)||sanitize_title($label)===sanitize_title($name);
                $number=preg_match('/^(\d{1,2})\s*[-–—]\s*/u',$label,$a)&&preg_match('/^programme-2026-'.strtolower($code).'-(\d{1,2})$/',$t->slug,$b)&&(int)$a[1]===(int)$b[1];
                if($exact||$number)$matches[(int)$t->term_id]=true;
            }
        }
        return [$unit,count($matches)===1?(int)array_key_first($matches):0];
    }
    public static function prepare(WP_REST_Request $r): array|WP_Error {
        $job=get_transient('oi_drive_scan_'.get_current_user_id());$ids=$r['ids'];if(!$job||empty($job['done'])||!is_array($ids)||count($ids)>10)return new WP_Error('oi_scan','Termine une analyse puis sélectionne jusqu’à 10 Word.',['status'=>400]);
        $result=[];foreach(self::candidates($job['files']) as $file){if(!in_array($file['id'],$ids,true))continue;$tmp='';
            try{
                if($file['conflict'])throw new RuntimeException('Deux Word correspondent à la même fiche/version. Corrige le doublon dans Drive.');
                if((int)($file['size']??0)>12*1024*1024)throw new RuntimeException('Word de plus de 12 Mo.');
                $body=wp_remote_retrieve_body(self::request('files/'.rawurlencode($file['id']),['alt'=>'media','supportsAllDrives'=>'true']));
                // REST requests do not load WordPress administration file helpers.
                require_once ABSPATH.'wp-admin/includes/file.php';
                $tmp=wp_tempnam('oi-word');
                if(!$tmp||file_put_contents($tmp,$body)!==strlen($body))throw new RuntimeException('Impossible de créer le fichier Word temporaire. Réessaie ou vérifie l’espace disponible sur le serveur.');
                [$unit,$theme]=self::classification($file['path']);
                $stage=OI_Imports::stage($tmp,$file['name'],['key'=>$file['key'],'file_id'=>$file['id'],'version'=>$file['version'],'modified'=>$file['modifiedTime'],'path'=>$file['path_label'],'unit'=>$unit,'theme'=>$theme]);$result[]=['file'=>$file['name'],'id'=>$stage,'ok'=>true,'unchanged'=>$stage===0];
            }catch(Throwable $e){$result[]=['file'=>$file['name'],'ok'=>false,'message'=>$e->getMessage()];}finally{if($tmp&&is_file($tmp))unlink($tmp);}
        }return $result;
    }
}

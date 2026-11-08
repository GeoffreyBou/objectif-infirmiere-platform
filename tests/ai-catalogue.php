<?php
$n=0;$assert=static function($ok,$m)use(&$n){if(!$ok)throw new RuntimeException($m);$n++;};
define('OI_OPENAI_API_KEY','fixture-key');$before=[];foreach(['oi_ai_enabled','oi_vector_store','oi_ai_catalogue_permanent','oi_ai_model','oi_ai_quota']as$k)$before[$k]=get_option($k,null);
update_option('oi_ai_enabled',true);update_option('oi_vector_store','vs_catalogue');update_option('oi_ai_model','gpt-4.1-mini');update_option('oi_ai_quota',20);
$user=wp_insert_user(['user_login'=>'catalogue_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$admin=wp_insert_user(['user_login'=>'catalogue_admin_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'administrator']);$ids=[];
for($i=0;$i<110;$i++){$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Corpus fixture '.$i,'post_content'=>'Hygiène des mains.']);$ids[]=$id;foreach(['oi_ai_file'=>'file_'.$id,'oi_ai_store'=>'vs_catalogue','oi_ai_hash'=>OI_AI::hash($id)]as$k=>$v)update_post_meta($id,$k,$v);}
$pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Corpus test']);update_post_meta($pack,'oi_fiches',$ids);update_user_meta($user,'oi_packs',[$pack]);update_user_meta($user,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user);
$search=[];$body=[];$calls=0;$transport=function($pre,$args,$url)use($ids,&$search,&$body,&$calls){
 if(!str_starts_with($url,'https://api.openai.com/'))return $pre;$calls++;
 if(str_ends_with($url,'/search')){$search=json_decode($args['body'],true);$data=['data'=>[['file_id'=>'file_'.$ids[0],'content'=>[['type'=>'text','text'=>str_repeat('Extrait clinique. ',2000)]]]]];}
 elseif(str_ends_with($url,'/responses')){$body=json_decode($args['body'],true);$data=['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>wp_json_encode(['answer'=>'Hygiène des mains.','source_ids'=>[$ids[0]],'source_urls'=>[],'sufficient_sources'=>true]),'annotations'=>[]]]]],'usage'=>['input_tokens'=>100,'output_tokens'=>20]];}
 else{$data=['id'=>'vs_catalogue','expires_after'=>null];}
 return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200]];
};add_filter('pre_http_request',$transport,10,3);
try{
 wp_set_current_user($user);$r=new WP_REST_Request('POST','/oi/v1/ai');$r->set_param('question','Explique l’hygiène des mains.');$r->set_param('model','gpt-4.1');$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);
 $assert($res->get_status()===200,'Corpus greater than 100 fiches accepted');$assert(count($search['filters']['value'])===110,'All authorized current hashes searched');
 $assert(strlen($body['input'])<5000,'Generation context bounded instead of catalogue concatenation');$assert(count($body['text']['format']['schema']['properties']['source_ids']['items']['enum'])===1,'Schema narrowed to actually retrieved fiche');
 $assert($body['model']==='gpt-4.1-mini','Student cannot override model');$assert(OI_Credits::balances($user)['IA']===4,'Single debit for large-corpus answer');
 $count=$calls;foreach(['start'=>'POST','index'=>'POST','status'=>'GET']as$action=>$method){$r=new WP_REST_Request($method,'/oi/v1/ai-catalogue/'.$action);$assert(rest_do_request($r)->get_status()===403,'Student cannot manage catalogue');}$assert($calls===$count,'Unauthorized catalogue requests never call provider');
 wp_set_current_user($admin);$r=new WP_REST_Request('POST','/oi/v1/ai-catalogue/start');$res=rest_do_request($r);$assert($res->get_status()===200&&get_option('oi_ai_catalogue_permanent'),'Permanent store confirmed');
 $res=rest_do_request(new WP_REST_Request('GET','/oi/v1/ai-catalogue/status'));$assert($res->get_data()['ready']>=110,'Catalogue status reflects current indexed hashes');
 $r=new WP_REST_Request('POST','/oi/v1/ai-catalogue/index');$r->set_param('fiche_id',$pack);$assert(rest_do_request($r)->get_status()===400,'Index rejects non-fiche content');
 echo "AI catalogue : $n assertions réussies.\n";
}finally{
 remove_filter('pre_http_request',$transport,10);foreach($before as$k=>$v){if($v===null)delete_option($k);else update_option($k,$v);}
 foreach($ids as$id){delete_post_meta($id,'oi_ai_file');wp_clear_scheduled_hook('oi_sync_fiche',[$id]);wp_delete_post($id,true);}wp_delete_post($pack,true);
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);wp_delete_user($admin);global$wpdb;foreach(['oi_ai_usage','oi_ai_requests','oi_wallets','oi_credit_grants','oi_credit_operations']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$user]);
}

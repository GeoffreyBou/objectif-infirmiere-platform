<?php
$n=0;$assert=static function($ok,$m)use(&$n){if(!$ok)throw new RuntimeException($m);$n++;};
define('OI_OPENAI_API_KEY','fixture-not-real');$before=[];foreach(['oi_ai_enabled','oi_ai_web_enabled','oi_vector_store','oi_ai_quota']as$k)$before[$k]=get_option($k,null);
update_option('oi_ai_enabled',true);update_option('oi_ai_web_enabled',false);update_option('oi_vector_store','vs_research');update_option('oi_ai_quota',20);
$user=wp_insert_user(['user_login'=>'research_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$fiche=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Hygiène accessible','post_content'=>'Les gants ne remplacent pas l’hygiène des mains.']);
$other=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'CONTENU INTERDIT','post_content'=>'SECRET_UNAUTHORIZED_COURSE']);
$pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Accès de recette']);update_post_meta($pack,'oi_fiches',[$fiche]);update_user_meta($user,'oi_packs',[$pack]);
foreach([$fiche,$other]as$id)foreach(['oi_ai_file'=>'file_'.$id,'oi_ai_hash'=>OI_AI::hash($id),'oi_ai_store'=>'vs_research']as$k=>$v)update_post_meta($id,$k,$v);
wp_set_current_user($user);update_user_meta($user,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user);
$observed='https://www.has-sante.fr/jcms/fixture';$declared=$observed;$calls=0;$body=[];$search=[];$web_body=[];$web_ok=true;$course_only=false;$cite_course=true;
$transport=function($pre,$args,$url)use($fiche,$other,$observed,&$declared,&$calls,&$body,&$search,&$web_body,&$web_ok,&$course_only,&$cite_course){
 if(!str_starts_with($url,'https://api.openai.com/'))return $pre;
 if(str_ends_with($url,'/search')){$search=json_decode($args['body'],true);$data=['data'=>[['file_id'=>'file_'.$other,'content'=>[['type'=>'text','text'=>'SECRET_UNAUTHORIZED_COURSE']]],['file_id'=>'file_'.$fiche,'content'=>[['type'=>'text','text'=>'Les gants ne remplacent pas l’hygiène des mains.']]]]];}
 else{$calls++;$body=json_decode($args['body'],true);if(!empty($body['tools']))$web_body=$body;$data=['status'=>'completed','output'=>[['type'=>'web_search_call','status'=>'completed','action'=>['sources'=>[['url'=>$observed,'title'=>'HAS – Hygiène']]]],['type'=>'message','content'=>[['type'=>'output_text','text'=>wp_json_encode(['answer'=>$course_only?'Je te propose une grille d’analyse fondée sur les principes d’hygiène de tes fiches.':'Les gants ne remplacent pas l’hygiène des mains [R1].','source_ids'=>$cite_course?[$fiche]:[],'source_urls'=>$course_only?[]:[$declared],'sufficient_sources'=>true]),'annotations'=>[]]]]],'usage'=>['input_tokens'=>100,'output_tokens'=>20]];}
 if(!empty($body['tools'])&&str_ends_with($url,'/responses'))$data['output'][1]['content'][0]['text']='Les gants ne remplacent pas l’hygiène des mains, selon la HAS.';
 if(!$web_ok&&!empty($body['tools'])&&str_ends_with($url,'/responses'))$data['output'][0]['action']['sources']=[];
 return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200]];
};add_filter('pre_http_request',$transport,10,3);
try{
 foreach(['http://www.has-sante.fr/a','https://has-sante.fr.evil.example/a','https://user@has-sante.fr/a','https://has-sante.fr:444/a','javascript:alert(1)','https://127.0.0.1/a']as$url)$assert(OI_AI_Sources::web_url($url)==='','Unsafe/unapproved URL rejected');
 $assert(OI_AI_Sources::web_url($observed.'?utm_source=openai#section')===$observed,'Tracking and fragment normalization');
 $assert(OI_AI_Sources::reference_text('[HAS]('.$observed.'?utm_source=openai&trk=tracking)',[['url'=>$observed]])==='[R1]','Verified markdown link converted to inline reference');
 $assert(is_wp_error(OI_AI_Sources::reference_text('https://evil.example/a',[['url'=>$observed]])),'Unverified URL in prose rejected');
 $native=['output'=>[['type'=>'web_search_call','status'=>'completed','action'=>['type'=>'search']],['type'=>'message','content'=>[['annotations'=>[['type'=>'url_citation','url'=>$observed,'title'=>'HAS']]]]]]];
 $assert(isset(OI_AI_Sources::web_evidence($native)[$observed]),'Native provider URL citation proves a completed search result without source list');
 $native['output'][0]['status']='failed';$assert(OI_AI_Sources::web_evidence($native)===[],'Annotation without completed search is not evidence');
 $r=new WP_REST_Request('POST','/oi/v1/ai');$r->set_param('question','Vérifie les recommandations officielles.');$r->set_param('research',true);$r->set_param('request_id',wp_generate_uuid4());
 $assert(rest_do_request($r)->get_status()===503&&$calls===0,'Disabled research makes no provider call');update_option('oi_ai_web_enabled',true);
 $declared='https://www.has-sante.fr/jcms/not-retrieved';$res=rest_do_request($r);$assert($res->get_status()===502&&OI_Credits::balances($user)['IA']===5,'Allowed domain but unobserved page rejected and refunded');
 $assert(!str_contains($body['input'],'SECRET_UNAUTHORIZED_COURSE'),'Inaccessible retrieval hit never reaches generation');
 $assert(count($search['filters']['value'])===1&&str_starts_with($search['filters']['value'][0],$fiche.':'),'Search restricted by current accessible hash');
 $declared='https://evil.example/fake';$r->set_param('request_id',wp_generate_uuid4());$assert(rest_do_request($r)->get_status()===502,'Unapproved declared reference rejected');
 $declared=$observed;$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);$assert($res->get_status()===200,'Sourced research accepted');
 $assert($res->get_data()['web_sources'][0]['url']===$observed&&$res->get_data()['web_sources'][0]['consulted_at']===gmdate('Y-m-d'),'Reference returned with actual consultation date');
 $assert(OI_Credits::balances($user)['IA']===4,'One accepted answer debits once');
 global $wpdb;$journal=$wpdb->get_row($wpdb->prepare("SELECT input_tokens,output_tokens FROM {$wpdb->prefix}oi_ai_requests WHERE user_id=%d AND request_key=%s",$user,$r['request_id']));
 $assert((int)$journal->input_tokens===200&&(int)$journal->output_tokens===40,'Journal sums web and generation tokens once');
 $assert($web_body['tools'][0]['type']==='web_search'&&$web_body['tools'][0]['filters']['allowed_domains']===OI_AI_Sources::DOMAINS,'Provider search uses fixed health domain allowlist');
 $assert(empty($body['tools'])&&!empty($web_body['tools'])&&!isset($web_body['text']),'Search with native citations precedes structured generation');
 $assert($body['text']['format']['schema']['properties']['source_urls']['items']['enum']===[$observed],'Final URL schema restricted to observed references');
 $r->set_param('research',false);$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);$assert($res->get_status()===502&&OI_Credits::balances($user)['IA']===4,'Research-off cannot accept external claims');
 $assert(empty($body['tools']),'No web tool when research is off');
 $web_ok=false;$r->set_param('research',true);$r->set_param('request_id',wp_generate_uuid4());$before_calls=$calls;$res=rest_do_request($r);
 $assert($res->get_status()===502&&$calls===$before_calls+1&&OI_Credits::balances($user)['IA']===4,'Unproven web search stops before generation and refunds credit');
 // Clear only this isolated fixture's rate bucket before the additional regression cases.
 $wpdb->delete($wpdb->prefix.'oi_rates',['bucket'=>hash('sha256','ai|user:'.$user.'|'.intdiv(time(),60))]);
 $web_ok=true;$course_only=true;$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);
 $assert($res->get_status()===200&&count($res->get_data()['sources'])===1&&$res->get_data()['web_sources']===[],'Research enabled accepts course-grounded synthesis without forcing an external reference');
 $assert(OI_Credits::balances($user)['IA']===3,'Course synthesis consumes one credit');
 $cite_course=false;$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);
 $assert($res->get_status()===502&&OI_Credits::balances($user)['IA']===3,'Answer without any evidence still rejected and refunded');
 echo "AI research : $n assertions réussies.\n";
}finally{
 remove_filter('pre_http_request',$transport,10);foreach($before as$k=>$v){if($v===null)delete_option($k);else update_option($k,$v);}
 foreach([$fiche,$other]as$id){delete_post_meta($id,'oi_ai_file');wp_clear_scheduled_hook('oi_sync_fiche',[$id]);wp_delete_post($id,true);}wp_delete_post($pack,true);
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);global$wpdb;foreach(['oi_ai_usage','oi_ai_requests','oi_wallets','oi_credit_grants','oi_credit_operations']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$user]);
}

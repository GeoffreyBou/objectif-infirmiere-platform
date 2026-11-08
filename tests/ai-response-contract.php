<?php
$n=0;$assert=static function($ok,$m)use(&$n){if(!$ok)throw new RuntimeException($m);$n++;};
define('OI_OPENAI_API_KEY','fixture-key-not-real');
$before=[];foreach(['oi_ai_enabled','oi_vector_store']as$key)$before[$key]=get_option($key,null);
$prior_error=get_transient('oi_ai_last_provider_error');
update_option('oi_ai_enabled',false);update_option('oi_vector_store','vs_response_contract');
$user=wp_insert_user(['user_login'=>'ai_contract_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'administrator']);
$fiche=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Fixture calcul','post_content'=>'750 mg à 100 mg/mL donne 7,5 mL.']);
foreach(['oi_ai_file'=>'file_contract','oi_ai_hash'=>OI_AI::hash($fiche),'oi_ai_store'=>'vs_response_contract']as$key=>$value)update_post_meta($fiche,$key,$value);
wp_set_current_user($user);update_user_meta($user,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user);
$state='incomplete';$calls=0;$payload=[];$hit=true;$source_id=$fiche;
$transport=function($pre,$args,$url)use(&$state,&$calls,&$payload,$fiche,&$hit,&$source_id){
 if(str_ends_with($url,'/search'))return ['headers'=>[],'body'=>wp_json_encode(['data'=>$hit?[['file_id'=>'file_contract','content'=>[['type'=>'text','text'=>'750 mg à 100 mg/mL donne 7,5 mL.']]]]:[]]),'response'=>['code'=>200]];
 if(!str_ends_with($url,'/responses'))return $pre;
 $calls++;$payload=json_decode($args['body'],true);
 return ['headers'=>[],'body'=>wp_json_encode(['status'=>$state,'incomplete_details'=>['reason'=>'max_output_tokens'],'output'=>[['type'=>'file_search_call','results'=>$hit?[['file_id'=>'file_contract']]:[]],['type'=>'message','content'=>[['type'=>'output_text','text'=>wp_json_encode(['answer'=>'7,5 mL.','source_ids'=>[$source_id],'source_urls'=>[],'sufficient_sources'=>true]),'annotations'=>[['type'=>'file_citation','file_id'=>'file_contract']]]]]],'usage'=>['input_tokens'=>111,'output_tokens'=>22]]),'response'=>['code'=>200]];
};add_filter('pre_http_request',$transport,10,3);
try {
 $r=new WP_REST_Request('POST','/oi/v1/ai-validation/ask');$r->set_param('question','Explique le calcul.');$r->set_param('request_id',wp_generate_uuid4());
 $r->set_param('model','unapproved-model');$assert(rest_do_request($r)->get_status()===400&&$calls===0,'Unapproved preview model rejected before provider call');$r->set_param('model','gpt-4.1');
 $res=rest_do_request($r);$assert($res->get_status()===502,'Incomplete answer with valid citation still rejected');
 $assert(OI_Credits::balances($user)['IA']===5,'Incomplete answer refunds credit');
 $assert(!str_contains(wp_json_encode($res->get_data()),'7,5 mL'),'Unaccepted answer text not exposed in diagnostic');
 $state='completed';set_transient('oi_ai_last_provider_error',['reason'=>'billing_or_quota'],60);$r->set_param('request_id',wp_generate_uuid4());
 $res=rest_do_request($r);$assert($res->get_status()===200,'Complete sourced preview accepted while students disabled');
 $assert($payload['model']==='gpt-4.1'&&$res->get_data()['model']==='gpt-4.1','Authorized admin comparison selects requested model');
 $assert($res->get_data()['usage']['input_tokens']===111,'Admin receives token counts for evaluation');
 $assert(OI_Credits::balances($user)['IA']===4,'Successful answer costs exactly one credit');
 $assert(get_transient('oi_ai_last_provider_error')===false,'Successful generation clears obsolete billing diagnostic');
 $assert(str_contains($payload['instructions'],'sans sous-questions supplémentaires')&&str_contains($payload['instructions'],'validation clinique'),'Prompt contains observed quality corrections');
 $assert(rest_do_request($r)->get_status()===409&&$calls===2,'Replay rejected without extra provider call');
 $assert(OI_Credits::balances($user)['IA']===4,'Replay does not debit');
 $hit=false;$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);
 $assert($res->get_status()===422&&OI_Credits::balances($user)['IA']===4,'Valid source ID without retrieval evidence rejected and refunded');
 $hit=true;$source_id=999999999;$r->set_param('request_id',wp_generate_uuid4());$res=rest_do_request($r);
 $assert($res->get_status()===502&&OI_Credits::balances($user)['IA']===4,'Invented source ID rejected and refunded');
 $assert($payload['text']['format']['strict']===true&&str_contains($payload['input'],'Extraits de cours autorisés'),'Structured contract and retrieval proof requested');
 $assert(!(bool)get_option('oi_ai_enabled'),'Preview never changes general activation');
 echo "AI response contract : $n assertions réussies.\n";
}finally{
 remove_filter('pre_http_request',$transport,10);
 foreach($before as$key=>$value){if($value===null)delete_option($key);else update_option($key,$value);}
 if($prior_error===false)delete_transient('oi_ai_last_provider_error');else set_transient('oi_ai_last_provider_error',$prior_error,HOUR_IN_SECONDS);
 delete_post_meta($fiche,'oi_ai_file');wp_delete_post($fiche,true);wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);
 global $wpdb;foreach(['oi_ai_usage','oi_ai_requests','oi_wallets','oi_credit_grants','oi_credit_operations']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$user]);
}

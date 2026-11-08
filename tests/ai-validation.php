<?php
$n=0;$assert=static function($ok,$message)use(&$n){if(!$ok)throw new RuntimeException($message);$n++;};
$old=get_option('oi_ai_enabled');update_option('oi_ai_enabled',false);
$admin=wp_insert_user(['user_login'=>'ai_val_admin_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'administrator']);
$student=wp_insert_user(['user_login'=>'ai_val_student_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$prior_error=get_transient('oi_ai_last_provider_error');
$calls=0;
$transport=function($pre,$args,$url)use(&$calls){if(!str_starts_with($url,'https://api.openai.com/'))return $pre;$calls++;return ['headers'=>[],'body'=>'{"id":"gpt-4.1-mini"}','response'=>['code'=>200]];};
define('OI_OPENAI_API_KEY','fixture-not-real');add_filter('pre_http_request',$transport,10,3);
try {
 foreach([0,$student] as $uid){wp_set_current_user($uid);foreach(['check','prepare','index','ask'] as $action){$r=new WP_REST_Request('POST','/oi/v1/ai-validation/'.$action);$assert(in_array(rest_do_request($r)->get_status(),[401,403],true),'Non-admin route denied');}}
 wp_set_current_user($student);$r=new WP_REST_Request('POST','/oi/v1/ai');$r->set_param('question','Pourquoi ?');$r->set_param('preview',true);
 $assert(rest_do_request($r)->get_status()===503,'Public preview parameter cannot enable assistant');
 $assert(is_wp_error(OI_AI_Validation::ask($r)),'Direct preview handler also checks admin');
 $assert($calls===0,'Unauthorized requests do not call OpenAI');
 wp_set_current_user($admin);$r=new WP_REST_Request('POST','/oi/v1/ai-validation/check');$res=rest_do_request($r);
 $assert($res->get_status()===200&&$res->get_data()['connected'],'Admin model connection check');
 $assert($calls===1,'Only read-only model request sent');
 $r=new WP_REST_Request('POST','/oi/v1/ai-validation/prepare');$r->set_param('fiche_ids',array_fill(0,16,1));$assert(rest_do_request($r)->get_status()===400,'Batch bounded to 15');
 $r->set_param('fiche_ids',[999999999]);$assert(rest_do_request($r)->get_status()===400,'Unknown fiche rejected');
 $r=new WP_REST_Request('POST','/oi/v1/ai-validation/index');$r->set_param('fiche_id',999999999);$assert(rest_do_request($r)->get_status()===400,'Out of selection index denied');
 $assert(!(bool)get_option('oi_ai_enabled'),'Validation never enables student assistant');
 remove_filter('pre_http_request',$transport,10);
 $error_transport=fn()=>['headers'=>[],'body'=>wp_json_encode(['error'=>['code'=>'credit_balance_exhausted','type'=>'insufficient_quota','message'=>'Credit balance exhausted. private-marker-do-not-record']]),'response'=>['code'=>429]];
 add_filter('pre_http_request',$error_transport,10,3);
 try {
  $error=OI_AI::request('POST','responses',[]);$diag=get_transient('oi_ai_last_provider_error');
  $assert(is_wp_error($error)&&$error->get_error_data()['upstream_code']==='credit_balance_exhausted','Billing refusal identified');
  $assert($diag['reason']==='billing_or_quota'&&$diag['type']==='insufficient_quota','Diagnostic only stores categories');
  $assert(!str_contains(wp_json_encode($diag),'private-marker'),'Provider message not retained');
 }finally{remove_filter('pre_http_request',$error_transport,10);}
 echo "AI validation : $n assertions réussies.\n";
}finally{if($prior_error===false)delete_transient('oi_ai_last_provider_error');else set_transient('oi_ai_last_provider_error',$prior_error,HOUR_IN_SECONDS);remove_filter('pre_http_request',$transport,10);update_option('oi_ai_enabled',$old);wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($admin);wp_delete_user($student);}

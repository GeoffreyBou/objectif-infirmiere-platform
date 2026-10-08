<?php
$GLOBALS['ai_checks']=0;
function ai_check($condition,$message) {if(!$condition)throw new RuntimeException($message);$GLOBALS['ai_checks']++;}
define('OI_OPENAI_API_KEY','fixture_'.wp_generate_password(32,false));
$old_enabled=get_option('oi_ai_enabled',false);update_option('oi_ai_enabled',true);
$old_store=get_option('oi_vector_store',null);$old_quota=get_option('oi_ai_quota',null);update_option('oi_vector_store','vs_fixture');update_option('oi_ai_quota',3);
$user=wp_insert_user(['user_login'=>'ai_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$fiche=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Furosémide TEST','post_content'=>'Surveillance de la kaliémie.']);
$forbidden=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'SECRET AUTRE PACK','post_content'=>'Ne doit pas être transmis']);
$pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'TEST IA']);update_post_meta($pack,'oi_fiches',[$fiche]);update_user_meta($user,'oi_packs',[$pack]);
update_user_meta($user,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user);
$GLOBALS['ai_response_calls']=0;$GLOBALS['ai_forbidden_citation']=true;$GLOBALS['ai_response_fail']=false;$GLOBALS['ai_uploads']=0;$GLOBALS['ai_body']=null;$GLOBALS['ai_attached']=[];$GLOBALS['ai_attaches']=0;$GLOBALS['ai_attach_fail_once']=false;
$transport=function($pre,$args,$url)use($fiche) {
 if(!str_starts_with($url,'https://api.openai.com/v1/'))return $pre;
 if(str_ends_with($url,'/responses')) {
  $GLOBALS['ai_response_calls']++;
  $GLOBALS['ai_body']=json_decode($args['body'],true);
  if($GLOBALS['ai_response_fail'])return new WP_Error('network','Timeout');
  $annotations=[['type'=>'file_citation','file_id'=>get_post_meta($fiche,'oi_ai_file',true)]];if($GLOBALS['ai_forbidden_citation'])$annotations[]=['type'=>'file_citation','file_id'=>'file_forbidden'];
  $data=['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Le furosémide peut favoriser une perte de potassium.','annotations'=>$annotations]]]],'usage'=>['input_tokens'=>100,'output_tokens'=>40]];
 } elseif(str_ends_with($url,'/files') && $args['method']==='POST' && str_contains($args['headers']['Content-Type'],'multipart')) {
  $GLOBALS['ai_uploads']++;$data=['id'=>'file_test'.$GLOBALS['ai_uploads']];
 } elseif(preg_match('~/vector_stores/vs_fixture/files/(file_test[0-9]+)$~',$url,$matches)) {
  if(empty($GLOBALS['ai_attached'][$matches[1]])) return ['headers'=>[],'body'=>'{}','response'=>['code'=>404,'message'=>'Not found']];
  $data=['id'=>$matches[1],'status'=>'completed'];
 } elseif(str_ends_with($url,'/vector_stores/vs_fixture/files') && $args['method']==='POST') {
  $body=json_decode($args['body'],true);$GLOBALS['ai_attached'][$body['file_id']]=true;$GLOBALS['ai_attaches']++;
  if($GLOBALS['ai_attach_fail_once']) {$GLOBALS['ai_attach_fail_once']=false;return new WP_Error('network','Timeout after attach');}
  $data=['id'=>$body['file_id'],'status'=>'in_progress'];
 } else {$data=['id'=>'file_test','status'=>'completed','deleted'=>true];}
 return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200,'message'=>'OK']];
};add_filter('pre_http_request',$transport,10,3);
try {
 OI_AI::sync($fiche);ai_check(get_post_meta($fiche,'oi_ai_status',true)==='completed','Synchronisation complète');
 OI_AI::sync($fiche);ai_check($GLOBALS['ai_uploads']===1,'Pas de duplication à contenu identique');
 wp_update_post(['ID'=>$fiche,'post_content'=>'Surveillance infirmière de la kaliémie, version deux.']);OI_AI::sync($fiche);ai_check($GLOBALS['ai_uploads']===2 && get_post_meta($fiche,'oi_ai_file',true)==='file_test2','Mise à jour remplace document');
 wp_update_post(['ID'=>$fiche,'post_content'=>'Version trois à réindexer.']);$GLOBALS['ai_attach_fail_once']=true;OI_AI::sync($fiche);ai_check(get_post_meta($fiche,'oi_ai_status',true)==='attach_failed','Panne après association conservée pour reprise');OI_AI::sync($fiche);ai_check($GLOBALS['ai_uploads']===3 && $GLOBALS['ai_attaches']===3 && get_post_meta($fiche,'oi_ai_file',true)==='file_test3','Reprise sans nouveau fichier ni association doublonnée');
 wp_set_current_user($user);
 $r=new WP_REST_Request('POST','/oi/v1/ai');$r->set_param('question','Pourquoi surveiller la kaliémie ?');$r->set_param('fiche_id',$forbidden);ai_check(rest_do_request($r)->get_status()===403,'Contexte interdit rejeté');
 $r->set_param('request_id',wp_generate_uuid4());$r->set_param('fiche_id',$fiche);ai_check(rest_do_request($r)->get_status()===502,'Réponse contenant une citation interdite rejetée');ai_check(OI_Credits::balances($user)['IA']===5,'Citation invalide : crédit restitué');$r->set_param('request_id',wp_generate_uuid4());$GLOBALS['ai_forbidden_citation']=false;$response=rest_do_request($r);ai_check($response->get_status()===200,'Réponse sourcée simulée');
 ai_check(count($response->get_data()['sources'])===1 && $response->get_data()['sources'][0]['id']===$fiche,'Citation interdite exclue');
 ai_check(count($GLOBALS['ai_body']['tools'][0]['filters']['value'])===1 && str_starts_with($GLOBALS['ai_body']['tools'][0]['filters']['value'][0],$fiche.':'),'Recherche bornée aux documents autorisés');
 ai_check(!str_contains(wp_json_encode($GLOBALS['ai_body']),get_userdata($user)->user_login),'Aucun identifiant utilisateur transmis');
 ai_check(OI_Credits::balances($user)['IA']===4,'Réponse réussie : un débit');
 $r->set_param('request_id',wp_generate_uuid4());$GLOBALS['ai_response_fail']=true;ai_check(rest_do_request($r)->get_status()===503,'Erreur API présentée proprement');
 ai_check(OI_Credits::balances($user)['IA']===4,'Panne OpenAI : crédit restitué');
 ai_check(rest_do_request('/oi/v1/fiches/'.$fiche)->get_status()===200,'Fiches indépendantes de OpenAI');
 $r->set_param('request_id',wp_generate_uuid4());ai_check(rest_do_request($r)->get_status()===429,'Quota bloque quatrième réservation');
 // The zero-credit check must run before transport, independently of the daily limit.
 global $wpdb;$wpdb->update($wpdb->prefix.'oi_credit_grants',['remaining'=>0],['user_id'=>$user,'kind'=>'IA']);
 $bucket=hash('sha256','ai|user:'.$user.'|'.intdiv(time(),60));$wpdb->delete($wpdb->prefix.'oi_rates',['bucket'=>$bucket]);
 $calls=$GLOBALS['ai_response_calls'];$r->set_param('request_id',wp_generate_uuid4());
 $zero=OI_AI::ask($r);ai_check(is_wp_error($zero)&&$zero->get_error_code()==='oi_no_credit','Solde nul refusé explicitement');
 ai_check($GLOBALS['ai_response_calls']===$calls,'Solde nul : aucun appel OpenAI');
 $r->set_param('question',str_repeat('a',2001));ai_check(rest_do_request($r)->get_status()===400,'Question trop longue refusée');
 wp_update_post(['ID'=>$fiche,'post_status'=>'draft']);OI_AI::sync($fiche);ai_check(!get_post_meta($fiche,'oi_ai_file',true),'Dépublication retire fichier');
 echo 'IA : '.$GLOBALS['ai_checks']." assertions réussies (OpenAI simulé)\n";
}finally{
 remove_filter('pre_http_request',$transport,10);
 update_option('oi_ai_enabled',$old_enabled);
 foreach(['oi_vector_store'=>$old_store,'oi_ai_quota'=>$old_quota]as$key=>$value){if($value===null)delete_option($key);else update_option($key,$value);}
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);foreach([$pack,$fiche,$forbidden]as$id)wp_delete_post($id,true);
 global $wpdb;foreach(['oi_ai_usage','oi_ai_requests','oi_wallets','oi_credit_grants','oi_credit_operations']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$user]);
}

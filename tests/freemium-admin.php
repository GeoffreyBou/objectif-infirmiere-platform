<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Tests locaux uniquement.');
global $wpdb;$checks=0;$users=[];$campaigns=[];$oldDays=get_option('oi_ai_retention_days',false);$oldInput=get_option('oi_ai_input_cost',false);$oldOutput=get_option('oi_ai_output_cost',false);
$assert=function($v,$m)use(&$checks){if(!$v)throw new RuntimeException($m);$checks++;};
$newUser=function($role='oi_etudiant')use(&$users){$u=wp_insert_user(['user_login'=>'admin_test_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'user_email'=>'admin_test_'.wp_generate_password(12,false).'@example.invalid','role'=>$role]);if(is_wp_error($u))throw new RuntimeException('Fixture invalide');$users[]=$u;return $u;};
try{
 $admin=$newUser('administrator');$user=$newUser();update_user_meta($user,'oi_email_pending',1);
 $token=bin2hex(random_bytes(32));update_user_meta($user,'oi_email_token',hash('sha256',$token));update_user_meta($user,'oi_email_expires',time()-1);
 $assert(is_wp_error(OI_Verification::confirm($user,$token))&&!OI_Verification::verified($user),'Jeton expiré refusé');
 $failMail=fn()=>false;add_filter('pre_wp_mail',$failMail,100);
 $assert(is_wp_error(OI_Verification::issue($user))&&OI_Credits::balances($user)['IA']===0,'Échec livraison : compte non crédité');remove_filter('pre_wp_mail',$failMail,100);
 $assert(OI_Verification::issue($user)===true,'Nouvel e-mail capturé localement');
 $assert(is_wp_error(OI_Verification::confirm($user,$token)),'Ancien jeton invalidé après renvoi');
 update_user_meta($user,'oi_email_verified',gmdate('c'));delete_user_meta($user,'oi_email_pending');OI_Credits::welcome($user);
 wp_update_user(['ID'=>$user,'user_email'=>'changed_'.wp_generate_password(10,false).'@example.invalid']);
 $assert(!OI_Verification::verified($user)&&(bool)get_user_meta($user,'oi_email_pending',true),'Changement e-mail impose une nouvelle vérification');
 $assert(OI_Credits::balances($user)['IA']===5,'Changement e-mail conserve crédits existants');
 update_user_meta($user,'oi_email_verified',gmdate('c'));delete_user_meta($user,'oi_email_pending');OI_Credits::welcome($user);
 $assert(OI_Credits::balances($user)['IA']===5,'Nouvelle vérification ne redonne pas le cadeau');
 $id=wp_generate_uuid4();$campaigns[]=$id;$fields=['campaign'=>$id,'kind'=>'IA','amount'=>3,'reason'=>'Test campagne','users'=>(string)$user];
 $assert(is_wp_error(OI_Credit_Admin::campaign($fields,$user)),'Étudiant ne peut attribuer de crédits');
 $r=OI_Credit_Admin::campaign($fields,$admin);$assert(!is_wp_error($r)&&$r['processed']===1&&OI_Credits::balances($user)['IA']===8,'Attribution administrative');
 $fields['amount']=999;OI_Credit_Admin::campaign($fields,$admin);$assert(OI_Credits::balances($user)['IA']===8,'Rejeu conserve sélection et montant initial');
 $id=wp_generate_uuid4();$campaigns[]=$id;
 $wpdb->insert($wpdb->prefix.'oi_campaigns',['id'=>$id,'kind'=>'QCM','amount'=>2,'reason'=>'Reprise','user_ids'=>wp_json_encode([$user]),'created_by'=>$admin,'created_at'=>gmdate('Y-m-d H:i:s')]);
 OI_Credits::grant($user,'QCM',2,'campaign:'.$id,'Reprise');$r=OI_Credit_Admin::campaign(['campaign'=>$id],$admin);
 $assert($r['processed']===1&&OI_Credits::balances($user)['QCM']===7,'Reprise après interruption ne recrédite pas le lot');
 $wpdb->update($wpdb->prefix.'oi_campaigns',['expires_at'=>gmdate('Y-m-d H:i:s',time()-60)],['id'=>$id]);$assert(is_wp_error(OI_Credit_Admin::campaign(['campaign'=>$id],$admin)),'Campagne expirée refusée');
 update_option('oi_ai_retention_days',0);delete_option('oi_ai_input_cost');delete_option('oi_ai_output_cost');
 $request=wp_generate_uuid4();OI_AI_Journal::record($user,$request,'model-test','welcome','success',['usage'=>['input_tokens'=>100,'output_tokens'=>50]],'Question privée','Réponse privée');
 $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_ai_requests WHERE user_id=%d AND request_key=%s",$user,$request));
 $assert($row->question===''&&$row->answer===''&&$row->cost_usd===null,'Textes exclus par défaut, coût inconnu non affiché gratuit');
 update_option('oi_ai_retention_days',2);update_option('oi_ai_input_cost',2);update_option('oi_ai_output_cost',8);
 $request=wp_generate_uuid4();OI_AI_Journal::record($user,$request,'model-test','welcome','failed',['usage'=>['input_tokens'=>100,'output_tokens'=>50]],'Question privée','Réponse privée');
 $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_ai_requests WHERE user_id=%d AND request_key=%s",$user,$request));
 $assert($row->question==='Question privée'&&abs((float)$row->cost_usd-0.0006)<0.00000001,'Coût et rétention optionnelle mesurés, échec compris');
 $wpdb->update($wpdb->prefix.'oi_ai_requests',['created_at'=>gmdate('Y-m-d H:i:s',time()-3*DAY_IN_SECONDS)],['id'=>$row->id]);OI_AI_Journal::purge();
 $assert((string)$wpdb->get_var($wpdb->prepare("SELECT question FROM {$wpdb->prefix}oi_ai_requests WHERE id=%d",$row->id))==='','Purge expire les textes sans nouvelle question');
 $assert((bool)wp_next_scheduled('oi_purge_ai_texts'),'Purge périodique programmée');
 $report=OI_Metrics::report();$assert(isset($report['achats_premium_confirmes'])&&!str_contains(wp_json_encode($report),'Question privée')&&!str_contains(wp_json_encode($report),'@'),'Rapport agrégé sans conversation ni e-mail');
 $assert($report['conversion_gratuit_payant_pct']>=0&&$report['conversion_gratuit_payant_pct']<=100,'Conversion mesurée dans la cohorte vérifiée');
 echo "Administration, vérification et confidentialité : $checks assertions réussies\n";
}finally{
 foreach($campaigns as$id)$wpdb->delete($wpdb->prefix.'oi_campaigns',['id'=>$id]);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$u){foreach(['oi_wallets','oi_credit_grants','oi_credit_operations','oi_unlocks','oi_ai_requests','oi_metrics']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$u]);wp_delete_user($u);}
 foreach(['oi_ai_retention_days'=>$oldDays,'oi_ai_input_cost'=>$oldInput,'oi_ai_output_cost'=>$oldOutput]as$key=>$value){if($value===false)delete_option($key);else update_option($key,$value);}
}

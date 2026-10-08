<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Tests locaux uniquement.');
global $wpdb;$checks=0;$users=[];$posts=[];$old=get_option('oi_premium_pack',false);
$assert=function($v,$m)use(&$checks){if(!$v)throw new RuntimeException($m);$checks++;};
try {
 $user=wp_insert_user(['user_login'=>'credit_'.wp_generate_password(15,false),'user_pass'=>wp_generate_password(32),'role'=>'oi_etudiant']);$users[]=$user;
 update_user_meta($user,'oi_email_pending',1);$token=bin2hex(random_bytes(32));update_user_meta($user,'oi_email_token',hash('sha256',$token));update_user_meta($user,'oi_email_expires',time()+3600);
 $assert(is_wp_error(OI_Credits::welcome($user)),'Aucun cadeau avant vérification');
 $assert(OI_Credits::balances($user)===['FICHE'=>0,'QCM'=>0,'IA'=>0],'Solde initial nul');
 $assert(is_wp_error(OI_Verification::confirm($user,str_repeat('a',64))),'Jeton incorrect refusé');
 $assert(OI_Verification::confirm($user,$token)===true,'Adresse confirmée');
 $assert(OI_Credits::balances($user)===['FICHE'=>5,'QCM'=>5,'IA'=>5],'Bienvenue 5/5/5');
 OI_Verification::confirm($user,$token);OI_Credits::welcome($user);
 $assert(OI_Credits::balances($user)===['FICHE'=>5,'QCM'=>5,'IA'=>5],'Rejeu sans doublon');
 $pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Test crédits']);$posts[]=$pack;update_option('oi_premium_pack',$pack);$ids=[];
 for($i=0;$i<6;$i++){$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Crédits '.$i,'post_content'=>'Contenu protégé']);$ids[]=$id;$posts[]=$id;update_post_meta($id,'oi_quiz',[['question'=>'Question','options'=>['A','B'],'correct'=>[0],'explanation'=>'Correction']]);}
 update_post_meta($pack,'oi_fiches',$ids);
 $assert(!OI_Model::can_read($user,$ids[0]),'Contenu verrouillé');
 wp_set_current_user($user);
 $request=new WP_REST_Request('GET','/oi/v1/fiches');$catalogue=rest_do_request($request);
 $assert($catalogue->get_status()===200 && count($catalogue->get_data()['items'])===6,'Catalogue explorable avant déblocage');
 $assert(!str_contains(wp_json_encode($catalogue->get_data()),'Contenu protégé'),'Catalogue sans corps de fiche');
 $denied=rest_do_request(new WP_REST_Request('GET','/oi/v1/fiches/'.$ids[0]));$assert($denied->get_status()===403,'URL directe refusée');

 $r=OI_Credits::unlock($user,'FICHE',$ids[0]);$assert($r['consumed']===true,'Premier débit fiche');
 $r=OI_Credits::unlock($user,'FICHE',$ids[0]);$assert($r['consumed']===false && OI_Credits::balances($user)['FICHE']===4,'Relecture sans débit');
 $assert(OI_Model::can_read($user,$ids[0])&&!OI_Offer::can_access($user,'QCM',$ids[0]),'Droits Fiche et QCM séparés');
 $read=rest_do_request(new WP_REST_Request('GET','/oi/v1/fiches/'.$ids[0]));$assert($read->get_status()===200 && $read->get_data()['quiz']===[],'La fiche seule ne transmet pas le QCM');
 $qcm=rest_do_request(new WP_REST_Request('GET','/oi/v1/qcm/'.$ids[0]));$assert($qcm->get_status()===403,'Série verrouillée même après accès fiche');

 OI_Credits::unlock($user,'QCM',$ids[1]);OI_Credits::unlock($user,'QCM',$ids[1]);
 $assert(OI_Credits::balances($user)['QCM']===4 && !OI_Model::can_read($user,$ids[1]),'QCM permanent sans ouvrir fiche');
 $assert(OI_Revision::progress($user)['total']===1,'Une série QCM seule ne gonfle pas le nombre de fiches');
 $qcm=rest_do_request(new WP_REST_Request('GET','/oi/v1/qcm/'.$ids[1]));$data=$qcm->get_data();$assert($qcm->get_status()===200&&!isset($data['content'])&&!isset($data['quiz'][0]['correct']),'QCM autonome sans fiche ni réponses correctes');

 for($i=1;$i<5;$i++)OI_Credits::unlock($user,'FICHE',$ids[$i]);
 $assert(is_wp_error(OI_Credits::unlock($user,'FICHE',$ids[5]))&&!OI_Model::can_read($user,$ids[5]),'Solde nul : accès refusé');
 $r=OI_Credits::grant($user,'FICHE',2,'campaign:test','Promotion',gmdate('Y-m-d H:i:s',time()+3600));$assert($r===true,'Attribution promotionnelle');
 $assert(OI_Credits::grant($user,'FICHE',2,'campaign:test','Promotion')===false,'Campagne idempotente');
 $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_grants SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE user_id=%d AND source_key='campaign:test'",$user));
 $assert(OI_Credits::balances($user)['FICHE']===0,'Expiration effective');
 $request=wp_generate_uuid4();$reservation=OI_Credits::reserve_ai($user,$request);
 $assert($reservation['state']==='reserved' && OI_Credits::balances($user)['IA']===4,'Réservation atomique IA');
 $assert(OI_Credits::reserve_ai($user,$request)['duplicate']===true,'Rejeu de requête sans nouveau débit');
 $assert(is_wp_error(OI_Credits::reserve_ai($user,wp_generate_uuid4())),'Une seule requête IA en vol');
 $assert(OI_Credits::settle_ai($user,$reservation['key'],false)===true && OI_Credits::balances($user)['IA']===5,'Échec : crédit restitué');
 OI_Credits::settle_ai($user,$reservation['key'],false);$assert(OI_Credits::balances($user)['IA']===5,'Restitution unique');
 $r=OI_Credits::reserve_ai($user,wp_generate_uuid4());OI_Credits::settle_ai($user,$r['key'],true);
 $assert(OI_Credits::balances($user)['IA']===4,'Succès : un crédit consommé');
 $stale=OI_Credits::reserve_ai($user,wp_generate_uuid4());
 $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}oi_credit_operations SET updated_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 6 MINUTE) WHERE user_id=%d AND op_key=%s",$user,$stale['key']));
 $assert(OI_Credits::balances($user)['IA']===4,'Interruption prolongée : lecture du solde restitue le crédit');
 $assert(OI_Credits::settle_ai($user,$stale['key'],true)===false,'Réponse tardive ne débite pas une réservation remboursée');
 $failure=OI_Credits::transaction($user,function()use($user){OI_Credits::grant_locked($user,'IA',100,'rollback','Test');throw new RuntimeException('échec injecté');});
 $assert(is_wp_error($failure)&&OI_Credits::balances($user)['IA']===4,'Échec transactionnel : aucun crédit ajouté');
 update_user_meta($user,'oi_packs',[$pack]);$assert(OI_Offer::premium($user)&&OI_Model::can_read($user,$ids[5]),'Premium : accès immédiat');
 $r=OI_Credits::unlock($user,'FICHE',$ids[5]);$assert($r['consumed']===false,'Premium : aucun crédit consommé');
 $assert(count(OI_Credits::history($user))>=10,'Journal des motifs disponible');
 echo "Crédits : $checks assertions réussies\n";
} finally {
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $u){foreach(['oi_wallets','oi_credit_grants','oi_credit_operations','oi_unlocks']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$u]);wp_delete_user($u);}foreach($posts as$p)wp_delete_post($p,true);
 if($old===false)delete_option('oi_premium_pack');else update_option('oi_premium_pack',$old);
}

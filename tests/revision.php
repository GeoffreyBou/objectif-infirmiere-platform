<?php
$GLOBALS['revision_checks']=0;
function revision_check($condition,$message) {if(!$condition)throw new RuntimeException($message);$GLOBALS['revision_checks']++;}
$user=wp_insert_user(['user_login'=>'rev_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$other=wp_insert_user(['user_login'=>'rev_other_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$pack=(int)get_option('oi_demo_pack');$fiche=OI_Model::ids(get_post_meta($pack,'oi_fiches',true))[0];update_user_meta($user,'oi_packs',[$pack]);
try {
 wp_set_current_user($user);
 $r=new WP_REST_Request('POST','/oi/v1/fiches/'.$fiche.'/state');$r->set_param('field','favorite');$r->set_param('value',true);revision_check(rest_do_request($r)->get_status()===200,'Favori enregistré');
 wp_cache_delete($user,'user_meta');revision_check(OI_Revision::state($user,$fiche)['favorite']===true,'Favori persistant');
 $r->set_param('field','revised');rest_do_request($r);revision_check(OI_Revision::progress($user)['revised']===1,'Progression compte révision');
 $r->set_param('field','oi_packs');revision_check(rest_do_request($r)->get_status()===400,'Champ arbitraire rejeté');
 $r->set_param('field','favorite');$r->set_param('value','false');revision_check(rest_do_request($r)->get_status()===400,'Valeur non booléenne rejetée');
 $get=rest_do_request('/oi/v1/fiches/'.$fiche)->get_data();revision_check(!isset($get['quiz'][0]['correct']),'Correction non divulguée à la lecture');revision_check(OI_Revision::progress($user)['viewed']===1,'Consultation enregistrée');
 $q=new WP_REST_Request('POST','/oi/v1/fiches/'.$fiche.'/quiz');$q->set_param('answers',[[0]]);$result=rest_do_request($q)->get_data();revision_check($result['score']===1,'Correction côté serveur');revision_check(OI_Revision::progress($user)['quizzes']===1,'Quiz enregistré');
 $q->set_param('answers',[[999]]);revision_check(rest_do_request($q)->get_status()===400,'Choix hors bornes refusé');
 wp_set_current_user($other);revision_check(rest_do_request($q)->get_status()===403,'Quiz interdit autre étudiant');revision_check(OI_Revision::state($other,$fiche)['favorite']===false,'Favoris isolés');
 wp_set_current_user(0);revision_check(rest_do_request($r)->get_status()===401,'Modification anonyme refusée');
 echo 'Révision : '.$GLOBALS['revision_checks']." assertions réussies\n";
}finally{require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);wp_delete_user($other);}

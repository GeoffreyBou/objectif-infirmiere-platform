<?php
$GLOBALS["count"]=0;
function check($condition,$message) { global $count; if(!$condition) throw new RuntimeException($message); $count++; }
$user=wp_insert_user(['user_login'=>'exp_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$pack=(int)get_option('oi_demo_pack');
try {
 wp_set_current_user($user);
 $empty=rest_do_request('/oi/v1/fiches');check($empty->get_status()===200 && $empty->get_data()['total']===0,'Catalogue fermé sans droits');
 update_user_meta($user,'oi_packs',[$pack]);
 $list=rest_do_request('/oi/v1/fiches');check($list->get_status()===200 && $list->get_data()['total']===10,'10 fiches visibles');
 $id=$list->get_data()['items'][0]['id'];$fiche=rest_do_request('/oi/v1/fiches/'.$id);check($fiche->get_status()===200 && str_contains($fiche->get_data()['content'],'Vigilance IDE'),'Contenu complet autorisé');
 $search=new WP_REST_Request('GET','/oi/v1/fiches');$search->set_param('q','potassium');$found=rest_do_request($search)->get_data();check($found['total']>=3,'Recherche dans contenu');
 $request=new WP_REST_Request('GET','/oi/v1/fiches');$request->set_param('semestre',$list->get_data()['semesters'][0]['id']);check(rest_do_request($request)->get_data()['total']===5,'Filtre semestre');
 wp_set_current_user(0);check(rest_do_request('/oi/v1/fiches/'.$id)->get_status()===401,'REST anonyme interdit');
 check(rest_do_request('/wp/v2/oi_fiche')->get_status()===404,'REST standard inaccessible');
 $public=new WP_Query(['s'=>'Furosémide']);check(!in_array($id,wp_list_pluck($public->posts,'ID'),true),'Pas de fuite recherche publique');
 echo "Expérience : ".$GLOBALS["count"]." assertions réussies\n";
} finally {require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);}

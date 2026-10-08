<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$GLOBALS['library_count']=0;function library_check($ok,$message){if(!$ok)throw new RuntimeException($message);$GLOBALS['library_count']++;}
function library_request($url){$parts=explode('?', $url, 2);$r=new WP_REST_Request('GET',$parts[0]);if(isset($parts[1])){parse_str($parts[1],$params);$r->set_query_params($params);}return rest_do_request($r);}
$user=wp_insert_user(['user_login'=>'library_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$other=wp_insert_user(['user_login'=>'library_other_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
$posts=[];$terms=[];$pack=OI_Offer::pack();$original=get_post_meta($pack,'oi_fiches',true);
try{
 foreach(['oi_enseignement'=>['Test A1','Test B1'],'oi_theme'=>['Test thème 1','Test thème 2']] as $tax=>$names)foreach($names as $name)$terms[$tax][]=(int)wp_insert_term($name.' '.wp_generate_password(6,false),$tax)['term_id'];
 for($i=0;$i<23;$i++){
  $id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>sprintf('Bibliothèque test %02d',$i)]);$posts[]=$id;
  wp_set_object_terms($id,[$terms['oi_enseignement'][$i===22?1:0]],'oi_enseignement');wp_set_object_terms($id,[$terms['oi_theme'][$i===21?1:0]],'oi_theme');
 }
 update_post_meta($pack,'oi_fiches',$posts);wp_set_current_user($user);
 $root=library_request('/oi/v1/library')->get_data();library_check(count(array_filter($root['folders'],fn($f)=>in_array($f['id'],$terms['oi_enseignement'],true)))===2,'Deux UE distinctes');library_check(array_values(array_filter($root['folders'],fn($f)=>$f['id']===$terms['oi_enseignement'][0]))[0]['count']===22,'Compteur indépendant de la pagination');
 $u=$terms['oi_enseignement'][0];$t=$terms['oi_theme'][0];
 $themes=library_request('/oi/v1/library?enseignement='.$u)->get_data();library_check(count($themes['folders'])===2,'Thèmes limités à l’UE');
 $leaf=library_request('/oi/v1/fiches?enseignement='.$u.'&theme='.$t)->get_data();library_check($leaf['total']===21&&count($leaf['items'])===20,'Pagination au troisième niveau');
 $search=library_request('/oi/v1/fiches?q='.rawurlencode(get_term($u,'oi_enseignement')->name))->get_data();library_check($search['total']===22,'Recherche par intitulé UE');
 $next=library_request('/oi/v1/fiches?enseignement='.$u.'&theme='.$t.'&page=2')->get_data();library_check(count($next['items'])===1,'Deuxième page accessible');
 library_check(library_request('/oi/v1/fiches/'.$posts[0])->get_status()===403,'Dossier ne débloque pas de contenu');
 library_check(library_request('/oi/v1/recent')->get_data()===[],'Aucune lecture au simple parcours');
 update_user_meta($user,'oi_packs',[$pack]);
 library_request('/oi/v1/fiches/'.$posts[0]);library_request('/oi/v1/fiches/'.$posts[1]);library_request('/oi/v1/fiches/'.$posts[0]);
 $recent=library_request('/oi/v1/recent')->get_data();library_check(array_column($recent,'id')===[$posts[0],$posts[1]],'Réouverture en tête sans doublon même dans la même seconde');
 library_check(!isset($recent[0]['content']),'Historique sans contenu protégé');
 wp_set_current_user($other);library_check(library_request('/oi/v1/recent')->get_data()===[],'Historique isolé par compte');
 wp_set_current_user($user);wp_update_post(['ID'=>$posts[0],'post_status'=>'draft']);library_check(array_column(library_request('/oi/v1/recent')->get_data(),'id')===[$posts[1]],'Fiche retirée non exposée');
 delete_user_meta($user,'oi_packs');library_check(library_request('/oi/v1/recent')->get_data()===[],'Perte d’accès respectée');
 wp_set_current_user(0);library_check(library_request('/oi/v1/library')->get_status()===401,'Dossiers privés');library_check(library_request('/oi/v1/recent')->get_status()===401,'Historique privé');
 echo 'Bibliothèque : '.$GLOBALS['library_count']." assertions réussies\n";
}finally{
 update_post_meta($pack,'oi_fiches',$original);foreach($posts as $id)wp_delete_post($id,true);foreach($terms as $tax=>$ids)foreach($ids as $id)wp_delete_term($id,$tax);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);wp_delete_user($other);
}

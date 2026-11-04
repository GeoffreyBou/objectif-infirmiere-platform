<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$GLOBALS['recommendation_checks']=0;function recommend_check($value,$message){if(!$value)throw new RuntimeException($message);$GLOBALS['recommendation_checks']++;}
$users=[];$posts=[];$terms=[];$pack=OI_Offer::pack();$oldPack=get_post_meta($pack,'oi_fiches',true);
try{
 foreach(['student','other'] as $kind)$users[]=wp_insert_user(['user_login'=>'recommend_'.$kind.'_'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);[$user,$other]=$users;wp_set_current_user($user);
 foreach(['oi_enseignement','oi_enseignement','oi_theme','oi_theme','oi_theme'] as $taxonomy){$t=wp_insert_term('Test recommandations '.wp_generate_password(8,false),$taxonomy);$terms[]=[$taxonomy,(int)$t['term_id']];}
 [$unit,$otherUnit,$theme,$otherTheme,$outsideTheme]=array_column($terms,1);
 $make=function($code,$title,$u,$t,$status='publish')use(&$posts){$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>$status,'post_title'=>$title,'post_content'=>'PROTECTED_RECOMMENDATION_CONTENT']);$posts[]=$id;update_post_meta($id,'oi_source_code',$code);wp_set_object_terms($id,[$u],'oi_enseignement');wp_set_object_terms($id,[$t],'oi_theme');return $id;};
 $previous=$make('B1-TST-001','Dernier titre alphabétique',$unit,$theme);
 $current=$make('B1-TST-002','Fiche actuelle',$unit,$theme);
 $next=$make('B1-TST-003','Zèbre',$unit,$theme);
 $later=$make('B1-TST-010','Abeille',$unit,$theme);
 $sameUnit=$make('B1-XYZ-001','Autre thème',$unit,$otherTheme);
 $outside=$make('C1-TST-001','Objectif hors UE',$otherUnit,$outsideTheme);
 $draft=$make('B1-TST-004','Brouillon',$unit,$theme,'draft');
 $hidden=$make('B1-TST-005','Hors catalogue',$unit,$theme);
 update_post_meta($pack,'oi_fiches',array_merge(OI_Model::ids($oldPack),array_diff($posts,[$hidden])));
 $related=fn()=>array_column(OI_API::related($current),'ID');
 recommend_check($related()===[$next,$later,$previous,$sameUnit],'Without a goal: stable code sequence, then same UE; no unrelated course');
 update_user_meta($user,'oi_revision',[$next=>['revised'=>true]]);
 recommend_check($related()===[$later,$previous,$next,$sameUnit],'Pending courses precede previously revised courses in the same theme');
 $goal=wp_generate_uuid4();$archived=wp_generate_uuid4();
 $store=['active'=>$goal,'goals'=>[$goal=>['id'=>$goal,'title'=>'Objectif actif','version'=>1,'selected'=>[$outside,$later,$next,$draft,$hidden,$current],'done'=>[$later=>time()]],$archived=>['selected'=>[$sameUnit],'done'=>[]]]];
 update_user_meta($user,'oi_goals',$store);
 recommend_check($related()===[$outside,$next,$previous,$sameUnit],'Active unfinished goal first across UEs, ignoring old revision flags and excluding completed/draft/unlisted/current courses');
 recommend_check(!OI_Offer::can_access($user,'FICHE',$outside),'Recommendations do not unlock locked goal content');
 $summary=OI_API::summary(get_post($outside));recommend_check(!$summary['access']['FICHE']&&!str_contains(wp_json_encode($summary),'PROTECTED_RECOMMENDATION_CONTENT'),'Locked recommendation exposes metadata only');
 wp_set_current_user($other);recommend_check($related()===[$next,$later,$previous,$sameUnit],'Recommendations and completion remain isolated by user');wp_set_current_user($user);
 $store['goals'][$goal]['done']=array_fill_keys($store['goals'][$goal]['selected'],time());update_user_meta($user,'oi_goals',$store);
 recommend_check($related()===[$previous,$sameUnit],'Finished objective falls back to remaining related courses without reintroducing completed goal courses');
 delete_user_meta($user,'oi_goals');delete_user_meta($user,'oi_revision');wp_set_object_terms($current,[],'oi_theme');
 recommend_check(count($related())===4&&!in_array($outside,$related(),true),'Missing theme falls back to the same UE');
 wp_set_object_terms($current,[],'oi_enseignement');recommend_check($related()===[],'No taxonomy means no unrelated arbitrary suggestions');
 // The real reader endpoint uses the same ranking and does not spend credits.
 wp_set_object_terms($current,[$unit],'oi_enseignement');wp_set_object_terms($current,[$theme],'oi_theme');
 update_user_meta($user,'oi_packs',[$pack]);$before=OI_Credits::balances($user);
 $response=rest_do_request('/oi/v1/fiches/'.$current);recommend_check($response->get_status()===200,'Reader endpoint remains accessible');
 recommend_check(array_column($response->get_data()['related'],'id')===[$next,$later,$previous,$sameUnit],'Reader returns the ranked recommendations');
 recommend_check(OI_Credits::balances($user)===$before,'Reading recommendations costs no credits');
 echo "Suggestions : ".$GLOBALS['recommendation_checks']." assertions réussies\n";
}finally{
 update_post_meta($pack,'oi_fiches',$oldPack);foreach($posts as $id)wp_delete_post($id,true);foreach($terms as [$taxonomy,$id])wp_delete_term($id,$taxonomy);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id)wp_delete_user($id);
}

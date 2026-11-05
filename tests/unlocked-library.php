<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
global $wpdb;$users=[];$posts=[];$checks=0;$oldPack=get_option('oi_premium_pack',false);
$assert=function($v,$m)use(&$checks){if(!$v)throw new RuntimeException($m);$checks++;};
try{
 foreach(['one','two']as$tag){$login='owned_'.wp_generate_password(10,false);$u=wp_insert_user(['user_login'=>$login,'user_email'=>$login.'@example.invalid','user_pass'=>wp_generate_password(32),'role'=>'oi_etudiant']);$users[]=$u;update_user_meta($u,'oi_email_verified',gmdate('c'));OI_Credits::welcome($u);}
 [$one,$two]=$users;wp_set_current_user($one);$ids=[];
 for($i=1;$i<=24;$i++){$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>$i===24?'draft':'publish','post_title'=>'Owned fixture '.sprintf('%02d',$i)]);$posts[]=$id;$ids[]=$id;}
 $pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Owned catalogue']);$posts[]=$pack;update_post_meta($pack,'oi_fiches',$ids);update_option('oi_premium_pack',$pack);
 OI_Credits::grant($one,'FICHE',30,'owned-test','Test');for($i=0;$i<21;$i++)OI_Credits::unlock($one,'FICHE',$ids[$i]);
 OI_Credits::unlock($two,'FICHE',$ids[21]);$wpdb->insert($wpdb->prefix.'oi_unlocks',['user_id'=>$one,'kind'=>'QCM','content_id'=>$ids[22],'created_at'=>gmdate('Y-m-d H:i:s')]);$wpdb->insert($wpdb->prefix.'oi_unlocks',['user_id'=>$one,'kind'=>'FICHE','content_id'=>$ids[23],'created_at'=>gmdate('Y-m-d H:i:s')]);
 $list=function($params=[]) { $r=new WP_REST_Request('GET','/oi/v1/fiches');foreach($params as$k=>$v)$r->set_param($k,$v);return OI_API::listing($r);};
 $before=OI_Credits::balances($one);$all=$list();$assert((int)$all['total']===23,'Catalogue conserve fiches verrouillées');$owned=$list(['unlocked'=>'1']);$assert((int)$owned['total']===21&&(int)$owned['pages']===2&&count($owned['items'])===20,'Filtre et pagination côté serveur');$assert(count($list(['unlocked'=>'1','page'=>2])['items'])===1,'Deuxième page');$assert(count($list(['unlocked'=>'1','q'=>'fixture 01'])['items'])===1,'Recherche dans ses fiches');$assert((int)$list(['unlocked'=>'1','q'=>'fixture 23'])['total']===0,'QCM seul exclu');$assert((int)$list(['unlocked'=>'1','q'=>'fixture 22'])['total']===0,'Déblocage autre compte exclu');$assert(OI_Credits::balances($one)===$before,'Consultation du filtre sans débit');
 wp_set_current_user($two);$owned=$list(['unlocked'=>true]);$assert((int)$owned['total']===1&&$owned['items'][0]['id']===$ids[21],'Isolation compte');update_user_meta($two,'oi_packs',[$pack]);$assert((int)$list(['unlocked'=>true])['total']===23,'Accès pack existant respecté');
 echo "Bibliothèque débloquée : $checks assertions OK\n";
}finally{wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as$id){foreach(['oi_credit_grants','oi_credit_operations','oi_wallets','oi_unlocks']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$id]);wp_delete_user($id);}foreach($posts as$id)wp_delete_post($id,true);if($oldPack===false)delete_option('oi_premium_pack');else update_option('oi_premium_pack',$oldPack);}

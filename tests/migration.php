<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
require '/migration/oi-migration.php';
$checks=0;$assert=function($v,$message)use(&$checks){if(!$v)throw new RuntimeException($message);$checks++;};
$before=[];foreach(['oi_migration_pending','oi_migration_before','page_on_front','show_on_front'] as $name)$before[$name]=get_option($name,false);
$admin=wp_insert_user(['user_login'=>'migration_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator']);
$legacy=wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_title'=>'Article historique test','post_content'=>'Contenu SEO conservé']);update_post_meta($legacy,'_yoast_wpseo_title','Titre SEO historique');
$ids=[];$source=random_int(1000000,9000000);
$send=function($records){$r=new WP_REST_Request('POST');$r->set_param('source','https://app-dev.objectif-infirmiere.fr');$r->set_param('records',$records);return OI_Migration::import_batch($r);};
$row=['source_id'=>$source,'type'=>'oi_fiche','title'=>'Fiche migration test','content'=>'<h2>Repère</h2><p>Contenu pédagogique.</p>','meta'=>['oi_source_code'=>'TEST-MIG-'.$source,'oi_quiz'=>serialize([['question'=>'Test','options'=>['Oui','Non'],'correct'=>[0],'explanation'=>'Explication.']])],'terms'=>[]];
try{
 wp_set_current_user(0);$assert(!OI_Migration::allowed(),'Import anonyme interdit');wp_set_current_user($admin);$assert(OI_Migration::allowed(),'Administrateur autorisé');
 OI_Migration::activate();update_option('page_on_front',$legacy);OI_App::install_pages();$assert((int)get_option('page_on_front')===$legacy,'Activation ne remplace pas l’accueil pendant la préparation');
 $result=$send([$row]);$assert(!is_wp_error($result),'Lot valide importé');$id=$result['records'][0]['id'];$ids[]=$id;
 $assert(get_post_meta($id,'oi_quiz',true)[0]['correct']===[0],'Structure QCM conservée');
 $assert(get_post_field('post_content',$legacy)==='Contenu SEO conservé'&&get_post_meta($legacy,'_yoast_wpseo_title',true)==='Titre SEO historique','Article et SEO existants préservés');
 $again=$send([$row]);$assert($again['records'][0]['id']===$id&&$again['records'][0]['reused'],'Reprise sans doublon');
 $changed=$row;$changed['title']='Titre changé';$assert(is_wp_error($send([$changed])),'Source modifiée non écrasée automatiquement');
 $object=$row;$object['source_id']++;$object['meta']['oi_docx_images']=serialize((object)['unsafe'=>true]);$assert(is_wp_error($send([$object])),'Objet sérialisé refusé');
 $secret=$row;$secret['source_id']++;$secret['meta']['oi_ai_file']='file_from_dev';$assert(is_wp_error($send([$secret])),'Ancienne indexation IA non importable');
 $ordinary=$row;$ordinary['type']='post';$assert(is_wp_error($send([$ordinary])),'Import ne peut pas modifier les articles');
 $first=$row;$first['source_id']+=10;$first['meta']['oi_source_code'].='B';$bad=$row;$bad['source_id']+=11;$bad['meta']['forbidden']='value';
 $assert(is_wp_error($send([$first,$bad])),'Lot partiellement invalide rejeté');
 $assert(!get_posts(['post_type'=>'oi_fiche','post_status'=>'any','meta_key'=>'oi_migration_source','meta_value'=>'app-dev:'.$first['source_id'],'fields'=>'ids']),'Lot invalide intégralement annulé');
 echo "Migration sélective : $checks assertions réussies.\n";
}finally{
 foreach($ids as $id)wp_delete_post($id,true);wp_delete_post($legacy,true);
 foreach($before as $name=>$value){if($value===false)delete_option($name);else update_option($name,$value);}
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($admin);
}

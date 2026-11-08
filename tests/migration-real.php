<?php
// Optional local check against private authenticated exports. No secrets or content are printed.
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
require '/migration/oi-migration.php';
$old=get_option('oi_migration_pending',false);$user=get_current_user_id();$ids=[];
$admin=wp_insert_user(['user_login'=>'migration_real_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator']);
try{
 wp_set_current_user($admin);update_option('oi_migration_pending',true);
 foreach(['oi_fiche','oi_fiche_art'] as $type){
  $files=glob('/batches/'.$type.'-*.json');if(!$files)throw new RuntimeException('Private fixtures absent');
  $data=json_decode(file_get_contents($files[0]),true,512,JSON_THROW_ON_ERROR);
  $row=$data['records'][0];$row['source_id']=random_int(10000000,20000000);
  // Use an isolated source code even if the local catalogue contains real review examples.
  $row['meta'][$type==='oi_fiche'?'oi_source_code':'oi_art_code']='TEST-REAL-'.$row['source_id'];
  $r=new WP_REST_Request('POST');$r->set_param('source',$data['source']);$r->set_param('records',[$row]);
  $result=OI_Migration::import_batch($r);if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
  $id=$result['records'][0]['id'];$ids[]=$id;
  if(get_post_field('post_content',$id)!==$row['content'])throw new RuntimeException('HTML modified');
  foreach($row['meta'] as $key=>$raw){$expected=is_serialized($raw)?unserialize($raw,['allowed_classes'=>false]):$raw;if(get_post_meta($id,$key,true)!==$expected)throw new RuntimeException('Metadata mismatch: '.$key);}
  echo $type.": roundtrip réel HTML/métadonnées/images vérifié.\n";
 }
}finally{
 foreach($ids as $id)wp_delete_post($id,true);
 if($old===false)delete_option('oi_migration_pending');else update_option('oi_migration_pending',$old);
 wp_set_current_user($user);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($admin);
}

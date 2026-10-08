<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
$action=$args[0]??'';
if($action==='setup'){
 $login='qcm_browser_'.wp_generate_password(10,false);$password=wp_generate_password(32,false);$admin=wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'user_email'=>$login.'@example.invalid','role'=>'administrator']);$studentLogin='qcm_browser_'.wp_generate_password(10,false);$studentPass=wp_generate_password(32,false);$student=wp_insert_user(['user_login'=>$studentLogin,'user_pass'=>$studentPass,'user_email'=>$studentLogin.'@example.invalid','role'=>'oi_etudiant']);wp_set_current_user($admin);
 $unit=get_term_by('slug','programme-2026-b1','oi_enseignement')->term_id;$theme=get_term_by('slug','programme-2026-b1-01','oi_theme')->term_id;$fiches=[];
 foreach(['B1-TST-981','B1-TST-982'] as $code){$id=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Atelier QCM '.$code,'post_author'=>$admin,'post_content'=>'Contenu de test local']);$fiches[]=$id;update_post_meta($id,'oi_source_code',$code);wp_set_object_terms($id,[$unit],'oi_enseignement');wp_set_object_terms($id,[$theme],'oi_theme');}
 update_post_meta($fiches[1],'oi_quiz',[['code'=>'B1-TST-982-Q001','question'=>'Question du second cours','options'=>['Réponse exacte','Autre réponse'],'correct'=>[0],'explanation'=>'Explication du second cours.']]);
 $pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Pack privé de test QCM','post_author'=>$admin]);update_post_meta($pack,'oi_fiches',$fiches);update_user_meta($student,'oi_packs',[$pack]);
 $tmp=tempnam(sys_get_temp_dir(),'qcm');copy(OI_DIR.'assets/modele-qcm.xlsx',$tmp);$zip=new ZipArchive();$zip->open($tmp);$xml=str_replace('B1-UGR-002','B1-TST-981',$zip->getFromName('xl/worksheets/sheet1.xml'));$zip->addFromString('xl/worksheets/sheet1.xml',$xml);$zip->close();$bytes=base64_encode(file_get_contents($tmp));unlink($tmp);
 echo wp_json_encode(['id'=>$admin,'login'=>$login,'password'=>$password,'student'=>['id'=>$student,'login'=>$studentLogin,'password'=>$studentPass],'file'=>$bytes,'fiches'=>$fiches,'theme'=>$theme,'unit'=>$unit]);return;
}
$admin=absint($args[1]??0);$student=absint($args[2]??0);
foreach([$admin,$student] as $user){$u=get_userdata($user);if(!$u||!str_starts_with($u->user_login,'qcm_browser_'))throw new RuntimeException('Fixture attendue');foreach(get_posts(['post_type'=>['oi_fiche','oi_pack','oi_qcm_import','oi_qcm_history','oi_quiz_session'],'post_status'=>['publish','draft','private'],'author'=>$user,'numberposts'=>-1,'fields'=>'ids']) as $post)wp_delete_post($post,true);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);}echo "Fixtures QCM nettoyées.\n";

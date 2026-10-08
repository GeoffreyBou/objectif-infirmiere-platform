<?php
$projects=[];
foreach (['iPhone','Android','tablette','desktop'] as $project) {
 $login='browser_'.wp_generate_password(12,false);$password=wp_generate_password(32,false);
 $id=wp_insert_user(['user_login'=>$login,'user_email'=>$login.'@example.invalid','user_pass'=>$password,'display_name'=>'Étudiante Démo','role'=>'oi_etudiant']);
 if(is_wp_error($id))throw new RuntimeException('Création utilisateur test impossible');
 update_user_meta($id,'oi_email_verified',gmdate('c'));OI_Credits::welcome($id);
 update_user_meta($id,'oi_packs',[(int)get_option('oi_demo_pack')]);
 $projects[$project]=['id'=>$id,'login'=>$login,'password'=>$password];
}
echo wp_json_encode($projects['desktop']+['projects'=>$projects]);

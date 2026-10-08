<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Test local uniquement.');
$user=wp_insert_user(['user_login'=>'race_'.wp_generate_password(16,false),'user_pass'=>wp_generate_password(32),'role'=>'oi_etudiant']);update_user_meta($user,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user);
$ids=OI_Offer::catalogue();if(count($ids)<6)throw new RuntimeException('Six contenus de démo nécessaires.');
echo wp_json_encode(['user'=>$user,'ids'=>array_slice($ids,0,6)]);

<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Test local uniquement.');
[$user,$id,$kind]=$args;$user=absint($user);$id=absint($id);
if(!str_starts_with(get_userdata($user)->user_login,'race_'))throw new RuntimeException('Compte de course invalide.');
$result=$kind==='IA'?OI_Credits::reserve_ai($user,wp_generate_uuid4()):OI_Credits::unlock($user,$kind,$id);
echo wp_json_encode(is_wp_error($result)?['code'=>$result->get_error_code()]:$result);

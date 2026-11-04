<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
if(($args[0]??'')==='setup'){
 $prefix='uat_'.wp_generate_password(8,false);$result=['prefix'=>$prefix];
 foreach(['admin'=>'administrator','student'=>'oi_etudiant','second'=>'oi_etudiant']as$key=>$role){$login=$prefix.'_'.$key;$password=wp_generate_password(32,false);$id=wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'user_email'=>$login.'@example.invalid','display_name'=>$prefix.' '.($key==='admin'?'Gestionnaire':($key==='student'?'Camille Martin':'Léa Dupont')),'role'=>$role]);if(is_wp_error($id))throw new RuntimeException('Fixture');update_user_meta($id,'oi_email_verified',gmdate('c'));$result[$key]=['id'=>$id,'login'=>$login,'password'=>$password];}
 echo wp_json_encode($result);return;
}
global $wpdb;require_once ABSPATH.'wp-admin/includes/user.php';
foreach(array_slice($args,1)as$raw){$id=absint($raw);$u=get_userdata($id);if(!$u||!str_starts_with($u->user_login,'uat_'))throw new RuntimeException('Fixture attendue');foreach(['oi_user_actions','oi_credit_grants','oi_credit_operations','oi_wallets','oi_unlocks']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$id]);wp_delete_user($id);}echo "Fixtures utilisateurs nettoyées.\n";

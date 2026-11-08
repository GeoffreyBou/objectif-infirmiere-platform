<?php
$checks=0;
$assert=static function($condition,$message)use(&$checks){if(!$condition)throw new RuntimeException($message);$checks++;};
$names=['OI_STRIPE_SECRET_KEY'=>'stripe_api_key','OI_STRIPE_WEBHOOK_SECRET'=>'stripe_webhook_secret'];
$environment=[];foreach($names as $name=>$field){$environment[$name]=getenv($name);putenv($name);}
$before=[];foreach($names as $name=>$field){$before[$name]=get_option('oi_encrypted_'.strtolower($name),false);}
$admin=wp_insert_user(['user_login'=>'stripe_config_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator']);
$student=wp_insert_user(['user_login'=>'stripe_restricted_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'oi_etudiant']);
$api='rk_test_'.wp_generate_password(32,false);$hook='whsec_'.wp_generate_password(32,false);
try {
 wp_set_current_user(0);$assert(is_wp_error(OI_Stripe_Secrets::store(['stripe_api_key'=>$api])),'Anonymous configuration denied');
 wp_set_current_user($student);$assert(is_wp_error(OI_Stripe_Secrets::store(['stripe_api_key'=>$api])),'Student configuration denied');
 wp_set_current_user($admin);
 $assert(OI_Stripe_Secrets::store(['stripe_api_key'=>$api,'stripe_webhook_secret'=>$hook])===true,'Admin configuration saved');
 $assert(OI_Stripe_Secrets::get('OI_STRIPE_SECRET_KEY')===$api,'API key decrypts correctly');
 $assert(OI_Stripe_Secrets::get('OI_STRIPE_WEBHOOK_SECRET')===$hook,'Webhook secret decrypts correctly');
 $assert(OI_Stripe::secret('OI_STRIPE_SECRET_KEY')===$api&&OI_Stripe::configured(),'Encrypted fallback usable by payment code');
 $hook_option=get_option('oi_encrypted_oi_stripe_webhook_secret');delete_option('oi_encrypted_oi_stripe_webhook_secret');
 $assert(!OI_Stripe::configured(),'Missing webhook prevents payment availability');
 update_option('oi_encrypted_oi_stripe_webhook_secret',$hook_option,false);
 putenv('OI_STRIPE_SECRET_KEY=sk_live_fixtureServerKey');
 $assert(OI_Stripe::secret('OI_STRIPE_SECRET_KEY')==='sk_live_fixtureServerKey'&&!OI_Stripe::configured(),'Server configuration takes priority; live payments remain disabled');
 putenv('OI_STRIPE_SECRET_KEY');
 $stored=get_option('oi_encrypted_oi_stripe_secret_key');
 $assert(!str_contains(wp_json_encode($stored),$api),'No plaintext in persisted API option');
 global $wpdb;$autoload=$wpdb->get_var("SELECT autoload FROM {$wpdb->options} WHERE option_name='oi_encrypted_oi_stripe_secret_key'");
 $assert(in_array($autoload,['no','off'],true),'Encrypted option not autoloaded');
 $assert(OI_Stripe_Secrets::get('OI_OPENAI_API_KEY')==='','Only Stripe keys supported');
 $assert(OI_Stripe_Secrets::store(['stripe_api_key'=>'','stripe_webhook_secret'=>''])===true&&get_option('oi_encrypted_oi_stripe_secret_key')===$stored,'Empty form preserves keys');
 $assert(is_wp_error(OI_Stripe_Secrets::store(['stripe_api_key'=>'sk_live_'.wp_generate_password(32,false)])),'Live key rejected');
 $assert(get_option('oi_encrypted_oi_stripe_secret_key')===$stored,'Rejected key leaves configuration intact');
 $assert(is_wp_error(OI_Stripe_Secrets::store(['stripe_api_key'=>['unexpected']])),'Array input rejected');
 $assert(is_wp_error(OI_Stripe_Secrets::store(['stripe_api_key'=>'sk_test_'.wp_generate_password(32,false),'stripe_webhook_secret'=>'bad'])),'Validate both before mutation');
 $assert(get_option('oi_encrypted_oi_stripe_secret_key')===$stored,'Invalid second field does not replace first');
 ob_start();OI_Stripe_Secrets::form();$form=ob_get_clean();
 $assert(!str_contains($form,$api)&&!str_contains($form,$hook),'Secrets absent from rendered form');
 $assert(substr_count($form,'type="password"')===2&&str_contains($form,'name="_wpnonce"'),'Write-only password fields and CSRF nonce');
 $swapped=get_option('oi_encrypted_oi_stripe_webhook_secret');update_option('oi_encrypted_oi_stripe_secret_key',$swapped,false);
 $assert(OI_Stripe_Secrets::get('OI_STRIPE_SECRET_KEY')==='','Purpose-bound encryption rejects swapped options');
 $bad=$stored;$bad['cipher']=base64_encode('tampered');update_option('oi_encrypted_oi_stripe_secret_key',$bad,false);
 $assert(OI_Stripe_Secrets::get('OI_STRIPE_SECRET_KEY')==='','Tampered ciphertext rejected');
 update_option('oi_encrypted_oi_stripe_secret_key',$stored,false);
 $assert(OI_Stripe_Secrets::get('OI_STRIPE_SECRET_KEY')===$api,'Original ciphertext remains readable');
 echo "Stripe secrets : $checks assertions réussies, aucune clé réelle utilisée.\n";
}finally{
 foreach($before as $name=>$value){$option='oi_encrypted_'.strtolower($name);if($value===false)delete_option($option);else update_option($option,$value,false);}
 foreach($environment as $name=>$value){if($value===false)putenv($name);else putenv($name.'='.$value);}
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($admin);wp_delete_user($student);
}

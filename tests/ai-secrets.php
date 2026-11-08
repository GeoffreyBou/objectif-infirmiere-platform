<?php
$checks=0;
$assert=static function($condition,$message)use(&$checks){if(!$condition)throw new RuntimeException($message);$checks++;};
$name='OI_OPENAI_API_KEY';$option='oi_encrypted_oi_openai_api_key';
$environment=getenv($name);putenv($name);$before=get_option($option,false);
$settings=[];foreach(['oi_ai_enabled','oi_vector_store','oi_ai_model'] as $setting)$settings[$setting]=get_option($setting);
$admin=wp_insert_user(['user_login'=>'ai_config_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator']);
$student=wp_insert_user(['user_login'=>'ai_restricted_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'oi_etudiant']);
$api='sk-proj-'.wp_generate_password(100,false).'_fixture-123';
try {
 wp_set_current_user(0);$assert(is_wp_error(OI_AI_Secrets::store(['openai_api_key'=>$api])),'Anonymous configuration denied');
 wp_set_current_user($student);$assert(is_wp_error(OI_AI_Secrets::store(['openai_api_key'=>$api])),'Student configuration denied');
 ob_start();OI_AI_Secrets::form();$assert(ob_get_clean()==='','Student cannot render configuration form');
 wp_set_current_user($admin);
 $assert(OI_AI_Secrets::store(['openai_api_key'=>$api])===true,'Admin configuration saved');
 $assert(OI_AI_Secrets::get($name)===$api&&OI_Stripe::secret($name)===$api,'AI requests can use decrypted key');
 $stored=get_option($option);$assert(!str_contains(wp_json_encode($stored),$api),'No plaintext persisted');
 global $wpdb;$autoload=$wpdb->get_var("SELECT autoload FROM {$wpdb->options} WHERE option_name='oi_encrypted_oi_openai_api_key'");
 $assert(in_array($autoload,['no','off'],true),'Secret not autoloaded');
 $assert(OI_AI_Secrets::get('OI_STRIPE_SECRET_KEY')==='','Other secret purposes rejected');
 $assert(OI_AI_Secrets::store(['openai_api_key'=>''])===true&&get_option($option)===$stored,'Empty input preserves key');
 foreach([['bad'],'invalid',"sk-proj-contains whitespace" , 'sk-'.str_repeat('x',1025)] as $invalid) {
  $assert(is_wp_error(OI_AI_Secrets::store(['openai_api_key'=>$invalid]))&&get_option($option)===$stored,'Invalid input rejected without replacing key');
 }
 ob_start();OI_AI_Secrets::form();$form=ob_get_clean();
 $assert(!str_contains($form,$api)&&str_contains($form,'type="password"')&&str_contains($form,'value=""')&&str_contains($form,'name="_wpnonce"'),'Form write-only with CSRF nonce');
 $bad=$stored;$bad['cipher']=base64_encode('tampered');update_option($option,$bad,false);
 $assert(OI_AI_Secrets::get($name)==='','Tampered ciphertext rejected');
 update_option($option,$stored,false);
 putenv($name.'=sk-server-fixture-priority');$assert(OI_Stripe::secret($name)==='sk-server-fixture-priority','Server environment takes priority');
 foreach($settings as $setting=>$value)$assert(get_option($setting)===$value,'Saving key leaves activation, model and sources unchanged');
 $assert(has_action('admin_post_oi_ai_secrets',[OI_AI_Secrets::class,'save'])!==false,'Authenticated save handler registered');
 $assert(!has_action('admin_post_nopriv_oi_ai_secrets'),'No public save handler');
 echo "AI secrets : $checks assertions réussies, aucune clé réelle utilisée.\n";
}finally{
 if($before===false)delete_option($option);else update_option($option,$before,false);
 if($environment===false)putenv($name);else putenv($name.'='.$environment);
 wp_set_current_user(0);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($admin);wp_delete_user($student);
}

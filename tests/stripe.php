<?php
$GLOBALS['stripe_checks']=0;
function stripe_check($condition,$message) { if(!$condition) throw new RuntimeException($message); $GLOBALS['stripe_checks']++; }
define('OI_STRIPE_SECRET_KEY','sk_test_'.wp_generate_password(20,false));
define('OI_STRIPE_WEBHOOK_SECRET','whsec_'.wp_generate_password(32,false));
$pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'TEST paiement']);
$fiche=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'TEST protégé']);
update_post_meta($pack,'oi_fiches',[$fiche]); update_post_meta($pack,'oi_price','price_testFixture');
$old_premium=get_option('oi_premium_pack',false);update_option('oi_premium_pack',$pack);
$email='test_'.strtolower(wp_generate_password(8,false)).'@example.invalid';
$user_id=wp_insert_user(['user_login'=>'stripe_'.wp_generate_password(16,false),'user_email'=>$email,'user_pass'=>wp_generate_password(32),'role'=>'oi_etudiant']);update_user_meta($user_id,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user_id);
$GLOBALS['stripe_session']=['id'=>'cs_test_'.wp_generate_password(15,false),'livemode'=>false,'mode'=>'payment','status'=>'complete','payment_status'=>'paid','metadata'=>['oi_pack_id'=>$pack,'oi_user_id'=>$user_id],'client_reference_id'=>(string)$user_id,'payment_intent'=>'pi_testFixture','customer_details'=>['email'=>$email],'customer'=>'cus_test','amount_total'=>5900,'currency'=>'eur'];
$GLOBALS['stripe_wrong_price']=false;
$transport=function($pre,$args,$url) {
 if(!str_starts_with($url,'https://api.stripe.com/v1/')) return $pre;
 $data=str_ends_with($url,'/line_items') ? ['data'=>[['price'=>['id'=>$GLOBALS['stripe_wrong_price']?'price_wrong':'price_testFixture'],'quantity'=>1]],'has_more'=>false] : $GLOBALS['stripe_session'];
 if(str_contains($url,'/prices/')) $data=['id'=>'price_testFixture','livemode'=>false,'active'=>true,'type'=>'one_time','unit_amount'=>5900,'currency'=>'eur'];
 if($args['method']==='POST') $data=['id'=>'cs_test_create','livemode'=>false,'url'=>'https://checkout.stripe.com/c/pay/cs_test_create'];
 return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200,'message'=>'OK']];
};
add_filter('pre_http_request',$transport,10,3);
$mail=function() {return true;}; add_filter('pre_wp_mail',$mail);
function event_request($signature=true,$timestamp=null) {
 $timestamp=$timestamp??time();
 $body=wp_json_encode(['id'=>'evt_test_'.wp_generate_password(10,false),'type'=>'checkout.session.completed','livemode'=>false,'data'=>['object'=>['id'=>$GLOBALS['stripe_session']['id']]]]);
 $r=new WP_REST_Request('POST','/oi/v1/stripe/webhook');$r->set_body($body);$r->set_header('stripe-signature','t='.$timestamp.',v1='.($signature?hash_hmac('sha256',$timestamp.'.'.$body,OI_STRIPE_WEBHOOK_SECRET):str_repeat('0',64))); return $r;
}
$user=get_userdata($user_id);
try {
 stripe_check(rest_do_request(event_request(false))->get_status()===400,'Falsification rejetée');
 stripe_check(rest_do_request(event_request(true,time()-1000))->get_status()===400,'Signature expirée rejetée');
 $GLOBALS['stripe_session']['payment_status']='unpaid';stripe_check(rest_do_request(event_request())->get_status()===400,'Non payé rejeté');stripe_check(!OI_Offer::premium($user_id)&&OI_Credits::balances($user_id)['IA']===5,'Paiement non confirmé : ni Premium ni bonus');
 $GLOBALS['stripe_session']['payment_status']='paid';$GLOBALS['stripe_wrong_price']=true;stripe_check(rest_do_request(event_request())->get_status()===400,'Prix falsifié rejeté');
 $GLOBALS['stripe_wrong_price']=false;
 $paid=rest_do_request(event_request());stripe_check($paid->get_status()===200,'Paiement accepté');
 $user=get_user_by('email',$email);stripe_check($user && in_array('oi_etudiant',$user->roles,true),'Compte étudiant existant conservé');stripe_check(OI_Model::can_read($user->ID,$fiche),'Droit attribué');stripe_check(OI_Credits::balances($user->ID)['IA']===105,'100 crédits ajoutés aux 5 restants');
 stripe_check(rest_do_request(event_request())->get_data()['duplicate']===true,'Rejeu idempotent');stripe_check(OI_Credits::balances($user->ID)['IA']===105,'Rejeu sans bonus doublonné');
 global $wpdb;
 stripe_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}oi_payments WHERE user_id=%d",$user->ID))===1,'Un paiement après rejeu');
 $GLOBALS['stripe_session']['id']='cs_test_'.wp_generate_password(15,false);stripe_check(rest_do_request(event_request())->get_status()===200,'Achat supplémentaire accepté');stripe_check(get_user_by('email',$email)->ID===$user->ID,'Pas de doublon de compte');stripe_check(OI_Credits::balances($user->ID)['IA']===105,'Deuxième achat sans nouveau bonus');
 $GLOBALS['stripe_session']['id']='cs_test_'.wp_generate_password(15,false);$GLOBALS['stripe_session']['livemode']=true;stripe_check(rest_do_request(event_request())->get_status()===400,'Session live rejetée');
 $r=new WP_REST_Request('POST','/oi/v1/checkout');$r->set_param('pack_id',$pack);stripe_check(rest_do_request($r)->get_status()===401,'Checkout anonyme interdit');
 wp_set_current_user($user_id);stripe_check(rest_do_request($r)->get_status()===409,'Acheteur déjà Premium : deuxième Checkout refusé');
 $wpdb->update($wpdb->prefix.'oi_payments',['status'=>'refunded'],['user_id'=>$user_id]);stripe_check(rest_do_request($r)->get_status()===200,'Checkout TEST créé pour un membre vérifié sans accès actif');
 $r->set_param('pack_id',999999);stripe_check(rest_do_request($r)->get_status()===400,'Pack inexistant rejeté');
 echo 'Stripe : '.$GLOBALS['stripe_checks']." assertions réussies (API et email simulés ; aucune transaction réelle)\n";
} finally {
 remove_filter('pre_http_request',$transport,10);remove_filter('pre_wp_mail',$mail);
 wp_set_current_user(0);if($old_premium===false)delete_option('oi_premium_pack');else update_option('oi_premium_pack',$old_premium);
 if($user) {foreach(['oi_credit_grants','oi_credit_operations','oi_wallets']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$user->ID]); $wpdb->delete($wpdb->prefix.'oi_payments',['user_id'=>$user->ID]);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user->ID); }
 wp_delete_post($pack,true);wp_delete_post($fiche,true);
}

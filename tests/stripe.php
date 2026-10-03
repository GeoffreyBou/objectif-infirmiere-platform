<?php
$GLOBALS['stripe_checks']=0;
function stripe_check($condition,$message) { if(!$condition) throw new RuntimeException($message); $GLOBALS['stripe_checks']++; }
define('OI_STRIPE_SECRET_KEY','sk_test_'.wp_generate_password(20,false));
define('OI_STRIPE_WEBHOOK_SECRET','whsec_'.wp_generate_password(32,false));
$pack=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'TEST paiement']);
$fiche=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'TEST protégé']);
update_post_meta($pack,'oi_fiches',[$fiche]); update_post_meta($pack,'oi_price','price_testFixture');
$email='test_'.strtolower(wp_generate_password(8,false)).'@example.invalid';
$GLOBALS['stripe_session']=['id'=>'cs_test_'.wp_generate_password(15,false),'livemode'=>false,'mode'=>'payment','status'=>'complete','payment_status'=>'paid','metadata'=>['oi_pack_id'=>$pack],'customer_details'=>['email'=>$email],'customer'=>'cus_test','amount_total'=>4900,'currency'=>'eur'];
$GLOBALS['stripe_wrong_price']=false;
$transport=function($pre,$args,$url) {
 if(!str_starts_with($url,'https://api.stripe.com/v1/')) return $pre;
 $data=str_ends_with($url,'/line_items') ? ['data'=>[['price'=>['id'=>$GLOBALS['stripe_wrong_price']?'price_wrong':'price_testFixture'],'quantity'=>1]],'has_more'=>false] : $GLOBALS['stripe_session'];
 if(str_contains($url,'/prices/')) $data=['id'=>'price_testFixture','livemode'=>false,'active'=>true,'type'=>'one_time'];
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
$user=null;
try {
 stripe_check(rest_do_request(event_request(false))->get_status()===400,'Falsification rejetée');
 stripe_check(rest_do_request(event_request(true,time()-1000))->get_status()===400,'Signature expirée rejetée');
 $GLOBALS['stripe_session']['payment_status']='unpaid';stripe_check(rest_do_request(event_request())->get_status()===400,'Non payé rejeté');stripe_check(!get_user_by('email',$email),'Aucun compte si non payé');
 $GLOBALS['stripe_session']['payment_status']='paid';$GLOBALS['stripe_wrong_price']=true;stripe_check(rest_do_request(event_request())->get_status()===400,'Prix falsifié rejeté');
 $GLOBALS['stripe_wrong_price']=false;
 $paid=rest_do_request(event_request());stripe_check($paid->get_status()===200,'Paiement accepté');
 $user=get_user_by('email',$email);stripe_check($user && in_array('oi_etudiant',$user->roles,true),'Compte étudiant créé');stripe_check(OI_Model::can_read($user->ID,$fiche),'Droit attribué');
 stripe_check(rest_do_request(event_request())->get_data()['duplicate']===true,'Rejeu idempotent');
 global $wpdb;
 stripe_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}oi_payments WHERE user_id=%d",$user->ID))===1,'Un paiement après rejeu');
 $GLOBALS['stripe_session']['id']='cs_test_'.wp_generate_password(15,false);stripe_check(rest_do_request(event_request())->get_status()===200,'Achat supplémentaire accepté');stripe_check(get_user_by('email',$email)->ID===$user->ID,'Pas de doublon de compte');
 $GLOBALS['stripe_session']['id']='cs_test_'.wp_generate_password(15,false);$GLOBALS['stripe_session']['livemode']=true;stripe_check(rest_do_request(event_request())->get_status()===400,'Session live rejetée');
 $r=new WP_REST_Request('POST','/oi/v1/checkout');$r->set_param('pack_id',$pack);stripe_check(rest_do_request($r)->get_status()===200,'Checkout TEST créé via transport simulé');
 $r->set_param('pack_id',999999);stripe_check(rest_do_request($r)->get_status()===400,'Pack inexistant rejeté');
 echo 'Stripe : '.$GLOBALS['stripe_checks']." assertions réussies (API et email simulés ; aucune transaction réelle)\n";
} finally {
 remove_filter('pre_http_request',$transport,10);remove_filter('pre_wp_mail',$mail);
 if($user) { $wpdb->delete($wpdb->prefix.'oi_payments',['user_id'=>$user->ID]);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user->ID); }
 wp_delete_post($pack,true);wp_delete_post($fiche,true);
}

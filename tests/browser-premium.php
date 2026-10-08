<?php
// Only the external Stripe transport is mocked; the signed webhook and wallet run normally.
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Tests locaux uniquement.');
$user=absint($args[0]??0);$action=$args[1]??'';$account=get_userdata($user);
if(!$account||!str_starts_with($account->user_login,'browser_')||$account->roles!==['oi_etudiant'])throw new RuntimeException('Fixture navigateur attendue.');
global $wpdb;$session='cs_test_browser'.$user;
if($action==='cleanup'){
 $wpdb->delete($wpdb->prefix.'oi_payments',['user_id'=>$user,'session_id'=>$session]);
 $wpdb->delete($wpdb->prefix.'oi_credit_grants',['user_id'=>$user,'source_key'=>'premium_bonus']);
 $wpdb->delete($wpdb->prefix.'oi_credit_operations',['user_id'=>$user,'op_key'=>'grant:IA:premium_bonus']);
 echo "Fixture Premium nettoyée.\n";return;
}
if($action!=='grant')throw new RuntimeException('Action invalide.');
if(!is_email($account->user_email)){wp_update_user(['ID'=>$user,'user_email'=>$account->user_login.'@example.invalid']);update_user_meta($user,'oi_email_verified',gmdate('c'));delete_user_meta($user,'oi_email_pending');}
define('OI_STRIPE_SECRET_KEY','sk_test_browser_fixture');define('OI_STRIPE_WEBHOOK_SECRET','whsec_browser_fixture');
$pack=OI_Offer::pack();if(!$pack)throw new RuntimeException('Configurer le pack local.');
$old=get_post_meta($pack,'oi_price',true);update_post_meta($pack,'oi_price','price_browser_premium');
$transport=function($pre,$args,$url)use($session,$pack,$user){
 if(!str_starts_with($url,'https://api.stripe.com/'))return $pre;
 $data=str_ends_with($url,'/line_items')?['data'=>[['price'=>['id'=>'price_browser_premium'],'quantity'=>1]],'has_more'=>false]:['id'=>$session,'livemode'=>false,'mode'=>'payment','payment_status'=>'paid','status'=>'complete','metadata'=>['oi_pack_id'=>$pack,'oi_user_id'=>$user],'client_reference_id'=>(string)$user,'amount_total'=>5900,'currency'=>'eur','payment_intent'=>'pi_browser_'.$user];
 return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200]];
};add_filter('pre_http_request',$transport,10,3);
try{
 $body=wp_json_encode(['id'=>'evt_browser_'.$user,'type'=>'checkout.session.completed','livemode'=>false,'data'=>['object'=>['id'=>$session]]]);$r=new WP_REST_Request('POST','/oi/v1/stripe/webhook');$r->set_body($body);$time=time();$r->set_header('stripe-signature','t='.$time.',v1='.hash_hmac('sha256',$time.'.'.$body,OI_STRIPE_WEBHOOK_SECRET));
 $result=rest_do_request($r);if($result->get_status()!==200||!OI_Offer::premium($user))throw new RuntimeException('Activation Premium impossible : '.wp_json_encode($result->get_data()));
 echo "Webhook signé traité ; transport Stripe simulé.\n";
}finally{remove_filter('pre_http_request',$transport,10);if($old==='')delete_post_meta($pack,'oi_price');else update_post_meta($pack,'oi_price',$old);}

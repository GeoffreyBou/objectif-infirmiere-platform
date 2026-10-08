<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Tests locaux uniquement.');
define('OI_STRIPE_SECRET_KEY','sk_test_refund_fixture');define('OI_STRIPE_WEBHOOK_SECRET','whsec_refund_fixture');
global $wpdb;$checks=0;$old=get_option('oi_premium_pack',false);
$assert=function($v,$m)use(&$checks){if(!$v)throw new RuntimeException($m);$checks++;};
$u=wp_insert_user(['user_login'=>'refund_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'user_email'=>'refund_'.wp_generate_password(12,false).'@example.invalid','role'=>'oi_etudiant']);update_user_meta($u,'oi_email_verified',gmdate('c'));OI_Credits::welcome($u);
$p=wp_insert_post(['post_type'=>'oi_pack','post_status'=>'publish','post_title'=>'Premium remboursement test']);$f=wp_insert_post(['post_type'=>'oi_fiche','post_status'=>'publish','post_title'=>'Fiche conservée']);update_post_meta($p,'oi_fiches',[$f]);update_post_meta($p,'oi_price','price_refund');update_option('oi_premium_pack',$p);
$session=['id'=>'cs_test_refundA','livemode'=>false,'mode'=>'payment','payment_status'=>'paid','status'=>'complete','metadata'=>['oi_pack_id'=>$p,'oi_user_id'=>$u],'client_reference_id'=>(string)$u,'amount_total'=>5900,'currency'=>'eur','payment_intent'=>'pi_refundA'];
$charge=['id'=>'ch_refundA','livemode'=>false,'payment_intent'=>'pi_refundA','amount'=>5900,'amount_refunded'=>0,'currency'=>'eur'];
$transport=function($pre,$args,$url)use(&$session,&$charge){if(!str_starts_with($url,'https://api.stripe.com/'))return $pre;$data=str_contains($url,'/charges/')?$charge:(str_ends_with($url,'/line_items')?['data'=>[['price'=>['id'=>'price_refund'],'quantity'=>1]],'has_more'=>false]:$session);return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200]];};add_filter('pre_http_request',$transport,10,3);
$send=function($type,$id){$body=wp_json_encode(['id'=>'evt_refund','type'=>$type,'livemode'=>false,'data'=>['object'=>['id'=>$id]]]);$r=new WP_REST_Request('POST','/oi/v1/stripe/webhook');$r->set_body($body);$r->set_header('stripe-signature','t='.time().',v1='.hash_hmac('sha256',time().'.'.$body,OI_STRIPE_WEBHOOK_SECRET));return rest_do_request($r);};
try{
 OI_Credits::unlock($u,'FICHE',$f);update_user_meta($u,'oi_revision',[$f=>['favorite'=>true,'revised'=>true]]);
 $session['amount_total']=3900;$assert($send('checkout.session.completed',$session['id'])->get_status()===400,'39 € ne débloque pas le Premium 59 €');$session['amount_total']=5900;
 $session['client_reference_id']='999999';$assert($send('checkout.session.completed',$session['id'])->get_status()===400,'Propriétaire incohérent refusé');$session['client_reference_id']=(string)$u;
 $assert($send('checkout.session.completed',$session['id'])->get_status()===200,'Paiement Premium confirmé');$assert(OI_Credits::balances($u)['IA']===105,'Bonus total exact');
 $charge['amount_refunded']=1000;$assert($send('charge.refunded',$charge['id'])->get_data()['refund']==='partial_review','Remboursement partiel signalé pour examen');$assert(OI_Offer::premium($u)&&OI_Credits::balances($u)['IA']===105,'Partiel : droits conservés');
 // Spend the five welcome credits, then keep one Premium request in flight during refund.
 for($i=0;$i<5;$i++){$r=OI_Credits::reserve_ai($u,wp_generate_uuid4());OI_Credits::settle_ai($u,$r['key'],true);}
 $pending=OI_Credits::reserve_ai($u,wp_generate_uuid4());
 $charge['amount_refunded']=5900;$assert($send('charge.refunded',$charge['id'])->get_data()['refund']==='full','Remboursement total confirmé');
 $assert(!OI_Offer::premium($u)&&OI_Credits::balances($u)['IA']===0,'Premium révoqué, crédits bonus non dépensés retirés');
 OI_Credits::settle_ai($u,$pending['key'],false);$assert(OI_Credits::balances($u)['IA']===0,'Échec IA concurrent ne recrée pas un bonus remboursé');
 $assert(OI_Model::can_read($u,$f)&&OI_Revision::state($u,$f)['favorite'],'Déblocage individuel, favoris et progression conservés');
 $assert($send('charge.refunded',$charge['id'])->get_status()===200&&OI_Credits::balances($u)['IA']===0,'Remboursement rejoué sans découvert');
 $session['id']='cs_test_refundB';$session['payment_intent']='pi_refundB';$assert($send('checkout.session.completed',$session['id'])->get_status()===200,'Rachat du même pack accepté après retrait');$assert(OI_Offer::premium($u)&&OI_Credits::balances($u)['IA']===0,'Rachat ne réattribue pas le bonus de 100');
 echo "Premium et remboursements : $checks assertions réussies (Stripe simulé)\n";
}finally{remove_filter('pre_http_request',$transport,10);foreach(['oi_payments','oi_credit_grants','oi_credit_operations','oi_wallets','oi_unlocks']as$t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$u]);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($u);wp_delete_post($p,true);wp_delete_post($f,true);if($old===false)delete_option('oi_premium_pack');else update_option('oi_premium_pack',$old);}

<?php
if(!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Local only');
define('OI_STRIPE_SECRET_KEY','sk_test_fixture');define('OI_STRIPE_WEBHOOK_SECRET','whsec_topup_fixture');define('OI_OPENAI_API_KEY','fixture');
$checks=0;$assert=function($ok,$message)use(&$checks){if(!$ok)throw new RuntimeException($message);$checks++;};
$old=[];foreach(['oi_ai_recharge_price'=>'price_topupFixture','oi_ai_enabled'=>true,'oi_vector_store'=>'vs_fixture'] as $key=>$value){$old[$key]=get_option($key,null);update_option($key,$value);}
$user=wp_insert_user(['user_login'=>'topup_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(),'user_email'=>'topup_'.wp_generate_password(8,false).'@example.invalid','role'=>'oi_etudiant']);
$other=wp_insert_user(['user_login'=>'topup_other_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(),'role'=>'oi_etudiant']);
update_user_meta($user,'oi_email_verified',gmdate('c'));update_user_meta($other,'oi_email_verified',gmdate('c'));OI_Credits::welcome($user);
$pack=OI_Offer::pack();$assert($pack>0,'Pack local requis');$sessions=[];$created=0;$priceAmount=1000;$wrongLine=false;$charge=[];$intentRefunds=[];
$transport=function($pre,$args,$url)use(&$sessions,&$created,&$priceAmount,&$wrongLine,&$charge,&$intentRefunds,$user){
 if(!str_starts_with($url,'https://api.stripe.com/v1/'))return $pre;
 if(str_contains($url,'/prices/'))$data=['id'=>'price_topupFixture','livemode'=>false,'active'=>true,'type'=>'one_time','unit_amount'=>$priceAmount,'currency'=>'eur'];
 elseif(str_ends_with($url,'/checkout/sessions')&&$args['method']==='POST'){
  $created++;$id='cs_test_topupFixture'.$created;$body=$args['body'];
  if($body['line_items[0][price]']!=='price_topupFixture'||$body['line_items[0][quantity]']!==1||!str_contains($body['success_url'],'{CHECKOUT_SESSION_ID}'))throw new RuntimeException('Checkout incorrect');
  $sessions[$id]=['id'=>$id,'livemode'=>false,'mode'=>'payment','status'=>'open','payment_status'=>'unpaid','url'=>'https://checkout.stripe.com/c/pay/'.$id,'amount_total'=>1000,'currency'=>'eur','metadata'=>['oi_user_id'=>$user,'oi_purchase_kind'=>'ai_topup'],'client_reference_id'=>(string)$user,'payment_intent'=>'pi_topupFixture'.$created,'customer'=>'cus_fixture'];$data=$sessions[$id];
 }elseif(str_ends_with($url,'/line_items'))$data=['data'=>[['price'=>['id'=>$wrongLine?'price_wrong':'price_topupFixture'],'quantity'=>1]],'has_more'=>false];
 elseif(str_contains($url,'/payment_intents/')){$intent=basename(parse_url($url,PHP_URL_PATH));$data=['id'=>$intent,'livemode'=>false,'status'=>'succeeded','amount_received'=>1000,'currency'=>'eur','latest_charge'=>['amount'=>1000,'amount_refunded'=>$intentRefunds[$intent]??0]];}
 elseif(str_contains($url,'/charges/'))$data=$charge;
 else{$id=basename($url);$data=$sessions[$id]??[];}
 return ['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200]];
};add_filter('pre_http_request',$transport,10,3);
$request=function($route,$data=[],$method='POST'){$r=new WP_REST_Request($method,'/oi/v1/'.$route);if($method==='GET')$r->set_query_params($data);else $r->set_body_params($data);return rest_do_request($r);};
$event=function($id,$type='checkout.session.completed',$valid=true){$body=wp_json_encode(['id'=>'evt_'.wp_generate_password(12,false),'livemode'=>false,'type'=>$type,'data'=>['object'=>['id'=>$id]]]);$r=new WP_REST_Request('POST','/oi/v1/stripe/webhook');$r->set_body($body);$r->set_header('stripe-signature','t='.time().',v1='.($valid?hash_hmac('sha256',time().'.'.$body,OI_STRIPE_WEBHOOK_SECRET):'invalid'));return rest_do_request($r);};
try{
 global $wpdb;
 wp_set_current_user(0);$assert($request('ai-recharge/checkout')->get_status()===401,'Anonyme refusé');
 wp_set_current_user($user);$assert($request('ai-recharge/checkout')->get_status()===403,'Gratuit refusé');
 update_user_meta($user,'oi_packs',[$pack]);$assert($request('ai-recharge/checkout')->get_status()===409,'Premium avec crédits : achat refusé');
 for($i=0;$i<5;$i++){$r=OI_Credits::reserve_ai($user,wp_generate_uuid4());OI_Credits::settle_ai($user,$r['key'],true);}
 update_option('oi_ai_enabled',false);$assert($request('ai-recharge/checkout')->get_status()===503,'Vente bloquée si assistant indisponible');update_option('oi_ai_enabled',true);
 $priceAmount=490;$assert($request('ai-recharge/checkout')->get_status()===400,'Prix Stripe différent de 10 EUR refusé');$priceAmount=1000;
 $checkout=$request('ai-recharge/checkout',['amount'=>1,'credits'=>99999]);$assert($checkout->get_status()===200,'Checkout de recharge créé');$id=array_key_last($sessions);$assert($created===1,'Une session créée');
 $assert($request('ai-recharge/checkout')->get_data()['url']===$checkout->get_data()['url']&&$created===1,'Double clic réutilise la session ouverte');
 $assert(OI_Credits::balances($user)['IA']===0,'Retour de Checkout ne crédite rien');
 $assert($event($id,'checkout.session.completed',false)->get_status()===400,'Webhook falsifié refusé');
 $assert($event($id)->get_status()===400,'Session non payée refusée');
 $sessions[$id]['status']='complete';$sessions[$id]['payment_status']='paid';
 $sessions[$id]['amount_total']=1;$assert($event($id)->get_status()===400,'Montant de paiement altéré refusé');$sessions[$id]['amount_total']=1000;
 $sessions[$id]['metadata']['oi_user_id']=$other;$assert($event($id)->get_status()===400,'Autre bénéficiaire refusé');$sessions[$id]['metadata']['oi_user_id']=$user;
 $wrongLine=true;$assert($event($id)->get_status()===400,'Autre produit refusé');$wrongLine=false;
 $assert($event($id)->get_status()===200,'Paiement confirmé accepté');$assert(OI_Credits::balances($user)['IA']===100,'Exactement 100 crédits attribués');
 $assert($event($id)->get_data()['duplicate']&&OI_Credits::balances($user)['IA']===100,'Webhook répété sans double crédit');
 $assert($event($id,'checkout.session.async_payment_succeeded')->get_data()['duplicate'],'Confirmation asynchrone répétée idempotente');
 $assert(OI_Storage::paid_packs($user)===[]&&OI_Offer::premium($user),'Recharge ne devient pas un pack et préserve le Premium');
 $assert($request('ai-recharge/status',['session_id'=>$id],'GET')->get_data()['status']==='paid','Retour vérifie cette session réellement payée');
 wp_set_current_user($other);$assert($request('ai-recharge/status',['session_id'=>$id],'GET')->get_status()===404,'Autre compte ne consulte pas le paiement');wp_set_current_user($user);
 $assert($request('ai-recharge/checkout')->get_status()===409,'Nouvelle recharge bloquée tant que solde positif');
 // Full refund revokes only that purchase, including refunds of an in-flight AI reservation.
 $r=OI_Credits::reserve_ai($user,wp_generate_uuid4());OI_Credits::settle_ai($user,$r['key'],true);$pending=OI_Credits::reserve_ai($user,wp_generate_uuid4());
 OI_Credits::grant($user,'IA',3,'gift','Crédits offerts');
 $charge=['id'=>'ch_topupFixture','livemode'=>false,'payment_intent'=>$sessions[$id]['payment_intent'],'amount'=>1000,'amount_refunded'=>200,'currency'=>'eur'];
 $assert($event('ch_topupFixture','charge.refunded')->get_data()['refund']==='partial_review','Remboursement partiel signalé sans retrait arbitraire');$charge['amount_refunded']=1000;
 $assert($event('ch_topupFixture','charge.refunded')->get_data()['refund']==='full','Remboursement complet enregistré');$assert(OI_Credits::balances($user)['IA']===3,'Crédits offerts préservés, seul achat remboursé retiré');
 OI_Credits::settle_ai($user,$pending['key'],false);$assert(OI_Credits::balances($user)['IA']===3,'Échec IA concurrent ne ressuscite pas la recharge remboursée');
 $assert($event($id)->get_data()['duplicate']&&OI_Credits::balances($user)['IA']===3,'Confirmation tardive ne recrédite pas un achat remboursé');
 for($i=0;$i<3;$i++){$r=OI_Credits::reserve_ai($user,wp_generate_uuid4());OI_Credits::settle_ai($user,$r['key'],true);}
 $assert($request('ai-recharge/checkout')->get_status()===200,'Nouvelle recharge possible après nouvel épuisement');$second=array_key_last($sessions);$sessions[$second]['status']='complete';$sessions[$second]['payment_status']='paid';
 $assert($event($second)->get_status()===200&&OI_Credits::balances($user)['IA']===100,'Second achat donne à nouveau 100 crédits, indépendamment du bonus Premium');
 for($i=0;$i<100;$i++){$r=OI_Credits::reserve_ai($user,wp_generate_uuid4());OI_Credits::settle_ai($user,$r['key'],true);}
 $assert($request('ai-recharge/checkout')->get_status()===200,'Troisième achat préparé après consommation');$third=array_key_last($sessions);
 $sessions[$third]['status']='complete';$sessions[$third]['payment_status']='paid';$intent=$sessions[$third]['payment_intent'];
 $charge['payment_intent']=$intent;$charge['amount_refunded']=1000;
 $assert($event('ch_topupFixture','charge.refunded')->get_data()['ignored'],'Remboursement arrivé avant confirmation sans attribution');$intentRefunds[$intent]=1000;
 $assert($event($third)->get_status()===200&&OI_Credits::balances($user)['IA']===0,'Paiement déjà remboursé ne donne aucun crédit lors de sa confirmation tardive');
 $assert($request('ai-recharge/status',['session_id'=>$third],'GET')->get_data()['status']==='refunded','Retour indique le remboursement et non une recharge réussie');
 echo "Recharges IA : $checks assertions réussies (Stripe simulé, aucun paiement réel)\n";
}finally{
 remove_filter('pre_http_request',$transport,10);wp_set_current_user(0);
 foreach($old as $key=>$value){if($value===null)delete_option($key);else update_option($key,$value);}
 foreach([$user,$other] as $u){foreach(['oi_payments','oi_credit_grants','oi_credit_operations','oi_wallets'] as $t)$wpdb->delete($wpdb->prefix.$t,['user_id'=>$u]);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($u);}
}

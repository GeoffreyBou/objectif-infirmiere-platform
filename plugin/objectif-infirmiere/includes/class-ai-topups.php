<?php
defined('ABSPATH') || exit;
/** One-off assistant credits, independently identified from content access. */
final class OI_AI_Topups {
    public const AMOUNT=1000;
    public const CREDITS=100;
    public static function register(): void {
        register_rest_route('oi/v1','/ai-recharge/checkout',['methods'=>'POST','permission_callback'=>[OI_API::class,'auth'],'callback'=>[self::class,'checkout']]);
        register_rest_route('oi/v1','/ai-recharge/status',['methods'=>'GET','permission_callback'=>[OI_API::class,'auth'],'callback'=>[self::class,'status']]);
    }
    public static function offer(): array {
        $price=(string)get_option('oi_ai_recharge_price','');
        $key=OI_Stripe::secret('OI_STRIPE_SECRET_KEY');
        return ['amount'=>self::AMOUNT,'credits'=>self::CREDITS,'currency'=>'EUR','payment_mode'=>'test','available'=>(bool)(preg_match('/^price_[A-Za-z0-9]+$/',$price)&&$key&&!preg_match('/^(sk|rk)_live_/',$key)&&get_option('oi_ai_enabled',false)&&get_option('oi_vector_store')&&OI_Stripe::secret('OI_OPENAI_API_KEY'))];
    }
    private static function url(array $session): string|WP_Error {
        $url=$session['url']??'';
        if(($session['livemode']??true)!==false||!is_string($url)||wp_parse_url($url,PHP_URL_SCHEME)!=='https'||wp_parse_url($url,PHP_URL_HOST)!=='checkout.stripe.com')return new WP_Error('oi_checkout','Session de paiement TEST invalide.',['status'=>502]);
        return $url;
    }
    public static function checkout(): array|WP_Error {
        $user=get_current_user_id();
        if(!OI_Limit::take('ai_recharge',10,300))return new WP_Error('oi_rate','Veuillez patienter avant de réessayer.',['status'=>429]);
        if(!OI_Verification::verified($user)||!OI_Offer::premium($user))return new WP_Error('oi_recharge_access','La recharge est réservée aux membres Premium.',['status'=>403]);
        global $wpdb;$lock='oi_recharge_'.md5(DB_NAME.$wpdb->prefix.$user);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)',$lock))!==1)return new WP_Error('oi_busy','Une recharge est déjà en cours.',['status'=>409]);
        try{
            if(OI_Credits::balances($user)['IA']!==0)return new WP_Error('oi_recharge_balance','Utilise tes crédits IA restants avant de recharger.',['status'=>409]);
            if($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oi_credit_operations WHERE user_id=%d AND state='reserved' LIMIT 1",$user)))return new WP_Error('oi_busy','Attends la réponse en cours avant de recharger.',['status'=>409]);
            if(!self::offer()['available'])return new WP_Error('oi_recharge_config','La recharge n’est pas encore disponible.',['status'=>503]);
            $pending=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments WHERE user_id=%d AND purchase_kind='ai_topup' AND status='pending' ORDER BY id DESC LIMIT 1",$user));
            if($pending){
                $session=OI_Stripe::request('GET','checkout/sessions/'.rawurlencode($pending->session_id));if(is_wp_error($session))return $session;
                if(($session['status']??'')==='open'){$url=self::url($session);return is_wp_error($url)?$url:['url'=>$url];}
                if(($session['status']??'')!=='expired')return new WP_Error('oi_recharge_pending','La confirmation de ta recharge est en cours. Patiente avant un nouvel achat.',['status'=>409]);
                if($wpdb->update($wpdb->prefix.'oi_payments',['status'=>'expired'],['id'=>$pending->id])===false)return new WP_Error('oi_storage','Réessaie dans un instant.',['status'=>503]);
            }
            $price=(string)get_option('oi_ai_recharge_price','');$verified=OI_Stripe::request('GET','prices/'.rawurlencode($price));if(is_wp_error($verified))return $verified;
            if(($verified['id']??'')!==$price||($verified['livemode']??true)!==false||($verified['active']??false)!==true||($verified['type']??'')!=='one_time'||($verified['unit_amount']??0)!==self::AMOUNT||strtolower($verified['currency']??'')!=='eur')return new WP_Error('oi_recharge_price','Un prix Stripe TEST ponctuel de 10 EUR est requis.',['status'=>400]);
            $session=OI_Stripe::request('POST','checkout/sessions',[
                'mode'=>'payment','line_items[0][price]'=>$price,'line_items[0][quantity]'=>1,
                'success_url'=>add_query_arg(['oi_payment'=>'recharged','view'=>'ai'],OI_App::url('member')).'&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'=>add_query_arg(['oi_payment'=>'cancelled','view'=>'recharge'],OI_App::url('member')),
                'client_reference_id'=>$user,'customer_email'=>wp_get_current_user()->user_email,
                'metadata[oi_user_id]'=>$user,'metadata[oi_purchase_kind]'=>'ai_topup',
            ]);
            if(is_wp_error($session))return $session;$url=self::url($session);if(is_wp_error($url))return $url;
            if(!preg_match('/^cs_test_[A-Za-z0-9]+$/',$session['id']??''))return new WP_Error('oi_checkout','Session TEST invalide.',['status'=>502]);
            $written=$wpdb->insert($wpdb->prefix.'oi_payments',['session_id'=>$session['id'],'event_id'=>'','user_id'=>$user,'pack_id'=>0,'purchase_kind'=>'ai_topup','stripe_price_id'=>$price,'credit_quantity'=>self::CREDITS,'amount'=>self::AMOUNT,'currency'=>'eur','status'=>'pending','notified'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]);
            if(!$written)return new WP_Error('oi_storage','La recharge n’a pas pu être préparée. Réessaie.',['status'=>503]);
            return ['url'=>$url];
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    /** Called only after Stripe signature verification, under the session lock. */
    public static function complete(object $payment,array $event): array|WP_Error {
        if($payment->status==='paid'||$payment->status==='refunded')return ['received'=>true,'duplicate'=>true];
        $session=OI_Stripe::request('GET','checkout/sessions/'.rawurlencode($payment->session_id));if(is_wp_error($session))return $session;
        if(($session['id']??'')!==$payment->session_id||($session['livemode']??true)!==false||($session['mode']??'')!=='payment'||($session['status']??'')!=='complete'||($session['payment_status']??'')!=='paid')return new WP_Error('oi_unpaid','Recharge non confirmée.',['status'=>400]);
        if(($session['metadata']['oi_purchase_kind']??'')!=='ai_topup'||(string)($session['metadata']['oi_user_id']??'')!==(string)$payment->user_id||(string)($session['client_reference_id']??'')!==(string)$payment->user_id||!get_userdata((int)$payment->user_id)||($session['amount_total']??0)!==(int)$payment->amount||strtolower($session['currency']??'')!==$payment->currency)return new WP_Error('oi_purchase','Recharge incohérente.',['status'=>400]);
        $lines=OI_Stripe::request('GET','checkout/sessions/'.rawurlencode($payment->session_id).'/line_items');if(is_wp_error($lines))return $lines;
        if(count($lines['data']??[])!==1||($lines['has_more']??false)||($lines['data'][0]['price']['id']??'')!==$payment->stripe_price_id||($lines['data'][0]['quantity']??0)!==1)return new WP_Error('oi_product','Produit de recharge invalide.',['status'=>400]);
        // A refund webhook can arrive before the completion webhook. Read the
        // charge now as well, so that an already refunded purchase is not credited.
        $intentId=$session['payment_intent']??'';
        if(!is_string($intentId)||!preg_match('/^pi_[A-Za-z0-9]+$/',$intentId))return new WP_Error('oi_purchase','Paiement de recharge introuvable.',['status'=>400]);
        $intent=OI_Stripe::request('GET','payment_intents/'.rawurlencode($intentId).'?expand%5B0%5D=latest_charge');if(is_wp_error($intent))return $intent;
        $charge=$intent['latest_charge']??[];
        if(($intent['id']??'')!==$intentId||($intent['livemode']??true)!==false||($intent['status']??'')!=='succeeded'||($intent['amount_received']??0)!==(int)$payment->amount||strtolower($intent['currency']??'')!==$payment->currency||!is_array($charge)||($charge['amount']??0)!==(int)$payment->amount)return new WP_Error('oi_purchase','Montant du paiement incohérent.',['status'=>400]);
        $refunded=min((int)$payment->amount,absint($charge['amount_refunded']??0));
        return OI_Credits::transaction((int)$payment->user_id,function()use($payment,$session,$event,$refunded){
            global $wpdb;
            $current=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments WHERE id=%d FOR UPDATE",$payment->id));
            if(in_array($current->status,['paid','refunded'],true))return ['received'=>true,'duplicate'=>true];
            $full=$refunded===(int)$current->amount;
            if(!$full)OI_Credits::grant_locked((int)$current->user_id,'IA',(int)$current->credit_quantity,'recharge:'.$current->session_id,'Recharge de 100 crédits IA');
            if($wpdb->update($wpdb->prefix.'oi_payments',['status'=>$full?'refunded':'paid','refunded_amount'=>$refunded,'refund_status'=>$full?'full':($refunded?'partial':''),'event_id'=>sanitize_text_field($event['id']??''),'customer_id'=>sanitize_text_field($session['customer']??''),'payment_intent'=>sanitize_text_field($session['payment_intent']??'')],['id'=>$current->id])===false)throw new RuntimeException('Paiement non enregistré.');
            return ['received'=>true];
        });
    }
    public static function status(WP_REST_Request $r): array|WP_Error {
        $session=is_string($r['session_id'])?$r['session_id']:'';
        if(!preg_match('/^cs_test_[A-Za-z0-9]+$/',$session))return new WP_Error('oi_session','Recharge introuvable.',['status'=>404]);
        global $wpdb;$payment=$wpdb->get_row($wpdb->prepare("SELECT status,credit_quantity FROM {$wpdb->prefix}oi_payments WHERE session_id=%s AND user_id=%d AND purchase_kind='ai_topup'",$session,get_current_user_id()));
        if(!$payment)return new WP_Error('oi_session','Recharge introuvable.',['status'=>404]);
        return ['status'=>$payment->status,'credits'=>(int)$payment->credit_quantity];
    }
}

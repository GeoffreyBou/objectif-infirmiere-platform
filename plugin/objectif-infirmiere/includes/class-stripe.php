<?php
defined('ABSPATH') || exit;
final class OI_Stripe {
    public static function secret(string $name): string {
        return defined($name) ? (string)constant($name) : (string)getenv($name);
    }
    public static function register(): void {
        register_rest_route('oi/v1', '/checkout', ['methods' => 'POST', 'permission_callback' => [OI_API::class, 'auth'], 'callback' => [self::class, 'checkout']]);
        register_rest_route('oi/v1', '/stripe/webhook', ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [self::class, 'webhook']]);
    }
    public static function request(string $method, string $path, array $body = []): array|WP_Error {
        $key = self::secret('OI_STRIPE_SECRET_KEY');
        if (!$key || str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_')) {
            return new WP_Error('oi_stripe_config', 'Le paiement TEST n’est pas encore configuré.', ['status' => 503]);
        }
        $response = wp_remote_request('https://api.stripe.com/v1/' . $path, ['method' => $method, 'timeout' => 25, 'redirection' => 0, 'headers' => ['Authorization' => 'Bearer ' . $key, 'Stripe-Version' => '2024-06-20'], 'body' => $body]);
        if (is_wp_error($response)) { return new WP_Error('oi_stripe_unavailable', 'Le paiement est momentanément indisponible.', ['status' => 503]); }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) >= 300 || !is_array($data)) { return new WP_Error('oi_stripe_response', 'Stripe n’a pas pu traiter cette demande.', ['status' => 502]); }
        return $data;
    }
    public static function checkout(WP_REST_Request $r): array|WP_Error {
        if (!OI_Limit::take('checkout', 20, 300)) { return new WP_Error('oi_rate', 'Veuillez réessayer dans quelques minutes.', ['status' => 429]); }
        if(!OI_Verification::verified(get_current_user_id()))return new WP_Error('oi_verify','Confirme ton adresse e-mail avant l’achat.',['status'=>403]);
        $pack = absint($r['pack_id']);
        if($pack!==OI_Offer::pack())return new WP_Error('oi_pack','Le pack Premium sélectionné n’est pas disponible.',['status'=>400]);
        if(OI_Offer::premium(get_current_user_id()))return new WP_Error('oi_owned','Ton accès Premium est déjà actif.',['status'=>409]);
        $price = get_post_meta($pack, 'oi_price', true);
        if (get_post_type($pack) !== 'oi_pack' || get_post_status($pack) !== 'publish' || !preg_match('/^price_[A-Za-z0-9]+$/', $price)) {
            return new WP_Error('oi_pack', 'Ce pack n’est pas disponible à l’achat.', ['status' => 400]);
        }
        $verified_price = self::request('GET', 'prices/' . rawurlencode($price));
        if (is_wp_error($verified_price)) { return $verified_price; }
        if (($verified_price['livemode'] ?? true) !== false || ($verified_price['active'] ?? false) !== true || ($verified_price['type'] ?? '') !== 'one_time') { return new WP_Error('oi_price_test', 'Un prix Stripe TEST actif et ponctuel est requis.', ['status' => 400]); }
        if(($verified_price['unit_amount']??0)!==5900||strtolower($verified_price['currency']??'')!=='eur')return new WP_Error('oi_price','Le prix attendu est 59 EUR en paiement unique.',['status'=>400]);
        $data = self::request('POST', 'checkout/sessions', [
            'mode' => 'payment', 'line_items[0][price]' => $price, 'line_items[0][quantity]' => 1,
            'success_url' => add_query_arg('oi_payment','received',OI_App::url('member')), 'cancel_url' => add_query_arg(['oi_payment'=>'cancelled','view'=>'premium'],OI_App::url('member')),
            'client_reference_id'=>get_current_user_id(), 'customer_email'=>wp_get_current_user()->user_email,
            'metadata[oi_user_id]'=>get_current_user_id(),
            'metadata[oi_pack_id]' => $pack,
        ]);
        if (is_wp_error($data)) { return $data; }
        $url = $data['url'] ?? '';
        if (($data['livemode'] ?? true) !== false || wp_parse_url($url, PHP_URL_HOST) !== 'checkout.stripe.com' || wp_parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return new WP_Error('oi_test_only', 'Une session de paiement TEST valide est requise.', ['status' => 502]);
        }
        OI_Metrics::count('checkout_started',get_current_user_id());
        return ['url' => $url];
    }
    public static function signature(string $body, string $header, string $secret, ?int $now = null): bool {
        if (!$secret || strlen($header) > 4096) { return false; }
        $timestamp = null; $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) { $timestamp = (int)$value; }
            if ($key === 'v1') { $signatures[] = $value; }
        }
        if (!$timestamp || abs(($now ?? time()) - $timestamp) > 300) { return false; }
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        foreach ($signatures as $signature) { if (hash_equals($expected, $signature)) { return true; } }
        return false;
    }
    public static function webhook(WP_REST_Request $r): array|WP_Error {
        if (strlen($r->get_body()) > 1048576 || !self::signature($r->get_body(), $r->get_header('stripe-signature'), self::secret('OI_STRIPE_WEBHOOK_SECRET'))) {
            OI_Log::event('stripe_webhook_rejected');
            return new WP_Error('oi_signature', 'Signature Stripe invalide.', ['status' => 400]);
        }
        $event = json_decode($r->get_body(), true);
        if (!is_array($event) || ($event['livemode'] ?? true) !== false) { return new WP_Error('oi_test_only', 'Événement TEST requis.', ['status' => 400]); }
        if(in_array($event['type']??'',['charge.refunded','refund.updated'],true))return self::refund($event);
        if (!in_array($event['type'] ?? '', ['checkout.session.completed','checkout.session.async_payment_succeeded'],true)) { return ['received' => true, 'ignored' => true]; }
        $session = $event['data']['object']['id'] ?? '';
        if (!preg_match('/^cs_test_[A-Za-z0-9]+$/', $session)) { return new WP_Error('oi_session', 'Session TEST invalide.', ['status' => 400]); }
        global $wpdb;
        $lock = 'oi_' . md5(DB_NAME . $wpdb->prefix . $session);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $lock)) !== 1) { return new WP_Error('oi_busy', 'Traitement en cours, réessayez.', ['status' => 503]); }
        try {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments WHERE session_id=%s", $session));
            if ($existing) { self::notify($existing); return ['received' => true, 'duplicate' => true]; }
            $verified = self::request('GET', 'checkout/sessions/' . rawurlencode($session));
            if (is_wp_error($verified)) { return $verified; }
            if (($verified['id'] ?? '') !== $session || ($verified['livemode'] ?? true) !== false || ($verified['mode'] ?? '') !== 'payment' || ($verified['payment_status'] ?? '') !== 'paid' || ($verified['status'] ?? '') !== 'complete') {
                return new WP_Error('oi_unpaid', 'Paiement TEST non confirmé.', ['status' => 400]);
            }
            $pack = absint($verified['metadata']['oi_pack_id'] ?? 0);
            $price = get_post_meta($pack, 'oi_price', true);
            $lines = self::request('GET', 'checkout/sessions/' . rawurlencode($session) . '/line_items');
            if (is_wp_error($lines)) { return $lines; }
            if (get_post_type($pack) !== 'oi_pack' || get_post_status($pack) !== 'publish' || !$price || count($lines['data'] ?? []) !== 1 || ($lines['has_more'] ?? false) || ($lines['data'][0]['price']['id'] ?? '') !== $price || ($lines['data'][0]['quantity'] ?? 0) !== 1) {
                return new WP_Error('oi_product', 'Le produit payé ne correspond pas au pack.', ['status' => 400]);
            }
            $premium=$pack===OI_Offer::pack();
            $owner=$premium?absint($verified['metadata']['oi_user_id']??0):0;
            if($premium && (!$owner || (string)($verified['client_reference_id']??'')!==(string)$owner || !get_userdata($owner) || !OI_Verification::verified($owner) || ($verified['amount_total']??0)!==5900 || strtolower($verified['currency']??'')!=='eur'))return new WP_Error('oi_purchase','Propriétaire, montant ou devise du Premium invalide.',['status'=>400]);
            $email = $premium?get_userdata($owner)->user_email:($verified['customer_details']['email'] ?? '');
            if (!is_email($email)) { return new WP_Error('oi_email', 'Adresse email du paiement invalide.', ['status' => 400]); }
            $email_lock = 'oi_email_' . md5(DB_NAME . $wpdb->prefix . strtolower($email));
            if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $email_lock)) !== 1) { return new WP_Error('oi_busy', 'Création du compte en cours, réessayez.', ['status'=>503]); }
            try {
            $user = $premium?get_userdata($owner):get_user_by('email', $email);
            if (!$user) {
                $id = wp_insert_user(['user_login' => 'oi_' . wp_generate_password(24, false), 'user_email' => $email, 'user_pass' => wp_generate_password(32), 'role' => 'oi_etudiant']);
                if (is_wp_error($id)) {
                    $user = get_user_by('email', $email);
                    if (!$user) { return new WP_Error('oi_user', 'Création du compte impossible, réessayez.', ['status' => 503]); }
                } else { update_user_meta($id, 'oi_account_setup_pending', true); OI_Log::event('user_created', ['user_id'=>$id]); $user = get_user_by('id', $id); }
            }
            $written=OI_Credits::transaction((int)$user->ID,function()use($wpdb,$session,$event,$verified,$user,$pack,$premium){
            $insert = $wpdb->insert($wpdb->prefix . 'oi_payments', ['session_id' => $session, 'event_id' => sanitize_text_field($event['id'] ?? ''), 'customer_id' => sanitize_text_field($verified['customer'] ?? ''), 'payment_intent'=>sanitize_text_field($verified['payment_intent']??''), 'user_id' => $user->ID, 'pack_id' => $pack, 'amount' => absint($verified['amount_total'] ?? 0), 'currency' => sanitize_key($verified['currency'] ?? ''), 'status' => 'paid', 'created_at' => current_time('mysql', true)]);
            if (!$insert) { return new WP_Error('oi_storage', 'Enregistrement du paiement impossible, réessayez.', ['status' => 503]); }
            if($premium)OI_Credits::grant_locked((int)$user->ID,'IA',100,'premium_bonus','Achat du Pack Premium');
            return true;
            });
            if(is_wp_error($written))return $written;
            $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments WHERE session_id=%s", $session));
            OI_Log::event('payment_recorded', ['user_id'=>$user->ID, 'pack_id'=>$pack]);
            OI_Log::event('pack_granted', ['user_id'=>$user->ID, 'pack_id'=>$pack]);
            self::notify($payment);
            return ['received' => true];
            } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $email_lock)); }
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    private static function refund(array $event): array|WP_Error {
        $object=$event['data']['object']??[];
        if(($event['type']??'')==='refund.updated' && ($object['status']??'')!=='succeeded')return ['received'=>true,'ignored'=>true];
        $id=($event['type']??'')==='charge.refunded'?($object['id']??''):($object['charge']??'');
        if(!is_string($id)||!preg_match('/^ch_[A-Za-z0-9]+$/',$id))return new WP_Error('oi_refund','Remboursement invalide.',['status'=>400]);
        $charge=self::request('GET','charges/'.rawurlencode($id));if(is_wp_error($charge))return $charge;
        if(($charge['livemode']??true)!==false||($charge['id']??'')!==$id)return new WP_Error('oi_refund','Remboursement TEST non confirmé.',['status'=>400]);
        global $wpdb;
        $payment=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments WHERE payment_intent=%s",sanitize_text_field($charge['payment_intent']??'')));
        if(!$payment || !$payment->payment_intent)return ['received'=>true,'ignored'=>true];
        if((int)($charge['amount']??0)!==(int)$payment->amount||strtolower($charge['currency']??'')!==$payment->currency)return new WP_Error('oi_refund','Montant du remboursement incohérent.',['status'=>400]);
        $amount=min((int)$payment->amount,absint($charge['amount_refunded']??0));
        if(!$amount)return ['received'=>true,'ignored'=>true];
        $result=OI_Credits::transaction((int)$payment->user_id,function()use($wpdb,$payment,$amount){
            $current=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oi_payments WHERE id=%d",$payment->id));
            $full=$amount===(int)$current->amount;
            $ok=$wpdb->update($wpdb->prefix.'oi_payments',['refunded_amount'=>max((int)$current->refunded_amount,$amount),'refund_status'=>$full?'full':'partial','status'=>$full?'refunded':$current->status],['id'=>$current->id]);
            if($ok===false)throw new RuntimeException('Remboursement non enregistré.');
            if($full && (int)$current->pack_id===OI_Offer::pack()&&!OI_Offer::premium((int)$current->user_id))OI_Credits::revoke_premium_locked((int)$current->user_id);
            return ['received'=>true,'refund'=>$full?'full':'partial_review'];
        });
        return $result;
    }
    private static function notify(object $payment): void {
        if ($payment->notified || $payment->status !== 'paid') { return; }
        // Native reset link is safe for both new and existing users, no passwords sent.
        $user = get_user_by('id', (int)$payment->user_id);
        if (!$user) { return; }
        $setup = (bool)get_user_meta($user->ID, 'oi_account_setup_pending', true);
        $url = OI_App::url('member');
        if ($setup) {
            $key = get_password_reset_key($user);
            if (is_wp_error($key)) { return; }
            $url = network_site_url('wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login), 'login');
        }
        $message = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:28px;color:#183c3a"><h1>Objectif Infirmière</h1><p>Votre accès est disponible.</p><p>Le pack <strong>' . esc_html(get_the_title($payment->pack_id)) . '</strong> a été ajouté à votre compte.</p><p><a style="background:#23695e;color:white;padding:12px 18px;text-decoration:none" href="' . esc_url($url) . '">' . ($setup ? 'Définir mon mot de passe' : 'Ouvrir mon espace') . '</a></p><p>À bientôt pour vos révisions.</p></div>';
        $sent = wp_mail($user->user_email, 'Votre accès Objectif Infirmière est disponible', $message, ['Content-Type: text/html; charset=UTF-8']);
        if ($sent) { global $wpdb; $wpdb->update($wpdb->prefix . 'oi_payments', ['notified' => 1], ['id' => $payment->id]); }
    }
}

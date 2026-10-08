<?php
defined('ABSPATH') || exit;
final class OI_Verification {
    public static function verified(int $user): bool {return user_can($user,'manage_options') || (bool)get_user_meta($user,'oi_email_verified',true);}
    public static function issue(int $user): bool|WP_Error {
        if(self::verified($user))return true;
        $account=get_userdata($user);if(!$account)return new WP_Error('oi_user','Compte introuvable.');
        $token=bin2hex(random_bytes(32));
        update_user_meta($user,'oi_email_token',hash('sha256',$token));
        update_user_meta($user,'oi_email_expires',time()+DAY_IN_SECONDS);
        $url=add_query_arg(['action'=>'oi_verify_email','user'=>$user,'token'=>$token],admin_url('admin-post.php'));
        $sent=wp_mail($account->user_email,'Confirme ton adresse — Objectif Infirmière',"Confirme ton adresse pour activer tes 5 crédits Fiche, 5 crédits QCM et 5 crédits IA :\n\n".$url."\n\nCe lien expire dans 24 heures. Si tu n’es pas à l’origine de cette inscription, ignore ce message.");
        update_user_meta($user,'oi_email_sent',(bool)$sent);
        if(!$sent)OI_Log::event('verification_email_failed',['user_id'=>$user]);
        return $sent?true:new WP_Error('oi_email_delivery','L’envoi n’a pas abouti. Réessaie dans quelques instants.',['status'=>503]);
    }
    public static function confirm(int $user,string $token): bool|WP_Error {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return new WP_Error('oi_token','Lien invalide ou expiré.',['status'=>400]);
        // Serialize token validation and grants under the same transaction as the wallet.
        $result=OI_Credits::transaction($user,function()use($user,$token){
            wp_cache_delete($user,'user_meta');
            $hash=(string)get_user_meta($user,'oi_email_token',true);
            if(!$hash||!hash_equals($hash,hash('sha256',$token))||(int)get_user_meta($user,'oi_email_expires',true)<time())return new WP_Error('oi_token','Lien invalide ou expiré.',['status'=>400]);
            update_user_meta($user,'oi_email_verified',gmdate('c'));delete_user_meta($user,'oi_email_pending');
            foreach(OI_Credits::TYPES as $kind)OI_Credits::grant_locked($user,$kind,5,'welcome','Crédits de bienvenue');
            return true;
        });
        wp_cache_delete($user,'user_meta');return $result;
    }
    public static function register(): void {
        add_action('profile_update',function($user,$previous){
            $current=get_userdata($user);
            if(!$current || strcasecmp($current->user_email,$previous->user_email)===0 || !in_array('oi_etudiant',$current->roles,true))return;
            foreach(['oi_email_verified','oi_email_token','oi_email_expires','oi_email_sent'] as $key)delete_user_meta($user,$key);
            update_user_meta($user,'oi_email_pending',1);
            self::issue((int)$user);
        },10,2);
        foreach(['admin_post_oi_verify_email','admin_post_nopriv_oi_verify_email'] as $hook)add_action($hook,[self::class,'page']);
        add_action('rest_api_init',function(){register_rest_route('oi/v1','/verify/resend',['methods'=>'POST','permission_callback'=>[OI_Offer::class,'member'],'callback'=>function(){if(!OI_Limit::take('verification_mail',3,HOUR_IN_SECONDS))return new WP_Error('oi_rate','Patiente avant de demander un nouveau lien.',['status'=>429]);$r=self::issue(get_current_user_id());return is_wp_error($r)?$r:['sent'=>true];}]);});
    }
    public static function page(): void {
        nocache_headers();header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
        $user=absint($_REQUEST['user']??0);$token=isset($_REQUEST['token'])&&is_string($_REQUEST['token'])?sanitize_text_field(wp_unslash($_REQUEST['token'])):'';
        // A GET from an email scanner must not validate an account or create a session.
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmer mon adresse</title><body style="font:18px system-ui;color:#142d50;background:#f7faff;padding:8vw"><main style="max-width:550px;margin:auto"><h1>Active ton espace de révision</h1><p>Confirme ton adresse pour recevoir tes crédits de bienvenue.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="oi_verify_email"><input type="hidden" name="user" value="'.$user.'"><input type="hidden" name="token" value="'.esc_attr($token).'">';wp_nonce_field('oi_verify_email');echo '<button style="background:#2363cf;color:white;padding:16px;border:0;border-radius:10px;font:inherit">Confirmer mon adresse</button></form></main></body></html>';exit;
        }
        check_admin_referer('oi_verify_email');
        $result=self::confirm($user,$token);
        if(is_wp_error($result)){wp_safe_redirect(add_query_arg('notice','verification',OI_App::url('login')),303);exit;}
        // Do not switch an already connected account through a verification link.
        if(is_user_logged_in()&&get_current_user_id()===$user){wp_safe_redirect(OI_App::url('member'),303);exit;}
        wp_safe_redirect(add_query_arg('notice','verified',OI_App::url('login')),303);exit;
    }
}
